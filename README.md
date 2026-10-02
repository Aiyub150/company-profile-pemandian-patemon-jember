# 🌊 Sistem Informasi Kasir, POS & E-Ticketing Pariwisata (Universal Open-Source Edition)
### Platform Tata Kelola Destinasi Wisata, Tiket Digital & Kasir Loket Multi-Instansi

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.1-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Database](https://img.shields.io/badge/MySQL-8.0%20%7C%20MariaDB-005C84?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT%20%2F%20Open%20Source-green?style=for-the-badge)](LICENSE)
[![Architecture](https://img.shields.io/badge/Architecture-Front%20Controller%20%7C%20Modular%20MVC-0284c7?style=for-the-badge)](#)
[![Security Standard](https://img.shields.io/badge/Security-Defense--in--Depth%20Audited-10b981?style=for-the-badge)](#)

---

## 🏛️ 1. Tentang Sistem

**Sistem Informasi Kasir, POS & E-Ticketing Pariwisata** adalah platform open-source universal yang dirancang untuk mengelola operasional destinasi wisata, taman rekreasi, museum, wahana air, maupun fasilitas publik lainnya. 

Platform ini bersifat **universal dan mudah disesuaikan (adaptable)** oleh instansi kedinasan (BUMD/UPTD), pengelola swasta, maupun komunitas wisata.

Sistem menjembatani dua kebutuhan utama tata kelola pariwisata modern:
1. **Layanan Publik & E-Ticketing Digital:** Memperkenalkan daya tarik wisata, galeri, fasilitas, serta kemudahan reservasi tiket masuk secara mandiri.
2. **Point of Sale (POS) Kasir Loket:** Pencatatan transaksi fisik loket yang cepat, pencegahan kebocoran retribusi, verifikasi barcode fisik/digital, serta pembuatan laporan keuangan bertingkat standar dinas.

---

## ✨ 2. Fitur Utama, Library & Kutipan Kode (Libraries & Code Snippets)

Berikut adalah rincian fitur utama beserta pustaka (library) dan kutipan kode (*code snippets*) yang mendasarinya:

### A. Point of Sale (POS) Kasir Loket & Foto Kamera Live
* **Fungsi:** Transaksi loket cepat untuk pengunjung langsung (*walk-in guest*) tanpa akun maupun pengunjung terdaftar, dilengkapi pengambilan foto bukti bayar secara live melalui WebRTC camera.
* **Pustaka & API:** Browser HTML5 WebRTC `navigator.mediaDevices.getUserMedia()`, HTML5 Canvas API.
* **Kutipan Kode (WebRTC Camera Capture):**
  ```javascript
  // Mengakses stream kamera perangkat secara real-time
  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    .then(stream => {
      const videoEl = document.getElementById('webcamVideo');
      videoEl.srcObject = stream;
      videoEl.play();
    })
    .catch(err => {
      Swal.fire({ icon: 'error', title: 'Akses Kamera Ditolak', text: err.message });
    });
  ```

### B. E-Ticketing & Generator Barcode
* **Fungsi:** Menghasilkan kode unik tiket dan barcode fisik/digital untuk validasi di pintu masuk.
* **Pustaka Backend:** `picqer/php-barcode-generator` (`Picqer\Barcode\BarcodeGeneratorPNG`).
* **Kutipan Kode (PHP Barcode Generator):**
  ```php
  use Picqer\Barcode\BarcodeGeneratorPNG;

  $generator = new BarcodeGeneratorPNG();
  $barcode_data = $generator->getBarcode($kode_tiket, $generator::TYPE_CODE_128);
  $barcode_base64 = 'data:image/png;base64,' . base64_encode($barcode_data);
  // Barcode siap ditampilkan pada nota/voucher
  ```

### C. Pemindaian & Verifikasi Tiket (QR & Barcode Scanner)
* **Fungsi:** Petugas pintu masuk memindai kode tiket menggunakan kamera HP/laptop untuk validasi status (*valid*, *sudah digunakan*, atau *kadaluarsa*).
* **Pustaka Frontend:** `html5-qrcode` library.
* **Kutipan Kode (Barcode Scanner):**
  ```javascript
  const html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 });
  html5QrcodeScanner.render((decodedText) => {
      // Kirim hasil scan ke endpoint validasi
      fetch('/admin/validasi-tiket?code=' + encodeURIComponent(decodedText))
          .then(res => res.json())
          .then(data => handleValidationResponse(data));
  });
  ```

### D. Cetak Struk Thermal 80mm & Laporan PDF
* **Fungsi:** Mencetak nota pembayaran format thermal (80mm) dan menghasilkan laporan omzet/pendapatan format PDF standar dinas.
* **Pustaka:** Backend `dompdf/dompdf`, Frontend `jspdf` & `@media print` thermal CSS.
* **Kutipan Kode (Struk Thermal 80mm CSS):**
  ```css
  @media print {
      @page {
          size: 80mm auto;
          margin: 0;
      }
      body {
          width: 80mm;
          font-family: 'Courier New', monospace;
          font-size: 12px;
      }
  }
  ```

### E. Mesin Dwibahasa (i18n Engine) & Interseptor Dynamic Modal
* **Fungsi:** Mengubah seluruh antarmuka teks, placeholder, tooltip, dan dialog konfirmasi (SweetAlert) dari Bahasa Indonesia ke Bahasa Inggris secara instan tanpa reload halaman.
* **Pustaka:** Vanilla JavaScript custom i18n engine (`public/js/patemon-i18n.js`) dengan monkey-patching `Swal.fire`.
* **Kutipan Kode (SweetAlert Translation Interceptor):**
  ```javascript
  const originalFire = window.Swal.fire.bind(window.Swal);
  window.Swal.fire = function(config) {
      if (getPatemonLanguage() === 'en') {
          if (config.title) config.title = translateText(config.title);
          if (config.text) config.text = translateText(config.text);
          if (config.confirmButtonText) config.confirmButtonText = translateText(config.confirmButtonText);
      }
      return originalFire(config);
  };
  ```

### F. Moderasi Konten & Filter Kata Terlarang (Toxic Word Filter)
* **Fungsi:** Memfilter ulasan publik dari kata-kata kasar/terlarang secara otomatis sebelum disimpan ke basis data.
* **Pustaka:** Custom Regex Engine & Multi-word Tokenizer PHP.
* **Kutipan Kode (PHP Word Filter):**
  ```php
  function filterToxicWords(string $text, array $bad_words): string {
      foreach ($bad_words as $word) {
          $pattern = '/' . preg_quote($word, '/') . '/i';
          $text = preg_replace($pattern, str_repeat('*', mb_strlen($word)), $text);
      }
      return $text;
  }
  ```

### G. Layanan Email, Aktivasi Staf Baru & Mailpit Web UI (Port 8001 & Port 8025)
* **Fungsi:** Pengiriman email aktivasi akun staf baru berformat HTML modern, reset kata sandi, dan notifikasi sistem secara instan menggunakan socket RFC 5321 tanpa memerlukan konfigurasi email eksternal (Google/SendGrid). Dilengkapi antarmuka web interaktif untuk membaca kotak masuk email secara lokal.
* **Layanan & Port:**
  * **Socket SMTP Server:** `127.0.0.1:8001` (Port 8001)
  * **Mailpit Web UI (Kotak Masuk Email):** `http://localhost:8025/` (Port 8025)
* **Pustaka & Driver:** Native PHP Stream Socket (`fsockopen`/`stream_socket_client`) & binary terintegrasi Mailpit.
* **Kutipan Kode (SMTP Socket Dispatcher):**
  ```php
  // Mengirim email HTML melalui socket SMTP port 8001
  $socket = @fsockopen('127.0.0.1', 8001, $errno, $errstr, 5);
  if ($socket) {
      fputs($socket, "EHLO localhost\r\n");
      fputs($socket, "MAIL FROM:<no-reply@patemon.jemberkab.go.id>\r\n");
      fputs($socket, "RCPT TO:<" . $to_email . ">\r\n");
      fputs($socket, "DATA\r\n");
      fputs($socket, "Subject: " . $subject . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $html_body . "\r\n.\r\n");
      fputs($socket, "QUIT\r\n");
      fclose($socket);
  }
  ```

### H. Kalender Operasional, Hari Libur Nasional & Penutupan Pemeliharaan Kolam
* **Fungsi:** Tata kelola jadwal operasional harian terintegrasi API Kalender Libur Nasional Indonesia (dengan sistem local cache JSON). Sistem secara cerdas membedakan:
  * **Hari Libur Nasional & Cuti Bersama:** Pemesanan tiket **TETAP BUKA** secara normal untuk memaksimalkan potensi kunjungan wisatawan di hari libur.
  * **Pemeliharaan / Penutupan Kolam (`tutup_pemeliharaan`):** Ditetapkan secara khusus oleh Super Admin (misal: kuras kolam berkala atau perbaikan wahana). Sistem secara otomatis mengunci loket pemesanan tiket, menampilkan indikator status **Tutup** dan banner pengumuman pemeliharaan di landing page secara publik (tanpa harus login), serta memblokade transaksi di sisi backend.
* **Tabel Basis Data:** `calendar_holidays` (kolom: `tanggal`, `keterangan`, `tipe`, `created_by`, `created_at`).
* **Kutipan Kode (Logika Pengecekan Penutupan):**
  ```php
  // Memeriksa penutupan kolam khusus oleh Super Admin
  function get_active_closure_today() {
      global $conn;
      $today = date('Y-m-d');
      $stmt = $conn->prepare("SELECT id, tanggal, keterangan, tipe FROM calendar_holidays WHERE tanggal = ? AND tipe IN ('tutup_pemeliharaan', 'libur') LIMIT 1");
      $stmt->bind_param("s", $today);
      $stmt->execute();
      return $stmt->get_result()->fetch_assoc() ?: null;
  }
  ```

### I. Notifikasi Modern & Konfirmasi Interaktif SweetAlert2
* **Fungsi:** Menggantikan seluruh dialog native peramban (`alert`, `confirm`, `prompt`) dengan SweetAlert2 bermotif modern yang selaras tema sistem, mencakup flash toast notification saat operasi database, konfirmasi hapus data / soft delete, serta modal info operasional saat kolam ditutup pemeliharaan.
* **Pustaka:** `SweetAlert2` library (`Swal.fire`).

---

## 📐 3. Pola Arsitektur Sistem

Aplikasi ini dibangun menggunakan arsitektur perangkat lunak berbasis **Front Controller Pattern**, dipadukan dengan konsep **Modular MVC (Model-View-Controller)** yang ramping dan terstruktur:

```mermaid
flowchart TD
    Client([Browser / Mobile Device]) --> Gateway{Web Gateway}

    subgraph Front_Controller_Layer [Routing & Security Shield Layer]
        Gateway --> Router[Front Controller: router.php / .htaccess]
        Router --> Shield[Security Shield: Whitelist & Blocklist]
        Shield -->|Deteksi Akses Ilegal| Deny[403 Forbidden Response]
        Shield -->|Rute Valid| Dispatcher[Route Dispatcher]
    end

    subgraph Business_Logic_Layer [Core Logic & Model Layer]
        Dispatcher --> CoreConfig[dist/app/config.php]
        CoreConfig --> AuthGuard[RBAC Access Guardian: check_auth]
        CoreConfig --> CSRFGuard[Cryptographic CSRF Validator]
        CoreConfig --> DataAccess[Data Access Layer: MySQLi Prepared Engine]
        CoreConfig --> BusinessEngines[Business Services: Toxic, Audit, Logs]
    end

    subgraph Presentation_Layer [View & Component Layer]
        Dispatcher --> MasterLayout[Master Layout Architecture]
        MasterLayout --> AdminHeader[Universal Topbar & Session Header]
        MasterLayout --> ViewTemplates[Modular View Components]
        MasterLayout --> AdminFooter[Universal Footer & Script Bundles]
        ViewTemplates --> ReactiveUI[Searchable Select, Theme Switcher, POS Realtime]
    end

    DataAccess --> Database[(MySQL / MariaDB)]
```

### Karakteristik Arsitektur:
1. **Front Controller & Clean URL Engine:** Seluruh lalu lintas HTTP diarahkan melalui gerbang tunggal yang memetakan rute ramah pengguna (*clean routes*) ke controller terkait, memblokir berkas sensitif, dan mengisolasi akses berkas bukti transaksi.
2. **Modular View-Controller:** Setiap modul administratif beroperasi secara independen di dalam namespace direktorinya masing-masing dengan tetap mewarisi standar *Master Layout* untuk menjaga konsistensi antarmuka.
3. **Role-Based Access Control (RBAC):** Sistem secara ketat membedakan 4 tingkat wewenang pengguna (*Super Admin*, *Administrator*, *Staf Kasir*, dan *Pengunjung*) pada tingkat gateway dan logika controller.
4. **Data Access Layer Terproteksi:** Seluruh komunikasi ke database diwajibkan melewati mekanisme *parameterized prepared statements* untuk menjamin kekebalan dari manipulasi query SQL.
5. **Universal Component Ecosystem:** Dilengkapi komponen frontend modular tanpa dependensi berat, seperti modul pencarian dropdown interaktif (*Searchable Select*) dan mesin dwibahasa (*i18n engine*).

---

## 🗄️ 4. Model Data & Relasi Entitas

Secara konseptual, struktur data aplikasi terbagi menjadi tiga domain utama:

```mermaid
erDiagram
    USERS ||--o{ TRANSAKSI : "melayani / membuat"
    TRANSAKSI ||--|{ DETAIL_TRANSAKSI : "memiliki"
    TIKET ||--o{ DETAIL_TRANSAKSI : "dikategorikan"
    USERS ||--o{ ACTIVITY_LOGS : "mencatat_audit"
    USERS ||--o{ CALENDAR_HOLIDAYS : "menetapkan_jadwal"
    
    USERS {
        int id_user PK
        string nama
        string username UK
        string email
        string password_hash
        int level "Super Admin, Admin, Staf, Pengunjung"
    }

    TIKET {
        int id_tiket PK
        string nama_tiket
        int harga
        string ikon
    }

    TRANSAKSI {
        int id_transaksi PK
        int id_user FK
        string nama_pemesan "Tamu loket manual"
        date tgl_pemesanan
        int total_harga
        string metode_pembayaran
        string status "pending, done, cancel"
        datetime deleted_at "Soft delete"
    }

    DETAIL_TRANSAKSI {
        int id_detail PK
        int id_transaksi FK
        int id_tiket FK
        int jumlah
        int subtotal
    }

    CALENDAR_HOLIDAYS {
        int id PK
        date tanggal UK
        string keterangan
        string tipe "libur, tutup_pemeliharaan, cuti"
        int created_by FK
        datetime created_at
    }
```

* **Domain Pengguna & Otorisasi:** Menyimpan data akun pengguna, level wewenang, kredensial terenkripsi aman (*Argon2id/Bcrypt*), log percobaan login, dan riwayat audit trail.
* **Domain Katalog & Transaksi:** Mengelola kategori tiket dinamis, header transaksi penjualan tiket, serta rincian item tiket per transaksi. Mendukung pencatatan tamu loket langsung (*walk-in*) dan penandaan *soft-delete* demi kepatuhan audit kas keuangan.
* **Domain Pemantauan & Moderasi:** Menampung kamus kata terlarang (*toxic words*) untuk sensor otomatis, tabel ulasan publik, serta log performa respon HTTP server.

---

## 💻 5. Panduan Instalasi & Pengaturan Lingkungan (Setup Guide)

### Prasyarat Sistem
* **PHP:** Versi 8.1 ke atas (dengan ekstensi `mysqli`, `curl`, `mbstring`, `fileinfo`, `gd`, `openssl`).
* **Basis Data:** MySQL 8.0+ atau MariaDB 10.4+.
* **Web Server:** Apache dengan modul `mod_rewrite` aktif, Nginx, atau PHP Built-in Server untuk lingkungan pengembangan.
* **Composer:** Opsional (digunakan apabila ingin mengunduh atau memperbarui pustaka vendor).

---

### Langkah-Langkah Pemasangan Lokal

1. **Unduh atau Kloning Repositori:**
   ```bash
   git clone https://github.com/Aiyub150/aplikasi-kasir-dan-portofolio-pemandian-patemon.git
   cd aplikasi-kasir-dan-portofolio-pemandian-patemon
   ```

2. **Inisialisasi Basis Data:**
   * Buat basis data baru bernama `pemandian` melalui phpMyAdmin, DBeaver, atau terminal MySQL.
   * Impor skema resmi yang tersedia pada direktori `database/pemandian.sql`:
     ```bash
     mysql -u root -p pemandian < database/pemandian.sql
     ```

3. **Konfigurasi Koneksi Database:**
   * Buka berkas `dist/app/config.php`.
   * Sesuaikan kredensial server database lokal Anda:
     ```php
     $host = "127.0.0.1";
     $user = "root";
     $pass = "";
     $db   = "pemandian";
     $port = 3306;
     ```

4. **Menjalankan Server Pengembangan Lokal Terpadu:**
   Jalankan launcher server terpadu yang secara otomatis mengaktifkan server web PHP, socket server SMTP, dan Webmail Mailpit:
   ```bash
   php serve.php
   ```
   Setelah perintah dijalankan, seluruh port layanan pengujian akan aktif secara simultan:
   
   | Layanan / Modul | Port | URL / Alamat Akses | Keterangan & Fungsi |
   | :--- | :---: | :--- | :--- |
   | **Aplikasi Kasir & Tiket** | `8000` | `http://localhost:8000/` | Website utama, landing page, kasir POS loket, dan panel manajemen admin. |
   | **Mailpit Web UI (Webmail)** | `8025` | `http://localhost:8025/` | Antarmuka browser untuk membaca seluruh email masuk (aktivasi staf, reset password, struk). |
   | **Mailpit SMTP Server** | `8001` | `127.0.0.1:8001` | Socket pengiriman email lokal berbasis RFC 5321 (otomatis digunakan sistem). |
   
   > **Catatan Pengujian Email:** Ketika Super Admin menambah staf baru atau pengguna meminta reset password, email tidak dikirim ke internet publik melainkan langsung masuk ke **Mailpit Web UI** pada port **8025**. Buka `http://localhost:8025` pada browser untuk melihat dan mengeklik tautan aktivasi.

---

### Akun Pengujian Bawaan Sistem

| Peran Pengguna | Tingkat Akses | Username | Kata Sandi | Deskripsi Wewenang |
| :--- | :---: | :--- | :--- | :--- |
| **Super Admin** | Level 1 | `super_admin` | `admin123` | Akses penuh, server logs, history audit, kelola seluruh akun. |
| **Administrator** | Level 2 | `admin` | `admin123` | Kelola transaksi, tarif tiket, filter kata kasar, laporan, moderasi. |
| **Staf Kasir** | Level 3 | `staff` | `staff123` | Operasional kasir loket (POS), verifikasi barcode, laporan shift mandiri. |
| **Pengunjung** | Level 0 | `tes` | `admin123` | Reservasi tiket mandiri, unduh struk voucher & barcode QR. |

---

## 🛠️ 6. Panduan Pengembangan & Kustomisasi (Development Workflow)

Bagian ini memandu pengembang dalam memodifikasi, menyesuaikan, atau menambahkan modul baru:

### 1. Kustomisasi Branding & Profil Wisata
* **Identitas & Kontak Objek Wisata:** Informasi nama instansi, alamat, email dinas, dan nomor telepon pengelola diatur secara terpusat pada konstanta konfigurasi di `dist/app/config.php`.
* **Aset Logo & Lambang Kedinasan:** Lambang Pemkab Jember dan logo resmi objek wisata tersimpan pada direktori `public/img/`.
* **Galeri & Banner Utama:** Konten gambar fasilitas dan kolam renang dapat diperbarui melalui modul manajemen galeri di panel admin atau langsung disesuaikan pada file view beranda `dist/views/index.php`.

### 2. Kustomisasi Model Bisnis & Kategori Tiket
* Sistem tidak membatasi jenis tiket. Anda dapat menambahkan tiket musiman, tiket pelajar/mahasiswa, maupun paket rombongan langsung melalui menu **Kategori Tiket** di panel admin.
* Seluruh tiket baru yang ditambahkan otomatis tersedia di antarmuka pemesanan online pengunjung dan modul POS kasir tanpa perlu menulis kode baru.

### 3. Standar Penambahan Halaman / Modul Baru
Saat membuat modul baru di dalam direktori `dist/views/`:
1. **Otorisasi di Baris Pertama:** Selalu panggil fungsi verifikasi hak akses di awal file untuk menentukan siapa yang berhak membuka modul (misal: `check_auth([1, 2]);`).
2. **Gunakan Master Layout:** Bungkus konten halaman menggunakan `admin_header.php` di bagian atas dan `admin_footer.php` di bagian bawah untuk mempertahankan keseragaman tema, bilah navigasi, dan skrip pembantu.
3. **Pendaftaran Rute Bersih:** Daftarkan rute URL bersih baru ke dalam pemetaan router di `router.php` dan buat alias rute pada fungsi helper `route_url()` di `dist/app/config.php`.
4. **Pencegahan CSRF:** Pastikan setiap formulir mutasi data menggunakan metode `POST`, menyertakan token keamanan, dan memvalidasinya sebelum memproses data.

---

## 🚀 7. Panduan Publikasi & Deployment ke Server Produksi (Publishing Guide)

Berikut adalah panduan teknis langkah demi langkah untuk menerbitkan aplikasi ke lingkungan produksi (*Production Web Server*):

### Opsi A: Deployment ke Shared Hosting (cPanel)
1. **Unggah Berkas Proyek:**
   * Kompres seluruh isi folder proyek menjadi arsip `.zip`.
   * Unggah dan ekstrak arsip tersebut ke dalam direktori `public_html` atau subdomain pilihan Anda di cPanel File Manager.
2. **Impor Basis Data:**
   * Buka menu **MySQL Databases** di cPanel, buat basis data baru beserta pengguna database dengan hak akses penuh (*ALL PRIVILEGES*).
   * Buka **phpMyAdmin**, pilih basis data yang baru dibuat, lalu impor berkas `database/pemandian.sql`.
3. **Sesuaikan Konfigurasi Koneksi:**
   * Buka berkas `dist/app/config.php` via editor cPanel, perbarui nama basis data, nama pengguna, dan kata sandi sesuai dengan yang dibuat di langkah sebelumnya.
4. **Verifikasi Web Server Rewrite:**
   * Pastikan berkas `.htaccess` di root direktori telah terunggah dengan benar untuk memastikan pemetaan rute bersih berfungsi sempurna pada Apache/LiteSpeed.

---

### Opsi B: Deployment ke Virtual Private Server (VPS / Cloud Server)
Rekomendasi konfigurasi server produksi menggunakan Ubuntu Server dengan Nginx/Apache:

#### 1. Pengaturan Direktori & Hak Akses Berkas (File Permissions)
Terapkan izin kepemilikan web server (`www-data`) dengan prinsip hak akses terkecil (*least privilege*):
```bash
# Atur kepemilikan ke user web server
sudo chown -R www-data:www-data /var/www/pemandian-patemon

# Direktori standar: 755, Berkas standar: 644
sudo find /var/www/pemandian-patemon/ -type d -exec chmod 755 {} \;
sudo find /var/www/pemandian-patemon/ -type f -exec chmod 644 {} \;

# Berikan izin tulis khusus hanya pada direktori upload bukti pembayaran & avatar
sudo chmod -R 775 /var/www/pemandian-patemon/dist/app/payment
sudo chmod -R 775 /var/www/pemandian-patemon/public/img/avatars
```

#### 2. Konfigurasi Nginx (Virtual Host)
Jika menggunakan Nginx sebagai reverse proxy / web server, gunakan konfigurasi blok server berikut:
```nginx
server {
    listen 80;
    server_name tiket.patemon.jemberkab.go.id;
    root /var/www/pemandian-patemon;
    index dist/views/index.php;

    # Blokir akses publik ke berkas sensitif dan repositori git
    location ~* /\.(env|git|sql|md)$ {
        deny all;
        return 403;
    }

    # Proteksi berkas dependensi dan database dump
    location ~* /(composer\.(json|lock)|database/) {
        deny all;
        return 403;
    }

    # Routing engine fallback
    location / {
        try_files $uri $uri/ /dist/views/index.php?$query_string;
    }

    # Pemrosesan PHP FastCGI
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Optimasi Cache untuk Aset Statis
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }
}
```

#### 3. Penerapan Sertifikat Keamanan SSL/TLS (HTTPS)
Wajib mengaktifkan enkripsi data lalu lintas untuk menjaga kerahasiaan kata sandi dan transaksi:
```bash
sudo certbot --nginx -d tiket.patemon.jemberkab.go.id
```

#### 4. Pengerasan Lingkungan Produksi (Production Hardening Checklist)
* Nonaktifkan penampilan pesan error di layar publik pada lingkungan produksi: pastikan konfigurasi `display_errors = Off` dan `log_errors = On` di `php.ini`.
* Pastikan flag cookie sesi (`session.cookie_secure = 1` dan `session.cookie_httponly = 1`) aktif.
* Jadwalkan backup basis data otomatis secara berkala menggunakan cron job:
  ```bash
  0 2 * * * mysqldump -u db_user -p'db_pass' pemandian | gzip > /var/backups/patemon/db_$(date +\%F).sql.gz
  ```

---

## 📈 8. Rencana Pengembangan Masa Depan (Roadmap)

Sistem dirancang modular untuk mengakomodasi peningkatan skala di masa mendatang:

1. **Integrasi Payment Gateway Otomatis:** Menghubungkan modul pembayaran dengan gateway nasional (seperti Midtrans Snap, Xendit, atau Duitku) untuk otomatisasi verifikasi status lunas tanpa perlu tinjauan bukti transfer manual.
2. **Kios Tiket Mandiri (Self-Service Kiosk):** Antarmuka kasir dapat diintegrasikan dengan layar sentuh (*touchscreen*) loket dan printer termal dispenser tiket di pintu gerbang utama wisata.
3. **Turnstile Gate Integration (Smart Gate IoT):** Menyambungkan scanner barcode dengan mikrokontroler palang pintu otomatis (*turnstile barrier gate*) untuk validasi akses masuk tanpa kontak fisik.
4. **Aplikasi Mobile Petugas:** Pengembangan aplikasi mobile berbasis Android/iOS untuk petugas keamanan dan penjaga kolam guna memantau kapasitas daya tampung pengunjung secara dinamis.

---

## 👨‍💻 9. Kontribusi & Lisensi

* **Pengembang Utama:** [Aiyub150](https://github.com/Aiyub150)
* **Instansi Terkait:** UPTD Pariwisata Pemandian Patemon, Dinas Pariwisata dan Kebudayaan Pemerintah Kabupaten Jember.
* **Lisensi Penggunaan:** Proyek ini masih dibuat open-source dan gratis sehingga instansi manapun bisa menyesuaikan sesuai kebutuhan.
