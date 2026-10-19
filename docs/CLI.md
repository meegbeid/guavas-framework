# Perintah CLI Guavas

Jalankan dari akar proyek dengan `php guavas-cmd`. Bantuan umum tersedia lewat `php guavas-cmd --help`; setiap kelompok menerima `--help`. Perintah yang tidak dikenal atau opsi yang tidak valid mengembalikan pesan kesalahan.

| Perintah | Kegunaan |
| --- | --- |
| `doctor` | Memeriksa PHP, OpenSSL, konfigurasi, dan struktur dasar proyek tanpa mengubah data. |
| `system info` | Menampilkan versi dan informasi lisensi kode. |
| `system config` | Menampilkan konfigurasi aktif; `APP_KEY` disembunyikan. |
| `system set NAMA NILAI` | Mengubah opsi awal yang didukung; lihat `system --help`. |
| `system license` | Menampilkan status lisensi kode framework. |
| `system key --generate` | Menampilkan kunci aplikasi acak baru tanpa menyimpannya. |
| `system register [--label=NAMA]` | Mendaftarkan instalasi ke MeeGbe dengan kunci customer. |
| `system registration status` | Menampilkan status registrasi lokal tanpa token. |
| `system registration refresh` | Memutar token instalasi melalui API. |
| `system registration revoke` | Mencabut token di server, lalu menghapus token lokal. |
| `http [--ip=IP] [--port=PORT] [--dir=FOLDER] [--force-index]` | Menjalankan server PHP lokal. Folder harus berada di dalam `public_html`. |
| `cache clear [--name=NAMA\|--expiry-only\|--view]` | Membersihkan cache yang dipilih. |
| `route Controller@method [--parameter=nilai]` | Memanggil method controller publik melalui CLI. Gunakan hanya untuk method yang memang aman dipanggil dari terminal. |
| `gwsf blacklist`, `gwsf ipban` | Melihat riwayat firewall dan daftar IP terblokir. |
| `gwsf add/remove --whitelist=IP` | Menambah atau menghapus IP pada whitelist. |
| `gwsf add/remove --blacklist=IP` | Menambah atau menghapus IP pada daftar blokir. |
| `gwsf release --ip=IP` | Melepas IP dari riwayat blacklist dan daftar blokir. |
| `gwsf ipban-update` | Memblokir IP yang sudah mencapai lima catatan pelanggaran. |
| `gwsf flush [--force]` | Membersihkan daftar blokir; tanpa `--force` catatan permanen dipertahankan. |
| `migrate --help` | Melihat perintah migrasi SQL. `status` hanya membaca; `init`, `baseline`, dan eksekusi mengubah database. |

## Registrasi customer

Jalankan `php guavas-cmd system register` pada terminal interaktif dan masukkan kunci saat diminta. Masukan interaktif tidak masuk riwayat perintah shell, tetapi dapat terlihat di layar terminal. Untuk otomasi, set `GUAVAS_LICENSE_KEY` di lingkungan proses. Jangan menaruh kunci di argumen CLI, file kode, atau `composer.json`.

ID instalasi tersimpan di `storage/guavas-updates/installation.json`. Token tersimpan terpisah di `storage/guavas-updates/registration.json`. Folder ini berada di luar `public_html` dan diabaikan Git. Simpan cadangan ID instalasi bersama proyek jika ingin identitasnya tetap sama setelah pemindahan server. CLI tidak mengirim kunci atau token saat aplikasi melayani halaman biasa.

Secara bawaan registrasi memakai `https://meegbe.id/api/guavas/v1`. Untuk menguji API lain, set `GUAVAS_API_BASE_URL` ke alamat HTTPS yang dipercaya. Pencabutan hanya menghapus token lokal setelah server mengonfirmasi permintaan. Kegagalan jaringan tidak menghentikan aplikasi.

## Migrasi

`migrate status` menampilkan keadaan file SQL di `database/`. Status `PENDING` berarti belum tercatat pada ledger, bukan bukti SQL belum pernah dijalankan. Pada database baru gunakan `migrate init`; pada database lama, audit skema lebih dulu dan tandai file yang sudah diterapkan dengan `migrate baseline <nama>` atau `--all`. Jangan menjalankan ulang DDL lama tanpa pemeriksaan.
