# Cara deploy Lulus ke cPanel

Isi folder ini sudah siap unggah. Butuh PHP 8.0+ dan satu database MySQL —
keduanya standar di paket hosting cPanel.

## 1. Unggah

1. cPanel → **File Manager** → masuk ke `public_html`.
2. Unggah seluruh isi folder `deploy` (bukan foldernya, isinya), lalu Extract kalau diunggah sebagai zip.

Susunannya jadi seperti ini:

```
public_html/
  index.php            halaman utama + form booking
  booking/track/       halaman cek status booking
  api/                 endpoint slot, diskon, kirim booking
  admin/               panel admin
  app/                 konfigurasi & kode internal (tidak bisa dibuka dari browser)
  assets/  fonts/
  install.php          dipakai sekali, lalu hapus
```

## 2. Bikin database

cPanel → **MySQL Databases**:

1. Buat database, misal `lulus`.
2. Buat user database, beri password yang kuat.
3. **Add User To Database** → centang **ALL PRIVILEGES**.

Catat nama database, user, dan password lengkap dengan prefix akunmu
(biasanya `namaakun_lulus`).

## 3. Isi konfigurasi

1. Di File Manager, salin `app/config.sample.php` menjadi `app/config.php`.
2. Edit `app/config.php`:
   - `db` → host `localhost`, lalu nama database, user, dan password dari langkah 2.
   - `site.url` → alamat situs tanpa garis miring di akhir, misal `https://lulus.id`.
   - `site.whatsapp` → nomor admin format `62xxxxxxxxxx` (tanpa `+` dan tanpa spasi).
   - `site.instagram`, `site.email` → opsional; kalau kosong, tautannya otomatis disembunyikan.
   - `mail.from` → alamat email di domain sendiri, misal `booking@lulus.id`.
     Buat dulu di cPanel → **Email Accounts** supaya emailnya tidak masuk spam.
   - `mail.admin_to` → email yang menerima pemberitahuan tiap ada booking baru.
   - `booking.payment_info` → nomor rekening yang ikut ditempel di pesan penagihan DP.

## 4. Jalankan pemasangan

1. Buka `https://domainmu/install.php`.
2. Klik **Jalankan pemasangan** — tabel dibuat, slot bawaan dan contoh kode diskon diisi.
   Aman diulang kapan saja; kalau ada kolom baru dari versi berikutnya, ini yang menambahkannya.
3. Di bagian **Password admin**, ketik password yang kamu mau, klik **Bikin hash**,
   salin hasilnya ke `app/config.php` bagian `admin.pass_hash`.
4. Di bagian **Pintu masuk admin**, klik **Bikin kunci rahasia**, salin nilainya ke
   `app/config.php` bagian `admin.secret`, lalu simpan tautan `/admin/?k=...` di bookmark.
5. **Hapus `install.php` dari server.**

## 5. Cek

- `https://domainmu/` → form booking jalan, slot muncul setelah tanggal dipilih.
- `https://domainmu/booking/track/` → halaman cek status booking.
- `https://domainmu/admin/` → masuk pakai user `admin` dan password langkah 4.

Coba booking satu kali sebagai uji coba, lalu hapus lewat halaman admin.

## Admin: tanpa biaya tambahan, tanpa pintu yang kelihatan

Admin ikut di dalam hosting dan domain yang sama — tidak ada langganan, subdomain,
atau layanan lain yang perlu dibeli. Yang membedakannya cuma satu: pintunya sengaja
tidak kelihatan.

Tiga lapis pengamanannya:

1. **Tautan rahasia.** Selama `admin.secret` diisi, membuka `/admin/` langsung menjawab
   *404 Not Found* — sama seperti halaman yang memang tidak ada. Satu-satunya jalan masuk
   adalah `https://domainmu/admin/?k=<secret>`. Setelah dibuka sekali, sesimu diizinkan
   sampai kamu menekan Keluar. Simpan tautan itu di bookmark, jangan dibagikan.
2. **Password.** Setelah pintunya terbuka pun, masih ada layar login.
3. **Tidak ditautkan dan tidak diindeks.** Tidak ada tautan ke admin dari halaman mana pun,
   dan headernya memasang `noindex, nofollow` supaya tidak masuk hasil pencarian.

