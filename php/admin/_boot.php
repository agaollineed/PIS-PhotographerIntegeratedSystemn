<?php
/* Kerangka tampilan admin.

   Tampilannya sengaja dibangun mengelilingi satu hal: memindahkan booking
   dari satu tahap ke tahap berikutnya, lalu mengabari kliennya. Jadi tidak ada
   tabel padat atau papan statistik — yang ada jalur tahap dan kartu booking,
   memakai bahasa visual yang sama dengan halaman publik Lulus. */

require_once __DIR__ . '/../app/booking.php';
require_once __DIR__ . '/../app/mailer.php';
boot();

function admin_gate(): void {
  $secret = (string)cfg('admin.secret', '');
  if ($secret === '' || !empty($_SESSION['lulus_gate'])) return;

  $k = (string)($_GET['k'] ?? '');
  if ($k !== '' && hash_equals($secret, $k)) {
    $_SESSION['lulus_gate'] = true;
    header('Location: ' . strtok((string)($_SERVER['REQUEST_URI'] ?? 'index.php'), '?'));
    exit;
  }

  http_response_code(404);
  header('Content-Type: text/html; charset=utf-8');
  exit("<!doctype html>\n<html><head><title>404 Not Found</title></head>\n"
     . "<body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>");
}
admin_gate();

function admin_logged_in(): bool { return !empty($_SESSION['lulus_admin']); }

function admin_login_attempt(string $user, string $pass): bool {
  $u = (string)cfg('admin.user', 'admin');
  $h = (string)cfg('admin.pass_hash', '');
  usleep(300000);
  if ($h === '' || !hash_equals($u, $user)) return false;
  if (!password_verify($pass, $h)) return false;
  session_regenerate_id(true);
  $_SESSION['lulus_admin'] = true;
  return true;
}

function admin_require(): void {
  if (admin_logged_in()) return;
  header('Location: index.php');
  exit;
}

function admin_guard_post(): void {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
  if (!csrf_ok($_POST['csrf'] ?? null)) { http_response_code(419); exit('Sesi kedaluwarsa. Muat ulang halaman.'); }
}

function flash(?string $msg = null): ?string {
  boot();
  if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
  $m = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $m;
}

