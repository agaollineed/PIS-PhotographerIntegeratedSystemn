<?php
/* Booking manual: untuk pesanan yang masuk lewat WhatsApp atau DM.
   Aturannya sama persis dengan form di website — kuota slot, durasi paket,
   dan harga dihitung ulang lewat build_quote(). */
require_once __DIR__ . '/_boot.php';
admin_require();

$tanggal = (string)($_POST['date'] ?? $_GET['tanggal'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) $tanggal = '';
$slots  = $tanggal ? slots_for_date($tanggal) : [];
$error  = '';
$in     = $_POST;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['simpan'])) {
  admin_guard_post();
  [$pkgId, $tierIdx] = array_pad(explode(':', (string)($_POST['paket'] ?? '')), 2, '');

  $addons = [];
  foreach ((array)($_POST['addon'] ?? []) as $aid => $on) {
    if (!$on) continue;
    $addons[] = ['id' => $aid, 'qty' => max(1, (int)($_POST['qty'][$aid] ?? 1))];
  }

  try {
    $quote = build_quote([
      'email' => $_POST['email'] ?? '', 'name' => $_POST['name'] ?? '',
      'wa' => $_POST['wa'] ?? '', 'ig' => $_POST['ig'] ?? '',
      'university' => $_POST['university'] ?? '', 'faculty' => $_POST['faculty'] ?? '',
      'area' => $_POST['area'] ?? '', 'place' => $_POST['place'] ?? '', 'meet' => $_POST['meet'] ?? '',
      'date' => $tanggal, 'slots' => (array)($_POST['slot'] ?? []),
      'pkg' => $pkgId, 'tier' => $tierIdx, 'groupSize' => (int)($_POST['groupSize'] ?? 3),
      'addons' => $addons, 'discount' => $_POST['discount'] ?? '',
      'payment' => $_POST['payment'] ?? 'dp', 'notes' => $_POST['notes'] ?? '', 'agree' => true,
    ]);
    $b = booking_insert($quote);
    flash('Booking ' . $b['code'] . ' dibuat. Pesan WhatsApp-nya sudah siap di bawah, tinggal kirim.');
    header('Location: booking.php?id=' . (int)$b['id']); exit;
  } catch (QuoteError $e) {
    $error = $e->getMessage();
  } catch (Throwable $e) {
    error_log('[lulus] booking manual: ' . $e->getMessage());
    $error = 'Gagal menyimpan booking. Cek log server.';
  }
}

$v = fn(string $k, string $d = '') => e((string)($in[$k] ?? $d));
[$minDate, $maxDate] = date_bounds();
$terpilih = (array)($in['slot'] ?? []);

admin_head('Catat manual'); ?>

<form class="card" method="get">
  <h2>Catat booking manual</h2>
  <p class="sub">Untuk pesanan yang masuk lewat WhatsApp atau DM. Kuota slot, durasi paket, dan harganya
    dihitung dengan aturan yang sama seperti form di website, jadi jadwalnya tidak akan bentrok.</p>
  <div class="grid">
    <div><label for="tanggal">Tanggal sesi</label>
      <input id="tanggal" name="tanggal" type="date" value="<?= e($tanggal) ?>"
             min="<?= e($minDate) ?>" max="<?= e($maxDate) ?>" required></div>
    <div style="align-self:end"><div class="acts"><button class="btn" type="submit">Lihat slot</button></div></div>
  </div>
</form>

<?php if ($error): ?>
  <div class="card"><p style="color:#ffc9c9"><strong>Belum bisa disimpan:</strong> <?= e($error) ?></p></div>
<?php endif; ?>

<?php if ($tanggal && !$slots): ?>
  <div class="card"><p class="sub">Tidak ada slot tersedia pada <?= e(fmt_date($tanggal)) ?>.
    Cek <a href="slots.php">pengaturan slot</a> atau pilih tanggal lain.</p></div>
<?php endif; ?>

