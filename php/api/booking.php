<?php
/* POST api/booking.php  (JSON)  → simpan booking, balas kode booking. */
require_once __DIR__ . '/../app/quote.php';
require_once __DIR__ . '/../app/booking.php';
require_once __DIR__ . '/../app/mailer.php';
boot();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_err('Metode tidak didukung.', 405);

$in = json_in();
if (!csrf_ok($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($in['csrf'] ?? null))) {
  json_err('Sesi kedaluwarsa. Muat ulang halaman lalu kirim lagi.', 419);
}

/* Rem sederhana: maksimal 5 booking per IP per jam. */
$ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
if ($ip !== '') {
  $n = (int)(one('SELECT COUNT(*) AS c FROM bookings WHERE client_ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [$ip])['c'] ?? 0);
  if ($n >= 5) json_err('Terlalu banyak percobaan booking. Coba lagi satu jam lagi atau hubungi admin lewat WhatsApp.', 429);
}

try {
  $quote = build_quote($in);
  $b = booking_insert($quote);
} catch (QuoteError $e) {
  json_err($e->getMessage());
} catch (Throwable $e) {
  error_log('[lulus] booking gagal: ' . $e->getMessage());
  json_err('Booking gagal disimpan. Coba lagi sebentar lagi, atau hubungi admin lewat WhatsApp.', 500);
}

try { mail_booking_created($b); } catch (Throwable $e) { error_log('[lulus] email booking: ' . $e->getMessage()); }

$wa = "Halo admin Lulus, saya baru booking lewat website.\n\n" . implode("\n", booking_lines($b));

json_out([
  'ok'        => true,
  'code'      => $b['code'],
  'status'    => status_label($b['status']),
  'total'     => (int)$b['total'],
  'dp'        => (int)$b['dp_amount'],
  'rest'      => (int)$b['rest_amount'],
  'email'     => $b['client_email'],
  'track_url' => track_url($b['code']),
  'wa_url'    => wa_link($wa),
  'lines'     => booking_lines($b),
]);
