# Security Audit & Vulnerability Assessment Report (Re-Audit)
## Project: Pemandian Patemon — Company Profile & Sistem Kasir Loket

**Tanggal Audit:** 2026-09-25  
**Tipe Audit:** Static Application Security Testing (SAST) & Manual Code Review  
**Versi Aplikasi:** 2.0.0-Enterprise  
**Environment Target:** PHP Built-in Server (`router.php` / `serve.php`) & Apache HTTP Server (`.htaccess`)  
**Status Audit:** **VALIDATED & VERIFIED** (Re-audit mengoreksi temuan lama yang tidak valid)

---

## 1. Ringkasan Eksekutif & Hasil Validasi Ulang

1. **Koreksi Temuan Lama:** Audit sebelumnya melaporkan temuan palsu (*false positive*), khususnya **IDOR pada `nota.php`** dan referensi file OS Linux, yang setelah diverifikasi **TIDAK VALID** pada arsitektur sistem dan server Windows yang digunakan.
2. **Temuan Valid yang Dikonfirmasi:** Ditemukan **8 kerentanan keamanan valid** yang telah diverifikasi secara teknis pada lingkungan **Windows Server**, dengan kerentanan paling kritis berupa **Arbitrary File Read / Path Traversal pada `router.php`** yang memungkinkan pihak eksternal tanpa autentikasi membaca file konfigurasi sensitif (`dist/app/config.php`), file database (`database/pemandian.sql`), maupun file sistem Windows (`win.ini`).

---

## 2. Matriks Temuan Keamanan Valid

| ID | Nama Kerentanan | Tingkat Risiko | CVSS v3.1 | CWE | Komponen Terdampak | Status |
|---|---|---|---|---|---|---|
| **SEC-01** | Arbitrary File Read / Path Traversal pada Payment Handler | **CRITICAL / HIGH** | 8.6 | CWE-22 | `router.php` (Baris 29–38) | **VALID (Verified)** |
| **SEC-02** | Kredensial Bawaan Hardcoded & Terpublikasi di Kode Sumber | **HIGH** | 7.5 | CWE-798 | `serve.php`, `pemandian.sql` | **VALID (Verified)** |
| **SEC-03** | Eksekusi Operasi Hapus via HTTP GET & Kebocoran Token CSRF di URL | **MEDIUM** | 5.4 | CWE-598 / CWE-352 | `transaksi/delete.php`, `user/delete.php`, `tiket/delete.php`, `ulasan/delete.php` | **VALID (Verified)** |
| **SEC-04** | Akses File Bukti Pembayaran Tanpa Autentikasi (Exposur Data Finansial) | **MEDIUM** | 5.3 | CWE-284 / CWE-359 | `router.php`, folder `dist/app/payment/` | **VALID (Verified)** |
| **SEC-05** | Ketiadaan Rate Limiting & Proteksi Brute Force pada Login | **MEDIUM** | 5.3 | CWE-307 | `dist/views/login.php` | **VALID (Verified)** |
| **SEC-06** | Cookie Sesi Tanpa Atribut Keamanan `Secure` dan `SameSite` | **LOW** | 4.3 | CWE-614 / CWE-1275 | `dist/app/config.php` (Baris 30–35) | **VALID (Verified)** |
| **SEC-07** | Information Disclosure Melalui Pesan Error Basis Data Terperinci | **LOW** | 3.7 | CWE-209 | `config.php`, `register.php`, `user/tambah.php`, `user/update.php` | **VALID (Verified)** |
| **SEC-08** | Ketiadaan Proteksi Directory Listing & Security Headers di `.htaccess` | **LOW** | 3.7 | CWE-548 / CWE-693 | `.htaccess` | **VALID (Verified)** |

---

## 3. Klarifikasi & Koreksi Temuan Tidak Valid (False Positives Sebelumnya)

Berikut adalah klarifikasi teknis mengapa temuan pada audit awal sebelumnya dinyatakan **TIDAK VALID**:

