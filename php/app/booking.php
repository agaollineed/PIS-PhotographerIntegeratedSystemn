<?php
/* Simpan, baca, dan ubah status booking. */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/quote.php';   // QuoteError

/** Simpan booking hasil build_quote() dalam satu transaksi. */
function booking_insert(array $q): array {
  $pdo = db();
  $pdo->beginTransaction();
  try {
    /* Jam sesi + jam yang ditahan karena add-on waktu: dua-duanya mengunci kuota. */
    $semua = array_merge($q['slots'], $q['hold'] ?? []);

    /* Kunci baris slot pada tanggal itu, lalu cek ulang kuota supaya dua orang
       yang menekan kirim bersamaan tidak sama-sama dapat slot terakhir. */
    foreach ($semua as $s) {
      $used = (int)(one(
        "SELECT COUNT(*) AS c FROM booking_slots bs JOIN bookings b ON b.id = bs.booking_id
          WHERE bs.slot_date = ? AND bs.start_time = ? AND b.status <> 'batal' FOR UPDATE",
        [$q['photo_date'], $s['start']]
      )['c'] ?? 0);
      if ($used >= $s['capacity']) {
        throw new QuoteError('Slot ' . $s['start'] . ' baru saja dipesan orang lain. Pilih slot lain, ya.');
      }
    }

    $code = gen_code();
    $t = $q['tier'];
    q('INSERT INTO bookings
        (code, status, client_name, client_email, whatsapp, instagram, university, faculty,
         area, area_label, place, meet_point, transport, photo_date, slot_hours,
         package_id, package_name, package_kind, tier_index, tier_label, tier_hours, tier_edited,
         group_size, package_price, addons_total, subtotal, discount_code, discount_amount,
         total, payment, dp_amount, notes, client_ip, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
      [
        $code, 'menunggu', $q['client_name'], $q['client_email'], $q['whatsapp'], $q['instagram'],
        $q['university'], $q['faculty'], $q['area'], $q['area_label'], $q['place'], $q['meet_point'],
        $q['transport'], $q['photo_date'], $q['slot_hours'],
        $q['package']['id'], $q['package']['name'], $q['package']['kind'], $q['tier_index'],
        $t['label'], $t['hours'], $t['edited'], $q['group_size'],
        $q['package_price'], $q['addons_total'], $q['subtotal'], $q['discount_code'], $q['discount'],
        $q['total'], $q['payment'], $q['dp_amount'], $q['notes'],
        substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
      ]);
    $id = (int)$pdo->lastInsertId();

    foreach ($q['slots'] as $s) {
      q('INSERT INTO booking_slots (booking_id, slot_date, start_time, end_time, label, kind) VALUES (?, ?, ?, ?, ?, ?)',
        [$id, $q['photo_date'], $s['start'], $s['end'], $s['label'], 'sesi']);
    }
    foreach ($q['hold'] ?? [] as $s) {
      q('INSERT INTO booking_slots (booking_id, slot_date, start_time, end_time, label, kind) VALUES (?, ?, ?, ?, ?, ?)',
        [$id, $q['photo_date'], $s['start'], $s['end'], $s['label'], 'tahan']);
    }
    foreach ($q['addons'] as $a) {
      q('INSERT INTO booking_addons (booking_id, addon_id, name, unit, price, qty, total) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$id, $a['id'], $a['name'], $a['unit'], $a['price'], $a['qty'], $a['total']]);
    }
    if ($q['discount_row']) {
      q('UPDATE discounts SET used_count = used_count + 1 WHERE id = ?', [$q['discount_row']['id']]);
    }
    q('INSERT INTO booking_events (booking_id, status, note, created_at) VALUES (?, ?, ?, NOW())',
      [$id, 'menunggu', 'Booking masuk lewat website.']);

    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
  return booking_by_id($id);
}

function booking_full(?array $row): ?array {
  if (!$row) return null;
  $semua = all('SELECT * FROM booking_slots WHERE booking_id = ? ORDER BY start_time', [$row['id']]);
  $row['slots'] = array_values(array_filter($semua, fn($s) => ($s['kind'] ?? 'sesi') !== 'tahan'));
  $row['hold']  = array_values(array_filter($semua, fn($s) => ($s['kind'] ?? 'sesi') === 'tahan'));
  $row['addons'] = all('SELECT * FROM booking_addons WHERE booking_id = ? ORDER BY id', [$row['id']]);
  $row['events'] = all('SELECT * FROM booking_events WHERE booking_id = ? ORDER BY id', [$row['id']]);
  $row['rest_amount'] = (int)$row['total'] - (int)$row['dp_amount'];
  return $row;
}

function booking_by_id(int $id): ?array {
  return booking_full(one('SELECT * FROM bookings WHERE id = ?', [$id]));
}

/** Cari booking untuk halaman track: kode + email harus cocok keduanya. */
function booking_track(string $code, string $email): ?array {
  $code  = strtoupper(trim($code));
  $email = strtolower(trim($email));
  if ($code === '' || $email === '') return null;
  return booking_full(one('SELECT * FROM bookings WHERE code = ? AND client_email = ?', [$code, $email]));
}

function booking_set_status(int $id, string $status, string $note = ''): ?array {
  if (!isset(status_list()[$status])) return null;
  q('UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $id]);
  q('INSERT INTO booking_events (booking_id, status, note, created_at) VALUES (?, ?, ?, NOW())',
    [$id, $status, $note !== '' ? $note : status_note($status)]);
  return booking_by_id($id);
}

/** Total jam tambahan dari add-on (extra 30 menit dan sejenisnya). */
function booking_addon_hours(array $b): float {
  $h = 0.0;
  foreach ($b['addons'] as $a) {
    $c = find_addon($a['addon_id']);
    $h += (float)($c['hours'] ?? 0) * (int)$a['qty'];
  }
  return $h;
}

/** Rentang sesi sebenarnya, misalnya 08:00–09:30 untuk paket 1 jam + extra 30 menit. */
function booking_window(array $b): string {
  if (!$b['slots']) return '';
  $mulai = fmt_hm($b['slots'][0]['start_time']);
  $jam = (float)$b['tier_hours'] + booking_addon_hours($b);
  return $mulai . '–' . date('H:i', strtotime("1970-01-01 {$mulai}") + (int)round($jam * 3600));
}

function booking_slot_text(array $b): string {
  /* Kalau ada add-on waktu, slotnya pasti berurutan — jadi tampilkan satu
     rentang utuh, bukan daftar jam per jam. */
  if (booking_addon_hours($b) > 0) return booking_window($b);
  return implode(', ', array_map(fn($s) => fmt_hm($s['start_time']) . '–' . fmt_hm($s['end_time']), $b['slots']));
}

/** Ringkasan satu baris untuk WhatsApp dan email. */
function booking_lines(array $b): array {
  $slots = booking_slot_text($b);
  $lines = [
    'Kode booking: ' . $b['code'],
    'Nama: ' . $b['client_name'],
    'WhatsApp: ' . $b['whatsapp'],
    'Universitas: ' . $b['university'] . ' — ' . $b['faculty'],
    'Paket: ' . $b['package_name'] . ' (' . $b['tier_label'] . ', ' . $b['tier_edited'] . ' foto edit)'
      . ((int)$b['group_size'] ? ', ' . $b['group_size'] . ' gradumates' : ''),
    'Jadwal: ' . fmt_date($b['photo_date']) . ' · ' . $slots,
    'Lokasi: ' . $b['area_label'] . ($b['meet_point'] ? ' (' . $b['meet_point'] . ')' : ''),
    'Transport: ' . $b['transport'],
  ];
  if (!empty($b['hold'])) {
    $lines[] = 'Jam ditahan: '
      . implode(', ', array_map(fn($s) => fmt_hm($s['start_time']) . '–' . fmt_hm($s['end_time']), $b['hold']))
      . ' (biar sesimu tidak dipotong booking lain)';
  }
  foreach ($b['addons'] as $a) {
    $lines[] = 'Add-on: ' . $a['name'] . ' ×' . $a['qty'] . ' — ' . money((int)$a['total']);
  }
  $lines[] = 'Subtotal: ' . money((int)$b['subtotal']);
  if ((int)$b['discount_amount'] > 0) {
    $lines[] = 'Diskon (' . $b['discount_code'] . '): -' . money((int)$b['discount_amount']);
  }
  $lines[] = 'Total: ' . money((int)$b['total']);
  $lines[] = $b['payment'] === 'lunas'
    ? 'Pembayaran: lunas di muka'
    : 'DP 40%: ' . money((int)$b['dp_amount']) . ' · sisa ' . money((int)$b['rest_amount']) . ' maks. H-1';
  if ($b['notes']) $lines[] = 'Catatan: ' . $b['notes'];
  return $lines;
}

/* ---------- Pesan WhatsApp siap kirim ----------
   Admin memakai ini untuk mengabari klien secara manual: kode booking,
   penagihan DP, sampai link hasil foto. */

function wa_link_to(string $number, string $text = ''): string {
  $no = preg_replace('/\D/', '', $number);
  return 'https://wa.me/' . $no . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function wa_message(array $b, string $status = ''): string {
  $status = $status !== '' ? $status : $b['status'];
  $nama   = explode(' ', trim($b['client_name']))[0] ?: $b['client_name'];
  $jadwal = fmt_date($b['photo_date']) . ', ' . booking_slot_text($b);
  $paket  = $b['package_name'] . ' (' . $b['tier_label'] . ', ' . (int)$b['tier_edited'] . ' foto edit)';
  $lokasi = $b['area_label'] . ($b['meet_point'] ? ' — ' . $b['meet_point'] : '');
  $total  = money((int)$b['total']);
  $dp     = money((int)$b['dp_amount']);
  $sisa   = money((int)$b['rest_amount']);
  $kode   = $b['code'];
  $rek    = trim((string)cfg('booking.payment_info', ''));
  $lacak  = "Mau cek status booking kapan saja? Buka link ini ya 👇\n" . track_url($kode)
          . "\n(kode {$kode}, email {$b['client_email']})";

  $ringkas = "📌 Kode booking: {$kode}\n"
           . "📸 Paket: {$paket}\n"
           . "🗓️ Jadwal: {$jadwal}\n"
           . "📍 Lokasi: {$lokasi}\n"
           . "💰 Total: {$total}";

  $bayar = $b['payment'] === 'lunas'
    ? "💳 Pelunasan: {$total}"
    : "💳 DP 40%: {$dp}\n🔖 Sisa {$sisa}, paling lambat H-1";

  $halo = "Halo Kak {$nama}!";

  switch ($status) {
    case 'menunggu':
      return "{$halo} 👋\n\nTerima kasih banyak sudah booking di Lulus 🤍 Pesananmu sudah kami catat, "
           . "sekarang lagi kami cek ketersediaan slotnya.\n\n{$ringkas}\n{$bayar}\n\n"
           . "Kami kabari lagi ya begitu slotnya pasti 🙏\n\n{$lacak}";

    case 'dikonfirmasi':
      $tf = $rek !== ''
        ? "Biar jadwalnya aman, DP-nya bisa ditransfer ke:\n🏦 {$rek}\n\nKalau sudah, kirim bukti transfernya ke chat ini ya 🙏"
        : "Untuk detail pembayaran DP, balas chat ini ya 🙏";
      return "{$halo} 🎉\n\nKabar baik — slot kamu sudah kami kunci ✅\n\n{$ringkas}\n{$bayar}\n\n{$tf}\n\n{$lacak}";

    case 'dp':
      return "{$halo} ✅\n\nDP sebesar {$dp} sudah kami terima, terima kasih ya 🙏 "
           . "Jadwal sesimu resmi terkunci 🔒\n\n{$ringkas}\n🔖 Sisa pelunasan {$sisa}, paling lambat H-1.\n\n"
           . "Sampai ketemu di hari H ya, semoga cuacanya cerah ☀️\n\n{$lacak}";

    case 'lunas':
      return "{$halo} 🎊\n\nPelunasan sudah kami terima — semuanya beres ✨\n\n{$ringkas}\n\n"
           . "Tinggal ketemu di {$jadwal}. Siapkan toga, senyum terbaik, dan orang-orang tersayang ya 😊📸\n\n{$lacak}";

    case 'sesi':
      return "{$halo} 🥳\n\nTerima kasih banyak untuk sesinya, seru banget! 🤍\n\n"
           . "Foto-fotomu sekarang masuk antrean editing, selesai sekitar 2–3 hari 🎨\n"
           . "📌 Kode booking: {$kode}\n\nKami kabari lagi begitu hasilnya siap ya 🙌\n\n{$lacak}";

    case 'terkirim':
      $link = $b['drive_link'] ? "📁 {$b['drive_link']}" : '📁 Link Google Drive-nya menyusul di chat ini ya.';
      $ig   = trim((string)cfg('site.instagram', ''));
      $tag  = $ig !== ''
        ? "\n\nKalau suka hasilnya, kami seneng banget kalau kamu mau tag @" . ltrim($ig, '@') . " waktu upload 💙"
        : '';
      return "{$halo} 🎁\n\nHasil fotomu sudah siap! ✨\n\n{$link}\n\n"
           . "⏳ Link-nya aktif 14 hari, jangan lupa diunduh sebelum kedaluwarsa ya.{$tag}\n\n"
           . "📌 Kode booking: {$kode}";

    case 'batal':
      return "{$halo} 🙏\n\nBooking {$kode} untuk {$jadwal} kami tandai dibatalkan.\n\n"
           . "Kalau ini keliru atau kamu mau atur ulang jadwalnya, balas chat ini ya — "
           . "kami bantu carikan slot baru 😊";
  }
  return "{$halo} 👋\n\n{$ringkas}\n\n{$lacak}";
}

/** Pesan singkat untuk tombol cepat di daftar booking (tanpa query tambahan). */
function wa_message_short(array $r): string {
  $nama = explode(' ', trim($r['client_name']))[0] ?: $r['client_name'];
  return "Halo Kak {$nama}! 👋 Ini admin Lulus soal booking {$r['code']} — "
       . strtolower(status_label($r['status'])) . ".\n\n"
       . '🗓️ ' . fmt_date($r['photo_date']) . "\n"
       . "Cek status kapan saja 👇\n" . track_url($r['code']);
}
