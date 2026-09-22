<?php
/* Halaman utama: booking disusun menurut tahapnya, bukan tabel.
   Satu klik memajukan booking ke tahap berikutnya dan membuka pesannya. */
require_once __DIR__ . '/_boot.php';

if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: index.php'); exit; }

/* ---------- login ---------- */
if (!admin_logged_in()) {
  $bad = false;
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    admin_guard_post();
    if (admin_login_attempt((string)($_POST['user'] ?? ''), (string)($_POST['pass'] ?? ''))) {
      header('Location: index.php'); exit;
    }
    $bad = true;
  }
  admin_head('Masuk', false); ?>
  <div class="card" style="max-width:400px;margin:14vh auto 0">
    <p class="mark" style="display:block;margin-bottom:20px"><span aria-hidden="true"><span class="sw">L</span>u<span class="sw">l</span>us</span><span class="tag-admin">admin</span></p>
    <?php if ($bad): ?><p class="tag off" style="margin-bottom:10px">User atau password salah</p><?php endif; ?>
    <?php if (!cfg('admin.pass_hash')): ?>
      <p class="sub">Password admin belum diset. Buka <code>install.php</code> untuk membuat hash,
        lalu tempel ke <code>app/config.php</code>.</p>
    <?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label for="u">User</label><input id="u" name="user" autocomplete="username" required autofocus>
      <label for="p">Password</label><input id="p" name="pass" type="password" autocomplete="current-password" required>
      <div class="acts"><button class="btn" type="submit">Masuk</button></div>
    </form>
  </div>
  <?php admin_foot(); exit;
}

/* ---------- memajukan tahap langsung dari kartu ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  admin_guard_post();
  $id   = (int)($_POST['id'] ?? 0);
  $maju = (string)($_POST['maju'] ?? '');
  if ($id && isset(status_list()[$maju])) {
    booking_set_status($id, $maju);
    flash('Sekarang di tahap "' . status_label($maju) . '". Pesan untuk kliennya sudah siap di bawah.');
    header('Location: booking.php?id=' . $id); exit;
  }
  header('Location: index.php'); exit;
}

$cari    = clean((string)($_GET['q'] ?? ''), 60);
$tanggal = (string)($_GET['tanggal'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) $tanggal = '';

if ($tanggal !== '') {
  $rows = all('SELECT * FROM bookings WHERE photo_date = ? ORDER BY id ASC', [$tanggal]);
} elseif ($cari !== '') {
  $like = '%' . $cari . '%';
  $rows = all('SELECT * FROM bookings
                WHERE code LIKE ? OR client_name LIKE ? OR client_email LIKE ? OR whatsapp LIKE ? OR university LIKE ?
                ORDER BY id DESC LIMIT 100', array_fill(0, 5, $like));
} else {
  $rows = all('SELECT * FROM bookings ORDER BY photo_date ASC, id ASC LIMIT 400');
}

/* Kelompokkan per tahap, urut sesuai alur. */
$lanes = [];
foreach (array_merge(status_flow(), ['batal']) as $st) $lanes[$st] = [];
foreach ($rows as $r) $lanes[$r['status']][] = $r;
foreach (['terkirim', 'batal'] as $st) $lanes[$st] = array_reverse($lanes[$st]);   // yang selesai: terbaru dulu

$perlu = count($lanes['menunggu']);

