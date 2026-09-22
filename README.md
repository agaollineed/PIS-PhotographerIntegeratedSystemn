<div align="center">

# Lulus

**Website fotografer wisuda dengan pemesanan sesi dan panel admin.**

<img src="assets/crowd.webp" alt="Portofolio fotografi wisuda Lulus" width="720">

</div>

## Ringkasan

Lulus adalah situs fotografi wisuda untuk menampilkan portofolio, paket sesi, dan ketersediaan jadwal. Klien dapat mengirim booking dan melacak statusnya; admin mengelola slot, diskon, booking, dan pengiriman hasil foto.

| Bagian | Fitur |
|---|---|
| Situs publik | Portofolio, paket Single/Couple/Group/Arak-arakan, pilihan add-on, formulir booking |
| Booking | Validasi harga dan durasi di server, kode booking, pelacakan status dengan kode dan email |
| Admin | Booking masuk dan manual, kalender slot, diskon, perubahan status, pesan WhatsApp siap kirim |
| Notifikasi | Email opsional; alur booking tetap dapat dipakai tanpa fungsi email hosting |

## Teknologi

- Frontend: HTML, CSS, dan JavaScript tanpa framework build frontend.
- Backend: PHP 8.0+ dan MySQL.
- Generator: Python 3 untuk menghasilkan preview mandiri dan paket deploy.
- Target deploy: shared hosting dengan cPanel.

## Struktur proyek

```text
.
├── template.html          # Template halaman utama
├── build.py               # Generator preview dan paket deploy
├── php/                   # API, logika booking, instalasi, dan admin
├── assets/                # Foto dan gambar situs
├── fonts/                 # Font lokal
├── dist/
│   ├── preview/           # Preview HTML mandiri; booking hanya simulasi
│   └── deploy/            # Hasil siap unggah ke public_html
└── CARA-DEPLOY.md         # Panduan pemasangan di cPanel
```

## Jalankan secara lokal

Jalankan generator dari root proyek:

```bash
python3 build.py
```

Buka `dist/preview/lulus-landing.html` untuk melihat desain. Preview ini tidak menyimpan booking. Untuk menguji alur booking nyata, gunakan PHP dan MySQL dengan konfigurasi lokal berdasarkan `php/app/config.sample.php`.

Harga dan aturan paket didefinisikan di `build.py`. Saat dibangun, data yang sama dipakai untuk kartu harga, formulir, dan validasi harga di server.

## Deploy

Unggah **isi** `dist/deploy/` ke `public_html`, buat database MySQL, lalu salin `app/config.sample.php` menjadi `app/config.php` di server dan isi kredensial serta kontak. Jalankan `install.php` sekali untuk membuat tabel dan menyiapkan akses admin, kemudian hapus berkas instalasi dari server.

Langkah lengkap ada di [CARA-DEPLOY.md](CARA-DEPLOY.md).

## Catatan keamanan

`app/config.php` berisi kredensial dan tidak boleh di-commit. Berkas contoh `config.sample.php` hanya berisi placeholder. Sebelum situs dipakai publik, atur password admin, kunci akses admin, dan periksa konfigurasi database serta pembayaran.

## Status

Ini adalah source dan hasil build proyek Lulus. Lisensi kode dan izin penggunaan foto belum dinyatakan di repository ini; hubungi pemilik proyek sebelum menggunakan ulang asetnya.
