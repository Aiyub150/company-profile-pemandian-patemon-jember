# Security Proof of Concept (PoC) & Validation Guide (Re-Audit)
## Project: Pemandian Patemon — Company Profile & Sistem Kasir Loket

**Tanggal:** 2026-09-25  
**Tujuan Dokumen:** Verifikasi teknis, pembuktian celah (*reproduction steps*), dan validasi perbaikan (*fix verification*) untuk tim pengembang internal.

> ⚠️ **DISCLAIMER:** Dokumen pengujian keamanan ini dibuat khusus untuk keperluan audit dan mitigasi internal pada lingkungan laboratorium (*local development*). Dilarang keras menggunakan metode pengujian ini pada sistem produksi tanpa izin tertulis dari pemilik aset.

---

## DAFTAR PENGUJIAN & VALIDASI

1. [VERIFIKASI FALSE POSITIVE: Pembuktian IDOR Nota.php Tidak Valid](#verifikasi-false-positive-pembuktian-idor-notaphp-tidak-valid)
2. [POC-01: Arbitrary File Read / Path Traversal pada `router.php`](#poc-01-arbitrary-file-read--path-traversal-pada-routerphp)
3. [POC-02: Kebocoran Token CSRF & Operasi Hapus via HTTP GET](#poc-02-kebocoran-token-csrf--operasi-hapus-via-http-get)
4. [POC-03: Akses Tanpa Autentikasi ke Bukti Pembayaran Finansial](#poc-03-akses-tanpa-autentikasi-ke-bukti-pembayaran-finansial)
5. [POC-04: Pengujian Brute Force Tanpa Pembatasan (Rate Limiting Check)](#poc-04-pengujian-brute-force-tanpa-pembatasan-rate-limiting-check)
6. [POC-05: Pengujian Directory Listing pada Apache (`.htaccess`)](#poc-05-pengujian-directory-listing-pada-apache-htaccess)

---

## VERIFIKASI FALSE POSITIVE: Pembuktian IDOR Nota.php Tidak Valid

Pada laporan awal, dilaporkan bahwa `dist/views/tiket/nota.php` memiliki celah IDOR yang memungkinkan pengunjung level 0 membaca nota transaksi pengguna lain.

### Analisis & Pembuktian Kode:
Mari kita uji logika SQL pada baris 21–29 `nota.php`:
```php
$user_lvl = (int)($_SESSION['level'] ?? 0);
$session_tx_id = (int)($_SESSION['id_transaksi'] ?? 0);
$is_own_session = ($session_tx_id > 0 && $session_tx_id === $id_transaksi) ? 1 : 0;

$stmt = $conn->prepare("SELECT transaksi.*, users.nama as user_nama, users.no_telepon, users.email 
    FROM transaksi 
    LEFT JOIN users ON transaksi.id_user = users.id_user 
    WHERE id_transaksi = ? AND (transaksi.id_user = ? OR ? = 1 OR ? = 2 OR ? = 3 OR ? = 1) LIMIT 1");
$stmt->bind_param("iiiiii", $id_transaksi, $id_user, $user_lvl, $user_lvl, $user_lvl, $is_own_session);
```

### Simulasi Evaluasi Parameter:
Misalkan penyerang mendaftar sebagai pengunjung biasa (`id_user = 10`, `level = 0`), lalu mencoba membuka transaksi milik pengunjung lain (`id_transaksi = 1` milik `id_user = 3`):
1. Parameter yang di-bind ke query:
   - `id_transaksi` = 1
   - `id_user` = 10
   - `user_lvl` (parameter ke-3) = 0
   - `user_lvl` (parameter ke-4) = 0
   - `user_lvl` (parameter ke-5) = 0
   - `is_own_session` (parameter ke-6) = 0
2. Klausa WHERE di engine database:
   ```sql
   WHERE id_transaksi = 1 
     AND (
       transaksi.id_user = 10  -- FALSE (id_user transaksi 1 adalah 3)
       OR 0 = 1                -- FALSE
       OR 0 = 2                -- FALSE
       OR 0 = 3                -- FALSE
       OR 0 = 1                -- FALSE
     )
   ```
3. Hasil evaluasi seluruh kondisi di dalam tanda kurung adalah **FALSE**.
4. Database menghasilkan **0 baris data** (`$transaksi_data = null`).
5. Baris 32 `nota.php` langsung memutus eksekusi:
   ```php
   if (!$transaksi_data) {
       die("<h2>Nota Transaksi Tidak Ditemukan</h2><p>Anda tidak memiliki akses ke transaksi ini atau nomor ID salah.</p>");
   }
   ```

**Kesimpulan:** IDOR pada `nota.php` **terbukti tidak valid (False Positive)**. Sistem otorisasi telah mengisolasi transaksi antarpengunjung dengan aman.

---

## POC-01: Arbitrary File Read / Path Traversal pada `router.php`

* **Target File:** `router.php` (Baris 29–38)
* **Kategori:** SEC-01 (Critical / High)
* **Deskripsi:** Pemanfaatan URL-encoding `..%2f` pada path `/app/payment/` untuk keluar dari direktori `dist/app/payment/` dan membaca file PHP atau sistem secara langsung melalui fungsi `readfile()`.

### Karakteristik pada Lingkungan Windows Server:
Pada lingkungan sistem operasi Windows (termasuk XAMPP di Windows atau PHP Built-in Server di Windows), kerentanan ini bekerja dengan mekanisme identik karena:
1. **Normalisasi Separator oleh PHP di Windows:** Engine PHP pada sistem operasi Windows menerima tanda garis miring forward slash (`/`) maupun backslash (`\`) secara ekuivalen untuk fungsi filesystem seperti `file_exists()`, `is_dir()`, dan `readfile()`.
2. **Dukungan URL Encoding Traversal:** Baik encoding `%2f` (slash) maupun `%5c` (backslash) didekodekan oleh `urldecode($uri)` pada baris 7 menjadi pemisah direktori yang valid di Windows.
3. **Penyasaran File Proyek Lokal:** Target utama kebocoran informasi pada Windows adalah file konfigurasi dan source code aplikasi itu sendiri, seperti `dist/app/config.php` (berisi password database) atau `.env`.

### Langkah Validasi / Reproduksi di Windows:

#### Opsi A: Menggunakan PowerShell (Windows 10 / 11 / Server)
```powershell
# Menjalankan request pengujian pembacaan file config.php
curl.exe -i "http://localhost:8000/app/payment/..%2fconfig.php"

# Atau menggunakan Invoke-WebRequest di PowerShell:
(Invoke-WebRequest -Uri "http://localhost:8000/app/payment/..%2fconfig.php").Content
```

#### Opsi B: Menggunakan Command Prompt (CMD Windows)
```cmd
curl.exe -i "http://localhost:8000/app/payment/..%2fconfig.php"
```

#### Opsi C: Menggunakan Browser (Google Chrome / Edge)
Buka URL berikut pada bilah alamat peramban:
```text
http://localhost:8000/app/payment/..%2fconfig.php
```

### Hasil yang Teramati (Vulnerable):
Pada Windows, server Built-in PHP atau Apache akan langsung mengembalikan isi file `config.php`:
```php
<?php
/**
 * Konfigurasi Database dan Helper Keamanan Terpusat
 * Aplikasi Kasir dan Portofolio Pemandian Patemon
 */
$host = getenv('DB_HOST') ?: '127.0.0.1';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$database = getenv('DB_NAME') ?: 'pemandian';
...
```

### Mekanisme Akar Masalah di Windows:
1. Di Windows, `__DIR__` menghasilkan path absolut dengan backslash, misal: `C:\xampp\htdocs\pemandian`.
2. Baris 31 `router.php`:
   ```php
   $paymentFile = __DIR__ . '/dist/app/payment/' . $relPath;
   ```
   Kombinasi path menjadi: `C:\xampp\htdocs\pemandian/dist/app/payment/../config.php`.
3. Fungsi Windows API / PHP runtime menyelesaikan `payment/..` kembali ke direktori `dist/app/`, sehingga file `dist/app/config.php` ditemukan (`file_exists == true`) dan langsung dibaca oleh `readfile()`.

### Validasi Setelah Perbaikan (Mitigasi Cross-Platform):
Setelah patch keamanan diterapkan pada `router.php`:
```powershell
curl.exe -i "http://localhost:8000/app/payment/..%2fconfig.php"
```
**Hasil yang Diharapkan:** Server merespons `HTTP/1.1 404 Not Found` atau `403 Forbidden`, dan tidak ada konten file yang disajikan ke publik.

---

## POC-02: Kebocoran Token CSRF & Operasi Hapus via HTTP GET

* **Target File:**
  - `dist/views/transaksi/delete.php`
  - `dist/views/user/delete.php`
  - `dist/views/tiket/delete.php`
  - `dist/views/ulasan/delete.php`
* **Kategori:** SEC-03 (Medium)
* **Deskripsi:** Operasi penghapusan data mengizinkan metode HTTP `GET` dan menaruh token CSRF pada query parameter URL.

### Langkah Pembuktian Kebocoran:
1. Login sebagai Administrator (`admin:admin123`).
2. Masuk ke halaman `/admin/transaksi`.
3. Buka tab **Network** pada Developer Tools peramban (F12).
4. Klik tombol **Hapus** pada salah satu baris data (atau periksa tautan konfirmasi SweetAlert2).
5. Perhatikan URL yang diakses:
   ```http
   GET /admin/transaksi/delete?id=5&csrf=e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855 HTTP/1.1
   Host: localhost:8000
   Cookie: PHPSESSID=...
   ```
6. **Titik Kebocoran 1 (Server Log):** Buka terminal / file log web server (`access.log`). Terlihat token CSRF lengkap tercatat di log:
   ```text
   127.0.0.1 - - [25/Sep/2026:15:10:00 +0800] "GET /admin/transaksi/delete?id=5&csrf=e3b0c442... HTTP/1.1" 302 -
   ```
7. **Titik Kebocoran 2 (Header Referer):** Jika halaman hasil redirect atau halaman asal memuat aset dari domain pihak ketiga (misalnya `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/...`), browser menyertakan URL lengkap beserta token CSRF dalam header `Referer`:
   ```http
   Referer: http://localhost:8000/admin/transaksi?csrf=e3b0c442...
   ```
8. **Titik Bahaya 3 (Pre-fetching):** Jika browser atau proxy melakukan *pre-fetching* tautan GET tersebut, record akan terhapus tanpa interaksi klik pengguna.

### Validasi Setelah Perbaikan:
Setelah diubah menjadi form `POST`:
```bash
# Permintaan GET harus ditolak
curl -i -X GET "http://localhost:8000/admin/transaksi/delete?id=5" -b "PHPSESSID=VALID_SESSION"
```
**Hasil yang Diharapkan:** Server menolak operasi dan mengembalikan redirect aman atau `HTTP 405 Method Not Allowed`.

---

## POC-03: Akses Tanpa Autentikasi ke Bukti Pembayaran Finansial

* **Target File:** `router.php`, direktori `dist/app/payment/`
* **Kategori:** SEC-04 (Medium)
* **Deskripsi:** Pengambilan gambar bukti transfer perbankan nasabah dapat dilakukan langsung tanpa memeriksa sesi login pengguna.

### Langkah Reproduksi:
1. Buka browser dalam mode **Incognito / Private Window** (tanpa login sama sekali).
2. Akses file bukti transfer yang ada di server, misalnya:
   ```
   http://localhost:8000/app/payment/logo2.png
   http://localhost:8000/app/payment/download.jpeg
   ```
3. Periksa respons:
   ```http
   HTTP/1.1 200 OK
   Content-Type: image/png
   Cache-Control: public, max-age=86400
   ```
4. Gambar langsung terbuka dan dapat diunduh oleh siapa saja.
5. Pada bukti pembayaran riil, gambar tersebut memuat informasi sensitif:
   - Nama lengkap nasabah pengirim,
   - Nomor rekening asal dan bank tujuan,
   - Nominal transfer,
   - Tanggal dan jam transaksi.

### Validasi Setelah Perbaikan:
Saat pengguna yang belum login mengakses URL tersebut:
**Hasil yang Diharapkan:** Pengguna diarahkan ke `/login` atau menerima respons `HTTP/1.1 403 Forbidden`.

---

## POC-04: Pengujian Brute Force Tanpa Pembatasan (Rate Limiting Check)

* **Target File:** `dist/views/login.php`
* **Kategori:** SEC-05 (Medium)
* **Deskripsi:** Endpoint login tidak memiliki mekanisme pelambatan respons atau pemblokiran setelah kegagalan berulang.

### Skrip Pengujian Otomatisasi (Testing Simulation):
Jalankan pengujian 20 kali percobaan login gagal secara beruntun:
```bash
#!/bin/bash
# Dijalankan di terminal lokal development untuk menguji ketiadaan rate limit

echo "Memulai pengujian rate limiting login..."
for i in {1..20}; do
  STATUS=$(curl -s -o /dev/null -w "%{http_code}" -X POST http://localhost:8000/login \
    -d "username=admin&password=wrongpassword$i&csrf_token=dummy")
  echo "Percobaan ke-$i: HTTP Status $STATUS"
done
```

### Hasil yang Teramati:
- Seluruh 20 percobaan menghasilkan respons yang sama secara instan dalam hitungan milidetik tanpa adanya:
  - Penundaan waktu (*sleep/delay*),
  - Pemblokiran IP sementara (*lockout*),
  - Munculnya tantangan CAPTCHA.
- Hal ini membuktikan penyerang dapat menjalankan ribuan tebakan per menit untuk meretas password akun administrator.

---

## POC-05: Pengujian Directory Listing pada Apache (`.htaccess`)

* **Target File:** `.htaccess`, direktori `dist/app/payment/`, `public/img/avatars/`
* **Kategori:** SEC-08 (Low)
* **Deskripsi:** Ketiadaan `Options -Indexes` menyebabkan Apache menyajikan daftar file saat folder diakses langsung.

### Langkah Pengujian:
1. Jalankan aplikasi menggunakan Apache (misal melalui XAMPP di `http://localhost/company-profile-pemandian-patemon-jember/`).
2. Akses folder penyimpanan di browser:
   ```
   http://localhost/company-profile-pemandian-patemon-jember/dist/app/payment/
   http://localhost/company-profile-pemandian-patemon-jember/public/img/avatars/
   ```
3. Jika modul `mod_autoindex` aktif dan `Options -Indexes` belum dipasang:
   - Halaman menampilkan **"Index of /dist/app/payment"** beserta seluruh daftar file gambar yang pernah diunggah oleh wisatawan.

### Validasi Setelah Perbaikan:
Tambahkan `Options -Indexes` pada baris paling atas `.htaccess`:
```apache
Options -Indexes
```
Akses kembali URL direktori di atas.
**Hasil yang Diharapkan:** Server merespons `HTTP/1.1 403 Forbidden` (*Access to this directory is forbidden*).