/* ---------- kalender ikhtisar ---------- */
$bulan = (string)($_GET['bulan'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) $bulan = date('Y-m');
$awal  = $bulan . '-01';
$hari1 = (int)date('N', strtotime($awal));          // 1 = Senin
$jml   = (int)date('t', strtotime($awal));
$akhir = date('Y-m-t', strtotime($awal));

/* Tiga query untuk seluruh bulan, bukan per hari. */
$tplAll = all('SELECT weekday, capacity FROM slot_templates WHERE active = 1');
$sesi = [];
foreach (all('SELECT bs.slot_date AS d, COUNT(DISTINCT b.id) AS orang, COUNT(*) AS slot
                FROM booking_slots bs JOIN bookings b ON b.id = bs.booking_id
               WHERE bs.slot_date BETWEEN ? AND ? AND b.status <> "batal"
               GROUP BY bs.slot_date', [$awal, $akhir]) as $r) {
  $sesi[$r['d']] = ['orang' => (int)$r['orang'], 'slot' => (int)$r['slot']];
}
$tutup = [];
foreach (all('SELECT block_date, start_time FROM slot_blocks WHERE block_date BETWEEN ? AND ?', [$awal, $akhir]) as $r) {
  $tutup[$r['block_date']] = $r['start_time'] === null ? 'penuh' : 'sebagian';
}

/** Kapasitas slot pada satu hari dalam seminggu. */
function kapasitas_hari(array $tpl, int $wd): int {
  $n = 0;
  foreach ($tpl as $t) if ($t['weekday'] === null || (int)$t['weekday'] === $wd) $n += (int)$t['capacity'];
  return $n;
}

$BULAN = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

/** Satu kartu booking. */
function kartu(array $r): void {
  $next = status_next($r['status']);
  $slot = one('SELECT GROUP_CONCAT(CONCAT(LEFT(start_time,5), "–", LEFT(end_time,5)) ORDER BY start_time SEPARATOR ", ") AS s
                 FROM booking_slots WHERE booking_id = ? AND kind <> \'tahan\'', [$r['id']])['s'] ?? '';
  ?>
  <article class="bk">
    <p class="bk-code"><a href="booking.php?id=<?= (int)$r['id'] ?>"><?= e($r['code']) ?></a></p>
    <p class="bk-who"><?= e($r['client_name']) ?> · <?= e($r['whatsapp']) ?></p>
    <p class="bk-when"><b><?= e(fmt_date($r['photo_date'])) ?></b><?= $slot ? ' · ' . e($slot) : '' ?></p>
    <p class="bk-sum"><?= e($r['package_name']) ?> — <?= e($r['tier_label']) ?> ·
      <span class="money"><?= e(money((int)$r['total'])) ?></span>
      <?= $r['payment'] === 'lunas' ? '(lunas di muka)' : '(DP ' . e(money((int)$r['dp_amount'])) . ')' ?></p>
    <div class="bk-side">
      <span class="tag <?= e(status_tone($r['status'])) ?>"><?= e(status_label($r['status'])) ?></span>
      <form class="bk-acts" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <?php if ($next): ?>
          <button class="btn small" name="maju" value="<?= e($next) ?>">→ <?= e(status_label($next)) ?></button>
        <?php endif; ?>
        <a class="btn small quiet" target="_blank" rel="noopener"
           href="<?= e(wa_link_to($r['whatsapp'], wa_message_short($r))) ?>">WhatsApp</a>
        <a class="btn small quiet" href="booking.php?id=<?= (int)$r['id'] ?>">Buka</a>
      </form>
    </div>
  </article>
<?php }

admin_head('Booking'); ?>

<section class="card">
  <div class="cal-head">
    <h2><?= e($BULAN[(int)date('n', strtotime($awal))]) ?> <?= e(date('Y', strtotime($awal))) ?></h2>
    <span class="sub"><?= array_sum(array_column($sesi, 'orang')) ?> sesi bulan ini</span>
    <div class="cal-nav">
      <a href="?bulan=<?= e(date('Y-m', strtotime($awal . ' -1 month'))) ?>" aria-label="Bulan sebelumnya">‹</a>
      <a href="?bulan=<?= e(date('Y-m')) ?>" aria-label="Bulan ini" style="width:auto;padding:0 12px;font-size:.85rem">Hari ini</a>
      <a href="?bulan=<?= e(date('Y-m', strtotime($awal . ' +1 month'))) ?>" aria-label="Bulan berikutnya">›</a>
    </div>
  </div>
  <div class="cal">
    <?php foreach (['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $d): ?><span class="dow"><?= $d ?></span><?php endforeach; ?>
    <?php for ($i = 1; $i < $hari1; $i++): ?><span class="day pad"></span><?php endfor; ?>
    <?php for ($d = 1; $d <= $jml; $d++):
      $iso  = sprintf('%s-%02d', $bulan, $d);
      $wd   = (int)date('w', strtotime($iso));
      $isi  = $sesi[$iso] ?? null;
      $cap  = kapasitas_hari($tplAll, $wd);
      $ktp  = $tutup[$iso] ?? null;
      $pct  = ($cap && $isi) ? 100 * $isi['slot'] / $cap : 0;
      $cls  = ($iso < date('Y-m-d') ? ' lewat' : '') . ($iso === date('Y-m-d') ? ' kini' : '')
            . ($ktp === 'penuh' ? ' libur' : ($pct >= 67 ? ' isi3' : ($pct >= 34 ? ' isi2' : ($pct > 0 ? ' isi1' : '')))); ?>
      <a class="day<?= $cls ?>" href="?tanggal=<?= e($iso) ?>"
         title="<?= e(fmt_date($iso)) ?><?= $ktp === 'penuh' ? ' · tutup' : ($isi ? ' · ' . $isi['orang'] . ' sesi, ' . $isi['slot'] . ' dari ' . $cap . ' jam terpakai' : ' · kosong') ?>">
        <span class="d"><?= $d ?></span>
        <?php if ($ktp === 'penuh'): ?><span class="tutup">tutup</span>
        <?php elseif ($isi): ?>
          <span class="n" data-n="<?= (int)$isi['orang'] ?>"><?= $isi['orang'] ?> sesi</span>
          <span class="jam"><?= $isi['slot'] ?>/<?= $cap ?> jam</span>
        <?php endif; ?>
      </a>
    <?php endfor; ?>
  </div>
  <p class="cal-legend"><span>Klik tanggal untuk melihat sesinya.</span>
    <span>Makin biru latarnya, makin penuh jam sesi hari itu.</span>
    <span>Arsir merah = tanggal ditutup.</span></p>
</section>

<form class="card" method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
  <input name="q" value="<?= e($cari) ?>" placeholder="Cari kode booking, nama, email, atau nomor WhatsApp"
         style="flex:1;min-width:240px" aria-label="Cari booking">
  <button class="btn quiet" type="submit">Cari</button>
  <?php if ($tanggal !== ''): ?>
  <div class="lane">
    <div class="lane-head">
      <h2><?= e(fmt_date($tanggal)) ?></h2>
      <span class="n"><?= count($rows) ?> sesi</span>
      <span class="todo"><a href="index.php">Semua booking</a></span>
    </div>
    <?php foreach ($rows as $r) kartu($r); ?>
    <?php if (!$rows): ?><p class="sub" style="padding:0 4px">Belum ada sesi pada tanggal ini.</p><?php endif; ?>
  </div>
<?php elseif ($cari !== ''): ?><a class="btn quiet" href="index.php">Semua</a><?php endif; ?>
</form>

<?php if ($cari !== ''): ?>
  <div class="lane">
    <div class="lane-head"><h2><?= count($rows) ?> hasil untuk “<?= e($cari) ?>”</h2></div>
    <?php foreach ($rows as $r) kartu($r); ?>
    <?php if (!$rows): ?><p class="sub" style="padding:0 4px">Tidak ada yang cocok.</p><?php endif; ?>
  </div>
<?php else: ?>

  <?php if ($perlu): ?>
    <p class="sub" style="margin:0 4px 20px"><?= $perlu ?> booking menunggu dikonfirmasi.</p>
  <?php else: ?>
    <p class="sub" style="margin:0 4px 20px">Tidak ada yang menunggu konfirmasi. Semua terkendali.</p>
  <?php endif; ?>

  <?php foreach ($lanes as $st => $daftar):
    if ($st === 'batal' && !$daftar) continue; ?>
    <section class="lane <?= $daftar ? '' : 'empty' ?>">
      <div class="lane-head">
        <h2><?= e(status_label($st)) ?></h2>
        <span class="n"><?= $daftar ? count($daftar) : 'kosong' ?></span>
        <?php if ($daftar): ?><span class="todo"><?= e(status_todo($st)) ?></span><?php endif; ?>
      </div>
      <?php foreach ($daftar as $r) kartu($r); ?>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
<?php admin_foot();
