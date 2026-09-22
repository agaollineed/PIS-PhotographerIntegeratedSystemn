<?php
/* Jam sesi yang ditawarkan ke klien, dan tanggal yang ditutup. */
require_once __DIR__ . '/_boot.php';
admin_require();

$HARI = [null => 'Setiap hari', 0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  admin_guard_post();

  if (isset($_POST['tambah_slot'])) {
    $wd  = $_POST['weekday'] === '' ? null : (int)$_POST['weekday'];
    $st  = (string)($_POST['start_time'] ?? '');
    $en  = (string)($_POST['end_time'] ?? '');
    $lbl = clean((string)($_POST['label'] ?? ''), 60) ?: 'Sesi';
    $cap = max(1, (int)($_POST['capacity'] ?? 1));
    if ($st && $en && $en > $st) {
      q('INSERT INTO slot_templates (weekday, start_time, end_time, label, capacity) VALUES (?,?,?,?,?)',
        [$wd, $st, $en, $lbl, $cap]);
      flash('Jam sesi ditambahkan.');
    } else flash('Jam selesai harus lebih besar dari jam mulai.');
  }

  if (isset($_POST['ubah_slot'])) {
    q('UPDATE slot_templates SET capacity = ?, active = ? WHERE id = ?',
      [max(1, (int)$_POST['capacity']), isset($_POST['active']) ? 1 : 0, (int)$_POST['id']]);
    flash('Jam sesi diperbarui.');
  }

  if (isset($_POST['hapus_slot'])) {
    q('DELETE FROM slot_templates WHERE id = ?', [(int)$_POST['id']]);
    flash('Jam sesi dihapus. Booking yang sudah masuk tidak terpengaruh.');
  }

  if (isset($_POST['tambah_blok'])) {
    $d = (string)($_POST['block_date'] ?? '');
    $t = (string)($_POST['start_time'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
      q('INSERT INTO slot_blocks (block_date, start_time, reason) VALUES (?,?,?)',
        [$d, $t !== '' ? $t : null, clean((string)($_POST['reason'] ?? ''), 160)]);
      flash('Tanggal ditutup.');
    }
  }

  if (isset($_POST['hapus_blok'])) {
    q('DELETE FROM slot_blocks WHERE id = ?', [(int)$_POST['id']]);
    flash('Blokir dibuka lagi.');
  }

  header('Location: slots.php'); exit;
}

$tpl    = all('SELECT * FROM slot_templates ORDER BY COALESCE(weekday, -1), start_time');
$blocks = all('SELECT * FROM slot_blocks WHERE block_date >= CURDATE() - INTERVAL 7 DAY ORDER BY block_date, start_time');
$cek    = (string)($_GET['cek'] ?? date('Y-m-d'));
$lihat  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $cek) ? slots_for_date($cek) : [];

admin_head('Jam sesi'); ?>

<div class="card">
  <h2>Jam sesi yang ditawarkan</h2>
  <p class="sub">Daftar ini yang muncul di form booking, dikurangi booking yang sudah masuk dan tanggal yang ditutup.
    Kapasitas = berapa sesi yang sanggup kamu jalankan bersamaan pada jam itu.</p>
  <ul class="list" style="margin-top:14px">
    <?php foreach ($tpl as $t): $fid = 's' . (int)$t['id']; ?>
      <li>
        <span style="min-width:140px;font-weight:500" class="money"><?= e(fmt_hm($t['start_time'])) ?>–<?= e(fmt_hm($t['end_time'])) ?></span>
        <span class="grow dim"><?= e($t['label']) ?> · <?= e($HARI[$t['weekday'] === null ? null : (int)$t['weekday']]) ?>
          · <?= e(fmt_hours(slot_hours($t['start_time'], $t['end_time']))) ?></span>
        <label style="margin:0;display:flex;gap:7px;align-items:center;font-size:.86rem">kapasitas
          <input form="<?= $fid ?>" name="capacity" type="number" min="1" max="9" value="<?= (int)$t['capacity'] ?>"
                 style="width:72px;min-height:38px" aria-label="Kapasitas"></label>
        <label style="margin:0;display:flex;gap:7px;align-items:center;font-size:.86rem">aktif
          <input form="<?= $fid ?>" type="checkbox" name="active" value="1" <?= (int)$t['active'] ? 'checked' : '' ?>></label>
        <button form="<?= $fid ?>" class="btn small quiet" name="ubah_slot" value="1">Simpan</button>
        <button form="<?= $fid ?>" class="btn small danger" name="hapus_slot" value="1"
                onclick="return confirm('Hapus jam sesi ini?')">Hapus</button>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php foreach ($tpl as $t): ?>
    <form id="s<?= (int)$t['id'] ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"></form>
  <?php endforeach; ?>
