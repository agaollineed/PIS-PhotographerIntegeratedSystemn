<?php
/* Helper bersama: sesi, CSRF, katalog, slot, dan perhitungan harga.
   Semua harga dihitung ulang di sini dari catalog.php — angka yang dikirim
   browser tidak pernah dipercaya. */

require_once __DIR__ . '/db.php';

function boot(): void {
  static $done = false;
  if ($done) return;
  $done = true;
  date_default_timezone_set(cfg('site.timezone', 'Asia/Jakarta'));
  if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
      'httponly' => true,
      'samesite' => 'Lax',
      'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
  }
}

/* ---------- CSRF ---------- */

function csrf_token(): string {
  boot();
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}

function csrf_ok(?string $t): bool {
  boot();
  return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

/* ---------- I/O JSON ---------- */

function json_out($data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function json_err(string $msg, int $code = 422, array $extra = []): void {
  json_out(['ok' => false, 'error' => $msg] + $extra, $code);
}

function json_in(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '[]', true);
  return is_array($data) ? $data : [];
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ---------- Katalog (dibuat build.py) ---------- */

function catalog(): array {
  static $c = null;
  if ($c === null) $c = require __DIR__ . '/catalog.php';
  return $c;
}

function find_package(?string $id): ?array {
  foreach (catalog()['packages'] as $p) if ($p['id'] === $id) return $p;
  return null;
}

function find_addon(?string $id): ?array {
  foreach (catalog()['addons'] as $a) if ($a['id'] === $id) return $a;
  return null;
}

function area_label(?string $code, string $place = ''): string {
  $areas = catalog()['areas'];
  if ($code === 'lain') return $place !== '' ? $place : ($areas['lain'] ?? 'Lokasi lain');
  return $areas[$code] ?? '-';
}

function transport_text(array $pkg, string $area): string {
  if (in_array($area, $pkg['transport'], true)) return 'Termasuk';
  if ($area === 'lain') return 'Dikonfirmasi admin';
  return 'Khusus area ITS';
}

/* ---------- Format ---------- */

function money(int $n): string { return 'Rp' . number_format($n, 0, ',', '.'); }

function fmt_date(string $ymd): string {
  static $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
  static $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $t = strtotime($ymd);
  if (!$t) return $ymd;
  return $hari[(int)date('w', $t)] . ', ' . (int)date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function fmt_hm(string $t): string { return substr($t, 0, 5); }

function fmt_hours(float $h): string {
  $s = rtrim(rtrim(number_format($h, 1, ',', ''), '0'), ',');
  return $s . ' jam';
}

/* ---------- Status booking ---------- */

function status_list(): array {
  return [
    'menunggu'     => ['Menunggu konfirmasi', 'Pesananmu sudah kami catat. Admin Lulus sedang mengecek slotnya, sebentar ya.'],
    'dikonfirmasi' => ['Slot dikonfirmasi',   'Slotmu sudah aman. Tinggal bayar DP 40% supaya jadwalnya terkunci.'],
    'dp'           => ['DP diterima',         'DP sudah kami terima, terima kasih. Jadwal sesimu resmi terkunci.'],
    'lunas'        => ['Lunas',               'Pelunasan beres. Tinggal ketemu di hari sesi, sampai jumpa.'],
    'sesi'         => ['Sesi selesai',        'Sesinya sudah berjalan. Fotomu masuk antrean editing, sekitar 2–3 hari.'],
    'terkirim'     => ['Hasil dikirim',       'Hasil fotomu sudah dikirim. Link-nya aktif 14 hari, jangan lupa diunduh.'],
    'batal'        => ['Dibatalkan',          'Booking ini dibatalkan. Kabari admin kalau keliru, kami bantu atur ulang.'],
  ];
}

function status_flow(): array {
  return ['menunggu', 'dikonfirmasi', 'dp', 'lunas', 'sesi', 'terkirim'];
}

function status_label(string $s): string { return status_list()[$s][0] ?? $s; }

/** Emoji penanda tahap, dipakai di halaman cek status. */
function status_emoji(string $s): string {
  return match ($s) {
    'menunggu'     => "\u{23F3}",
    'dikonfirmasi' => "\u{2705}",
    'dp'           => "\u{1F512}",
    'lunas'        => "\u{1F389}",
    'sesi'         => "\u{1F4F8}",
    'terkirim'     => "\u{1F381}",
    'batal'        => "\u{1F64F}",
    default        => "\u{2022}",
  };
}

/** Waktu dengan bahasa sehari-hari, bukan cap waktu log. */
function waktu_santai(string $dt): string {
  $t = strtotime($dt);
  if (!$t) return '';
  $selisih = time() - $t;
  if ($selisih < 60)   return 'baru saja';
  if ($selisih < 3600) return (int)floor($selisih / 60) . ' menit lalu';

  $jam = date('H.i', $t);
  $tgl = date('Y-m-d', $t);
  if ($tgl === date('Y-m-d'))                      return 'hari ini, ' . $jam;
  if ($tgl === date('Y-m-d', strtotime('-1 day'))) return 'kemarin, ' . $jam;

  $hari = (int)round((strtotime(date('Y-m-d')) - strtotime($tgl)) / 86400);
  if ($hari > 1 && $hari < 7) return $hari . ' hari lalu';

  static $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $teks = (int)date('j', $t) . ' ' . $bulan[(int)date('n', $t)];
  return date('Y', $t) === date('Y') ? $teks : $teks . ' ' . date('Y', $t);
}
function status_note(string $s): string  { return status_list()[$s][1] ?? ''; }

/* ---------- Slot ---------- */

function date_bounds(): array {
  $min = date('Y-m-d', strtotime('+' . (int)cfg('booking.min_days_ahead', 0) . ' day'));
  $max = date('Y-m-d', strtotime('+' . (int)cfg('booking.max_days_ahead', 365) . ' day'));
  return [$min, $max];
}

function slot_hours(string $start, string $end): float {
  $a = strtotime("1970-01-01 $start");
  $b = strtotime("1970-01-01 $end");
  return round(max(0, $b - $a) / 3600, 2);
}

/** Slot tersedia pada satu tanggal: template hari itu, dikurangi blokir dan booking aktif. */
function slots_for_date(string $ymd): array {
  [$min, $max] = date_bounds();
  if ($ymd < $min || $ymd > $max) return [];

  $wd = (int)date('w', strtotime($ymd));
  $tpl = all(
    'SELECT start_time, end_time, label, capacity FROM slot_templates
      WHERE active = 1 AND (weekday IS NULL OR weekday = ?) ORDER BY start_time',
    [$wd]
  );

  $blocks = all('SELECT start_time FROM slot_blocks WHERE block_date = ?', [$ymd]);
  $dayOff = false;
  $blocked = [];
  foreach ($blocks as $b) {
    if ($b['start_time'] === null) $dayOff = true;
    else $blocked[fmt_hm($b['start_time'])] = true;
  }
  if ($dayOff) return [];

  $taken = [];
  foreach (all(
    "SELECT bs.start_time, COUNT(*) AS c
       FROM booking_slots bs JOIN bookings b ON b.id = bs.booking_id
      WHERE bs.slot_date = ? AND b.status <> 'batal'
      GROUP BY bs.start_time", [$ymd]) as $r) {
    $taken[fmt_hm($r['start_time'])] = (int)$r['c'];
  }

  $today = date('Y-m-d');
  $now   = date('H:i');
  $out = [];
  foreach ($tpl as $t) {
    $start = fmt_hm($t['start_time']);
    $end   = fmt_hm($t['end_time']);
    if ($ymd === $today && $start <= $now) continue;          // slot hari ini yang sudah lewat
    $cap  = (int)$t['capacity'];
    $used = $taken[$start] ?? 0;
    $left = isset($blocked[$start]) ? 0 : max(0, $cap - $used);
    $out[] = [
      'key'       => "$start-$end",
      'start'     => $start,
      'end'       => $end,
      'label'     => $t['label'],
      'hours'     => slot_hours($start, $end),
      'capacity'  => $cap,
      'available' => $left,
    ];
  }
  return $out;
}

/* ---------- Diskon ---------- */

/** @return array{ok:bool, error?:string, row?:array, amount?:int} */
function check_discount(string $code, int $subtotal): array {
  $code = strtoupper(trim($code));
  if ($code === '') return ['ok' => false, 'error' => 'Kode diskon kosong.'];
  $d = one('SELECT * FROM discounts WHERE code = ?', [$code]);
  if (!$d || !(int)$d['active'])                       return ['ok' => false, 'error' => 'Kode diskon tidak ditemukan.'];
  if ($d['valid_until'] && $d['valid_until'] < date('Y-m-d')) return ['ok' => false, 'error' => 'Kode diskon sudah kedaluwarsa.'];
  if ($d['max_uses'] !== null && (int)$d['used_count'] >= (int)$d['max_uses']) {
    return ['ok' => false, 'error' => 'Kuota kode diskon ini sudah habis.'];
  }
  if ($subtotal < (int)$d['min_subtotal']) {
    return ['ok' => false, 'error' => 'Kode ini berlaku untuk pesanan mulai ' . money((int)$d['min_subtotal']) . '.'];
  }
  $amount = $d['type'] === 'percent'
    ? (int)round($subtotal * ((float)$d['value'] / 100))
    : (int)$d['value'];
  if ($d['max_amount'] !== null) $amount = min($amount, (int)$d['max_amount']);
  $amount = min($amount, $subtotal);
  return ['ok' => true, 'row' => $d, 'amount' => $amount];
}

/* ---------- Normalisasi input ---------- */

function norm_wa(string $v): string {
  $d = preg_replace('/\D/', '', $v);
  if (str_starts_with($d, '0'))      $d = '62' . substr($d, 1);
  elseif (str_starts_with($d, '8'))  $d = '62' . $d;
  return $d;
}

function norm_ig(string $v): string {
  $v = trim($v);
  $v = preg_replace('~^https?://(www\.)?instagram\.com/~i', '', $v);
  return ltrim(trim($v, '/ '), '@');
}

function clean(string $v, int $max = 200): string {
  $v = trim(preg_replace('/\s+/u', ' ', strip_tags($v)));
  return mb_substr($v, 0, $max);
}

function gen_code(): string {
  $prefix = cfg('booking.code_prefix', 'LLS');
  /* Huruf dan angka yang tidak gampang tertukar waktu dibacakan atau diketik
     ulang: tanpa 0/O dan tanpa 1/I. Empat karakter sudah lebih dari sejuta
     kemungkinan, dan keunikannya tetap dicek ke database. */
  $abc = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
  $max = strlen($abc) - 1;
  for ($i = 0; $i < 50; $i++) {
    $acak = '';
    for ($j = 0; $j < 4; $j++) $acak .= $abc[random_int(0, $max)];
    $code = $prefix . '-' . $acak;
    if (!one('SELECT id FROM bookings WHERE code = ?', [$code])) return $code;
  }
  return $prefix . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/** Contoh kode untuk placeholder di formulir. */
function code_example(): string {
  return cfg('booking.code_prefix', 'LLS') . '-7F3K';
}

function track_url(string $code = ''): string {
  $base = rtrim(cfg('site.url', ''), '/') . '/booking/track/';
  return $code ? $base . '?code=' . rawurlencode($code) : $base;
}

function wa_link(string $text = ''): string {
  $no = cfg('site.whatsapp', '');
  return 'https://wa.me/' . $no . ($text ? '?text=' . rawurlencode($text) : '');
}