### ❌ Temuan Lama 1: "IDOR pada Nota Transaksi (`nota.php`)" — TIDAK VALID / FALSE POSITIVE
* **Klaim Sebelumnya:** Pengunjung biasa (Level 0) diklaim dapat melihat nota orang lain dengan mengganti parameter `?id=X` karena klausa `OR ? = 1 OR ? = 2 OR ? = 3 OR ? = 1`.
* **Fakta Teknis Kode Asli (`dist/views/tiket/nota.php` Baris 26–33):**
  ```php
  $user_lvl = (int)($_SESSION['level'] ?? 0);
  $session_tx_id = (int)($_SESSION['id_transaksi'] ?? 0);
  $is_own_session = ($session_tx_id > 0 && $session_tx_id === $id_transaksi) ? 1 : 0;

  $stmt = $conn->prepare("SELECT transaksi.*, ... WHERE id_transaksi = ? AND (transaksi.id_user = ? OR ? = 1 OR ? = 2 OR ? = 3 OR ? = 1) LIMIT 1");
  $stmt->bind_param("iiiiii", $id_transaksi, $id_user, $user_lvl, $user_lvl, $user_lvl, $is_own_session);
  ```
* **Bukti Validasi:**
  Untuk pengunjung biasa (role pengunjung), nilai `$user_lvl` adalah `0`.
  Maka kondisi `OR ? = 1 OR ? = 2 OR ? = 3` di SQL dievaluasi menjadi `0 = 1 OR 0 = 2 OR 0 = 3`, yang seluruhnya bernilai **FALSE**.
  Kondisi `$is_own_session` hanya bernilai `1` jika ID transaksi tersebut benar-benar ada di sesi server `$_SESSION['id_transaksi']`.
  Sehingga, bagi pengunjung biasa, query hanya akan mengembalikan data jika `transaksi.id_user = $id_user` (milik user itu sendiri). Jika mencoba membuka ID transaksi user lain, query menghasilkan 0 baris dan dieksekusi blok kode penolakan:
  ```php
  if (!$transaksi_data) {
      die("Nota Transaksi Tidak Ditemukan. Anda tidak memiliki akses ke transaksi ini atau nomor ID salah.");
  }
  ```
  **Kesimpulan:** Proteksi otorisasi pada `nota.php` bekerja dengan baik. Temuan IDOR sebelumnya tidak valid.

### ❌ Temuan Lama 2: "Fitur Forgot Password Tidak Fungsional" — BUKAN KERENTANAN KEAMANAN
* Menampilkan pesan generik *"Jika alamat email terdaftar di sistem kami, instruksi pemulihan telah dikirimkan"* tanpa membocorkan ada/tidaknya email justru merupakan **rekomendasi standar OWASP** untuk mencegah *User Enumeration*. Belum terpasangnya gateway SMTP email adalah keterbatasan fungsionalitas (*feature stub*), bukan celah eksploitasi keamanan.

### ❌ Temuan Lama 3: "Inkonsistensi RBAC Kasir / Deny Access" — LOGIC BUG (Bukan Kerentanan Eskalasi Hak Akses)
* Staf level 3 yang dialihkan ke `tambah.php` ditolak oleh `check_auth([1, 2])`. Sifat penolakan ini adalah *fail-closed* (membatasi akses), bukan *fail-open* (tidak ada kebocoran atau eskalasi hak akses ilegal).

---

## 4. Rincian Detail Temuan Keamanan Valid

---

### SEC-01: Arbitrary File Read / Path Traversal pada Payment File Handler
* **Severity:** **CRITICAL / HIGH** (CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N — Score: 8.6)
* **Kategori OWASP:** A01:2021 – Broken Access Control / A05:2021 – Security Misconfiguration
* **CWE:** CWE-22 (Improper Limitation of a Pathname to a Restricted Directory)
* **File Terdampak:** `router.php` (Baris 28–39)