<?php if ($slots): ?>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="date" value="<?= e($tanggal) ?>">

  <div class="card">
    <h2>Slot — <?= e(fmt_date($tanggal)) ?></h2>
    <p class="sub">Centang sesuai durasi paket. Boleh jam yang berjauhan. Satu slot penuh tidak bisa dipilih lagi.</p>
    <div class="grid">
      <?php foreach ($slots as $s): $on = in_array($s['key'], $terpilih, true); ?>
        <label style="display:flex;gap:9px;align-items:center;margin:8px 0;<?= $s['available'] < 1 ? 'opacity:.45' : '' ?>">
          <input type="checkbox" name="slot[]" value="<?= e($s['key']) ?>" style="width:19px;min-height:19px"
                 <?= $on ? 'checked' : '' ?> <?= $s['available'] < 1 ? 'disabled' : '' ?>>
          <span><?= e($s['start']) ?>–<?= e($s['end']) ?>
            <span class="sub"><?= e($s['label']) ?> · <?= $s['available'] < 1 ? 'penuh' : 'sisa ' . (int)$s['available'] ?></span></span>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <h2>Paket</h2>
    <div class="grid">
      <div><label for="paket">Paket &amp; durasi</label>
        <select id="paket" name="paket" required>
          <option value="">-- pilih --</option>
          <?php foreach (catalog()['packages'] as $p): foreach ($p['tiers'] as $i => $t):
            $val = $p['id'] . ':' . $i; ?>
            <option value="<?= e($val) ?>" <?= ($in['paket'] ?? '') === $val ? 'selected' : '' ?>>
              <?= e($p['name']) ?> — <?= e($t['label']) ?> (<?= e(money((int)$t['price'])) ?>)
            </option>
          <?php endforeach; endforeach; ?>
        </select></div>
      <div><label for="area">Area</label>
        <select id="area" name="area" required>
          <?php foreach (catalog()['areas'] as $k => $lbl): ?>
            <option value="<?= e($k) ?>" <?= ($in['area'] ?? '') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="groupSize">Jumlah gradumates <span class="sub">(group session)</span></label>
        <select id="groupSize" name="groupSize">
          <?php foreach ([3, 4, 5] as $n): ?>
            <option value="<?= $n ?>" <?= (int)($in['groupSize'] ?? 3) === $n ? 'selected' : '' ?>><?= $n ?> orang</option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="payment">Pembayaran</label>
        <select id="payment" name="payment">
          <option value="dp" <?= ($in['payment'] ?? 'dp') === 'dp' ? 'selected' : '' ?>>DP 40%</option>
          <option value="lunas" <?= ($in['payment'] ?? '') === 'lunas' ? 'selected' : '' ?>>Lunas di muka</option>
        </select></div>
    </div>
    <label for="place">Nama lokasi <span class="sub">(wajib kalau area “kampus / lokasi lain”)</span></label>
    <input id="place" name="place" value="<?= $v('place') ?>" placeholder="Contoh: UNESA Lidah Wetan">
    <label for="meet">Titik temu</label>
    <input id="meet" name="meet" value="<?= $v('meet') ?>" placeholder="Fakultas, gedung, atau patokan">
  </div>

  <div class="card">
    <h2>Add-on</h2>
    <?php foreach (catalog()['addons'] as $a): $on = !empty($in['addon'][$a['id']]); ?>
      <div class="grid" style="align-items:center;border-bottom:1px solid rgba(255,255,255,.12);padding:8px 0">
        <label style="display:flex;gap:9px;align-items:center;margin:0">
          <input type="checkbox" name="addon[<?= e($a['id']) ?>]" value="1" style="width:19px;min-height:19px" <?= $on ? 'checked' : '' ?>>
          <span><?= e($a['name']) ?> <span class="sub"><?= e(money((int)$a['price'])) ?> /<?= e($a['unit']) ?><?= $a['hours'] ? ' · +' . e(fmt_hours((float)$a['hours'])) : '' ?></span></span>
        </label>
        <input name="qty[<?= e($a['id']) ?>]" type="number" min="1" max="<?= (int)$a['max'] ?>"
               value="<?= (int)($in['qty'][$a['id']] ?? 1) ?>" style="max-width:110px" aria-label="Jumlah <?= e($a['name']) ?>">
      </div>
    <?php endforeach; ?>
    <label for="discount">Kode diskon</label>
    <input id="discount" name="discount" value="<?= $v('discount') ?>" placeholder="Kosongkan kalau tidak ada" style="text-transform:uppercase">
  </div>

  <div class="card">
    <h2>Data klien</h2>
    <div class="grid">
      <div><label for="name">Nama lengkap</label><input id="name" name="name" value="<?= $v('name') ?>" required></div>
      <div><label for="email">Email</label><input id="email" name="email" type="email" value="<?= $v('email') ?>" required></div>
      <div><label for="wa">WhatsApp</label><input id="wa" name="wa" value="<?= $v('wa') ?>" placeholder="0812xxxxxxxx" required></div>
      <div><label for="ig">Instagram</label><input id="ig" name="ig" value="<?= $v('ig') ?>" placeholder="@username"></div>
      <div><label for="university">Universitas</label><input id="university" name="university" value="<?= $v('university') ?>" required></div>
      <div><label for="faculty">Fakultas / jurusan</label><input id="faculty" name="faculty" value="<?= $v('faculty') ?>" required></div>
    </div>
    <label for="notes">Catatan</label>
    <textarea id="notes" name="notes"><?= $v('notes') ?></textarea>
    <p class="sub" style="margin-top:10px">Email klien dipakai untuk mencocokkan kode booking di halaman track,
      jadi pastikan sama persis dengan yang dia pakai nanti.</p>
    <div class="acts"><button class="btn" type="submit" name="simpan" value="1">Simpan &amp; buat kode booking</button></div>
  </div>
</form>
<?php endif; ?>
<?php admin_foot();
