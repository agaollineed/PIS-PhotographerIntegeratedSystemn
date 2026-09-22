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
@font-face{font-family:"Poppins";font-weight:200;font-display:swap;src:url(../../fonts/poppins-latin-200-normal.woff2) format("woff2")}
@font-face{font-family:"Poppins";font-weight:300;font-display:swap;src:url(../../fonts/poppins-latin-300-normal.woff2) format("woff2")}
@font-face{font-family:"Poppins";font-weight:400;font-display:swap;src:url(../../fonts/poppins-latin-400-normal.woff2) format("woff2")}
@font-face{font-family:"Poppins";font-weight:500;font-display:swap;src:url(../../fonts/poppins-latin-500-normal.woff2) format("woff2")}
@font-face{font-family:"Plus Jakarta Sans";font-weight:300;font-display:swap;src:url(../../fonts/plus-jakarta-sans-latin-300-normal.woff2) format("woff2")}
@font-face{font-family:"Plus Jakarta Sans";font-weight:300;font-style:italic;font-display:swap;src:url(../../fonts/plus-jakarta-sans-latin-300-italic.woff2) format("woff2")}
@font-face{font-family:"Plus Jakarta Sans";font-weight:400;font-display:swap;src:url(../../fonts/plus-jakarta-sans-latin-400-normal.woff2) format("woff2")}
@font-face{font-family:"Plus Jakarta Sans";font-weight:500;font-display:swap;src:url(../../fonts/plus-jakarta-sans-latin-500-normal.woff2) format("woff2")}
@font-face{font-family:"Pinyon Script";font-weight:400;font-display:swap;src:url(../../fonts/pinyon-script-latin-400-normal.woff2) format("woff2")}

:root{
  /* palet diambil dari gradient grain di pricelist Lulus */
  --sky:#b7d3f4;
  --mist:#7fa8cb;
  --dusk:#4a6993;
  --navy:#112452;
  --abyss:#051f50;
  --deep:#03153a;
  --ink:#000;
  --paper:#fff;
  --paper-2:#f2f6fb;
  --muted:#4a5363;
  --line:rgba(0,0,0,.14);
  --on-dark:#fff;
  --on-dark-muted:rgba(255,255,255,.76);
  --on-dark-line:rgba(255,255,255,.22);
  --danger-on-dark:#ffc9c9;

  --f-ui:"Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  --f-brand:"Poppins", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  --f-script:"Pinyon Script", "Snell Roundhand", "Apple Chancery", cursive;

  --gutter:clamp(20px, 5vw, 56px);
  --wrap:min(1180px, 100% - 2 * var(--gutter));
  --grain:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='220'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.95' numOctaves='2' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
}

*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:76px;-webkit-text-size-adjust:100%;
  /* warna kanvas saat overscroll; tanpa ini muncul pita putih di ujung atas */
  background:var(--abyss);overscroll-behavior-y:none}
