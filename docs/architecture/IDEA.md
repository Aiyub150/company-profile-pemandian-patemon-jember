# IDEA.md

# Sistem Informasi Kasir & Reservasi Wisata Pemandian Patemon

## 1. Gambaran Umum

Project ini merupakan sistem informasi terpadu untuk Pemandian Patemon Jember yang menggabungkan:

* website profil dan informasi wisata,
* reservasi tiket,
* e-ticketing,
* operasional kasir/POS,
* validasi tiket,
* pelaporan transaksi,
* manajemen pengguna dan hak akses,
* audit aktivitas,
* serta komponen keamanan aplikasi.

Project menggunakan PHP Native dengan struktur aplikasi modular dan database MySQL/MariaDB. Dependensi utama PHP mencakup Dompdf untuk pembuatan dokumen PDF dan Picqer Barcode Generator untuk kebutuhan barcode.

---

# 2. Visi Project

Tujuan jangka panjang project adalah menjadikan Pemandian Patemon memiliki sistem digital yang:

1. mudah digunakan oleh pengunjung,
2. cepat digunakan oleh petugas loket,
3. mampu mengurangi kesalahan pencatatan transaksi,
4. menyediakan data operasional yang mudah dipantau,
5. memiliki kontrol akses yang jelas,
6. aman terhadap manipulasi dan serangan umum,
7. mudah dikembangkan,
8. mudah dipelihara,
9. dapat digunakan pada perangkat desktop maupun mobile,
10. dan memiliki arsitektur yang cukup terstruktur untuk dikembangkan menjadi sistem pariwisata yang lebih besar.

Project tidak hanya dianggap sebagai website company profile.

Project harus dipandang sebagai:

**Public Tourism Platform + Reservation System + Ticketing System + POS + Management Information System**

---

# 3. Kondisi Sistem Saat Ini

Sistem saat ini memiliki beberapa domain utama:

### Public Tourism

Digunakan oleh pengunjung untuk:

* melihat informasi wisata,
* melihat fasilitas,
* melihat galeri,
* melihat tarif,
* memberikan ulasan,
* menggunakan antarmuka bahasa Indonesia dan Inggris.

### Reservation & E-Ticketing

Digunakan untuk:

* melakukan reservasi,
* memilih tiket,
* memilih metode pembayaran,
* mendapatkan bukti transaksi,
* mendapatkan barcode/tiket digital,
* melakukan validasi tiket.

### POS / Kasir

Digunakan oleh staf untuk:

* transaksi pengunjung walk-in,
* memilih jenis tiket,
* menentukan jumlah tiket,
* menghitung total transaksi,
* menghitung uang kembalian,
* mencetak nota,
* melakukan pencatatan transaksi.

### Ticket Validation

Digunakan untuk:

* membaca barcode melalui kamera,
* memeriksa tiket,
* memastikan tiket valid,
* mencegah tiket digunakan secara tidak semestinya.

### Reporting

Digunakan untuk:

* laporan harian,
* laporan mingguan,
* laporan bulanan,
* laporan tahunan,
* kebutuhan dokumentasi dan administrasi.

### Administration

Digunakan untuk:

* mengelola pengguna,
* mengelola tiket,
* mengelola transaksi,
* mengelola konten,
* mengelola moderasi,
* melihat audit/log.

Repository juga mendokumentasikan empat level akses: Super Admin, Administrator, Staf Kasir, dan Pengunjung.

---

# 4. Prinsip Utama Pengembangan

Setiap pengembangan baru harus mengikuti prinsip berikut.

## 4.1 Jangan merusak sistem yang sudah berjalan

Perubahan harus dilakukan secara bertahap.

Sebelum mengubah sebuah modul:

1. pahami fungsi modul,
2. pahami dependency-nya,
3. pahami data yang digunakan,
4. pahami halaman atau modul lain yang bergantung kepadanya.

Jangan melakukan rewrite besar tanpa alasan teknis yang kuat.

---

## 4.2 Pertahankan arsitektur yang sudah ada

Project menggunakan pendekatan Front Controller dan modular MVC sederhana.

Pengembangan baru harus mengikuti pola yang sudah digunakan project daripada membuat pola baru yang tidak konsisten.

Jika sebuah modul baru dibuat, usahakan memiliki:

* route,
* controller/handler,
* view,
* validasi,
* authorization,
* database access,
* error handling.

---

## 4.3 Security by Default

Setiap fitur baru harus dianggap tidak aman sampai divalidasi.

Minimal periksa:

* SQL Injection,
* XSS,
* CSRF,
* authentication bypass,
* authorization bypass,
* IDOR,
* path traversal,
* unrestricted file upload,
* session issues,
* brute force,
* information disclosure,
* insecure direct object access,
* manipulasi parameter transaksi.

