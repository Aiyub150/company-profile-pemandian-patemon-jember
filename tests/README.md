# Automated Tests & Quality Assurance Suite

Direktori ini memuat rangkaian skrip pengujian otomatis (*automated testing suite*) untuk aplikasi **Pemandian Patemon Jember**.

Skrip-skrip ini dirancang agar developer baru maupun tim pengembang dapat memvalidasi kebenaran logika bisnis, integritas database, keamanan autentikasi, serta mencegah regresi (*breaking changes*) dengan mudah tanpa perlu melakukan klik manual berulang kali di antarmuka web.

---

## 📁 Struktur Direktori

```text
tests/
├── Unit/
│   ├── TestRunner.php       # Micro test framework dengan assertion & color output
│   ├── AuthTest.php         # Verifikasi hash BCRYPT, RBAC, dan session helper
│   ├── CalendarTest.php     # Pengujian status libur/pemeliharaan & operasional
│   └── TransactionTest.php  # Validasi kalkulasi tiket, TRX code, dan filter kata toxic
├── run_all.php              # Master script runner untuk mengeksekusi semua suite
└── README.md                # Dokumentasi petunjuk pengujian
```

---

## 🚀 Prasyarat Pengujian

1. **PHP CLI** aktif (versi >= 8.1).
2. Database MySQL lokal aktif dan terhubung (koneksi diatur otomatis melalui `dist/app/config.php`).

---

## ⚡ Cara Menjalankan Test

### 1. Menjalankan Seluruh Suite Sekaligus (Rekomendasi)
Dari root proyek, jalankan:
```bash
php tests/run_all.php
```
*Output akan menampilkan status kelulusan setiap kelompok pengujian (Auth, Kalender, Transaksi) beserta ringkasan total.*

### 2. Menjalankan Test Spesifik Tertentu
Anda dapat mengeksekusi unit test secara terpisah sesuai fitur yang sedang dikerjakan:

* **Pengujian Autentikasi, Role & CSRF:**
  ```bash
  php tests/Unit/AuthTest.php
  ```
* **Pengujian Logika Kalender & Hari Libur/Tutup:**
  ```bash
  php tests/Unit/CalendarTest.php
  ```
* **Pengujian Tiket & Kalkulasi Transaksi:**
  ```bash
  php tests/Unit/TransactionTest.php
  ```

---

## 💡 Menambahkan Test Baru
Untuk menambahkan skrip pengujian baru:
1. Buat file baru di dalam `tests/Unit/NamaFiturTest.php`.
2. Muat runner dengan `require_once __DIR__ . '/TestRunner.php';`.
3. Inisialisasi `$runner = new TestRunner();` dan gunakan `$runner->assert($kondisi, 'Deskripsi Pengujian');`.
4. Akhiri skrip dengan `exit($runner->summarize());`.
5. Daftarkan path file tersebut ke dalam array `$testFiles` di `tests/run_all.php`.
