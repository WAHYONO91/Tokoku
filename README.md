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



# TokoApp – Mode LAN/Offline (tanpa internet)

Masalah utama di repo ini: ada beberapa resource yang di-load dari internet (CDN/API), sehingga saat PC tidak punya akses internet, Chrome menunggu resource tersebut dan sebagian halaman jadi tidak stabil.

Patch ini mengubah referensi ke vendor lokal:
- Pico CSS  -> /tokoapp/assets/vendor/pico.min.css
- Chart.js  -> /tokoapp/assets/vendor/chart.umd.min.js
- JsBarcode -> /tokoapp/assets/vendor/JsBarcode.all.min.js
- QRCode JS -> /tokoapp/assets/vendor/qrcode.min.js

## 1) Yang wajib kamu siapkan (sekali saja)
Download file berikut (1 kali, pakai internet):
1. pico.min.css (PicoCSS v2)
2. chart.umd.min.js (Chart.js)
3. JsBarcode.all.min.js (JsBarcode)
4. qrcode.min.js (QRCodeJS)

Taruh di folder:
tokoapp/assets/vendor/

## 2) Cara deploy di jaringan lokal (banyak PC, tanpa bentrok)
Rekomendasi: 1 PC jadi SERVER (Apache/Nginx + PHP + MySQL). PC lain cukup akses via browser.

- Server: set IP statis (contoh 192.168.1.10)
- Install XAMPP/Laragon/WAMP (Windows) atau LAMP (Linux)
- Copy folder 'tokoapp' ke web root (htdocs)
- Import db.sql ke MySQL (db name: tokoapp)
- Edit config.php:
  mysql host = 127.0.0.1 (tetap di server)
  user/pass  = jangan pakai root untuk produksi LAN; buat user khusus.

PC Client:
- Buka: http://192.168.1.10/tokoapp/
Tidak perlu internet, cukup jaringan LAN.

## 3) Catatan performa
- Pastikan MySQL pakai InnoDB
- Aktifkan OPcache di PHP (kalau memungkinkan)
- Jangan jalankan DB di tiap PC kalau tujuanmu data 1 pintu (akan pecah data)


---

## Update & Manajemen (Git & Composer)

### 1. Mengambil Update Terbaru (Git Pull)
Jika Anda menggunakan Git untuk mengelola project ini, jalankan perintah ini di terminal untuk menarik kode terbaru dari GitHub:
```bash
git pull origin main
```
*Catatan: Pastikan Anda berada di folder `htdocs/tokoapp`.*

### 2. Update Database & Dependency (Composer)
Aplikasi ini sekarang mendukung **Composer** untuk mempermudah update database secara otomatis.

**Instalasi Pertama / Update Total:**
Jalankan perintah ini setelah melakukan `git pull` untuk memastikan semua dependency dan database sudah up-to-date:
```bash
composer install
```

**Update Database Manual via CLI:**
Jika Anda hanya ingin menjalankan migrasi database tanpa update dependency, gunakan:
```bash
composer run-script migrate
```

---

## Struktur Folder Singkat

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


Demo di https://singbanter.my.id/tokoapp/

Tambahkan lisensi jika diperlukan (MIT/Apache-2.0/dll). Jika tidak, hapus bagian ini.
informasi terkait aplikasi hubungi saya di WA 085875099445 
