<?php
/* Halaman cek status booking: kode + email. */
require_once __DIR__ . '/../../app/booking.php';
boot();

$code  = strtoupper(trim((string)($_POST['code']  ?? $_GET['code']  ?? '')));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$b = null;
$error = '';
$submitted = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

if ($submitted) {
  if (!csrf_ok($_POST['csrf'] ?? null)) {
    $error = 'Sesi kedaluwarsa. Coba kirim ulang formulirnya.';
  } else {
    /* Rem sederhana supaya kode booking tidak bisa ditebak beruntun. */
    $now = time();
    $win = $_SESSION['track_win'] ?? 0;
    if ($now - $win > 600) { $_SESSION['track_win'] = $now; $_SESSION['track_n'] = 0; }
    $_SESSION['track_n'] = ($_SESSION['track_n'] ?? 0) + 1;

    if ($_SESSION['track_n'] > 12) {
      $error = 'Terlalu banyak percobaan. Tunggu sepuluh menit, atau tanya admin lewat WhatsApp.';
    } elseif ($code === '' || $email === '') {
      $error = 'Isi kode booking dan email yang kamu pakai saat daftar.';
    } else {
      usleep(250000);
      $b = booking_track($code, $email);
      if (!$b) $error = 'Booking tidak ditemukan. Pastikan kode dan emailnya sama persis dengan yang kamu daftarkan.';
    }
  }
}

$site = cfg('site.name', 'Lulus');
$home = rtrim(cfg('site.url', ''), '/') . '/';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#051f50">
<title>Cek status booking | <?= e($site) ?></title>
<meta name="description" content="Lacak status booking sesi foto <?= e($site) ?> dengan kode booking dan email yang kamu pakai saat mendaftar.">
<style>
{{styles}}