Mau lebih jauh? Folder `admin` boleh kamu ganti namanya jadi apa saja
(misalnya `ruang-kerja` atau `panel-7f3a2b`) — semua tautan di dalamnya relatif,
jadi tetap jalan tanpa mengubah kode apa pun. Alamatnya jadi
`https://domainmu/ruang-kerja/?k=<secret>`. Lapis paling keras: cPanel →
**Directory Privacy** → kunci folder itu dengan password tingkat server.

## Pemakaian sehari-hari

| Mau apa | Buka |
|---|---|
| Lihat booking masuk, ubah status, ambil pesan WhatsApp | `/admin/` |
| Catat booking yang masuk lewat WhatsApp atau DM | `/admin/new.php` |
| Atur jam sesi per hari, tutup tanggal libur | `/admin/slots.php` |
| Bikin dan matikan kode diskon | `/admin/discounts.php` |

**Alur harian yang dimaksudkan:**

1. Booking masuk dari website. Kode booking dibuat otomatis, misalnya `LLS-260923-001`.
2. Buka booking itu di admin. Di kotak **Pesan untuk klien** sudah tersedia teks siap kirim
   lengkap dengan kode booking, jadwal, total, dan tautan cek status. Klik **Buka WhatsApp**
   untuk langsung membuka chat ke nomor klien dengan teks itu, atau **Salin teks** kalau mau
   ditempel sendiri.
3. Begitu slot dipastikan, ubah statusnya jadi *Slot dikonfirmasi*. Pesan di kotak itu ikut
   berubah jadi penagihan DP lengkap dengan nomor rekening dari `booking.payment_info`.
4. Setiap perubahan status langsung terlihat klien di `/booking/track/`, tanpa perlu ditanyakan.
5. Setelah sesi selesai dan foto diedit, tempel link Google Drive di kolom **Link Google Drive
   hasil foto**, ubah status jadi *Hasil dikirim* — linknya otomatis ikut ke pesan WhatsApp dan
   muncul di halaman track.

Booking yang datang lewat WhatsApp atau DM dicatat di **Booking manual**. Aturannya sama persis
dengan form di website — kuota slot, durasi paket, dan harga dihitung ulang — jadi jadwalnya tidak
akan bentrok dengan booking dari website. Begitu disimpan, kodenya langsung jadi dan pesan
WhatsApp-nya sudah siap kirim.

Email otomatis sifatnya pelengkap. Kalau `mail.enabled` dimatikan atau hosting memblokir
`mail()`, seluruh alur di atas tetap jalan penuh lewat WhatsApp.

## Kalau email tidak terkirim

Sebagian hosting mematikan fungsi `mail()` PHP. Situs tetap jalan — hanya email
otomatisnya yang tidak keluar. Cek `app/config.php`:

- pastikan `mail.from` memakai alamat di domain sendiri;
- kalau tetap gagal, set `mail.enabled` ke `false` supaya tidak ada jeda saat kirim booking,
  dan andalkan notifikasi WhatsApp.

## Mengubah harga atau paket

Harga tidak diedit langsung di file hasil build. Ubah di `build.py`
(bagian `PKGS` dan `ADDONS`), jalankan `python3 build.py`, lalu unggah ulang
`index.php` dan `app/catalog.php`.

## Mengganti foto

Taruh JPEG barunya di `assets/`, lalu:

```
python3 compress.py && python3 build.py
```

`compress.py` membuat versi WebP dari tiap JPEG — dan versi kecil untuk foto
full-bleed `crowd` dan `ijazah` supaya ponsel tidak mengunduh gambar 1920px.
Halaman memakai `<picture>`: browser mengambil WebP, dan JPEG-nya tetap ada
sebagai cadangan untuk browser lama. Butuh `cwebp` sekali pasang
(`brew install webp` di macOS, `apt install webp` di Linux).

Ukuran halaman sekarang sekitar 630 KB di desktop dan 570 KB di ponsel,
turun dari 880 KB.

## Font

`subset_fonts.py` membuang huruf yang tidak terpakai dari Pinyon Script — font hias
untuk wordmark dan kapital nama paket. Hasilnya 38 KB jadi 18 KB, plus versi 2,8 KB
berisi huruf L dan l saja untuk wordmark di admin. Cukup dijalankan ulang kalau kamu
mengganti font-nya:

```
pip install fonttools brotli && python3 subset_fonts.py && python3 build.py
```