Jangan mengandalkan frontend sebagai mekanisme keamanan.

Semua validasi penting harus dilakukan di server.

---

# 5. Filosofi Produk

Sistem harus memprioritaskan tiga pihak utama:

## Pengunjung

Pengunjung harus dapat:

* menemukan informasi wisata,
* memahami tarif,
* melakukan reservasi,
* menerima tiket,
* memahami status reservasi,
* dan melakukan validasi tiket dengan mudah.

Interface harus sederhana dan tidak membutuhkan pengetahuan teknis.

---

## Petugas Kasir

Petugas harus dapat melakukan transaksi secepat mungkin.

POS harus mengurangi pekerjaan manual, bukan menambahnya.

Flow ideal:

```text
Pilih tiket
    ↓
Tentukan jumlah
    ↓
Hitung total
    ↓
Pilih pembayaran
    ↓
Konfirmasi
    ↓
Cetak / kirim bukti
```

Setiap langkah yang tidak penting harus diminimalkan.

---

## Administrator

Administrator membutuhkan informasi yang akurat dan mudah dianalisis.

Dashboard harus membantu menjawab:

* berapa transaksi hari ini?
* berapa jumlah pengunjung?
* berapa omzet?
* jenis tiket apa yang paling banyak digunakan?
* bagaimana tren transaksi?
* transaksi apa yang dibatalkan?
* siapa yang melakukan perubahan?
* apakah terdapat aktivitas yang mencurigakan?

---

# 6. Ide Pengembangan Fitur

Berikut adalah arah pengembangan yang dapat dipertimbangkan.

## 6.1 Dashboard Operasional

Dashboard sebaiknya berkembang dari sekadar menampilkan angka menjadi pusat informasi operasional.

Contoh:

```text
Today's Revenue
Today's Visitors
Tickets Sold
Pending Reservations
Cancelled Transactions
Active Staff
```

Tambahkan grafik untuk:

* transaksi per jam,
* pengunjung per hari,
* omzet per hari,
* jenis tiket,
* metode pembayaran.

---

## 6.2 Reservation Management

Sistem reservasi dapat dikembangkan menjadi lifecycle yang jelas:

```text
PENDING
   ↓
PAID
   ↓
CONFIRMED
   ↓
USED
   ↓
COMPLETED
```

Status pembatalan:

```text
PENDING
   ↓
CANCELLED
```

Jangan mencampurkan status pembayaran, status tiket, dan status transaksi apabila ketiganya memiliki makna berbeda.

---

## 6.3 Ticket Lifecycle

Setiap tiket sebaiknya mempunyai lifecycle yang eksplisit.

Contoh:

```text
GENERATED
   ↓
ACTIVE
   ↓
SCANNED
   ↓
USED
```

Tiket yang sudah digunakan tidak boleh dianggap valid kembali kecuali terdapat mekanisme administratif yang memang dirancang untuk kasus tersebut.

---

## 6.4 Anti-Fraud

Sistem dapat dikembangkan untuk mendeteksi pola transaksi tidak normal.

Contoh:

* tiket yang sama digunakan dua kali,
* transaksi dibatalkan setelah pembayaran,
* perubahan harga secara abnormal,
* transaksi dalam jumlah sangat besar,
* perubahan data transaksi setelah transaksi selesai,
* login gagal berulang kali,
* aktivitas admin di luar pola normal.

Sistem tidak harus langsung memblokir.

Pada tahap awal cukup:

```text
Detect
  ↓
Log
  ↓
Alert
  ↓
Administrator Review
```

---

# 7. Ide untuk Security Center

Tambahkan halaman khusus untuk keamanan aplikasi.

Contoh:

```text
Security Center
├── Login Attempts
├── Failed Login
├── Blocked IP
├── Audit Trail
├── Suspicious Requests
├── File Access
├── Admin Activity
└── Security Events
```

Hal ini dapat menjadi pusat observabilitas keamanan tanpa mencampurkan seluruh informasi keamanan ke dashboard operasional.

---

# 8. Audit Trail

Semua tindakan penting sebaiknya dapat ditelusuri.

Contoh:

```text
WHO
WHAT
WHEN
WHERE
TARGET
RESULT
```

Contoh:

```text
admin
UPDATE
2026-09-29 10:15
ticket
id=12
success
```

Untuk perubahan sensitif, simpan juga:

```text
before
after
```

Namun jangan menyimpan password atau secret dalam audit log.

---

# 9. Reporting & Export

Laporan harus dipisahkan antara:

### Operational Report

Untuk kebutuhan sehari-hari.

### Financial Report

Untuk kebutuhan transaksi dan penerimaan.

### Audit Report

Untuk penelusuran perubahan.

