<?php
/* Kode diskon. */
require_once __DIR__ . '/_boot.php';
admin_require();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  admin_guard_post();

  if (isset($_POST['tambah'])) {
    $code = strtoupper(preg_replace('/[^A-Z0-9-]/i', '', (string)($_POST['code'] ?? '')));
    $type = ($_POST['type'] ?? 'percent') === 'amount' ? 'amount' : 'percent';
    $val  = max(0, (float)($_POST['value'] ?? 0));
    if ($code === '' || $val <= 0) {
      flash('Kode dan nilai diskon wajib diisi.');
    } elseif (one('SELECT id FROM discounts WHERE code = ?', [$code])) {
      flash('Kode ' . $code . ' sudah ada.');
    } else {
      q('INSERT INTO discounts (code, label, type, value, max_amount, min_subtotal, max_uses, valid_until, active)
         VALUES (?,?,?,?,?,?,?,?,1)', [
        $code, clean((string)($_POST['label'] ?? ''), 120), $type, $val,
        ($_POST['max_amount'] ?? '') === '' ? null : max(0, (int)$_POST['max_amount']),
        max(0, (int)($_POST['min_subtotal'] ?? 0)),
        ($_POST['max_uses'] ?? '') === '' ? null : max(1, (int)$_POST['max_uses']),
        preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['valid_until'] ?? '')) ? $_POST['valid_until'] : null,
      ]);
      flash('Kode ' . $code . ' dibuat.');
    }
  }

  if (isset($_POST['toggle'])) { q('UPDATE discounts SET active = 1 - active WHERE id = ?', [(int)$_POST['id']]); flash('Status kode diubah.'); }
  if (isset($_POST['hapus']))  { q('DELETE FROM discounts WHERE id = ?', [(int)$_POST['id']]); flash('Kode dihapus.'); }

  header('Location: discounts.php'); exit;
}

$rows = all('SELECT * FROM discounts ORDER BY active DESC, code');
$pct = fn($v) => rtrim(rtrim(number_format((float)$v, 1, ',', ''), '0'), ',') . '%';

admin_head('Diskon'); ?>

<div class="card">
  <h2>Kode diskon</h2>
  <p class="sub">Potongan dihitung dari subtotal (paket + add-on). DP 40% ikut menyesuaikan total setelah diskon.</p>
  <?php if (!$rows): ?>
    <p class="sub" style="margin-top:14px">Belum ada kode diskon.</p>
  <?php else: ?>
    <ul class="list" style="margin-top:14px">
      <?php foreach ($rows as $r): ?>
        <li>
          <span style="min-width:160px;font-weight:500"><?= e($r['code']) ?></span>
          <span class="grow dim">
            <?= $r['type'] === 'percent' ? e($pct($r['value'])) . ($r['max_amount'] !== null ? ', maks ' . e(money((int)$r['max_amount'])) : '')
                                         : e(money((int)$r['value'])) ?>
            <?= (int)$r['min_subtotal'] ? ' · min. ' . e(money((int)$r['min_subtotal'])) : '' ?>
            · dipakai <?= (int)$r['used_count'] ?><?= $r['max_uses'] !== null ? ' dari ' . (int)$r['max_uses'] : '' ?>
            <?= $r['valid_until'] ? ' · sampai ' . e(date('d/m/Y', strtotime($r['valid_until']))) : '' ?>
            <?= $r['label'] ? '<br>' . e($r['label']) : '' ?>
          </span>
          <span class="tag <?= (int)$r['active'] ? 'done' : 'off' ?>"><?= (int)$r['active'] ? 'aktif' : 'nonaktif' ?></span>
          <button form="d<?= (int)$r['id'] ?>" class="btn small quiet" name="toggle" value="1"><?= (int)$r['active'] ? 'Matikan' : 'Nyalakan' ?></button>
          <button form="d<?= (int)$r['id'] ?>" class="btn small danger" name="hapus" value="1"
                  onclick="return confirm('Hapus kode <?= e($r['code']) ?>?')">Hapus</button>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php foreach ($rows as $r): ?>
      <form id="d<?= (int)$r['id'] ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"></form>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<form class="card" method="post">
  <?= csrf_field() ?>
  <h2>Kode baru</h2>
  <div class="grid">
    <div><label for="c">Kode</label><input id="c" name="code" placeholder="WISUDA10" required style="text-transform:uppercase"></div>
    <div><label for="l">Keterangan</label><input id="l" name="label" placeholder="Promo wisuda periode 1"></div>
    <div><label for="t">Jenis</label>
      <select id="t" name="type"><option value="percent">Persen</option><option value="amount">Potongan rupiah</option></select></div>
    <div><label for="v">Nilai</label><input id="v" name="value" type="number" min="1" step="1" placeholder="10" required></div>
    <div><label for="ma">Maks potongan <span class="sub">(khusus persen)</span></label><input id="ma" name="max_amount" type="number" min="0" step="1000" placeholder="150000"></div>
    <div><label for="ms">Minimal subtotal</label><input id="ms" name="min_subtotal" type="number" min="0" step="1000" value="0"></div>
    <div><label for="mu">Kuota pemakaian</label><input id="mu" name="max_uses" type="number" min="1" placeholder="kosong = tanpa batas"></div>
    <div><label for="vu">Berlaku sampai</label><input id="vu" name="valid_until" type="date"></div>
  </div>
  <div class="acts"><button class="btn" name="tambah" value="1">Buat kode</button></div>
</form>
<?php admin_foot();