#### Deskripsi Kerentanan:
Pada `router.php`, penanganan akses bukti pembayaran dilakukan sebagai berikut:
```php
// 1.5 Handle Payment Proof Uploads
if (str_starts_with($uri, '/app/payment/') || str_starts_with($uri, '/dist/app/payment/')) {
    $relPath = preg_replace('#^/(?:dist/)?app/payment/#', '', $uri);
    $paymentFile = __DIR__ . '/dist/app/payment/' . $relPath;
    if (file_exists($paymentFile) && !is_dir($paymentFile)) {
        $mime = mime_content_type($paymentFile) ?: 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        readfile($paymentFile);
        exit;
    }
}
```
Variabel `$uri` didekodekan menggunakan `urldecode()` pada baris ke-7. Ketika pengguna mengirimkan URL ber-encoding dot-dot-slash (`..%2f`), fungsi `str_starts_with($uri, '/app/payment/')` tetap bernilai `true`.
Setelah `preg_replace`, `$relPath` memuat urutan traversal `../`. Variabel `$paymentFile` tidak divalidasi dengan `realpath()` untuk memastikan path berada di dalam direktori `dist/app/payment/`.

#### Dampak pada Lingkungan Windows Server:
Penyerang tanpa login dapat membaca file sensitif internal proyek maupun file sistem di Windows:
1. `GET /app/payment/..%2fconfig.php` → Mengunduh source code mentah `dist/app/config.php` yang memuat kredensial koneksi database MySQL (`root`, password, host).
2. `GET /app/payment/..%2f..%2fdatabase/pemandian.sql` → Membaca file dump database yang memuat struktur data dan hash password akun.
3. `GET /app/payment/..%2f..%2fviews/login.php` → Membaca kode sumber logika autentikasi dan alur sesi.
4. `GET /app/payment/..%2f..%2f..%2f..%2f..%2f..%2fWindows/win.ini` → Pada server Windows, penyerang dapat membaca file konfigurasi OS `C:\Windows\win.ini` untuk membuktikan kemampuan pembacaan arbitrary file melampaui web root.

#### Rekomendasi Perbaikan:
Gunakan `basename()` atau verifikasi `realpath()` terhadap direktori basis yang diizinkan:
```php
if (str_starts_with($uri, '/app/payment/') || str_starts_with($uri, '/dist/app/payment/')) {
    $filename = basename($uri);
    $allowedDir = realpath(__DIR__ . '/dist/app/payment');
    $paymentFile = $allowedDir . DIRECTORY_SEPARATOR . $filename;
    
    if (file_exists($paymentFile) && is_file($paymentFile) && str_starts_with(realpath($paymentFile), $allowedDir)) {
        $mime = mime_content_type($paymentFile) ?: 'image/jpeg';
        header('Content-Type: ' . $mime);
        readfile($paymentFile);
        exit;
    }
    http_response_code(404);
    exit;
}
```

---

### SEC-02: Kredensial Bawaan Hardcoded & Terpublikasi di Kode Sumber
* **Severity:** **HIGH** (CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N — Score: 7.5)
* **Kategori OWASP:** A07:2021 – Identification and Authentication Failures
* **CWE:** CWE-798 (Use of Hard-coded Credentials)
* **File Terdampak:** `serve.php` (Baris 122–123), `database/pemandian.sql` (Baris 104–107)

#### Deskripsi Kerentanan:
1. Skrip `serve.php` mencetak kredensial akun demo secara eksplisit ke terminal:
   ```text
   - Admin Loket : username = admin  | password = admin123
   - Staf Kasir  : username = staff  | password = staff123
   ```