### Management Report

Untuk analisis.

Jangan membuat satu laporan besar yang mencampurkan semuanya.

---

# 10. Mobile First

Karena petugas dapat menggunakan smartphone untuk scanner barcode, sistem harus memperhatikan perangkat mobile.

Prioritas UI:

```text
Desktop
Tablet
Mobile
```

Fitur scanner khususnya harus:

* memiliki tombol besar,
* mudah digunakan dengan satu tangan,
* memberikan feedback sukses/gagal yang jelas,
* tidak bergantung pada hover,
* tetap usable pada layar kecil.

---

# 11. Offline Tolerance

Di masa depan, sistem dapat dipertimbangkan untuk menangani gangguan koneksi.

Contoh:

```text
Normal Internet
     ↓
Online Validation

Internet unavailable
     ↓
Temporary Local Queue
     ↓
Sync when connection returns
```

Fitur ini jangan dibuat sebelum kebutuhan bisnisnya benar-benar jelas karena sinkronisasi data transaksi membutuhkan perancangan konsistensi yang baik.

---

# 12. Performance

Performance harus diperhatikan terutama pada:

* dashboard,
* transaksi POS,
* scanner tiket,
* pencarian,
* laporan,
* query database,
* halaman publik dengan banyak gambar.

Hindari:

* query berulang yang tidak perlu,
* mengambil seluruh tabel apabila hanya membutuhkan sebagian data,
* memuat asset besar tanpa optimasi,
* melakukan operasi berat pada setiap request.

Untuk fungsi yang sering digunakan, ukur terlebih dahulu sebelum melakukan optimasi besar.

---

# 13. Database

Database harus menjadi sumber data utama untuk informasi operasional.

Data penting seperti:

* harga tiket,
* transaksi,
* pengguna,
* status tiket,
* status pembayaran,
* audit,
* dan konfigurasi bisnis

tidak boleh hanya disimpan pada frontend atau hard-coded dalam JavaScript.

Perubahan schema harus mempertimbangkan kompatibilitas terhadap data lama.

---

# 14. Business Rules

Business rule harus dipisahkan dari tampilan.

Contoh:

```text
harga tiket
validasi tiket
perhitungan transaksi
status pembayaran
hak akses
```

tidak boleh hanya bergantung pada JavaScript frontend.

Server harus menjadi sumber kebenaran.

---

# 15. Ide Integrasi Eksternal

Di masa depan sistem dapat diintegrasikan dengan:

* payment gateway,
* QRIS,
* WhatsApp notification,
* email notification,
* cloud backup,
* analytics,
* API kalender nasional,
* API cuaca,
* sistem monitoring,
* dan layanan pemerintah yang relevan.

Namun setiap integrasi eksternal harus memiliki:

* timeout,
* error handling,
* retry strategy,
* logging,
* rate limiting bila perlu,
* fallback behavior,
* dan validasi response.

Jangan membuat sistem bergantung sepenuhnya pada layanan eksternal tanpa fallback.

---

# 16. API

Jika project semakin besar, pertimbangkan pembuatan API internal.

Contoh:

```text
/api/auth
/api/tickets
/api/reservations
/api/transactions
/api/reports
/api/scanner
/api/users
```

API dapat digunakan oleh:

* web frontend,
* mobile application,
* scanner,
* dashboard,
* dan sistem eksternal.

API harus memiliki authorization yang berbeda dengan akses halaman biasa bila memang dibutuhkan.

---

# 17. Observability

Sistem sebaiknya dapat menjawab tiga pertanyaan:

### Apa yang terjadi?

Logging.

### Mengapa terjadi?

Audit / error context.

### Seberapa sering terjadi?

Metrics.

Contoh metric:

```text
request_count
error_count
login_failure_count
transaction_count
scanner_failure_count
average_response_time
```

---

# 18. Testing Strategy

Project sebaiknya berkembang menuju testing yang lebih terstruktur.

Minimal testing dibagi menjadi:

## Functional Test

Apakah fitur bekerja?

## Regression Test

Apakah perubahan merusak fitur lama?

## Security Test

Apakah terdapat celah keamanan?

## Integration Test

Apakah modul bekerja bersama?

## UI Test

Apakah flow pengguna tetap berjalan?

---

# 19. Definition of Done

Sebuah fitur dianggap selesai apabila:

```text
Requirement understood
        ↓
Implementation complete
        ↓
Test executed
        ↓
Bug fixed
        ↓
Security checked
        ↓
Code reviewed
        ↓
Documentation updated
```

Jangan menganggap fitur selesai hanya karena halaman berhasil dibuka.

---

# 20. Prinsip untuk AI Coding Agent