body{margin:0;font-family:var(--f-ui);font-size:1rem;line-height:1.6;color:var(--ink);background:var(--paper);-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
img{display:block;max-width:100%;height:auto}
picture{display:contents}
button,input,select,textarea{font:inherit}
h1,h2,h3,p,ol,ul,figure{margin:0}
a{color:inherit}
:focus-visible{outline:2px solid var(--mist);outline-offset:3px}
[hidden]{display:none!important}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.skip{position:absolute;left:12px;top:-60px;z-index:100;padding:10px 16px;border-radius:8px;background:#fff;color:var(--abyss);font-weight:500;text-decoration:none}
.skip:focus{top:12px}
.wrap{width:var(--wrap);margin-inline:auto}

/* huruf script ala logo & pricelist: satu kapital swash di dalam kata sans */
.sw{font-family:var(--f-script);font-weight:400;font-size:1.34em;line-height:0;letter-spacing:0;margin-right:-.05em}

.grain{position:relative;isolation:isolate}
.grain::after{content:"";position:absolute;inset:0;z-index:0;pointer-events:none;background-image:var(--grain);background-size:220px;mix-blend-mode:overlay;opacity:.5}
.grain > *{position:relative;z-index:1}

/* ---------- tombol ---------- */
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 1.6em;border-radius:999px;border:1px solid transparent;font:500 .95rem/1 var(--f-ui);text-decoration:none;cursor:pointer;transition:background-color .2s,color .2s,border-color .2s}
.btn-light{background:#fff;color:var(--abyss)}
.btn-light:hover{background:var(--sky)}
.btn-line-light{background:transparent;color:#fff;border-color:rgba(255,255,255,.6)}
.btn-line-light:hover{background:rgba(255,255,255,.12)}
.btn-dark{background:var(--abyss);color:#fff}
.btn-dark:hover{background:var(--navy)}

/* ---------- nav ---------- */
.nav{position:fixed;inset:0 0 auto;z-index:50;padding-top:env(safe-area-inset-top,0px);color:var(--abyss);transition:background-color .3s,color .3s}
.nav-in{width:var(--wrap);margin-inline:auto;height:68px;display:flex;align-items:center;justify-content:space-between;gap:24px}
.brand{font-family:var(--f-brand);font-weight:300;font-size:1.55rem;line-height:1;text-decoration:none;letter-spacing:-.01em;white-space:nowrap}
.brand .sw{font-size:1.5em}
.nav-links{display:flex;gap:28px;list-style:none;padding:0}
.nav-links a{font-size:.92rem;font-weight:500;text-decoration:none}
.nav-links a:hover{text-decoration:underline;text-underline-offset:6px}
.nav-cta{min-height:40px;padding:0 1.2em;font-size:.9rem;background:var(--abyss);color:#fff}
.nav-cta:hover{background:var(--navy)}
/* latar penuh, bukan blur: backdrop-filter memaksa browser menghitung ulang
   area di belakang nav setiap frame saat halaman digulir */
.nav.solid{background:var(--deep);color:#fff}
.nav.solid .nav-cta{background:#fff;color:var(--abyss)}
.nav.solid .nav-cta:hover{background:var(--sky)}
@media (max-width:860px){.nav-links{display:none}}

/* ---------- hero ---------- */
.hero{min-height:100vh;min-height:100svh;display:grid;place-items:center;text-align:center;color:#fff;overflow:hidden;
  background:
    radial-gradient(70% 55% at 88% 102%, rgba(3,21,58,.95), rgba(3,21,58,0) 70%),
    radial-gradient(75% 55% at 8% 98%, rgba(40,74,122,.75), rgba(40,74,122,0) 70%),
    linear-gradient(180deg,#b7d3f4 0%,#b1cff0 16%,#93b6da 32%,#6389b4 45%,#34568a 58%,#15305f 72%,#051f50 100%)}
.hero-in{display:grid;justify-items:center;padding:clamp(150px,24vh,240px) var(--gutter) clamp(72px,10vh,110px)}
.wordmark{font-family:var(--f-brand);font-weight:300;font-size:clamp(5.2rem,18vw,13rem);line-height:1;letter-spacing:-.02em}
.wordmark .sw{font-size:1.52em;margin-right:-.09em}
.tagline{margin-top:clamp(28px,5vh,52px);font-family:var(--f-brand);font-weight:200;font-size:clamp(1rem,1.5vw,1.2rem);letter-spacing:.05em}
.tagline b{font-weight:500}
.hero-sub{max-width:36ch;margin-top:14px;font-weight:300;font-size:1rem;color:rgba(255,255,255,.88)}
.hero-cta{display:flex;flex-wrap:wrap;justify-content:center;gap:12px;margin-top:32px}
.wordmark{animation:mark 1.6s cubic-bezier(.2,.7,.2,1) both}
.tagline,.hero-sub,.hero-cta{animation:up 1s .7s cubic-bezier(.2,.7,.2,1) both}
@keyframes mark{from{opacity:0;filter:blur(14px);letter-spacing:.08em}to{opacity:1;filter:blur(0);letter-spacing:-.02em}}
@keyframes up{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

/* ---------- band foto arak-arakan ---------- */
.band{position:relative;background:#000}
.band img{width:100%;height:clamp(300px,52vw,720px);object-fit:cover;object-position:50% 40%}
.band::before,.band::after{content:"";position:absolute;left:0;right:0;z-index:1;pointer-events:none}
.band::before{top:0;height:38%;background:linear-gradient(var(--abyss),rgba(5,31,80,0))}
.band::after{bottom:0;height:30%;background:linear-gradient(rgba(0,0,0,0),#000)}

/* ---------- tentang ---------- */
.about{position:relative;overflow:hidden;background:#000;color:var(--on-dark-muted)}
.about-photo{position:absolute;inset:0 0 0 40%;margin:0}
.about-photo img{width:100%;height:100%;object-fit:cover;object-position:62% 40%;-webkit-mask-image:linear-gradient(90deg,transparent 0,#000 45%);mask-image:linear-gradient(90deg,transparent 0,#000 45%);opacity:.9}
.about-copy{position:relative;z-index:1;max-width:34rem;padding-block:clamp(96px,14vw,180px);font-family:var(--f-brand);font-weight:300;font-size:clamp(1.12rem,1.8vw,1.45rem);line-height:1.62}
.about-copy b{font-weight:400;color:#fff}
.about-copy .sign{display:block;margin-top:1.1em;color:#fff;font-weight:400}
@media (max-width:860px){
  .about-photo{position:relative;inset:auto;height:78vw;max-height:520px}
  .about-photo img{-webkit-mask-image:linear-gradient(180deg,transparent 0,#000 22%,#000 55%,transparent);mask-image:linear-gradient(180deg,transparent 0,#000 22%,#000 55%,transparent);object-position:70% 30%}
  .about-copy{padding-block:8px 88px}
}

/* ---------- portfolio ---------- */
.moments{position:relative;min-height:clamp(440px,54vw,720px);display:flex;align-items:flex-end;background:#000;color:#fff}
.moments img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:62% 35%}
.moments::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.8) 0,rgba(0,0,0,.4) 42%,rgba(0,0,0,0) 72%),linear-gradient(0deg,rgba(0,0,0,.9) 0,rgba(0,0,0,0) 50%)}
.moments-copy{position:relative;z-index:1;padding-bottom:clamp(44px,7vw,96px)}
.moments-copy > *{max-width:31rem}
.moments-title{font-family:var(--f-brand);font-weight:500;font-size:clamp(2.1rem,4.6vw,3.5rem);line-height:1.02;letter-spacing:-.025em}
.moments-copy p{margin-top:18px;font-family:var(--f-brand);font-weight:300;color:rgba(255,255,255,.84)}
/* mosaik delapan foto: lewati perenderannya selama masih di luar layar */
.gallery{background:#000;padding:12px 0 clamp(72px,10vw,120px);
  content-visibility:auto;contain-intrinsic-size:auto 760px}
.mosaic{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;aspect-ratio:3/1.94}
.mcol{display:flex;flex-direction:column;gap:12px;min-height:0}
.mcol figure{flex:var(--r) 1 0;min-height:0;overflow:hidden;background:#111}
.mcol img{width:100%;height:100%;object-fit:cover}
@media (max-width:700px){
  .mosaic{display:block;columns:2;column-gap:8px;aspect-ratio:auto}
  .mcol{display:contents}
  .mcol figure{break-inside:avoid;margin-bottom:8px}
  .mcol img{height:auto}
}
.gallery-more{margin-top:28px;color:var(--on-dark-muted);font-size:.95rem}
.gallery-more a{color:#fff;text-underline-offset:5px}

/* ---------- section umum (terang) ---------- */
.sec{padding-block:clamp(80px,11vw,150px)}
.sec-head{display:grid;gap:14px;margin-bottom:clamp(40px,6vw,72px)}
.sec-title{font-family:var(--f-ui);font-weight:500;font-size:clamp(2.1rem,4.4vw,3.3rem);line-height:1.04;letter-spacing:-.03em}
.sec-lede{max-width:52ch;color:var(--muted);font-size:1.05rem}

/* ---------- paket ---------- */
.pkg{border-top:1px solid var(--line)}
.pkg-split{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,7fr);gap:clamp(32px,6vw,96px);align-items:center;padding-block:clamp(48px,7vw,96px)}
.pkg-split.flip{grid-template-columns:minmax(0,7fr) minmax(0,5fr)}
.pkg-split.flip .pkg-photo{order:2}
.pkg-photo img{width:100%;aspect-ratio:4/5;object-fit:cover}
.pkg-name{font-family:var(--f-ui);font-weight:500;font-size:clamp(2.3rem,4.8vw,3.7rem);line-height:1.02;letter-spacing:-.035em}
.pkg-kind{margin:.35em 0 0 2.4em;font-size:1.05rem;line-height:1.2}
.pkg-kind .sw{font-size:1.5em}
.tiers{list-style:none;padding:0;margin-top:clamp(28px,4vw,44px)}
.tier{display:grid;grid-template-columns:clamp(5.4rem,8.6vw,7.4rem) minmax(0,1fr) auto;align-items:center;gap:8px 24px;padding-block:20px;border-top:1px solid var(--line)}
.tier:last-child{border-bottom:1px solid var(--line)}
.tier-price{font-weight:500;font-size:clamp(2rem,3.3vw,2.7rem);line-height:1;letter-spacing:-.04em;white-space:nowrap}
.tier-spec{color:var(--muted);font-size:.97rem;line-height:1.45}
.tier-spec strong{color:var(--ink);font-weight:500}
.tier-pick{min-height:44px;padding:0 1.1em;border-radius:999px;border:1px solid var(--abyss);background:transparent;color:var(--abyss);font-weight:500;font-size:.9rem;cursor:pointer;white-space:nowrap;transition:background-color .2s,color .2s}
.tier-pick:hover{background:var(--abyss);color:#fff}
.pkg-note{margin-top:18px;font-style:italic;font-weight:300;color:var(--muted);font-size:.95rem}
.pkg-wide{padding-top:clamp(48px,7vw,96px)}
.pkg-wide .tiers{display:grid;grid-template-columns:1fr 1fr;column-gap:48px}
.pkg-wide .tier:last-child{border-bottom:0}
.pkg-wide .tiers{border-bottom:1px solid var(--line)}
.pkg-band{margin-top:clamp(40px,6vw,72px)}
.pkg-band img{width:100%;height:clamp(220px,30vw,420px);object-fit:cover;object-position:50% 45%;-webkit-mask-image:linear-gradient(180deg,transparent 0,#000 30%);mask-image:linear-gradient(180deg,transparent 0,#000 30%)}
.pkg-cover{position:relative;overflow:hidden;background:#000;color:#fff}
.pkg-cover img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:62% 50%}
.pkg-cover::before{content:"";position:absolute;inset:0;z-index:1;background:linear-gradient(90deg,rgba(0,0,0,.84) 0,rgba(0,0,0,.66) 42%,rgba(0,0,0,.12) 78%)}
.cover-in{position:relative;z-index:2;padding-block:clamp(72px,10vw,130px)}
.cover-in > *{max-width:36rem}
.pkg-cover .pkg-kind{font-family:var(--f-brand);font-weight:300;letter-spacing:.32em;margin-left:5.2em}
.pkg-cover .tier{border-color:rgba(255,255,255,.28)}
.pkg-cover .tier-spec{color:rgba(255,255,255,.82)}
.pkg-cover .tier-spec strong{color:#fff}
.pkg-cover .tier-pick{border-color:#fff;color:#fff}
.pkg-cover .tier-pick:hover{background:#fff;color:#000}
.pkg-cover .pkg-note{color:rgba(255,255,255,.82)}
@media (max-width:860px){
  .pkg-split,.pkg-split.flip{grid-template-columns:1fr}
  .pkg-split.flip .pkg-photo{order:0}
  .pkg-photo img{aspect-ratio:5/4}
  .pkg-wide .tiers{grid-template-columns:1fr}
  .pkg-wide .tier:last-child{border-bottom:1px solid var(--line)}
  .pkg-wide .tiers{border-bottom:0}
  .pkg-cover::before{background:rgba(0,0,0,.66)}
  .pkg-cover .pkg-kind{margin-left:3em}
}
@media (max-width:520px){
  .tier{grid-template-columns:1fr auto}
  .tier-spec{grid-column:1 / -1;grid-row:2}
}

/* ---------- ketentuan ---------- */
/* bagian paket ditutup foto arak-arakan full-bleed, jadi tanpa padding bawah
   supaya tidak ada pita putih sebelum Terms & Conditions */
#paket{padding-bottom:0}
.terms-sec{background:var(--paper-2);content-visibility:auto;contain-intrinsic-size:auto 620px}
.terms{list-style:none;padding:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 56px;counter-reset:t}
.terms li{counter-increment:t;display:grid;grid-template-columns:2.4rem minmax(0,1fr);gap:0 14px;padding-block:24px;border-top:1px solid var(--line)}
.terms li::before{content:counter(t);font-weight:500;font-size:1.5rem;line-height:1.1;color:var(--dusk)}
.terms h3{font-weight:500;font-size:1.08rem;line-height:1.3}
.terms p{grid-column:2;margin-top:6px;color:var(--muted)}
@media (max-width:760px){.terms{grid-template-columns:1fr}}

/* ---------- booking ---------- */
.booking{color:#fff;color-scheme:dark;background:
  radial-gradient(60% 50% at 100% 0,rgba(74,105,147,.55),rgba(74,105,147,0) 70%),
  linear-gradient(180deg,var(--abyss) 0,#0a2660 55%,var(--navy) 100%)}
.booking .sec-lede{color:var(--on-dark-muted)}
.book-grid{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(280px,1fr);gap:clamp(28px,4vw,56px);align-items:start}
.steps{list-style:none;padding:0;display:flex;gap:10px;margin-bottom:32px;counter-reset:s}
.steps li{flex:1;counter-increment:s;display:flex;align-items:center;gap:10px;padding-top:12px;border-top:2px solid var(--on-dark-line);color:var(--on-dark-muted);font-size:.9rem;line-height:1.3}
.steps li::before{content:counter(s);display:grid;place-items:center;flex:none;width:26px;height:26px;border-radius:50%;border:1px solid currentColor;font-size:.8rem;font-weight:500}
.steps li.done{border-top-color:var(--mist)}
.steps li[aria-current="step"]{border-top-color:#fff;color:#fff}
.steps li[aria-current="step"]::before{background:#fff;color:var(--abyss);border-color:#fff}
.step{border:0;margin:0;padding:0;min-width:0}
.step legend{padding:0;margin-bottom:18px;font-weight:500;font-size:1.3rem;line-height:1.3;color:#fff}
.step legend:focus{outline:none}
.group-label{display:block;margin:22px 0 10px;font-weight:500;font-size:.92rem}
.opt-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.opt{position:relative;display:grid;gap:2px;padding:14px 40px 14px 16px;border:1px solid var(--on-dark-line);border-radius:12px;cursor:pointer;transition:border-color .2s,background-color .2s}
.opt:hover{border-color:rgba(255,255,255,.5)}
.opt input{position:absolute;opacity:0;width:1px;height:1px;pointer-events:none}
.opt::after{content:"";position:absolute;top:16px;right:16px;width:14px;height:14px;border-radius:50%;border:1px solid rgba(255,255,255,.55)}
.opt:has(input:checked){border-color:#fff;background:rgba(255,255,255,.1)}
.opt:has(input:checked)::after{background:var(--sky);border-color:var(--sky);box-shadow:inset 0 0 0 3px var(--abyss)}
.opt:has(input:focus-visible){outline:2px solid var(--sky);outline-offset:2px}
.opt:has(input:disabled){opacity:.5;cursor:not-allowed}
.opt-title{font-weight:500;line-height:1.3}
.opt-sub{font-size:.86rem;color:var(--on-dark-muted);line-height:1.35}
.opt-price{font-weight:500;font-size:.95rem;margin-top:4px}
.field{display:grid;gap:7px;margin-top:20px}
.field > label,.field > .lbl{font-weight:500;font-size:.92rem}
.opt-label{font-weight:400;color:var(--on-dark-muted)}
.input{width:100%;min-height:48px;padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.06);color:#fff;font-size:1rem;color-scheme:dark}
.input::placeholder{color:rgba(255,255,255,.6)}
.input:focus-visible{outline:2px solid var(--sky);outline-offset:1px;border-color:transparent}
.input[aria-invalid="true"]{border-color:var(--danger-on-dark)}
select.input option{background:#0a2660;color:#fff}
textarea.input{min-height:96px;resize:vertical}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.hint{font-size:.86rem;color:var(--on-dark-muted);line-height:1.45}
.check{display:flex;gap:12px;align-items:flex-start;margin-top:24px;font-size:.95rem;cursor:pointer}
.check input{width:20px;height:20px;margin:2px 0 0;flex:none;accent-color:var(--sky)}
.check a{color:#fff;text-underline-offset:4px}
.step-error{margin-top:14px;color:var(--danger-on-dark);font-size:.92rem}
.step-error:empty{display:none}
.form-nav{display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;margin-top:30px}
.form-nav .spacer{flex:1}
.send-note{margin-top:18px;padding:14px 16px;border-radius:12px;background:rgba(183,211,244,.14);border:1px solid rgba(183,211,244,.4);font-size:.95rem}
.summary{position:sticky;top:92px;padding:26px;border-radius:18px;background:rgba(3,21,58,.6);border:1px solid rgba(255,255,255,.16)}
.summary h3{font-weight:500;font-size:1.1rem;margin-bottom:14px}
.summary dl{display:grid;grid-template-columns:auto minmax(0,1fr);gap:10px 16px;margin:0;font-size:.93rem}
.summary dt{color:var(--on-dark-muted)}
.summary dd{margin:0;text-align:right;overflow-wrap:anywhere}
.summary .sum-total{margin-top:18px;padding-top:16px;border-top:1px solid var(--on-dark-line)}
.sum-total dd{font-weight:500}
.sum-total .big dd{font-size:1.35rem;letter-spacing:-.02em}
.sum-note{margin-top:16px;font-size:.84rem;color:var(--on-dark-muted);line-height:1.5}
@media (max-width:900px){
  .book-grid{grid-template-columns:1fr}
  .summary{position:static}
}
@media (max-width:560px){
  .opt-grid,.row2{grid-template-columns:1fr}
  .steps li{font-size:0;gap:0}
  .steps li::before{font-size:.8rem}
  .steps li[aria-current="step"]{font-size:.85rem;gap:8px}
}

/* ---------- slot jadwal ---------- */
.slot-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(182px,1fr));gap:10px}
.slot-grid:empty{display:none}
.slot{position:relative;display:grid;gap:3px;padding:14px 16px;border:1px solid var(--on-dark-line);border-radius:12px;cursor:pointer;transition:border-color .2s,background-color .2s}
.slot:hover{border-color:rgba(255,255,255,.5)}
.slot input{position:absolute;opacity:0;width:1px;height:1px;pointer-events:none}
.slot:has(input:checked){border-color:#fff;background:rgba(255,255,255,.1)}
.slot:has(input:focus-visible){outline:2px solid var(--sky);outline-offset:2px}
.slot:has(input:disabled){opacity:.42;cursor:not-allowed}
.slot-time{font-weight:500}
.slot-meta{font-size:.85rem;color:var(--on-dark-muted);line-height:1.35}
.slot-left{margin-top:4px;font-size:.78rem;letter-spacing:.03em;text-transform:uppercase;color:var(--sky)}
.slot:has(input:disabled) .slot-left{color:var(--danger-on-dark)}

/* ---------- add-on ---------- */
.addons{list-style:none;padding:0;display:grid;gap:10px}
.addon{display:grid;grid-template-columns:22px minmax(0,1fr) auto;gap:4px 14px;align-items:start;padding:14px 16px;border:1px solid var(--on-dark-line);border-radius:12px;transition:border-color .2s,background-color .2s}
.addon:hover{border-color:rgba(255,255,255,.5)}
.addon:has(.addon-on:checked){border-color:#fff;background:rgba(255,255,255,.08)}
.addon-on{width:20px;height:20px;margin:2px 0 0;flex:none;accent-color:var(--sky)}
.addon-name{font-weight:500;line-height:1.3;cursor:pointer}
.addon-price{text-align:right;font-weight:500;white-space:nowrap}
.addon-desc{grid-column:2/-1;font-size:.85rem;color:var(--on-dark-muted);line-height:1.4}
.addon-qty{grid-column:2/-1;display:none;align-items:center;gap:10px;margin-top:6px;font-size:.86rem;color:var(--on-dark-muted)}
.addon:has(.addon-on:checked) .addon-qty{display:flex}
.addon-qty label{font-weight:400}
.addon-qty input{width:84px;min-height:42px;padding:8px 10px;border-radius:9px;border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.06);color:#fff;color-scheme:dark}

/* ---------- kode diskon ---------- */
.disc-row{display:flex;gap:10px;align-items:start}
.disc-row .input{flex:1;text-transform:uppercase}
.disc-row .btn{flex:none}
.disc-ok{color:var(--sky)}
.disc-bad{color:var(--danger-on-dark)}

/* ---------- konfirmasi & hasil ---------- */
.review{display:grid;grid-template-columns:auto minmax(0,1fr);gap:10px 18px;margin:0;padding:20px 22px;border-radius:14px;background:rgba(255,255,255,.06);border:1px solid var(--on-dark-line);font-size:.94rem}
.review dt{color:var(--on-dark-muted)}
.review dd{margin:0;text-align:right;overflow-wrap:anywhere}
.review .rule{grid-column:1/-1;height:1px;background:var(--on-dark-line);margin:4px 0}
.review .big dd{font-size:1.2rem;font-weight:500;letter-spacing:-.02em}
.book-done{max-width:660px;margin:0 auto;padding:clamp(28px,4vw,46px);border-radius:20px;background:rgba(3,21,58,.62);border:1px solid rgba(255,255,255,.18);text-align:center}
.done-eyebrow{font-size:.82rem;letter-spacing:.14em;text-transform:uppercase;color:var(--on-dark-muted)}
.done-code{margin-top:8px;font-weight:500;font-size:clamp(1.9rem,5vw,2.7rem);letter-spacing:.02em;line-height:1.1}
.done-lede{margin-top:14px;color:var(--on-dark-muted)}
.book-done .review{margin-top:26px;text-align:left}
.done-cta{display:flex;flex-wrap:wrap;justify-content:center;gap:12px;margin-top:26px}
.done-note{margin-top:18px;font-size:.86rem;color:var(--on-dark-muted)}
.is-busy{opacity:.55;pointer-events:none}
@media (max-width:640px){
  .slot-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
  .slot{padding:12px 13px}
  .disc-row{flex-wrap:wrap}
  .disc-row .btn{width:100%}
}

@media (prefers-reduced-motion:reduce){
  html{scroll-behavior:auto}
  *,*::before,*::after{animation:none!important;transition:none!important}
}

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