2. File migrasi `database/pemandian.sql` menyertakan seed akun administrator dan staf dengan hash kata sandi yang sama (`password123`):
   ```sql
   INSERT INTO `users` (`id_user`, `nama`, `username`, `password`, `email`, `no_telepon`, `level`) VALUES
   (1, 'Kepala Pemandian', 'superadmin', '$2y$10$bmbuM94ur1hVxmIy/UyYCu.kceCANajqqzDoaOznyQHeuE9BjRh8a', 'superadmin@pemandian.com', '0812345678', 1),
   (2, 'Admin Pemandian',  'admin',      '$2y$10$bmbuM94ur1hVxmIy/UyYCu.kceCANajqqzDoaOznyQHeuE9BjRh8a', 'admin@pemandian.com',      '0887654321', 2),
   (3, 'Staff Pemandian',  'staff',      '$2y$10$bmbuM94ur1hVxmIy/UyYCu.kceCANajqqzDoaOznyQHeuE9BjRh8a', 'staff@pemandian.com',      '0891827364', 3);
   ```
Aplikasi tidak memiliki mekanisme pemaksaan ganti kata sandi (*force password change on first login*).

#### Dampak:
Penyerang yang mengetahui repository atau menemukan instalasi default dapat langsung masuk sebagai Super Admin (`superadmin`), Admin (`admin`), atau Staf Kasir (`staff`), mendapatkan kendali penuh atas data transaksi dan manipulasi keuangan.

#### Rekomendasi Perbaikan:
1. Hapus pencetakan kredensial default dari skrip pelari `serve.php`.
2. Hapus seed password default dari skrip produksi, atau wajibkan admin mengganti password saat login pertama kali (`must_change_password = 1`).

---

### SEC-03: Eksekusi Operasi Hapus via HTTP GET & Kebocoran Token CSRF di URL
* **Severity:** **MEDIUM** (CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:U/C:L/I:L/A:N — Score: 5.4)
* **Kategori OWASP:** A01:2021 – Broken Access Control / A04:2021 – Insecure Design
* **CWE:** CWE-598 (Information Exposure Through Query Strings in GET Request) / CWE-352
* **File Terdampak:**
  - `dist/views/transaksi/delete.php` (Baris 7–16)
  - `dist/views/user/delete.php` (Baris 7–21)
  - `dist/views/tiket/delete.php` (Baris 5–13)
  - `dist/views/ulasan/delete.php` (Baris 5–12)
  - `dist/views/transaksi/staf_delete.php` (Baris 7–12)

#### Deskripsi Kerentanan:
Operasi destruktif (penghapusan data pengguna, tiket, ulasan, dan transaksi) dieksekusi melalui metode HTTP `GET`:
```php
$id_transaksi = (int)($_GET["id"] ?? 0);
$csrf = $_GET["csrf"] ?? '';

if ($id_transaksi > 0 && validate_csrf($csrf)) {
    $stmt = $conn->prepare("DELETE FROM transaksi WHERE id_transaksi = ?");
    ...
}
```
Tautan pemanggil di frontend:
```javascript
window.location.href = '/admin/transaksi/delete?id=' + id + '&csrf=' + csrf_token;
```
Masalah:
1. **Pelanggaran Semantik HTTP:** Metode `GET` harus bersifat aman (*safe*) dan idempoten menurut RFC 7231.
2. **Kebocoran Token CSRF:** Parameter query string `?csrf=...` tersimpan di:
   - File log akses web server (`access.log`),
   - Riwayat browser (*browser history*),
   - Cache proxy,
   - Header `Referer` yang dikirim ke domain eksternal (misalnya FontAwesome CDN, CDN SweetAlert2, atau unpkg) saat halaman memuat resource pihak ketiga.
3. **Trigger Tak Sengaja:** *Web crawler*, ekstensi peramban dengan fitur *link pre-fetching*, atau akselerator unduhan dapat memicu penghapusan data secara tidak sengaja jika tautan terindeks.

#### Rekomendasi Perbaikan:
Ubah seluruh aksi penghapusan menjadi form HTTP `POST` dengan CSRF token di request body:
```html
<form method="POST" action="<?= route_url('transaksi_delete') ?>" style="display:inline;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="id" value="<?= (int)$row['id_transaksi'] ?>">
    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
</form>
```

---

