<?php
/* Validasi payload booking + hitung ulang seluruh harga dari katalog.
   Dipakai api/booking.php. Melempar QuoteError kalau ada yang tidak beres. */

require_once __DIR__ . '/lib.php';

class QuoteError extends RuntimeException {}

function fail(string $msg): void { throw new QuoteError($msg); }

function build_quote(array $in): array {
  /* ---- identitas ---- */
  $email = strtolower(clean((string)($in['email'] ?? ''), 120));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Alamat email belum valid.');

  $name = clean((string)($in['name'] ?? ''), 120);
  if (mb_strlen($name) < 2) fail('Isi nama lengkap.');

  $wa = norm_wa((string)($in['wa'] ?? ''));
  if (!preg_match('/^62\d{8,13}$/', $wa)) fail('Nomor WhatsApp belum valid. Contoh: 0812 3456 7890.');

  $ig = norm_ig((string)($in['ig'] ?? ''));
  if ($ig !== '' && !preg_match('/^[A-Za-z0-9._]{1,30}$/', $ig)) fail('Username Instagram belum valid.');

  $university = clean((string)($in['university'] ?? ''), 120);
  if (mb_strlen($university) < 2) fail('Isi nama universitas.');
  $faculty = clean((string)($in['faculty'] ?? ''), 120);
  if (mb_strlen($faculty) < 2) fail('Isi nama fakultas atau jurusan.');

  /* ---- paket & tier ---- */
  $pkg = find_package((string)($in['pkg'] ?? ''));
  if (!$pkg) fail('Paket belum dipilih.');
  $ti = $in['tier'] ?? null;
  if (!is_numeric($ti) || !isset($pkg['tiers'][(int)$ti])) fail('Pilihan durasi paket belum benar.');
  $ti = (int)$ti;
  $tier = $pkg['tiers'][$ti];

  $groupSize = 0;
  if (!empty($pkg['group'])) {
    $groupSize = (int)($in['groupSize'] ?? 0);
    if ($groupSize < 3 || $groupSize > 5) fail('Jumlah gradumates untuk group session 3–5 orang.');
  }

  /* ---- area ---- */
  $area = (string)($in['area'] ?? '');
  if (!isset(catalog()['areas'][$area])) fail('Area sesi belum dipilih.');
  if (!in_array($area, $pkg['areas'], true)) fail($pkg['name'] . ' tidak tersedia untuk area itu.');
  $place = $area === 'lain' ? clean((string)($in['place'] ?? ''), 160) : '';
  if ($area === 'lain' && $place === '') fail('Tulis nama kampus atau lokasi sesi.');
  $meet = clean((string)($in['meet'] ?? ''), 160);

  /* ---- tanggal & slot ---- */
  $date = (string)($in['date'] ?? '');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) fail('Tanggal sesi belum benar.');
  [$min, $max] = date_bounds();
  if ($date < $min) fail('Tanggal sesi tidak boleh sebelum ' . fmt_date($min) . '.');
  if ($date > $max) fail('Tanggal sesi terlalu jauh ke depan.');

  $wanted = array_values(array_unique(array_map('strval', (array)($in['slots'] ?? []))));
  if (!$wanted) fail('Pilih minimal satu slot sesi.');
  if (count($wanted) > 4) fail('Maksimal 4 slot dalam satu booking.');

  $byKey = $byStart = [];
  foreach (slots_for_date($date) as $s) { $byKey[$s['key']] = $s; $byStart[$s['start']] = $s; }

  $slots = [];
  $totalHours = 0.0;
  foreach ($wanted as $key) {
    if (!isset($byKey[$key]))            fail('Slot ' . $key . ' tidak tersedia pada tanggal itu.');
    if ($byKey[$key]['available'] < 1)   fail('Slot ' . $byKey[$key]['start'] . ' sudah penuh. Pilih slot lain, ya.');
    $slots[] = $byKey[$key];
    $totalHours += $byKey[$key]['hours'];
  }
  usort($slots, fn($a, $b) => strcmp($a['start'], $b['start']));
  /* Tanpa add-on waktu, slot boleh berjauhan — misalnya 14:00 lalu 18:00. */

  /* ---- add-on ---- */
  $addons = [];
  $addonTotal = 0;
  foreach ((array)($in['addons'] ?? []) as $row) {
    $a = find_addon((string)($row['id'] ?? ''));
    if (!$a) fail('Ada add-on yang tidak dikenal.');
    $qty = (int)($row['qty'] ?? 1);
    if ($qty < 1) continue;
    if ($qty > (int)$a['max']) fail('Jumlah "' . $a['name'] . '" maksimal ' . $a['max'] . '.');
    $line = (int)$a['price'] * $qty;
    $addons[] = ['id' => $a['id'], 'name' => $a['name'], 'unit' => $a['unit'], 'price' => (int)$a['price'],
                 'qty' => $qty, 'total' => $line, 'hours' => (float)($a['hours'] ?? 0)];
    $addonTotal += $line;
  }

  /* ---- durasi ----
     Slot yang dipilih menanggung durasi paketnya saja. Waktu dari add-on
     (extra 30 menit) membuat sesi berlanjut ke jam berikutnya, jadi jam itu
     harus masih kosong lalu ikut ditahan supaya tidak dipesan orang lain. */
  $tierHours  = (float)$tier['hours'];
  $addonHours = 0.0;
  foreach ($addons as $a) $addonHours += (float)$a['hours'] * $a['qty'];
  $needHours = $tierHours + $addonHours;

  if ($totalHours + 0.001 < $tierHours) {
    fail($tier['label'] . ' butuh ' . fmt_hours($tierHours)
       . ', sedangkan slot yang kamu pilih baru ' . fmt_hours($totalHours)
       . '. Tambah slot di langkah Jadwal, atau pilih paket yang lebih pendek.');
  }

  $minSlot = min(array_map(fn($s) => $s['hours'], $slots));
  if ($totalHours - $tierHours >= $minSlot - 0.001) {
    fail($tier['label'] . ' cuma butuh ' . fmt_hours($tierHours)
       . ', sedangkan kamu memilih ' . fmt_hours($totalHours) . '. Kurangi satu slot.');
  }

  /* ---- jam yang ikut ditahan karena add-on waktu ---- */
  $hold = [];
  if ($addonHours > 0) {
    for ($i = 1; $i < count($slots); $i++) {
      if ($slots[$i]['start'] !== $slots[$i - 1]['end']) {
        fail('Karena kamu ambil extra 30 menit, jam sesinya harus berurutan — '
           . 'misalnya 09:00–10:00 lalu 10:00–11:00, bukan jam yang berjauhan.');
      }
    }

    $sisa   = $needHours - $totalHours;
    $cursor = end($slots)['end'];
    while ($sisa > 0.001) {
      $next = $byStart[$cursor] ?? null;
      if (!$next) {
        fail('Extra 30 menit membuat sesimu berlanjut lewat jam ' . $cursor
           . ', dan tidak ada jam sesi sesudah itu. Pilih jam yang lebih awal, ya.');
      }
      if ($next['available'] < 1) {
        fail('Extra 30 menit membuat sesimu berlanjut ke jam ' . $next['start']
           . ', tapi jam itu sudah dipesan orang lain. Pilih jam lain atau lepas add-on-nya.');
      }
      $hold[] = $next;
      $sisa -= $next['hours'];
      $cursor = $next['end'];
    }
  }

  $holdHours = array_sum(array_map(fn($s) => $s['hours'], $hold));
  $mulai = $slots[0]['start'];
  $akhir = date('H:i', strtotime("1970-01-01 $mulai") + (int)round($needHours * 3600));

  /* ---- total ---- */
  $pkgPrice = (int)$tier['price'];
  $subtotal = $pkgPrice + $addonTotal;

  $discCode = strtoupper(clean((string)($in['discount'] ?? ''), 40));
  $discAmount = 0;
  $discRow = null;
  if ($discCode !== '') {
    $chk = check_discount($discCode, $subtotal);
    if (!$chk['ok']) fail($chk['error']);
    $discAmount = (int)$chk['amount'];
    $discRow = $chk['row'];
  }

  $total = max(0, $subtotal - $discAmount);
  $payment = ($in['payment'] ?? 'dp') === 'lunas' ? 'lunas' : 'dp';
  $dp = $payment === 'lunas' ? $total : (int)round($total * (float)cfg('booking.dp_rate', 0.4));

  if (empty($in['agree'])) fail('Centang persetujuan Terms & Conditions.');

  return [
    'client_email' => $email,
    'client_name'  => $name,
    'whatsapp'     => $wa,
    'instagram'    => $ig,
    'university'   => $university,
    'faculty'      => $faculty,
    'area'         => $area,
    'area_label'   => area_label($area, $place),
    'place'        => $place,
    'meet_point'   => $meet,
    'transport'    => transport_text($pkg, $area),
    'photo_date'   => $date,
    'slots'        => $slots,
    'hold'         => $hold,
    'slot_hours'   => $totalHours + $holdHours,
    'need_hours'   => $needHours,
    'addon_hours'  => $addonHours,
    'sesi_mulai'   => $mulai,
    'sesi_selesai' => $akhir,
    'package'      => $pkg,
    'tier_index'   => $ti,
    'tier'         => $tier,
    'group_size'   => $groupSize,
    'addons'       => $addons,
    'package_price'=> $pkgPrice,
    'addons_total' => $addonTotal,
    'subtotal'     => $subtotal,
    'discount_code'=> $discRow ? $discRow['code'] : '',
    'discount_row' => $discRow,
    'discount'     => $discAmount,
    'total'        => $total,
    'payment'      => $payment,
    'dp_amount'    => $dp,
    'rest_amount'  => $total - $dp,
    'notes'        => clean((string)($in['notes'] ?? ''), 600),
  ];
}
