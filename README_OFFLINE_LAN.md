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