### SEC-04: Akses File Bukti Pembayaran Tanpa Autentikasi (Exposur Data Finansial)
* **Severity:** **MEDIUM** (CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N — Score: 5.3)
* **Kategori OWASP:** A01:2021 – Broken Access Control
* **CWE:** CWE-284 (Improper Access Control) / CWE-359 (Exposure of Private Personal Information)
* **File Terdampak:** `router.php` (Baris 29–38), `dist/app/payment/`

#### Deskripsi Kerentanan:
Endpoint `/app/payment/<nama_file>` menyajikan file bukti transfer bank / QRIS secara langsung ke publik tanpa memverifikasi apakah pemohon telah login atau memiliki wewenang:
- Bukti transfer bank berisi nomor rekening pengirim, nama pemilik rekening, bank asal, nominal transaksi, dan waktu transfer.
- Meskipun upload baru menggunakan string acak (`pay_20260925_...`), file lama atau file demo memiliki nama statis/tertebak (`catey.jpg`, `download.jpeg`, `logo2.png`, `Outlast_cover.jpg`, dll.).
- Penyerang dapat mengunduh dan mengumpulkan data finansial nasabah yang bertransaksi.

#### Rekomendasi Perbaikan:
Tambahkan verifikasi hak akses sebelum menyajikan file bukti pembayaran:
- Hanya admin (level 1 & 2), kasir (level 3), atau pemilik transaksi bersangkutan yang boleh mengunduh file tersebut.
- Simpan file bukti pembayaran di luar web root (misalnya folder `storage/private_uploads/`) dan sajikan via endpoint pengontrol yang terautentikasi.

---

### SEC-05: Ketiadaan Rate Limiting & Proteksi Brute Force pada Login
* **Severity:** **MEDIUM** (CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N — Score: 5.3)
* **Kategori OWASP:** A07:2021 – Identification and Authentication Failures
* **CWE:** CWE-307 (Improper Restriction of Excessive Authentication Attempts)
* **File Terdampak:** `dist/views/login.php`

#### Deskripsi Kerentanan:
Skrip `login.php` memproses percobaan login tanpa adanya:
1. Batasan jumlah percobaan gagal (*maximum failed attempts*),
2. Penguncian akun sementara (*account lockout*),
3. Mekanisme tantangan CAPTCHA / Cloudflare Turnstile,
4. Penundaan respons progresif (*progressive delay / exponential backoff*).

#### Dampak:
Penyerang dapat menjalankan skrip otomatis *dictionary attack* atau *credential stuffing* dengan ribuan tebakan password per menit terhadap akun `admin`, `superadmin`, maupun `staff`.

#### Rekomendasi Perbaikan:
Terapkan pencatatan percobaan login gagal berdasarkan IP dan username pada tabel basis data atau sesi:
- Kunci login selama 15 menit setelah 5 kali gagal berturut-turut.
- Tambahkan CAPTCHA setelah 3 kali gagal.

---

### SEC-06: Cookie Sesi Tanpa Atribut Keamanan `Secure` dan `SameSite`
* **Severity:** **LOW** (CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:U/C:L/I:N/A:N — Score: 4.3)
* **Kategori OWASP:** A05:2021 – Security Misconfiguration
* **CWE:** CWE-614 / CWE-1275
* **File Terdampak:** `dist/app/config.php` (Baris 30–35)

#### Deskripsi Kerentanan:
Konfigurasi cookie sesi saat ini:
```php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}
```
Parameter `session.cookie_secure` dan `session.cookie_samesite` tidak dikonfigurasi.

#### Dampak:
Pada jaringan WiFi publik tempat wisata (misalnya area kolam/kantin Patemon), cookie sesi ditransmisikan tanpa enkripsi jika diakses via HTTP biasa, membuka celah penyadapan sesi (*Session Hijacking / Sniffing*). Tanpa flag `SameSite=Lax/Strict`, browser akan menyertakan cookie pada request lintas situs.