function csrf_field(): string {
  return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/* ---------- Bahasa tahap ---------- */

/** Warna tahap: menunggu giliran kita, sedang berjalan, sudah kelar, batal. */
function status_tone(string $s): string {
  return match ($s) {
    'menunggu'              => 'wait',
    'dikonfirmasi', 'dp'    => 'run',
    'lunas', 'sesi'         => 'run',
    'terkirim'              => 'done',
    'batal'                 => 'off',
    default                 => 'run',
  };
}

/** Tahap sesudah ini pada alur; null kalau sudah di ujung atau dibatalkan. */
function status_next(string $s): ?string {
  $flow = status_flow();
  $i = array_search($s, $flow, true);
  return ($i === false || $i + 1 >= count($flow)) ? null : $flow[$i + 1];
}

/** Satu kalimat: apa yang perlu dikerjakan admin pada tahap ini. */
function status_todo(string $s): string {
  return match ($s) {
    'menunggu'     => 'Cek slotnya, lalu konfirmasi ke klien.',
    'dikonfirmasi' => 'Menunggu DP masuk dari klien.',
    'dp'           => 'Menunggu pelunasan, paling lambat H-1.',
    'lunas'        => 'Siap dijalankan di hari sesi.',
    'sesi'         => 'Foto sedang diedit, 2–3 hari.',
    'terkirim'     => 'Selesai. Link aktif 14 hari.',
    'batal'        => 'Dibatalkan.',
    default        => '',
  };
}

/* ---------- Kerangka halaman ---------- */

function admin_head(string $title, bool $chrome = true): void {
  $f = flash();
  $here = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
  ?><!doctype html>
<html lang="id">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Lulus</title>
<style>
@font-face{font-family:"Plus Jakarta Sans";font-weight:400;font-display:swap;src:url(../fonts/plus-jakarta-sans-latin-400-normal.woff2) format("woff2")}
@font-face{font-family:"Plus Jakarta Sans";font-weight:500;font-display:swap;src:url(../fonts/plus-jakarta-sans-latin-500-normal.woff2) format("woff2")}
@font-face{font-family:"Poppins";font-weight:300;font-display:swap;src:url(../fonts/poppins-latin-300-normal.woff2) format("woff2")}
/* hanya huruf L dan l — 2,8 KB, cukup untuk wordmark */
@font-face{font-family:"Pinyon Lulus";font-weight:400;font-display:swap;src:url(../fonts/pinyon-lulus.woff2) format("woff2")}

:root{
  --paper:#fff; --paper-2:#f4f7fc; --ink:#081b3d; --muted:#5d6a83;
  --line:#dde5f1; --line-2:#c7d5e8; --sky:#b7d3f4; --navy:#112452; --abyss:#051f50;
  --wait:#a86a00; --wait-bg:#fdf3e2; --run:#1f4f96; --run-bg:#eaf1fb;
  --done:#1b6b46; --done-bg:#e8f5ee; --off:#93332f; --off-bg:#fbecec;
  --f:"Plus Jakarta Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
  --f-brand:"Poppins",system-ui,sans-serif;
  --wrap:min(1060px,100% - 40px);
}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;background:var(--paper-2);color:var(--ink);font:16px/1.6 var(--f);-webkit-font-smoothing:antialiased}
a{color:var(--abyss)}
h1,h2,h3,p,ul,ol,figure{margin:0}
button,input,select,textarea{font:inherit;color:inherit}
:focus-visible{outline:2px solid var(--run);outline-offset:2px}
[hidden]{display:none!important}

/* --- bar atas --- */
.top{position:sticky;top:0;z-index:20;background:rgba(255,255,255,.94);border-bottom:1px solid var(--line);
  -webkit-backdrop-filter:saturate(140%) blur(6px);backdrop-filter:saturate(140%) blur(6px)}
.top-in{width:var(--wrap);margin-inline:auto;display:flex;align-items:center;gap:8px 26px;flex-wrap:wrap;padding:14px 0}
.mark{font-family:var(--f-brand);font-weight:300;font-size:1.45rem;line-height:1;letter-spacing:-.01em;text-decoration:none;white-space:nowrap}
.mark .sw{font-family:"Pinyon Lulus","Snell Roundhand","Apple Chancery",cursive;font-weight:400;
  font-size:1.5em;line-height:0;letter-spacing:0;margin-right:-.09em}
.mark .tag-admin{color:var(--muted);font-size:.68rem;letter-spacing:.18em;text-transform:uppercase;margin-left:10px;vertical-align:middle}
.tabs{display:flex;gap:20px;flex-wrap:wrap;list-style:none;padding:0}
.tabs a{font-size:.94rem;text-decoration:none;color:var(--muted);padding-bottom:3px;border-bottom:2px solid transparent}
.tabs a:hover{color:var(--ink)}
.tabs a.on{color:var(--ink);font-weight:500;border-bottom-color:var(--abyss)}
.top .sp{flex:1}
.top .out{font-size:.9rem;color:var(--muted);text-decoration:none}
.top .out:hover{color:var(--ink)}

main{width:var(--wrap);margin:0 auto;padding:28px 0 72px}
.flash{background:var(--done-bg);border:1px solid #b9e0cb;border-radius:12px;padding:13px 16px;margin-bottom:22px;font-size:.95rem}

/* --- kartu --- */
.card{background:var(--paper);border:1px solid var(--line);border-radius:16px;padding:22px 24px;margin-bottom:16px}
.card > h2{font-weight:500;font-size:1.05rem;margin-bottom:4px}
.card > h2 + .sub{color:var(--muted);font-size:.9rem;margin-bottom:16px}
.sub{color:var(--muted);font-size:.9rem}

/* --- tombol --- */
.btn{display:inline-flex;align-items:center;gap:8px;min-height:42px;padding:0 18px;border-radius:999px;border:1px solid transparent;
  background:var(--abyss);color:#fff;font-size:.93rem;font-weight:500;text-decoration:none;cursor:pointer;white-space:nowrap;
  transition:background-color .15s,border-color .15s,color .15s}
.btn:hover{background:var(--navy)}
.btn.quiet{background:transparent;color:var(--ink);border-color:var(--line-2)}
.btn.quiet:hover{background:var(--paper-2);border-color:var(--muted)}
.btn.danger{background:transparent;color:var(--off);border-color:#e6c3c3}
.btn.danger:hover{background:var(--off-bg)}
.btn.small{min-height:36px;padding:0 14px;font-size:.87rem}
.acts{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}

/* --- label tahap --- */
.tag{display:inline-flex;align-items:center;gap:7px;padding:4px 12px;border-radius:999px;font-size:.8rem;font-weight:500;white-space:nowrap}
.tag::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
.tag.wait{color:var(--wait);background:var(--wait-bg)}
.tag.run{color:var(--run);background:var(--run-bg)}
.tag.done{color:var(--done);background:var(--done-bg)}
.tag.off{color:var(--off);background:var(--off-bg)}

/* --- jalur tahap di halaman daftar --- */
.lane{margin-bottom:30px}
.lane-head{display:flex;align-items:baseline;gap:12px;padding:0 4px 12px}
.lane-head h2{font-weight:500;font-size:1.06rem}
.lane-head .n{color:var(--muted);font-size:.88rem}
.lane-head .todo{color:var(--muted);font-size:.88rem;margin-left:auto;text-align:right}
.lane.empty .lane-head{padding-bottom:0;opacity:.55}
.lane.empty h2{font-weight:400;font-size:.95rem}

.bk{background:var(--paper);border:1px solid var(--line);border-radius:16px;padding:18px 20px;margin-bottom:10px;
  display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px 20px;align-items:start;transition:border-color .15s}
.bk:hover{border-color:var(--line-2)}
.bk-code{font-weight:500;font-size:1.02rem;letter-spacing:.01em}
.bk-code a{text-decoration:none}
.bk-code a:hover{text-decoration:underline;text-underline-offset:3px}
.bk-who{color:var(--muted);font-size:.92rem}
.bk-when{grid-column:1;font-size:.93rem;margin-top:4px}
.bk-when b{font-weight:500}
.bk-sum{grid-column:1;color:var(--muted);font-size:.9rem}
.bk-side{grid-row:1/span 4;grid-column:2;display:flex;flex-direction:column;align-items:flex-end;gap:10px}
.bk-acts{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
@media(max-width:720px){
  .bk{grid-template-columns:1fr}
  .bk-side{grid-row:auto;grid-column:1;align-items:flex-start;margin-top:12px}
  .bk-acts{justify-content:flex-start}
}

/* --- jalur tahap di halaman detail: ini kendalinya --- */
.track{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:0;margin:6px 0 4px;list-style:none;padding:0}
.track li{position:relative;text-align:center}
.track li::after{content:"";position:absolute;top:13px;left:50%;width:100%;height:2px;background:var(--line);z-index:0}
.track li:last-child::after{display:none}
.track li.done::after{background:var(--run)}
.step{position:relative;z-index:1;display:grid;justify-items:center;gap:8px;width:100%;padding:0 4px 4px;
  border:0;background:none;cursor:pointer;color:var(--muted);font:inherit;font-size:.82rem;line-height:1.3}
.step::before{content:"";width:28px;height:28px;border-radius:50%;background:var(--paper);border:2px solid var(--line-2)}
.step:hover{color:var(--ink)}
.step:hover::before{border-color:var(--run)}
li.done .step{color:var(--ink)}
li.done .step::before{background:var(--run);border-color:var(--run);
  box-shadow:inset 0 0 0 3px var(--paper),inset 0 0 0 9px var(--run)}
li.now .step{color:var(--ink);font-weight:500}
li.now .step::before{background:var(--abyss);border-color:var(--abyss);box-shadow:0 0 0 4px rgba(183,211,244,.55)}
.step time{display:block;color:var(--muted);font-size:.74rem;font-weight:400}
@media(max-width:760px){
  .track{grid-template-columns:1fr;gap:2px}
  .track li{text-align:left}
  .track li::after{top:22px;left:13px;width:2px;height:100%}
  .step{grid-template-columns:28px minmax(0,1fr);justify-items:start;align-items:center;gap:0 14px;padding:6px 0;text-align:left}
}

/* --- kalender ikhtisar --- */
.cal-head{display:flex;flex-wrap:wrap;align-items:baseline;gap:10px 16px;margin-bottom:4px}
.cal-head h2{flex:1;font-weight:500;font-size:1.05rem}
.cal-nav{display:flex;gap:6px}
.cal-nav a{display:grid;place-items:center;width:34px;height:34px;border:1px solid var(--line-2);border-radius:10px;
  text-decoration:none;color:var(--muted);font-size:1rem}
.cal-nav a:hover{color:var(--ink);border-color:var(--muted)}
.cal{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px;margin-top:16px}
.dow{font-size:.7rem;letter-spacing:.09em;text-transform:uppercase;color:var(--muted);text-align:center;padding-bottom:2px}
.cal .day{display:block;min-height:76px;padding:8px 9px;border:1px solid var(--line);border-radius:12px;background:var(--paper);
  text-decoration:none;color:inherit;transition:border-color .15s,background-color .15s}
a.day:hover{border-color:var(--line-2);background:var(--paper-2)}
.cal .d{display:inline-block;font-size:.88rem;font-weight:500;min-width:1.4em}
.cal .lewat{opacity:.42}
.cal .kini{border-color:var(--abyss);box-shadow:inset 0 0 0 1px var(--abyss)}
.cal .n{display:inline-block;margin-top:7px;font-size:.72rem;padding:2px 8px;border-radius:999px;
  background:var(--run);color:#fff;font-weight:500;white-space:nowrap}
.cal .jam{display:block;margin-top:4px;font-size:.7rem;color:var(--muted)}
.cal .tutup{display:block;margin-top:7px;font-size:.7rem;color:var(--off);font-weight:500}
/* Seberapa penuh hari itu dibaca dari warna latarnya, bukan dari batang kecil. */
.cal .isi1{background:#f0f5fc;border-color:#dbe6f5}
.cal .isi2{background:#dfeafa;border-color:#c3d7f1}
.cal .isi3{background:#c9dcf6;border-color:#a9c6ec}
.cal .day.libur{background:repeating-linear-gradient(135deg,#fbecec,#fbecec 6px,#fff5f5 6px,#fff5f5 12px);border-color:#eccfcf}
.cal .pad{border:0;background:none;min-height:0}
.cal-legend{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:14px;font-size:.82rem;color:var(--muted)}
@media(max-width:640px){
  .cal{gap:4px}
  .cal .day{min-height:56px;padding:6px 7px}
  .cal .d{font-size:.8rem}
  /* Di layar sempit chip "3 sesi" tidak muat — sisakan angkanya saja. */
  .cal .n{font-size:0;padding:0;width:20px;height:20px;border-radius:50%;display:grid;place-items:center}
  .cal .n::before{content:attr(data-n);font-size:.68rem;line-height:1}
  .cal .tutup{font-size:.6rem}
  .cal .jam{display:none}
}

/* --- pesan WhatsApp --- */
.wa textarea{width:100%;min-height:250px;padding:14px 16px;border-radius:12px;border:1px solid var(--line-2);
  background:var(--paper-2);font:13.5px/1.6 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;resize:vertical}
.wa-top{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px}
.wa-top select{flex:1;min-width:200px}
.said{color:var(--done);font-size:.88rem}

/* --- isian --- */
label{display:block;font-size:.86rem;color:var(--muted);margin:14px 0 6px}
input,select,textarea{width:100%;min-height:44px;padding:10px 13px;border-radius:11px;border:1px solid var(--line-2);background:var(--paper)}
input[type=checkbox]{width:19px;min-height:19px;accent-color:var(--abyss)}
textarea{min-height:90px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0 18px}

/* --- daftar sederhana (slot, diskon) --- */
.list{list-style:none;padding:0;margin:0}
.list li{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;padding:13px 0;border-top:1px solid var(--line)}
.list li:first-child{border-top:0}
.list .grow{flex:1;min-width:180px}
.list .dim{color:var(--muted);font-size:.9rem}
.money{font-variant-numeric:tabular-nums}
</style>
<?php if ($chrome): ?>
<header class="top">
  <div class="top-in">
    <a class="mark" href="index.php" aria-label="Lulus admin"><span aria-hidden="true"><span class="sw">L</span>u<span class="sw">l</span>us</span><span class="tag-admin">admin</span></a>
    <ul class="tabs">
      <li><a href="index.php" class="<?= $here === 'index.php' || $here === 'booking.php' ? 'on' : '' ?>">Booking</a></li>
      <li><a href="new.php" class="<?= $here === 'new.php' ? 'on' : '' ?>">Catat manual</a></li>
      <li><a href="slots.php" class="<?= $here === 'slots.php' ? 'on' : '' ?>">Jam sesi</a></li>
      <li><a href="discounts.php" class="<?= $here === 'discounts.php' ? 'on' : '' ?>">Diskon</a></li>
    </ul>
    <span class="sp"></span>
    <a class="out" href="<?= e(rtrim(cfg('site.url', ''), '/')) ?>/" target="_blank" rel="noopener">Lihat situs</a>
    <a class="out" href="index.php?logout=1">Keluar</a>
  </div>
</header>
<?php endif; ?>
<main>
<?php if ($f): ?><p class="flash"><?= e($f) ?></p><?php endif; ?>
<?php }

function admin_foot(): void { echo "</main>\n"; }
