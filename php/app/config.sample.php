<?php
/* ============================================================
   Lulus — konfigurasi situs
   Salin file ini jadi config.php lalu isi sesuai akun cPanel.
   File config.php TIDAK ikut ke repo/zip publik.
   ============================================================ */

return [

  /* ---- Database MySQL (cPanel > MySQL Databases) ---- */
  'db' => [
    'host' => 'localhost',
    'name' => 'namauser_lulus',
    'user' => 'namauser_lulus',
    'pass' => '',
    'charset' => 'utf8mb4',
  ],

  /* ---- Identitas & kontak ---- */
  'site' => [
    'name'      => 'Lulus',
    'url'       => 'https://lulus.id',      // tanpa garis miring di akhir
    'whatsapp'  => '',                      // 62xxxxxxxxxx, tanpa + dan spasi
    'instagram' => '',                      // username tanpa @
    'email'     => '',                      // email kontak yang tampil di situs
    'timezone'  => 'Asia/Jakarta',
  ],

  /* ---- Aturan bisnis ---- */
  'booking' => [
    'dp_rate'        => 0.40,   // DP 40% sesuai Terms & Conditions
    'code_prefix'    => 'LLS',  // kode booking jadi LLS-7F3K
    'max_days_ahead' => 365,    // batas tanggal sesi yang boleh dipilih
    'min_days_ahead' => 0,      // 0 = boleh booking untuk hari ini

    // Ikut ditempel di pesan WhatsApp penagihan DP. Kosongkan kalau belum ada.
    'payment_info'   => 'BCA 1234567890 a.n. Nama Pemilik',
  ],

  /* ---- Email ---- */
  'mail' => [
    'enabled'    => true,
    'from'       => 'booking@lulus.id',   // harus alamat di domain sendiri
    'from_name'  => 'Lulus',
    'admin_to'   => '',                   // notifikasi booking masuk; kosong = tidak dikirim
    'reply_to'   => '',                   // kosong = pakai 'from'
  ],

  /* ---- Admin panel (/admin) ----
     Bikin hash-nya sekali lewat install.php, lalu tempel di sini. */
  'admin' => [
    'user' => 'admin',
    'pass_hash' => '',                    // hasil password_hash('...', PASSWORD_DEFAULT)

    // Pintu masuk rahasia. Selama ini diisi, /admin/ menjawab 404 untuk siapa pun
    // yang tidak membuka /admin/?k=<secret> lebih dulu. Simpan tautan itu di bookmark.
    // Kosongkan kalau cukup pakai password saja. install.php bisa membuatkan nilainya.
    'secret' => '',
  ],
];
