<?php
/* GET api/discount.php?code=XXX&subtotal=123456 → cek kode diskon.
   Nilai akhir tetap dihitung ulang saat booking dikirim. */
require_once __DIR__ . '/../app/lib.php';
boot();

$code     = (string)($_GET['code'] ?? '');
$subtotal = max(0, (int)($_GET['subtotal'] ?? 0));

$chk = check_discount($code, $subtotal);
if (!$chk['ok']) json_out(['ok' => false, 'error' => $chk['error']]);

$d = $chk['row'];
json_out([
  'ok'     => true,
  'code'   => $d['code'],
  'amount' => $chk['amount'],
  'label'  => $d['type'] === 'percent'
                ? rtrim(rtrim(number_format((float)$d['value'], 1, ',', ''), '0'), ',') . '%'
                : money((int)$d['value']),
  'note'   => $d['label'] ?: '',
]);
