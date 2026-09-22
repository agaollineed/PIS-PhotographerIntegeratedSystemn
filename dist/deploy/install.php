<?php
/* ============================================================
   Pemasangan sekali jalan: bikin tabel + isi data awal.
   Buka https://domainmu/install.php, jalankan, lalu HAPUS file ini.
   ============================================================ */
require_once __DIR__ . '/app/lib.php';
boot();

$SCHEMA = [

'bookings' => "CREATE TABLE IF NOT EXISTS bookings (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code           VARCHAR(24)  NOT NULL,
  status         VARCHAR(20)  NOT NULL DEFAULT 'menunggu',
  client_name    VARCHAR(120) NOT NULL,
  client_email   VARCHAR(160) NOT NULL,
  whatsapp       VARCHAR(20)  NOT NULL,
  instagram      VARCHAR(40)      NULL,
  university     VARCHAR(120) NOT NULL,
  faculty        VARCHAR(120) NOT NULL,
  area           VARCHAR(12)  NOT NULL,
  area_label     VARCHAR(180) NOT NULL,
  place          VARCHAR(180)     NULL,
  meet_point     VARCHAR(180)     NULL,
  transport      VARCHAR(40)      NULL,
  photo_date     DATE         NOT NULL,
  slot_hours     DECIMAL(4,2) NOT NULL DEFAULT 0,
  package_id     VARCHAR(20)  NOT NULL,
  package_name   VARCHAR(80)  NOT NULL,
  package_kind   VARCHAR(120)     NULL,
  tier_index     TINYINT      NOT NULL DEFAULT 0,
  tier_label     VARCHAR(60)  NOT NULL,
  tier_hours     DECIMAL(4,2) NOT NULL DEFAULT 0,
  tier_edited    SMALLINT     NOT NULL DEFAULT 0,
  group_size     TINYINT      NOT NULL DEFAULT 0,
  package_price  INT          NOT NULL DEFAULT 0,
  addons_total   INT          NOT NULL DEFAULT 0,
  subtotal       INT          NOT NULL DEFAULT 0,
  discount_code  VARCHAR(40)      NULL,
  discount_amount INT         NOT NULL DEFAULT 0,
  total          INT          NOT NULL DEFAULT 0,
  payment        VARCHAR(10)  NOT NULL DEFAULT 'dp',
  dp_amount      INT          NOT NULL DEFAULT 0,
  notes          TEXT             NULL,
  admin_note     TEXT             NULL,
  drive_link     VARCHAR(255)     NULL,
  client_ip      VARCHAR(45)      NULL,
  created_at     DATETIME     NOT NULL,
  updated_at     DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code),
  KEY ix_date (photo_date),
  KEY ix_status (status),
  KEY ix_email (client_email),
  KEY ix_ip (client_ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'booking_slots' => "CREATE TABLE IF NOT EXISTS booking_slots (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NOT NULL,
  slot_date  DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time   TIME NOT NULL,
  label      VARCHAR(60) NULL,
  kind       VARCHAR(8) NOT NULL DEFAULT 'sesi' COMMENT 'sesi | tahan (jam sisa add-on)',
  PRIMARY KEY (id),
  KEY ix_slot (slot_date, start_time),
  KEY ix_booking (booking_id),
  CONSTRAINT fk_slot_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'booking_addons' => "CREATE TABLE IF NOT EXISTS booking_addons (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NOT NULL,
  addon_id   VARCHAR(30)  NOT NULL,
  name       VARCHAR(120) NOT NULL,
  unit       VARCHAR(30)      NULL,
  price      INT NOT NULL DEFAULT 0,
  qty        SMALLINT NOT NULL DEFAULT 1,
  total      INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_booking (booking_id),
  CONSTRAINT fk_addon_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'booking_events' => "CREATE TABLE IF NOT EXISTS booking_events (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NOT NULL,
  status     VARCHAR(20) NOT NULL,
  note       TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_booking (booking_id),
  CONSTRAINT fk_event_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'slot_templates' => "CREATE TABLE IF NOT EXISTS slot_templates (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  weekday    TINYINT NULL COMMENT '0=Minggu ... 6=Sabtu, NULL = semua hari',
  start_time TIME NOT NULL,
  end_time   TIME NOT NULL,
  label      VARCHAR(60) NOT NULL,
  capacity   TINYINT NOT NULL DEFAULT 1,
  active     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY ix_wd (weekday, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'slot_blocks' => "CREATE TABLE IF NOT EXISTS slot_blocks (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  block_date DATE NOT NULL,
  start_time TIME NULL COMMENT 'NULL = libur sehari penuh',
  reason     VARCHAR(160) NULL,
  PRIMARY KEY (id),
  KEY ix_date (block_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'discounts' => "CREATE TABLE IF NOT EXISTS discounts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(40) NOT NULL,
  label        VARCHAR(120) NULL,
  type         ENUM('percent','amount') NOT NULL DEFAULT 'percent',
  value        DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_amount   INT NULL,
  min_subtotal INT NOT NULL DEFAULT 0,
  max_uses     INT NULL,
  used_count   INT NOT NULL DEFAULT 0,
  valid_until  DATE NULL,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

/* Slot bawaan: sesi satu jam, 05:00 sampai 20:00, kapasitas 1 fotografer.
   Jam dan kapasitasnya bisa diubah kapan saja lewat /admin/slots.php. */
$SEED_SLOTS = [];
foreach (range(5, 19) as $h) {
  $label = $h < 10 ? 'Pagi' : ($h < 14 ? 'Siang' : ($h < 16 ? 'Sore' : ($h < 18 ? 'Golden hour' : 'Malam')));
  $SEED_SLOTS[] = [null, sprintf('%02d:00:00', $h), sprintf('%02d:00:00', $h + 1), $label, 1];
}

$SEED_DISCOUNT = ['WISUDA10', 'Promo wisuda — 10%', 'percent', 10, 150000, 500000, null, null];

$log = [];
$err = null;
$hash = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['run'])) {
  try {
    foreach ($SCHEMA as $name => $sql) { db()->exec($sql); $log[] = "Tabel $name siap."; }

    /* Penyesuaian untuk pemasangan lama: CREATE TABLE IF NOT EXISTS tidak
       menambahkan kolom baru ke tabel yang sudah ada. */
    $kolom = one("SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'booking_slots' AND COLUMN_NAME = 'kind'");
    if (!(int)($kolom['c'] ?? 0)) {
      db()->exec("ALTER TABLE booking_slots ADD COLUMN kind VARCHAR(8) NOT NULL DEFAULT 'sesi'");
      $log[] = 'Kolom booking_slots.kind ditambahkan.';
    }

    if ((int)(one('SELECT COUNT(*) AS c FROM slot_templates')['c'] ?? 0) === 0) {
      foreach ($SEED_SLOTS as $s) {
        q('INSERT INTO slot_templates (weekday, start_time, end_time, label, capacity) VALUES (?,?,?,?,?)', $s);
      }
      $log[] = 'Slot bawaan diisi: ' . count($SEED_SLOTS) . ' sesi satu jam, 05:00–20:00.';
    } else {
      $log[] = 'Slot sudah ada, tidak diubah.';
    }

    if (!one('SELECT id FROM discounts WHERE code = ?', [$SEED_DISCOUNT[0]])) {
      q('INSERT INTO discounts (code, label, type, value, max_amount, min_subtotal, max_uses, valid_until)
         VALUES (?,?,?,?,?,?,?,?)', $SEED_DISCOUNT);
      $log[] = 'Contoh kode diskon WISUDA10 dibuat (nonaktifkan lewat /admin kalau tidak dipakai).';
    }
    $log[] = 'Selesai. Hapus install.php sekarang.';
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['pw'])) {
  $hash = password_hash((string)$_POST['pw'], PASSWORD_DEFAULT);
}

$secret = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['secret'])) {
  $secret = bin2hex(random_bytes(12));
}
?>
<!doctype html>
<html lang="id">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pasang Lulus</title>
<style>
  body{margin:0;padding:40px 20px;background:#0a1830;color:#e8eefb;font:16px/1.6 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
  main{max-width:720px;margin:0 auto}
  h1{font-size:1.5rem;margin:0 0 6px} h2{font-size:1.05rem;margin:34px 0 10px}
  p,li{color:#b9c6de} code{background:rgba(255,255,255,.1);padding:2px 6px;border-radius:5px;font-size:.9em}
  .card{background:#12254a;border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:20px 22px;margin-top:16px}
  button{min-height:46px;padding:0 22px;border:0;border-radius:999px;background:#b7d3f4;color:#051f50;font-weight:600;cursor:pointer}
  input{min-height:46px;padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.28);background:rgba(255,255,255,.07);color:#fff;width:100%;max-width:320px}
  .ok{color:#b6f0c6} .bad{color:#ffc9c9} ul{padding-left:20px;margin:10px 0 0}
  textarea{width:100%;min-height:76px;border-radius:10px;border:1px solid rgba(255,255,255,.28);background:rgba(255,255,255,.07);color:#fff;padding:10px 14px;font-family:ui-monospace,monospace;font-size:.85rem}
</style>
<main>
  <h1>Pasang Lulus</h1>
  <p>Jalankan sekali setelah <code>app/config.php</code> diisi. Aman diulang — tabel yang sudah ada tidak ditimpa.</p>

  <?php if ($err): ?><div class="card"><p class="bad"><strong>Gagal:</strong> <?= e($err) ?></p></div><?php endif; ?>
  <?php if ($log): ?><div class="card"><ul><?php foreach ($log as $l): ?><li class="ok"><?= e($l) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

  <h2>1. Bikin tabel &amp; data awal</h2>
  <form method="post" class="card">
    <p>Database tujuan: <code><?= e(cfg('db.name')) ?></code> di <code><?= e(cfg('db.host')) ?></code></p>
    <button type="submit" name="run" value="1">Jalankan pemasangan</button>
  </form>

  <h2>2. Password admin</h2>
  <form method="post" class="card">
    <p>Ketik password admin, salin hasil hash-nya ke <code>app/config.php</code> bagian <code>admin.pass_hash</code>.</p>
    <p><input type="text" name="pw" placeholder="password baru" autocomplete="new-password"></p>
    <p><button type="submit">Bikin hash</button></p>
    <?php if ($hash): ?><p><textarea readonly onclick="this.select()"><?= e($hash) ?></textarea></p><?php endif; ?>
  </form>

  <h2>3. Pintu masuk admin</h2>
  <form method="post" class="card">
    <p>Selama <code>admin.secret</code> diisi, halaman <code>/admin/</code> menjawab <strong>404</strong> untuk
      siapa pun yang tidak membuka tautan rahasianya lebih dulu. Buat nilainya di sini,
      salin ke <code>app/config.php</code> bagian <code>admin.secret</code>, lalu simpan tautannya di bookmark.</p>
    <p><button type="submit" name="secret" value="1">Bikin kunci rahasia</button></p>
    <?php if ($secret): ?>
      <p><textarea readonly onclick="this.select()"><?= e($secret) ?></textarea></p>
      <p>Tautan masuknya:</p>
      <p><textarea readonly onclick="this.select()"><?= e(rtrim(cfg('site.url', ''), '/')) ?>/admin/?k=<?= e($secret) ?></textarea></p>
    <?php endif; ?>
  </form>

  <h2>4. Terakhir</h2>
  <div class="card"><p>Hapus <code>install.php</code> dari server, lalu buka tautan admin di atas untuk mengatur
    slot, diskon, status booking, dan membuat booking manual.</p></div>
</main>