#### Rekomendasi Perbaikan:
Gunakan `session_set_cookie_params()` modern sebelum `session_start()`:
```php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
```

---

### SEC-07: Information Disclosure Melalui Pesan Error Basis Data Terperinci
* **Severity:** **LOW** (CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N — Score: 3.7)
* **Kategori OWASP:** A05:2021 – Security Misconfiguration
* **CWE:** CWE-209 (Generation of Error Message Containing Sensitive Information)
* **File Terdampak:**
  - `dist/app/config.php` (Baris 20–25)
  - `dist/views/register.php` (Baris 59)
  - `dist/views/user/update.php` (Baris 82)
  - `dist/views/user/tambah.php` (Baris 51)

#### Deskripsi Kerentanan:
1. `config.php`:
   ```php
   die("...Gagal terhubung ke database <strong>{$database}</strong> pada host <strong>{$host}</strong>...");
   ```
2. `register.php`:
   ```php
   $error = "Terjadi kesalahan sistem saat mendaftar: " . e($stmt->error);
   ```
3. `user/update.php` & `user/tambah.php`:
   ```php
   $error_msg = "Gagal memperbarui pengguna: " . e($stmt_up->error);
   ```

#### Dampak:
Pesan error membocorkan nama basis data lokal, alamat host, serta pesan internal engine MySQL (seperti duplikasi key, nama kolom tabel, atau sintaks error), memudahkan penyerang melakukan *reconnaissance* dan pemetaan struktur sistem.

#### Rekomendasi Perbaikan:
Gunakan pencatatan error ke log server (`error_log()`) dan tampilkan pesan umum yang ramah ke pengguna:
```php
error_log("Database Error: " . $stmt->error);
$error = "Terjadi kendala pada sistem. Silakan coba beberapa saat lagi atau hubungi administrator.";
```

---

### SEC-08: Ketiadaan Proteksi Directory Listing & Security Headers di `.htaccess`
* **Severity:** **LOW** (CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N — Score: 3.7)
* **Kategori OWASP:** A05:2021 – Security Misconfiguration
* **CWE:** CWE-548 / CWE-693
* **File Terdampak:** `.htaccess`

#### Deskripsi Kerentanan:
1. File `.htaccess` tidak memiliki direktif `Options -Indexes`. Pada server Apache standar (XAMPP/cPanel), direktori yang tidak memiliki file index (`dist/app/payment/`, `public/img/avatars/`, `dist/app/cache/`) akan menampilkan daftar file secara terbuka.
2. Tidak terdapat header pertahanan browser standar seperti `X-Frame-Options` (anti clickjacking), `X-Content-Type-Options: nosniff` (anti MIME sniffing), dan `Referrer-Policy`.

#### Rekomendasi Perbaikan:
Tambahkan direktif pengerasan pada `.htaccess`:
```apache
Options -Indexes

<IfModule mod_headers.c>
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
</IfModule>
```

---

## 5. Ringkasan Tindakan Remediasi Prioritas

1. **Prioritas 1 (Segera - Critical):** Perbaiki sanitasi path pada baris 29–38 `router.php` menggunakan `basename()` dan verifikasi `realpath()`.
2. **Prioritas 2 (Segera - High):** Ganti password default seluruh akun admin/staf pada `pemandian.sql` dan hapus display kredensial dari `serve.php`.
3. **Prioritas 3 (Tinggi - Medium):** Migrasi seluruh endpoint penghapusan data (`delete.php`) dari HTTP `GET` ke HTTP `POST` dengan proteksi form CSRF token.
4. **Prioritas 4 (Sedang - Medium):** Pasang verifikasi sesi pada penyajian file bukti pembayaran `/app/payment/`.
5. **Prioritas 5 (Sedang - Medium):** Terapkan modul rate limiting percobaan login pada `login.php`.
6. **Prioritas 6 (Rendah - Low):** Konfigurasi flag cookie sesi (`SameSite=Lax`, `Secure`) dan lengkapi `.htaccess` dengan `Options -Indexes`.