AI agent yang mengerjakan project ini harus memahami bahwa project ini adalah aplikasi yang sudah memiliki struktur dan business logic.

AI tidak boleh:

* menghapus fitur tanpa alasan,
* mengganti framework hanya karena framework lain lebih populer,
* melakukan rewrite besar tanpa permintaan,
* membuat dependency baru tanpa kebutuhan,
* mengubah schema database secara sembarangan,
* menghapus security mechanism,
* menonaktifkan validasi untuk membuat test lewat,
* memasukkan credential atau secret ke source code.

AI harus:

* membaca code sebelum mengubahnya,
* mencari implementasi serupa,
* mengikuti pola yang sudah ada,
* melakukan perubahan minimal,
* menguji perubahan,
* memeriksa regression,
* memeriksa security,
* dan menjelaskan perubahan yang dilakukan.

---

# 21. Workflow Pengembangan yang Diinginkan

Project ini menggunakan pola:

```text
TASK
 ↓
UNDERSTAND
 ↓
INSPECT
 ↓
PLAN
 ↓
IMPLEMENT
 ↓
TEST
 ↓
FIX
 ↓
RETEST
 ↓
REVIEW
 ↓
DOCUMENT
 ↓
DONE
```

Jika test gagal:

```text
TEST
 ↓
FAIL
 ↓
ANALYZE
 ↓
FIX
 ↓
TEST AGAIN
```

Jika reviewer menemukan masalah:

```text
REVIEW
 ↓
ISSUE FOUND
 ↓
IMPLEMENT FIX
 ↓
TEST
 ↓
REVIEW AGAIN
```

---

# 22. Prioritas Pengembangan

Dalam menentukan pekerjaan baru, gunakan tiga kategori:

### Critical

Masalah keamanan, kehilangan data, transaksi salah, authentication/authorization bypass, atau kerusakan fungsi utama.

### Important

Bug dan fitur yang memengaruhi operasional utama.

### Enhancement

Peningkatan UX, performance, automation, visual, dan fitur tambahan.

Kategori ini digunakan untuk membantu menentukan urutan pekerjaan, bukan untuk menghalangi pekerjaan lain yang memang sedang diminta oleh user.

---

# 23. Roadmap Konseptual

## Phase 1 — Stabilization

Fokus:

* memastikan seluruh fitur utama berjalan,
* memperbaiki bug,
* memperbaiki security,
* memastikan database konsisten,
* memperbaiki error handling.

## Phase 2 — Operational Improvement

Fokus:

* meningkatkan POS,
* meningkatkan scanner,
* meningkatkan dashboard,
* meningkatkan laporan,
* meningkatkan UX mobile.

## Phase 3 — Automation

Fokus:

* notification otomatis,
* backup,
* monitoring,
* alert,
* automated reporting.

## Phase 4 — Platform Expansion

Fokus:

* API,
* mobile application,
* integrasi payment,
* integrasi sistem eksternal,
* analytics.

---

# 24. Ide Jangka Panjang

Dalam jangka panjang, project dapat berkembang dari sistem website + kasir menjadi platform digital pengelolaan destinasi wisata.

Potensi pengembangan:

```text
Tourism Website
      +
Reservation
      +
E-Ticketing
      +
POS
      +
Visitor Management
      +
Financial Reporting
      +
Analytics
      +
Operational Monitoring
      +
Security Monitoring
      +
External Integration
```

Tetapi pengembangan harus tetap dilakukan secara bertahap.

Jangan mengimplementasikan semua ide sekaligus.

---

# 25. Aturan untuk Developer dan AI Agent

Sebelum melakukan perubahan besar, jawab:

1. Apa masalah yang ingin diselesaikan?
2. Bagian sistem mana yang terdampak?
3. Apakah sudah ada implementasi serupa?
4. Apakah database perlu berubah?
5. Apakah authorization perlu berubah?
6. Apakah terdapat dampak security?
7. Bagaimana cara menguji perubahan?
8. Apakah perubahan dapat menyebabkan regression?

Jika pertanyaan tersebut belum dapat dijawab, lakukan inspection terlebih dahulu.

---

# 26. Prinsip Terakhir

Project ini harus berkembang secara:

**Incremental.**

**Testable.**

**Secure.**

**Maintainable.**

**Understandable.**

**Business-oriented.**

Fitur baru harus menyelesaikan masalah nyata.

Code baru harus mengikuti struktur project.

Perubahan besar harus memiliki alasan.

Dan setiap perubahan harus dapat diuji serta ditinjau kembali.

Tujuan akhirnya bukan membuat code sebanyak mungkin.

Tujuan akhirnya adalah membangun sistem Pemandian Patemon yang dapat digunakan dengan aman, stabil, mudah dipahami, dan dapat dikembangkan dalam jangka panjang.
