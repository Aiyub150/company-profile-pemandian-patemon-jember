# Sistem Inventaris Pemandian (SIP) - Berbasis Laravel

![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)

## 📌 Deskripsi Proyek
**Sistem Inventaris Pemandian (SIP)** adalah aplikasi manajemen inventaris berbasis web yang dikembangkan secara spesifik untuk mengelola aset, barang habis pakai, dan operasional fasilitas di Pemandian (khususnya dapat diimplementasikan untuk Pemandian Patemon). 

Proyek ini dibangun menggunakan **Framework Laravel** untuk memastikan skalabilitas, keamanan, dan kemudahan pemeliharaan (maintainability). Sistem ini didesain dengan pendekatan arsitektur yang terstruktur, menolak praktik pengkodean yang buruk (*bad smells*), dan mewajibkan standar tinggi dalam setiap *commit*-nya.

---

## ⚙️ Persyaratan Sistem (System Requirements)
Pastikan lingkungan pengembangan Anda memenuhi spesifikasi berikut sebelum menjalankan aplikasi:
- **PHP**: `^8.2` (Strict typing diwajibkan)
- **Composer**: `^2.0`
- **Database**: MySQL `8.0+` atau PostgreSQL `14.0+`
- **Node.js** & **NPM**: `^18.x` (Untuk kompilasi aset dengan Vite)
- **Ekstensi PHP**: BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML.

---

## 🚀 Panduan Instalasi (Installation Guide)

Ikuti langkah-langkah di bawah ini secara presisi. Kegagalan dalam mengikuti urutan dapat mengakibatkan malfungsi pada sistem.

1. **Kloning Repositori**
   ```bash
   git clone https://github.com/Aiyub150/Pemandian.git
   cd Pemandian
   ```

2. **Instalasi Dependensi PHP & Node.js**
   ```bash
   composer install --optimize-autoloader --no-dev # (Gunakan --no-dev hanya di Production)
   npm install
   ```

3. **Konfigurasi Environment**
   Salin berkas `.env.example` menjadi `.env` dan sesuaikan kredensial database.
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Migrasi Database & Seeding**
   Pastikan database telah terbuat sebelum menjalankan perintah ini.
   ```bash
   php artisan migrate --seed
   ```

5. **Kompilasi Aset (Frontend)**
   ```bash
   npm run build
   ```

6. **Menjalankan Server Lokal**
   ```bash
   php artisan serve
   ```

---

## 📂 Struktur Direktori Utama
Proyek ini mengadopsi standar struktur Laravel dengan beberapa penyesuaian untuk modul inventaris:
- `app/Models/Inventory/` - Model khusus domain inventaris (Barang, Kategori, Transaksi).
- `app/Services/` - *Business logic* dipisahkan dari Controller untuk menjaga kode tetap *Clean* dan *Testable*.
- `app/Http/Controllers/Api/` - Endpoint API untuk integrasi sistem eksternal atau *client-side rendering*.
- `tests/Feature/` - Kumpulan *Automated Tests* yang wajib lolos sebelum melakukan *Pull Request*.

---

## 🛡️ Standar Pengembangan & Kode Etik (Strict Guidelines)

Sebagai pengembang dalam proyek ini, Anda **DIWAJIBKAN** mematuhi aturan berikut. Tidak ada toleransi untuk kode yang berantakan.

1. **Standar Kode (Coding Standard):**
   - Wajib mematuhi **PSR-12**.
   - Setiap fungsi/method wajib memiliki *Return Type Declarations* dan *Type Hinting*.
   - Jangan pernah meletakkan *Business Logic* di dalam Controller atau Route. Gunakan *Service Pattern*.

2. **Database & Query:**
   - Dilarang keras menggunakan *Raw Query* kecuali untuk optimasi yang sangat spesifik dan telah disetujui. Gunakan *Eloquent ORM*.
   - Atasi masalah *N+1 Query Problem* menggunakan *Eager Loading* (`with()`).

3. **Git Workflow:**
   - *Branching*: Gunakan pola `feature/nama-fitur`, `bugfix/nama-bug`.
   - *Commit Messages*: Harus deskriptif, jelas, dan berbahasa Inggris (contoh: `feat: add inventory stock calculation service`).
   - Dilarang melakukan *Push* langsung ke *branch* `main` atau `master`. Gunakan *Pull Request* (PR).

4. **Pengujian (Testing):**
   - Setiap fitur baru **WAJIB** disertai dengan *Unit Test* atau *Feature Test* (PHPUnit/Pest).
   - *Code Coverage* tidak boleh turun di bawah 80%.

---

## 📞 Pemelihara Proyek (Maintainer)
- **Aiyub150** - *Lead Developer / Project Owner*

*Dokumentasi ini adalah kontrak kerja sistem. Ketidakpatuhan terhadap standar di atas akan mengakibatkan penolakan (reject) pada kode yang diajukan.*
