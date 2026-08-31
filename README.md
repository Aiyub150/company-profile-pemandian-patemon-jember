# Aplikasi Web Pemandian (PHP Native)

## Deskripsi
Aplikasi web ini adalah sistem informasi dan manajemen untuk operasional pemandian, dibangun menggunakan **PHP Native** dan **MySQL**. Sistem ini dirancang untuk memudahkan pengelolaan data secara sederhana, ringan, dan langsung berjalan pada server berbasis PHP tanpa memerlukan framework tambahan.

## Persyaratan Sistem
- **Web Server**: Apache (XAMPP / Laragon / LAMP stack)
- **PHP**: Versi 7.x atau 8.x
- **Database**: MySQL / MariaDB

## Struktur Direktori Utama
*(Catatan: Struktur ini adalah representasi umum aplikasi PHP Native, silakan sesuaikan jika terdapat nama folder spesifik)*
- `index.php` - Halaman utama aplikasi / *landing page*.
- `koneksi.php` / `config.php` - File konfigurasi untuk koneksi database MySQL.
- `admin/` - Direktori untuk panel kontrol administrator.
- `assets/` - Folder berisi *resource* frontend (CSS, JS, gambar).
- `database/` - Folder yang berisi file `.sql` untuk keperluan *import* struktur tabel.

## Panduan Instalasi
1. Kloning repositori ke dalam direktori server lokal Anda (misal: folder `htdocs` jika menggunakan XAMPP):
   ```bash
   git clone https://github.com/Aiyub150/Pemandian.git
   ```
2. Jalankan aplikasi web server (Apache) dan MySQL melalui panel kontrol (XAMPP/Laragon).
3. Buka phpMyAdmin di browser (`http://localhost/phpmyadmin`) dan buat *database* baru dengan nama `db_pemandian`.
4. Pilih menu **Import**, lalu unggah file `.sql` yang ada pada repositori ini.
5. Buka file konfigurasi koneksi (contoh: `koneksi.php`) dan pastikan kredensial database sudah sesuai:
   ```php
   $host = "localhost";
   $user = "root";
   $pass = "";
   $db   = "db_pemandian"; // Sesuaikan dengan nama database Anda
   ```
6. Akses aplikasi melalui browser:
   ```text
   http://localhost/Pemandian
   ```
