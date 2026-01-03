\# TOKOAPP (PHP + MySQL) — POS / Manajemen Toko (Offline/LAN)



Aplikasi \*\*Point of Sale (POS)\*\* dan manajemen toko berbasis \*\*PHP\*\* + \*\*MySQL\*\*, dirancang untuk berjalan di lingkungan lokal menggunakan \*\*XAMPP\*\* (offline / jaringan LAN).



\## Fitur Utama

\- POS / transaksi penjualan

\- Manajemen barang (tambah/edit/hapus), stok, dan laporan stok

\- Manajemen member (tambah/edit, redeem)

\- Pembelian/purchase dan laporan pembelian

\- Laporan penjualan (report) + cetak struk / barcode

\- API internal untuk kebutuhan aplikasi (folder `api/`)



> Catatan: Project ini ditujukan untuk lingkungan lokal. Pastikan konfigurasi dan keamanan sesuai kebutuhan sebelum dipakai publik.



---



\## Prasyarat

\- Windows + \*\*XAMPP\*\* (Apache + MySQL/MariaDB)

\- PHP (mengikuti versi bawaan XAMPP)

\- Browser modern



---



\## Instalasi (Local / XAMPP)

1\. Copy project ke:

&nbsp;  - `C:\\xampp\\htdocs\\tokoapp`



2\. Jalankan XAMPP:

&nbsp;  - Start \*\*Apache\*\*

&nbsp;  - Start \*\*MySQL\*\*



3\. Buat database:

&nbsp;  - Buka `http://localhost/phpmyadmin`

&nbsp;  - Buat database (contoh): `tokoapp`



4\. Import struktur database:

&nbsp;  - Gunakan file SQL \*\*yang kamu siapkan sendiri\*\* (disarankan: struktur saja, tanpa data sensitif).

&nbsp;  - Import via phpMyAdmin → tab Import → pilih file `.sql`



5\. Konfigurasi koneksi DB:

&nbsp;  - Copy template config:

&nbsp;    - `config.example.php` → `config.php`

&nbsp;  - Edit `config.php` sesuai environment lokal (host, user, password, dbname)



6\. Akses aplikasi:

&nbsp;  - `http://localhost/tokoapp`



---



\## Konfigurasi untuk Offline / LAN

\- Jalankan aplikasi di 1 PC server (XAMPP).

\- Client LAN mengakses via IP server, contoh:

&nbsp; - `http://192.168.1.10/tokoapp`

\- Pastikan firewall Windows mengizinkan Apache, dan PC client satu subnet.



---



\## Struktur Folder Singkat

\- `api/` : endpoint API internal (barang, member, stok, transaksi hold, dsb.)

\- `assets/` : vendor JS/CSS (barcode, chart, pico css, qrcode)

\- `auth/` : login/logout/reset admin

\- `includes/` : header/footer dan komponen tampilan

\- `uploads/` : asset logo aplikasi



---



\## Keamanan \& Catatan Penting

\- \*\*Jangan upload database dump\*\* (`\*.sql`, folder `backups/`) ke repo publik.

\- Pastikan `config.php` berisi kredensial lokal saja dan \*\*tidak\*\* dipublish.

\- Jika aplikasi akan dipakai di luar LAN, lakukan hardening:

&nbsp; - validasi input, sanitasi query, session security, rate limiting, dsb.



---



\## Cara Kontribusi (Opsional)

\- Fork repo

\- Buat branch fitur: `feature/nama-fitur`

\- Pull request dengan deskripsi perubahan



---



\## Lisensi

Tambahkan lisensi jika diperlukan (MIT/Apache-2.0/dll). Jika tidak, hapus bagian ini.



