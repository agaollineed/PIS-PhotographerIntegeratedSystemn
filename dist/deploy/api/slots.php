<?php
/* GET api/slots.php?date=YYYY-MM-DD → slot yang masih bisa dipesan hari itu. */
require_once __DIR__ . '/../app/lib.php';
boot();

$date = (string)($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
  json_err('Tanggal tidak valid.', 400);
}

[$min, $max] = date_bounds();
$slots = slots_for_date($date);

json_out([
  'ok'    => true,
  'date'  => $date,
  'label' => fmt_date($date),
  'min'   => $min,
  'max'   => $max,
  'slots' => array_map(fn($s) => [
    'key'       => $s['key'],
    'start'     => $s['start'],
    'end'       => $s['end'],
    'label'     => $s['label'],
    'hours'     => $s['hours'],
    'available' => $s['available'],
  ], $slots),
]);
