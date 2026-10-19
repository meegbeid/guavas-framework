# Konsep registrasi customer dan update resmi Guavas

Status: API server awal ada pada `meegbe-web`; klien registrasi, status, pembaruan token, dan pencabutan ada pada framework. Portal, updater klien, dan penerbitan rilis framework belum selesai.

## Tujuan

Framework selalu dapat dipasang dan dijalankan, baik ada kunci customer maupun tidak. Kunci digunakan agar MeeGbe mengenali customer yang dibuatkan sistem dengan Guavas dan dapat mengaitkan beberapa instalasi kepada customer yang sama. Status akses layanan update resmi adalah data terpisah; perubahan atau kedaluwarsa kunci registrasi tidak boleh menghentikan aplikasi. Kunci customer juga terpisah dari lisensi hak cipta GNU GPL dan `APP_KEY` aplikasi.

## Alur pengguna yang disarankan

1. Pengguna memperoleh paket Guavas melalui `composer create-project guavas/guavas nama-proyek` atau halaman unduh MeeGbe.
2. Saat instalasi, `scripts/setup.php` membuat `APP_KEY` dan ID instalasi acak lokal. Instalasi tetap selesai tanpa kunci customer atau koneksi ke MeeGbe.
3. Di portal `meegbe.id`, pembuat sistem membuat data customer dan satu kunci registrasi untuk customer tersebut. Beberapa sistem milik customer yang sama dapat memakai kunci customer yang sama, tetapi masing-masing memiliki ID instalasi berbeda. Bila unduhan dilakukan dari portal, kunci dapat ditampilkan di sana; instalasi dari Packagist dapat didaftarkan kemudian.
4. Pengguna atau pembuat sistem menjalankan `php guavas-cmd system register`. CLI meminta kunci secara interaktif atau membaca variabel lingkungan untuk instalasi otomatis. Kunci tidak ditaruh di argumen perintah, `composer.json`, atau riwayat shell.
5. CLI mengirim kunci, ID instalasi, label proyek opsional, dan versi Guavas melalui HTTPS ke API MeeGbe. Server mengaitkan instalasi dengan customer dan menerbitkan token instalasi. Kunci customer tidak perlu dikirim ulang pada setiap pemeriksaan update.
6. Portal MeeGbe menampilkan daftar customer beserta sistem yang terdaftar, versi terakhir yang dilaporkan, dan waktu kontak terakhir. Mengganti atau mencabut kunci registrasi hanya memengaruhi registrasi atau akses layanan berikutnya; sistem yang sudah terpasang tetap berjalan.
7. `php guavas-cmd system update --check` menggunakan token instalasi untuk layanan update resmi. Server menilai status akses update secara terpisah dari identitas customer, lalu mengembalikan manifest rilis bertanda tangan dan akses unduh berumur pendek bila tersedia.
8. Pengguna dapat menjalankan `--dry-run` untuk melihat perubahan dan konflik. Pemasangan update dilakukan setelah persetujuan eksplisit melalui perintah update. Kegagalan jaringan, kunci yang belum didaftarkan, atau status akses update tidak menghentikan aplikasi yang sudah terpasang.

## Pemisahan kunci dan data

| Data | Pembuat dan lokasi | Fungsi |
| --- | --- | --- |
| `APP_KEY` | Dibuat lokal saat instalasi; tetap di proyek pengguna | Kriptografi aplikasi dan CSRF; tidak dikirim ke MeeGbe. |
| ID instalasi | Dibuat lokal secara acak; disimpan di luar `public_html` | Membedakan setiap sistem milik customer yang sama. |
| Kunci customer | Dibuat server MeeGbe dan dikaitkan dengan customer | Kredensial registrasi untuk mengaitkan instalasi dengan customer. |
| Token instalasi | Diterbitkan server setelah registrasi; disimpan di luar `public_html` dan diabaikan Git | Autentikasi laporan versi dan permintaan update berikutnya. |
| Status akses update | Disimpan di server MeeGbe, terpisah dari kunci customer | Menentukan akses ke layanan update resmi tanpa mengubah hak menjalankan framework. |
| Kunci tanda tangan rilis | Kunci privat hanya pada sistem rilis; kunci publik ada di updater | Membuktikan rilis berasal dari penandatangan resmi. |

Server sebaiknya menyimpan hash dari kunci customer dan token, bukan nilai aslinya. File kredensial lokal, misalnya `storage/guavas-updates/registration.json`, harus diabaikan Git dan tidak pernah ditampilkan dalam log atau keluaran `system info`. Nilai `license.gv` saat ini adalah metadata lisensi kode; file tersebut tidak digunakan untuk mengidentifikasi customer. Kunci yang dibagikan kepada orang lain tidak dapat membuktikan identitas customer dengan sendirinya, sehingga portal perlu memperlihatkan setiap instalasi baru untuk ditinjau pemiliknya.

Pemeriksaan kedaluwarsa lisensi pada startup `Guavas::run()` tidak dihubungkan dengan kunci customer atau masa akses update. `system license` menampilkan lisensi kode, sedangkan `system registration status` menampilkan identitas instalasi dan customer lokal tanpa mencetak kunci atau token. Registrasi, refresh, dan revoke hanya menghubungi MeeGbe saat pengguna menjalankan perintah terkait; permintaan halaman biasa tidak menghubungi API lisensi.

## Komponen server MeeGbe