/* --- khusus halaman track --- */
body{background:var(--abyss);color:#fff;color-scheme:dark}
.track-page{min-height:100svh;display:flex;flex-direction:column;
  background:radial-gradient(120% 90% at 20% 0%,var(--navy) 0,var(--abyss) 45%,var(--deep) 100%)}
.track-nav{position:static;color:#fff;border-bottom:1px solid var(--on-dark-line)}
.track-nav .nav-in{height:72px}
.track-main{flex:1;padding-block:clamp(40px,7vw,80px)}
.track-head{max-width:46rem;margin-bottom:clamp(28px,4vw,40px)}
.track-head h1{font-family:var(--f-ui);font-weight:500;font-size:clamp(2rem,4.2vw,3rem);line-height:1.05;letter-spacing:-.03em}
.track-head p{margin-top:12px;color:var(--on-dark-muted)}
.track-grid{display:grid;grid-template-columns:minmax(0,340px) minmax(0,1fr);gap:clamp(24px,4vw,48px);align-items:start}
@media(max-width:900px){.track-grid{grid-template-columns:1fr}}
.panel{padding:26px;border-radius:18px;background:rgba(3,21,58,.6);border:1px solid rgba(255,255,255,.16)}
.panel h2{font-weight:500;font-size:1.1rem;margin-bottom:6px}
.track-form .field:first-of-type{margin-top:14px}
.track-error{margin-top:16px;padding:13px 16px;border-radius:12px;font-size:.93rem;
  background:rgba(255,201,201,.12);border:1px solid rgba(255,201,201,.45);color:#ffe2e2}
.code-big{font-family:var(--f-ui);font-weight:500;font-size:clamp(1.6rem,3.4vw,2.2rem);letter-spacing:.02em}
.state{display:inline-flex;align-items:center;gap:8px;margin-top:10px;padding:7px 15px;border-radius:999px;
  background:rgba(183,211,244,.16);border:1px solid rgba(183,211,244,.45);font-size:.92rem;font-weight:500}
.state.batal{background:rgba(255,201,201,.14);border-color:rgba(255,201,201,.5)}
.timeline{list-style:none;padding:0;margin-top:26px;display:grid;gap:2px}
.timeline li{display:grid;grid-template-columns:26px minmax(0,1fr);gap:0 14px;padding-bottom:18px;position:relative}
.timeline li::before{content:"";grid-row:1/span 2;width:14px;height:14px;margin:5px 0 0 6px;border-radius:50%;
  border:1px solid rgba(255,255,255,.45);background:transparent}
.timeline li:not(:last-child)::after{content:"";position:absolute;left:12px;top:24px;bottom:0;width:1px;background:var(--on-dark-line)}
.timeline li.done::before{background:var(--mist);border-color:var(--mist)}
.timeline li.now::before{background:var(--sky);border-color:var(--sky);box-shadow:0 0 0 4px rgba(183,211,244,.22)}
.timeline li.off{opacity:.45}
.timeline b{font-weight:500}
.em{display:inline-block;margin-right:.4em}
.timeline p{font-size:.9rem;color:var(--on-dark-muted);grid-column:2}
.timeline time{display:block;font-size:.82rem;color:var(--on-dark-muted);margin-top:2px}
.detail{display:grid;grid-template-columns:auto minmax(0,1fr);gap:10px 18px;margin:0;font-size:.94rem}
.detail dt{color:var(--on-dark-muted)}
.detail dd{margin:0;text-align:right;overflow-wrap:anywhere}
.pay{margin-top:18px;padding-top:16px;border-top:1px solid var(--on-dark-line)}
.pay .big dd{font-size:1.3rem;font-weight:500;letter-spacing:-.02em}
.drive{margin-top:18px;padding:14px 16px;border-radius:12px;background:rgba(183,211,244,.14);border:1px solid rgba(183,211,244,.4);font-size:.93rem}
.track-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}
.track-foot{padding:26px 0 calc(26px + env(safe-area-inset-bottom,0px));border-top:1px solid var(--on-dark-line);
  color:var(--on-dark-muted);font-size:.88rem}
.track-foot .wrap{display:flex;flex-wrap:wrap;gap:8px 24px;justify-content:space-between}
</style>
</head>
<body>
<div class="track-page grain">

  <header class="nav track-nav">
    <div class="nav-in">
      <a class="brand" href="<?= e($home) ?>" aria-label="<?= e($site) ?>, ke beranda">
        <span aria-hidden="true"><span class="sw">L</span>u<span class="sw">l</span>us</span></a>
      <a class="btn btn-line-light nav-cta" href="<?= e($home) ?>#booking" style="background:transparent;color:#fff">Booking sesi</a>
    </div>
  </header>

  <main class="track-main">
    <div class="wrap">
      <div class="track-head">
        <h1>Cek status booking</h1>
        <p>Masukkan kode booking dan email yang kamu pakai saat mendaftar. Kode booking dikirim ke emailmu
           begitu pesanan masuk — formatnya pendek seperti <strong><?= e(code_example()) ?></strong>.</p>
      </div>

      <div class="track-grid">
        <form class="panel track-form" method="post" novalidate>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <h2>Lacak booking</h2>
          <div class="field">
            <label for="code">Kode booking</label>
            <input class="input" id="code" name="code" value="<?= e($code) ?>" required
                   autocomplete="off" spellcheck="false" placeholder="<?= e(code_example()) ?>"
                   <?= $submitted && !$b ? 'aria-invalid="true"' : '' ?>>
          </div>
          <div class="field">
            <label for="email">Email</label>
            <input class="input" id="email" name="email" type="email" value="<?= e($email) ?>" required
                   autocomplete="email" placeholder="kamu@email.com"
                   <?= $submitted && !$b ? 'aria-invalid="true"' : '' ?>>
          </div>
          <div class="track-actions"><button class="btn btn-light" type="submit">Lihat booking</button></div>
          <?php if ($error): ?><p class="track-error" role="alert"><?= e($error) ?></p><?php endif; ?>
          <?php if (cfg('site.whatsapp')): ?>
            <p class="hint" style="margin-top:18px">Lupa kode bookingmu? <a href="<?= e(wa_link('Halo admin Lulus, saya lupa kode booking saya.')) ?>"
               target="_blank" rel="noopener" style="color:#fff">Tanya admin lewat WhatsApp.</a></p>
          <?php endif; ?>
        </form>

        <?php if ($b):
          $flow = status_flow();
          $idx  = array_search($b['status'], $flow, true);
          $lastEvent = [];
          foreach ($b['events'] as $ev) $lastEvent[$ev['status']] = $ev;
          $slotTxt = booking_slot_text($b);
        ?>
        <div>
          <div class="panel">
            <p class="hint">Kode booking</p>
            <p class="code-big"><?= e($b['code']) ?></p>
            <p class="state <?= $b['status'] === 'batal' ? 'batal' : '' ?>">
              <span class="em"><?= status_emoji($b['status']) ?></span><?= e(status_label($b['status'])) ?></p>
            <p class="hint" style="margin-top:12px"><?= e($lastEvent[$b['status']]['note'] ?? status_note($b['status'])) ?></p>
            <?php if (isset($lastEvent[$b['status']])): ?>
              <p class="hint" style="margin-top:4px">Diperbarui <?= e(waktu_santai($lastEvent[$b['status']]['created_at'])) ?>.</p>
            <?php endif; ?>

            <?php if ($b['status'] === 'batal'): ?>
              <ul class="timeline"><li class="now"><b><span class="em">🙏</span>Dibatalkan</b>
                <time><?= e(waktu_santai($b['updated_at'])) ?></time></li></ul>
            <?php else: ?>
              <ul class="timeline">
                <?php foreach ($flow as $i => $st):
                  $cls = $i < $idx ? 'done' : ($i === $idx ? 'now' : 'off'); ?>
                  <li class="<?= $cls ?>">
                    <b><span class="em"><?= status_emoji($st) ?></span><?= e(status_label($st)) ?></b>
                    <?php if (isset($lastEvent[$st])): ?>
                      <time><?= e(waktu_santai($lastEvent[$st]['created_at'])) ?></time>
                    <?php endif; ?>
                    <?php if ($i === $idx): ?><p><?= e(status_note($st)) ?></p><?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <?php if ($b['status'] === 'terkirim' && $b['drive_link']): ?>
              <p class="drive">Hasil fotomu sudah siap:
                <a href="<?= e($b['drive_link']) ?>" target="_blank" rel="noopener" style="color:#fff">buka Google Drive</a>.
                Link aktif 14 hari sejak dikirim — unduh sebelum kedaluwarsa, ya.</p>
            <?php endif; ?>
          </div>

          <div class="panel" style="margin-top:18px">
            <h2>Rincian sesi</h2>
            <dl class="detail">
              <dt>Nama</dt><dd><?= e($b['client_name']) ?></dd>
              <dt>Kampus</dt><dd><?= e($b['university']) ?><?= $b['faculty'] ? ' · ' . e($b['faculty']) : '' ?></dd>
              <dt>Paket</dt><dd><?= e($b['package_name']) ?><?= (int)$b['group_size'] ? ', ' . (int)$b['group_size'] . ' orang' : '' ?></dd>
              <dt>Durasi</dt><dd><?= e($b['tier_label']) ?>, <?= (int)$b['tier_edited'] ?> foto edit</dd>
              <dt>Jadwal</dt><dd><?= e(fmt_date($b['photo_date'])) ?><br><?= e($slotTxt) ?>
                <?php if (!empty($b['hold'])): ?><br><span class="hint">jam
                  <?= e(implode(', ', array_map(fn($s) => fmt_hm($s['start_time']) . '–' . fmt_hm($s['end_time']), $b['hold']))) ?>
                  kami tahan untukmu</span><?php endif; ?></dd>
              <dt>Lokasi</dt><dd><?= e($b['area_label']) ?><?= $b['meet_point'] ? '<br>' . e($b['meet_point']) : '' ?></dd>
              <dt>Transport</dt><dd><?= e($b['transport']) ?></dd>
              <?php foreach ($b['addons'] as $a): ?>
                <dt>Add-on</dt><dd><?= e($a['name']) ?> ×<?= (int)$a['qty'] ?> — <?= e(money((int)$a['total'])) ?></dd>
              <?php endforeach; ?>
            </dl>
            <dl class="detail pay">
              <dt>Subtotal</dt><dd><?= e(money((int)$b['subtotal'])) ?></dd>
              <?php if ((int)$b['discount_amount'] > 0): ?>
                <dt>Diskon <?= e($b['discount_code']) ?></dt><dd>-<?= e(money((int)$b['discount_amount'])) ?></dd>
              <?php endif; ?>
              <div class="big" style="display:contents"><dt>Total</dt><dd><?= e(money((int)$b['total'])) ?></dd></div>
              <?php if ($b['payment'] === 'lunas'): ?>
                <dt>Pembayaran</dt><dd>Lunas di muka</dd>
              <?php else: ?>
                <dt>DP 40%</dt><dd><?= e(money((int)$b['dp_amount'])) ?></dd>
                <dt>Pelunasan maks. H-1</dt><dd><?= e(money((int)$b['rest_amount'])) ?></dd>
              <?php endif; ?>
            </dl>
            <?php if (cfg('site.whatsapp')): ?>
              <div class="track-actions">
                <a class="btn btn-light" target="_blank" rel="noopener"
                   href="<?= e(wa_link('Halo admin Lulus, saya mau tanya soal booking ' . $b['code'] . '.')) ?>">Tanya admin</a>
                <a class="btn btn-line-light" href="<?= e($home) ?>#ketentuan">Terms &amp; Conditions</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php else: ?>
        <div class="panel">
          <h2>Yang bisa kamu lacak di sini</h2>
          <ul class="timeline" style="margin-top:18px">
            <?php foreach (status_flow() as $st): ?>
              <li class="off"><b><span class="em"><?= status_emoji($st) ?></span><?= e(status_label($st)) ?></b>
                <p><?= e(status_note($st)) ?></p></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <footer class="track-foot">
    <div class="wrap">
      <span>&copy; <?= date('Y') ?> <?= e($site) ?></span>
      <span><a href="<?= e($home) ?>">Kembali ke beranda</a></span>
    </div>
  </footer>
</div>
</body>
</html>
