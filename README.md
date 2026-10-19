# Guavas Framework

Guavas adalah kerangka aplikasi PHP dengan router, controller, view, penyimpanan, dan perintah CLI. Repositori ini menjadi **template proyek** untuk `composer create-project guavas/guavas`; isinya adalah titik awal aplikasi baru tanpa konfigurasi bisnis MeeGbe.

## Persyaratan

- PHP 8.2 atau lebih baru, dengan ekstensi OpenSSL.
- Composer 2.
- Untuk model MySQL, aktifkan ekstensi `mysqli`. Perintah migrasi juga memerlukan `pdo_mysql`.
- Untuk server Apache, aktifkan `mod_rewrite` dan arahkan DocumentRoot ke `public_html`.

## Instalasi

Setelah paket diterbitkan di Packagist atau repositori Composer privat:

```bash
composer create-project guavas/guavas nama-proyek
cd nama-proyek
php -S 127.0.0.1:8080 -t public_html
```

Buka `http://127.0.0.1:8080/`. Halaman “Guavas siap digunakan” menandakan route, controller, dan view awal berjalan.

Composer menjalankan `scripts/setup.php` setelah proyek dibuat. Skrip ini membuat kunci aplikasi acak, konfigurasi awal, pesan HTTP, ID instalasi lokal, serta folder penyimpanan. Semua file `.gv` dan data instalasi hasil inisialisasi bersifat lokal dan diabaikan Git. Jika instalasi memakai `--no-scripts`, jalankan `php scripts/setup.php` secara manual.

## Mulai mengembangkan

1. Ubah route dalam `app/routes/web.php`.
2. Tambahkan controller dalam `app/controllers/` dan view dalam `app/views/`.
3. Tambahkan dependensi aplikasi dengan `composer require` bila diperlukan.
4. Jalankan `php guavas-cmd --help` untuk melihat perintah CLI yang tersedia.

Gunakan `php guavas-cmd doctor` untuk memeriksa persyaratan dasar. Panduan lengkap perintah `system`, `http`, `cache`, `route`, `gwsf`, dan `migrate` tersedia di [docs/CLI.md](docs/CLI.md). Setiap kelompok perintah menerima `--help`.

Contoh route bawaan mengarah ke `HomeController@index`, yang merender `app/views/home.php`. Untuk menggunakan model atau migrasi, salin `app/config/connection.example.php` menjadi `app/config/connection.php`, lalu isi kredensial proyek sendiri. Pada subclass `Model`, tetapkan `$connection = 'main'` agar memakai koneksi contoh. File koneksi aktif diabaikan Git. Buat file SQL migrasi di `database/` bila diperlukan; tidak ada migrasi yang dijalankan saat instalasi.

## Struktur proyek

| Path | Kegunaan |
| --- | --- |
| `app/` | Route, controller, view, serta kode aplikasi. |
| `app/middlewares/` | Middleware yang dapat dipasang pada route. |
| `boot/` | File PHP yang dimuat ketika Guavas mulai. |
| `core/` | Kode inti Guavas dan perintah konsol. |
| `public_html/` | DocumentRoot dan titik masuk HTTP. |
| `storage/` | Cache, sesi, log, dan berkas sementara lokal. |
| `scripts/setup.php` | Inisialisasi proyek baru. |
| `guavas-cmd` | Titik masuk CLI. |
| `database/` | File SQL migrasi milik aplikasi. |

Jangan arahkan DocumentRoot ke akar proyek karena `app/`, `core/`, dan `storage/` bukan folder publik.

## Status paket

Template lokal sudah dapat dipakai untuk uji `create-project` dengan repositori Composer bertipe `path`. Perintah instalasi sederhana di atas baru tersedia setelah repo ini memiliki rilis Git dan paket `guavas/guavas` terdaftar di Packagist atau repositori Composer privat. Nomor versi awal dalam `core/version.json` adalah `0.1.0`.

Registrasi customer opsional sudah tersedia melalui `php guavas-cmd system register`. Kunci customer diminta saat perintah dijalankan atau dibaca dari variabel lingkungan `GUAVAS_LICENSE_KEY`. Perintah `php guavas-cmd system registration status` menampilkan status lokal tanpa token; `refresh` dan `revoke` tersedia untuk mengelola token. Secara bawaan CLI menghubungi `https://meegbe.id/api/guavas/v1`; untuk lingkungan pengujian HTTPS, atur `GUAVAS_API_BASE_URL`. Registrasi tidak diperlukan untuk menjalankan framework. Rancangan layanan update tersedia di [docs/UPDATE_ACCESS_CONCEPT.md](docs/UPDATE_ACCESS_CONCEPT.md); updater klien dan rilis resmi masih dalam pengembangan.

## Lisensi dan kontribusi

Distribusi ini memakai [GNU GPL versi 3 atau lebih baru](LICENSE) karena menyertakan `core/MySQLiDB.php` yang berlisensi GPL. Atribusi kode terdahulu yang berlisensi MIT tetap tersedia di [LICENSE-MIT](LICENSE-MIT), dengan rincian dalam [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md). Panduan kontribusi ada di [CONTRIBUTING.md](CONTRIBUTING.md).