- Portal akun: mengelola customer, membuat kunci registrasi, menampilkan daftar instalasi per customer, status akses update, dan pencabutan token.
- API registrasi: menerima kunci customer dan ID instalasi; mengaitkan instalasi dengan customer dan menerbitkan token instalasi.
- API rilis: mengembalikan rilis terbaru yang sesuai dengan kanal, versi terpasang, dan hak akses.
- Layanan unduh: memberikan URL sementara atau streaming ZIP setelah otorisasi.
- Proses rilis: menghasilkan manifest dan hash arsip, lalu menandatanganinya dengan Ed25519. Kunci privat tidak berada di aplikasi web publik atau paket Guavas.
- Audit: mencatat penerbitan kunci, aktivasi, pencabutan, dan unduhan tanpa mencatat nilai kunci atau token.

Skema registrasi awal pada `db_meegbe` lokal tersedia di [UPDATE_ACCESS_SERVER_SCHEMA.sql](UPDATE_ACCESS_SERVER_SCHEMA.sql). `tbl_licenses` menyimpan identitas customer dan hash kunci; `tbl_license_installations` menyimpan beberapa instalasi per kunci dan hash token masing-masing; `tbl_license_update_entitlements` menyimpan hak layanan update secara terpisah. `tbl_license_api_limits` membatasi percobaan registrasi. `customer_id` opsional menghubungkan lisensi dengan akun `tbl_customers`, sehingga customer yang belum memiliki akun tetap dapat dikenali melalui `customer_name`. Relasi lisensi dan instalasi tetap tersimpan saat kunci registrasi diputar. Tidak adanya baris hak update berarti layanan update belum diberikan; `updates_until` kosong pada baris aktif berarti tanpa batas waktu.

Skema awal diterapkan pada database lokal dan API server sudah ditambahkan pada `meegbe-web`. Pemilik proyek telah memasang API ke server live. Portal dan proses penerbitan rilis framework belum selesai. Tabel rilis dan audit dapat ditambahkan saat layanan tersebut dibangun.

## Kontrak API awal

| Endpoint | Masukan utama | Keluaran utama |
| --- | --- | --- |
| `POST /api/guavas/v1/registrations` | Kunci customer, ID instalasi, label opsional, versi | ID registrasi, token instalasi, dan identitas customer |
| `POST /api/guavas/v1/registrations/refresh` | Token instalasi | Token baru |
| `POST /api/guavas/v1/registrations/revoke` | Token instalasi | Konfirmasi pencabutan instalasi pemilik token |
| `GET /api/guavas/v1/releases/latest` | Token, kanal, versi terpasang | Manifest bertanda tangan atau tidak ada rilis |
| `POST /api/guavas/v1/releases/download` | Token instalasi dan versi pada JSON | Streaming ZIP terautentikasi |

Respons kesalahan perlu membedakan kunci salah, instalasi belum terdaftar, akses update tidak tersedia, token dicabut, rilis belum tersedia, dan gangguan server. Semua kondisi ini hanya memengaruhi fitur registrasi atau update resmi. Token API tidak boleh disisipkan ke URL unduh yang tercatat pada log umum; jika URL bertanda tangan digunakan, masa berlakunya singkat dan cakupannya satu arsip.

## Pemeriksaan sebelum memasang update

Klien harus memverifikasi tanda tangan manifest memakai kunci publik yang dibundel, lalu memeriksa `target_project`, versi, kebutuhan PHP, daftar path yang boleh berubah, ukuran arsip, dan SHA-256 setiap file. Lakukan perencanaan konflik dan pencadangan sebelum mengganti file. Jangan menimpa `app/`, `boot/`, `storage/`, konfigurasi lokal, atau data pengguna secara diam-diam. Mekanisme updater MeeGbe yang sudah ada dapat dijadikan acuan untuk verifikasi tanda tangan, hash, konflik, dan cadangan, tetapi target `meegbe-web` serta URL rilisnya harus digeneralisasi untuk `guavas/guavas`.

## Batas distribusi dan lisensi

Jika paket berada di Packagist publik, `composer create-project` dapat dijalankan tanpa melewati portal MeeGbe. Karena itu, kunci tidak dapat dijamin terbit *pada saat setiap unduhan*. Alur yang konsisten adalah mendaftarkan customer dan instalasinya ke MeeGbe setelah instalasi atau saat proyek diserahkan. Distribusi melalui repositori Composer privat dapat mengatur unduhan langsung dari MeeGbe, tetapi penerima kode GPL tetap mempunyai hak penggunaan dan redistribusi menurut lisensinya. Kunci customer tidak boleh dijadikan syarat agar aplikasi yang sudah diterima bisa berjalan.

## Keputusan sebelum implementasi

1. Distribusi utama: Packagist publik atau repositori Composer privat MeeGbe.
2. Siapa yang membuat dan memegang kunci customer: pembuat sistem, customer, atau keduanya melalui portal.
3. Apakah layanan update resmi terbuka untuk semua instalasi terdaftar atau memiliki status akses terpisah.
4. Kanal rilis awal: hanya `stable` atau juga `beta`.
5. Bagian mana yang boleh diubah updater: disarankan hanya `core/` dan metadata versi pada tahap awal.

## Rujukan

- [Composer: peristiwa `post-create-project-cmd`](https://getcomposer.org/doc/articles/scripts.md)
- [GNU GPL FAQ: biaya distribusi dan larangan mewajibkan penerima membayar atau memberi tahu](https://www.gnu.org/licenses/gpl-faq.en.html#DoesTheGPLAllowRequireFee)
- [PHP: tanda tangan terpisah Ed25519 dengan Sodium](https://www.php.net/manual/en/function.sodium-crypto-sign-detached.php)