</div>

<form class="card" method="post">
  <?= csrf_field() ?>
  <h2>Tambah jam sesi</h2>
  <div class="grid">
    <div><label for="wd">Hari</label>
      <select id="wd" name="weekday">
        <?php foreach ($HARI as $k => $v): ?>
          <option value="<?= $k === null ? '' : (int)$k ?>"><?= e($v) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label for="s1">Mulai</label><input id="s1" name="start_time" type="time" step="900" required></div>
    <div><label for="s2">Selesai</label><input id="s2" name="end_time" type="time" step="900" required></div>
    <div><label for="lb">Nama</label><input id="lb" name="label" placeholder="Golden hour"></div>
    <div><label for="cp">Kapasitas</label><input id="cp" name="capacity" type="number" min="1" max="9" value="1"></div>
  </div>
  <div class="acts"><button class="btn" name="tambah_slot" value="1">Tambah</button></div>
</form>

<div class="card">
  <h2>Tanggal yang ditutup</h2>
  <p class="sub">Libur, sudah ada acara, atau sedang di luar kota.</p>
  <form method="post">
    <?= csrf_field() ?>
    <div class="grid">
      <div><label for="bd">Tanggal</label><input id="bd" name="block_date" type="date" required></div>
      <div><label for="bt">Jam tertentu <span class="sub">(kosong = sehari penuh)</span></label><input id="bt" name="start_time" type="time" step="900"></div>
      <div><label for="br">Alasan</label><input id="br" name="reason" placeholder="Libur"></div>
    </div>
    <div class="acts"><button class="btn quiet" name="tambah_blok" value="1">Tutup tanggal</button></div>
  </form>
  <?php if ($blocks): ?>
    <ul class="list" style="margin-top:18px">
      <?php foreach ($blocks as $bl): ?>
        <li>
          <span style="min-width:200px"><?= e(fmt_date($bl['block_date'])) ?></span>
          <span class="grow dim"><?= $bl['start_time'] ? e(fmt_hm($bl['start_time'])) : 'sehari penuh' ?><?= $bl['reason'] ? ' · ' . e($bl['reason']) : '' ?></span>
          <button form="b<?= (int)$bl['id'] ?>" class="btn small quiet" name="hapus_blok" value="1">Buka lagi</button>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php foreach ($blocks as $bl): ?>
      <form id="b<?= (int)$bl['id'] ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$bl['id'] ?>"></form>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Lihat hasilnya</h2>
  <p class="sub">Persis seperti yang dilihat klien di form booking.</p>
  <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-top:12px">
    <div style="flex:1;min-width:200px"><label for="ck">Tanggal</label><input id="ck" name="cek" type="date" value="<?= e($cek) ?>"></div>
    <button class="btn quiet" type="submit">Lihat</button>
  </form>
  <?php if (!$lihat): ?>
    <p class="sub" style="margin-top:14px">Tidak ada slot tersedia pada tanggal itu.</p>
  <?php else: ?>
    <ul class="list" style="margin-top:14px">
      <?php foreach ($lihat as $p): ?>
        <li><span class="money" style="min-width:140px"><?= e($p['start']) ?>–<?= e($p['end']) ?></span>
          <span class="grow dim"><?= e($p['label']) ?></span>
          <span class="tag <?= $p['available'] ? 'done' : 'off' ?>"><?= $p['available'] ? 'sisa ' . (int)$p['available'] : 'penuh' ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
<?php admin_foot();
