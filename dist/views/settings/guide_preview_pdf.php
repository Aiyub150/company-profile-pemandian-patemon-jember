<?php
/**
 * Buku Panduan Pengguna Sistem (Manual Book Format Standar SIM-ASET)
 * Pemandian Patemon - Pemkab Jember (Universal Open-Source Edition)
 * Mendukung Ekspor Dwibahasa: Bahasa Indonesia & English (Feedback-8 Poin 6)
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

check_auth([0, 1, 2, 3], route_url('login'));

$lang = strtolower(trim($_GET['lang'] ?? 'id'));
if (!in_array($lang, ['id', 'en'], true)) {
    $lang = 'id';
}

// Load logo panjang Pemandian Patemon untuk halaman cover
$logo_jpg = __DIR__ . '/../../../public/img/logo_pemandian_wide.jpg';
$logo_png = __DIR__ . '/../../../public/img/logo_pemandian_transparant.png';
$logo_img_tag = '';

if (file_exists($logo_jpg)) {
    $logo_base64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logo_jpg));
    $logo_img_tag = '<div style="margin-bottom: 25px;"><img src="' . $logo_base64 . '" alt="Wisata Pemandian Patemon" style="width: 290px; max-width: 80%; height: auto;"></div>';
} elseif (file_exists($logo_png)) {
    $logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_png));
    $logo_img_tag = '<div style="margin-bottom: 25px;"><img src="' . $logo_base64 . '" alt="Wisata Pemandian Patemon" style="width: 290px; max-width: 80%; height: auto;"></div>';
}

if ($lang === 'en') {
    // English Content
    $doc_title = 'System Operations & User Manual - Pemandian Patemon';
    $stream_filename = 'Operations_Manual_Pemandian_Patemon.pdf';
    $html_content = '
    <!-- COVER PAGE (ENGLISH) -->
    <div class="cover-container">
        ' . $logo_img_tag . '
        <div class="cover-badge">OFFICIAL USER MANUAL & SOP</div>
        <h1 class="cover-title">TOURISM POS & RESERVATION SYSTEM OPERATIONS MANUAL</h1>
        <div class="cover-subtitle">Counter Retribution Governance, Online Ticketing & Public Accountability Reporting</div>
        <div class="cover-divider"></div>

        <div style="font-size: 11pt; font-weight: 600; color: #334155; margin-bottom: 8px;">
            Jember Regency Government
        </div>
        <div style="font-size: 10pt; color: #64748b;">
            Department of Tourism and Culture &bull; Patemon Natural Spring Baths
        </div>

        <div class="cover-meta">
            <strong>System Edition:</strong> Version 2.0.0 (Enterprise Open-Source)<br>
            <strong>Release Date:</strong> September 2026<br>
            <strong>Operational Location:</strong> Tanggul, Jember Regency, East Java
        </div>
    </div>

    <div class="page-break"></div>

    <!-- TABLE OF CONTENTS (ENGLISH) -->
    <div>
        <h1 class="toc-heading">TABLE OF CONTENTS</h1>
        <div class="toc-list">
            <div class="toc-item toc-chapter">CHAPTER I &bull; INTRODUCTION & ACCESS ARCHITECTURE</div>
            <div class="toc-item toc-sub">1.1 General System Overview & Purpose</div>
            <div class="toc-item toc-sub">1.2 4-Tier Role-Based Access Control (RBAC)</div>

            <div class="toc-item toc-chapter" style="margin-top: 15px;">CHAPTER II &bull; CASHIER COUNTER OPERATIONS (POINT OF SALE)</div>
            <div class="toc-item toc-sub">2.1 POS Transaction Workflow (Walk-in & Registered Guests)</div>
            <div class="toc-item toc-sub">2.2 Payment Methods & Live WebRTC Camera Evidence</div>
            <div class="toc-item toc-sub">2.3 Thermal 80mm Roll Receipt Issuance & Barcode Printing</div>

            <div class="toc-item toc-chapter" style="margin-top: 15px;">CHAPTER III &bull; TICKET CATEGORIES & RATE MANAGEMENT</div>
            <div class="toc-item toc-sub">3.1 Dynamic Tariff Adjustments</div>
            <div class="toc-item toc-sub">3.2 Visual Icon Configuration</div>

            <div class="toc-item toc-chapter" style="margin-top: 15px;">CHAPTER IV &bull; REVENUE & AUDIT REPORTING MODULE</div>
            <div class="toc-item toc-sub">4.1 Consolidated Reporting Filters (Daily, Weekly, Monthly, Annual)</div>
            <div class="toc-item toc-sub">4.2 Exporting Standard Government PDF & Excel Reports</div>
            <div class="toc-item toc-sub">4.3 Staff Shift Isolation & Personal Audit Trail</div>

            <div class="toc-item toc-chapter" style="margin-top: 15px;">CHAPTER V &bull; PROFILE SETTINGS & SECURITY HARDENING</div>
            <div class="toc-item toc-sub">5.1 Profile Management & Avatar Uploads</div>
            <div class="toc-item toc-sub">5.2 Bcrypt Password Policies & Multi-layered Defense</div>
        </div>
    </div>

    <div class="page-break"></div>

    <!-- CHAPTER I -->
    <div class="chapter-title">CHAPTER I &bull; INTRODUCTION & ACCESS ARCHITECTURE</div>
    <div class="sub-title">1.1 General System Overview & Purpose</div>
    <p>
        The Tourism POS, Cashier & Portfolio System is an integrated web-based platform designed to manage public recreation admissions, ticket counter operations, financial recording, and institutional accountability. The system is built with an open-source architecture that can be easily customized and deployed by any municipal agency, park authority, or recreational bath venue.
    </p>

    <div class="sub-title">1.2 4-Tier Role-Based Access Control (RBAC)</div>
    <p>The application strictly enforces hierarchical permission levels:</p>
    <table class="guide-table">
        <thead>
            <tr>
                <th style="width: 15%;">Tier</th>
                <th style="width: 25%;">Role</th>
                <th>Access Scope & Operational Responsibilities</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center; font-weight: bold;">Level 1</td>
                <td><strong>Super Admin</strong></td>
                <td>Full system governance: User management (create, update, soft-delete), ticket master catalog, all counter orders, consolidated revenue reports, feedback moderation, and full security audit trails.</td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold;">Level 2</td>
                <td><strong>Administrator</strong></td>
                <td>Operational administration: Ticket management, reviewing all transactions, managing visitor reviews and content, and inspecting government-standard financial reports.</td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold;">Level 3</td>
                <td><strong>Cashier Staff</strong></td>
                <td>Ticket sales operations: Direct Point of Sale (POS) input, customer contact recording, instant thermal receipt roll printing, personal dashboard performance, and self-shift revenue auditing.</td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold;">Level 0</td>
                <td><strong>Public / Visitor</strong></td>
                <td>Self-service booking: Online ticket reservations, e-voucher barcode downloads, feedback submission with automated profanity filtering, and multilingual viewing.</td>
            </tr>
        </tbody>
    </table>

    <!-- CHAPTER II -->
    <div class="chapter-title">CHAPTER II &bull; CASHIER COUNTER OPERATIONS (POINT OF SALE)</div>
    <div class="sub-title">2.1 POS Transaction Workflow</div>
    <ol>
        <li>Staff accesses the <strong>Cashier Panel</strong> or <strong>New POS Order</strong> menu.</li>
        <li>Select customer type: <em>Walk-in Guest (Counter Tamu)</em> with phone/WhatsApp number, or select a registered user account.</li>
        <li>Specify ticket quantities for available categories; bill total and tax are calculated authoritatively on the server.</li>
        <li>Choose payment method: Cash, QRIS, or Bank Transfer.</li>
        <li>For non-cash payments, capture an instant photo proof using the integrated WebRTC camera feed or upload a transfer slip from the device.</li>
        <li>Submit the order to produce a standardized 80mm thermal receipt roll complete with Code 128 barcode and QR Code.</li>
    </ol>

    <div class="sub-title">2.2 Thermal 80mm Roll Receipt Printing</div>
    <p>
        The receipt page features responsive CSS and thermal print media queries (@page 80mm auto) engineered specifically for standard POS receipt printers (Epson, Xprinter, etc.) without awkward A4 paper stretching.
    </p>

    <!-- CHAPTER III -->
    <div class="chapter-title">CHAPTER III &bull; TICKET CATEGORIES & RATE MANAGEMENT</div>
    <p>
        Pricing can be dynamically adjusted based on regional regulations or management decisions. Administrators can add new categories, configure prices, and assign FontAwesome visual icons for rapid cashier identification.
    </p>

    <!-- CHAPTER IV -->
    <div class="chapter-title">CHAPTER IV &bull; REVENUE & AUDIT REPORTING MODULE</div>
    <p>
        The reports section delivers comprehensive summaries across Daily, Weekly, Monthly, and Annual periods. Official documents feature official headers, spelled-out currency words, and tiered institutional approval signatures. Export is supported in both inline browser PDF and Excel format.
    </p>

    <!-- CHAPTER V -->
    <div class="chapter-title">CHAPTER V &bull; PROFILE SETTINGS & SECURITY HARDENING</div>
    <p>
        All operators can manage their profile information, upload avatar photos, and update passwords backed by Bcrypt hashing. The system incorporates Defense-in-Depth security protections, including CSRF tokens, XSS output encoding, parameterized queries, and strict file upload MIME verification.
    </p>
    ';
} else {
    // Indonesian Content (Default)
    $doc_title = 'Buku Panduan Operasional - Pemandian Patemon';
    $stream_filename = 'Buku_Panduan_Pemandian_Patemon.pdf';
    $html_content = '
    <!-- HALAMAN COVER RESMI (INDONESIA) -->
    <div class="cover-container">
        ' . $logo_img_tag . '
        <div class="cover-badge">BUKU PANDUAN PENGGUNA RESMI</div>
        <h1 class="cover-title">MANUAL SISTEM INFORMASI KASIR & WISATA PEMANDIAN PATEMON</h1>
        <div class="cover-subtitle">Tata Kelola Loket Retribusi, Pemesanan Tiket Online & Pelaporan Akuntabilitas Daerah</div>
        <div class="cover-divider"></div>

        <div style="font-size: 11pt; font-weight: 600; color: #334155; margin-bottom: 8px;">
            Pemerintah Kabupaten Jember
        </div>
        <div style="font-size: 10pt; color: #64748b;">
            Dinas Pariwisata dan Kebudayaan (Disparbud) Kabupaten Jember
        </div>

        <div class="cover-meta">
            <strong>Edisi Sistem:</strong> Versi 2.0.0 (Enterprise Open-Source)<br>
            <strong>Waktu Rilis:</strong> September 2026<br>
            <strong>Lokasi Operasional:</strong> Tanggul, Kabupaten Jember, Jawa Timur
        </div>
    </div>

    <div class="page-break"></div>

    <!-- HALAMAN DAFTAR ISI (INDONESIA) -->
    <div>
        <h1 class="toc-heading">DAFTAR ISI</h1>
        <div class="toc-list">
            <div class="toc-item toc-chapter">BAB I &bull; PENDAHULUAN & ARSITEKTUR HAK AKSES</div>
            <div class="toc-item toc-sub">1.1 Gambaran Umum Sistem & Regulasi Pemda</div>
            <div class="toc-item toc-sub">1.2 Hak Akses 4 Tingkat (Super Admin, Admin, Staf, Pengunjung)</div>

            <div class="toc-item toc-chapter" style="margin-top: 15px;">BAB II &bull; OPERASIONAL KASIR LOKET & TRANSAKSI (POS)</div>
            <div class="toc-item toc-sub">2.1 Alur Transaksi Kasir Loket (Pengunjung Langsung & Akun Terdaftar)</div>
            <div class="toc-item toc-sub">2.2 Metode Pembayaran & Pengambilan Foto Kamera WebRTC</div>
            <div class="toc-item toc-sub">2.3 Penerbitan Nota Roll Termal 80mm Berbarcode & Validasi</div>

            <div class="toc-item toc-chapter" style="margin-top: 15px;">BAB III &bull; MANAJEMEN TIKET & TARIF RETRIBUSI</div>
            <div class="toc-item toc-sub">3.1 Penambahan & Penyesuaian Tarif Tiket</div>
            <div class="toc-item toc-sub">3.2 Konfigurasi Ikon Visual Kategori Tiket</div>

            <div class="toc-item toc-chapter" style="margin-top: 15px;">BAB IV &bull; MODUL LAPORAN & AKUNTABILITAS RETRIBUSI</div>
            <div class="toc-item toc-sub">4.1 Filter Laporan Terpadu (Harian, Mingguan, Bulanan, Tahunan)</div>
            <div class="toc-item toc-sub">4.2 Ekspor Dokumen Resmi PDF Standar & Excel</div>
            <div class="toc-item toc-sub">4.3 Isolasi Pelaporan Kinerja Staf Kasir</div>

            <div class="toc-item toc-chapter" style="margin-top: 15px;">BAB V &bull; PENGATURAN PROFIL & KEAMANAN AKUN</div>
            <div class="toc-item toc-sub">5.1 Pembaruan Profil & Unggah Foto Avatar</div>
            <div class="toc-item toc-sub">5.2 Kebijakan Keamanan Kata Sandi Bcrypt & Proteksi Berlapis</div>
        </div>
    </div>

    <div class="page-break"></div>

    <!-- BAB I -->
    <div class="chapter-title">BAB I &bull; PENDAHULUAN & ARSITEKTUR HAK AKSES</div>
    <div class="sub-title">1.1 Gambaran Umum Sistem & Regulasi Pemda</div>
    <p>
        Aplikasi Sistem Kasir & Portofolio Wisata Pemandian Patemon merupakan platform digital terpadu yang mendigitalisasi pencatatan retribusi pengunjung loket, pemesanan tiket masuk wisatawan, serta pelaporan keuangan secara transparan dan akuntabel. Sistem bersifat open-source dan dapat disesuaikan untuk berbagai objek wisata alam, pemandian, dan waterpark.
    </p>

    <div class="sub-title">1.2 Hak Akses 4 Tingkat (Role-Based Access Control)</div>
    <p>Sistem menerapkan pemisahan tugas dan wewenang (RBAC) 4 tingkat standar pemerintahan:</p>
    <table class="guide-table">
        <thead>
            <tr>
                <th style="width: 15%;">Tingkat</th>
                <th style="width: 25%;">Peran</th>
                <th>Cakupan Hak Akses & Tanggung Jawab</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center; font-weight: bold;">Level 1</td>
                <td><strong>Super Admin</strong></td>
                <td>Hak akses tertinggi. Mengelola seluruh pengguna (tambah, edit, hapus), konfigurasi tarif tiket, seluruh transaksi kasir, laporan omzet lengkap, ulasan pengunjung, serta audit sistem.</td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold;">Level 2</td>
                <td><strong>Admin</strong></td>
                <td>Mengelola operasional tiket, melihat seluruh transaksi loket, mengelola data feedback pengunjung, dan mengakses seluruh modul laporan pendapatan.</td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold;">Level 3</td>
                <td><strong>Staf Kasir Loket</strong></td>
                <td>Melayani input transaksi loket (POS), menginput kontak tamu loket, mencetak struk roll termal 80mm, melihat performa loket pribadi pada dashboard staf, dan mencetak laporan transaksi atas namanya sendiri.</td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold;">Level 0</td>
                <td><strong>Pengunjung / Publik</strong></td>
                <td>Melakukan pemesanan tiket masuk secara mandiri, mengunduh nota digital berbarcode, dan mengirim kritik & saran.</td>
            </tr>
        </tbody>
    </table>

    <!-- BAB II -->
    <div class="chapter-title">BAB II &bull; OPERASIONAL KASIR LOKET & TRANSAKSI (POS)</div>
    <div class="sub-title">2.1 Alur Transaksi Kasir Loket</div>
    <ol>
        <li>Petugas kasir loket masuk ke menu <strong>Kasir Loket</strong> pada sidebar.</li>
        <li>Klik tombol <strong>Input Transaksi Baru (POS)</strong> untuk memulai tiket baru.</li>
        <li>Tentukan jenis pembeli: Masukkan nama & nomor telepon pengunjung langsung, atau pilih akun pengguna terdaftar.</li>
        <li>Pilih jumlah lembar tiket untuk tiap kategori (Dewasa, Anak-Anak, dll.). Nilai total harga dihitung otomatis oleh sistem.</li>
        <li>Pilih metode pembayaran (Tunai, QRIS, atau Transfer Bank). Untuk non-tunai, ambil foto struk menggunakan kamera WebRTC langsung atau unggah dari perangkat.</li>
        <li>Klik <strong>Proses & Cetak Nota</strong>. Sistem langsung mencetak struk nota roll termal 80mm berbarcode unik.</li>
    </ol>

    <div class="sub-title">2.2 Metode Pembayaran & Struk Roll Termal 80mm</div>
    <p>
        Nota transaksi dilengkapi dengan kode unik referensi <code>TRX-YYYYMMDD-XXXX</code>, barcode Code-128 standar industri, serta QR code. Ukuran cetak struk telah disesuaikan dengan format kertas roll termal 80mm agar tidak melebar saat dicetak ke printer POS loket.
    </p>

    <!-- BAB III -->
    <div class="chapter-title">BAB III &bull; MANAJEMEN TIKET & TARIF RETRIBUSI</div>
    <p>
        Pengaturan harga tiket disesuaikan dengan regulasi retribusi. Administrator dapat menambahkan jenis tiket baru, mengatur nominal tarif, dan menetapkan ikon visual untuk mempermudah identifikasi staf kasir.
    </p>

    <!-- BAB IV -->
    <div class="chapter-title">BAB IV &bull; MODUL LAPORAN & AKUNTABILITAS RETRIBUSI</div>
    <p>
        Menu Laporan menyediakan konsolidasi rekapitulasi data berdasarkan 4 periode waktu (Harian, Mingguan, Bulanan, dan Tahunan) lengkap dengan kop dinas resmi, nominal terbilang rupiah, serta tanda tangan pengesahan bertingkat.
    </p>

    <!-- BAB V -->
    <div class="chapter-title">BAB V &bull; PENGATURAN PROFIL & KEAMANAN AKUN</div>
    <p>
        Setiap pengguna sistem dapat mengelola profil pribadi, mengunggah foto avatar resmi dalam format JPG/PNG, serta memperbarui kata sandi secara berkala dengan perlindungan enkripsi Bcrypt.
    </p>
    <div class="note-box">
        <strong>Pemberitahuan Keamanan:</strong> Jangan membagikan kata sandi akun Anda kepada pihak lain. Segera hubungi Super Admin apabila terdapat aktivitas mencurigakan pada akun loket Anda.
    </div>
    ';
}

// Siapkan konten HTML buku panduan standar
$html = '
<!DOCTYPE html>
<html lang="' . $lang . '">
<head>
    <meta charset="utf-8">
    <title>' . htmlspecialchars($doc_title) . '</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 20mm 20mm 20mm 20mm;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.6;
            color: #1e293b;
        }
        .page-break {
            page-break-after: always;
        }

        /* Cover Page Styling */
        .cover-container {
            text-align: center;
            padding-top: 60px;
        }
        .cover-badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            font-weight: bold;
            font-size: 9pt;
            padding: 6px 14px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 25px;
        }
        .cover-title {
            font-size: 22pt;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.25;
            margin: 0 0 15px;
            letter-spacing: -0.5px;
        }
        .cover-subtitle {
            font-size: 12pt;
            color: #0284c7;
            font-weight: 600;
            margin-bottom: 40px;
        }
        .cover-divider {
            width: 80px;
            height: 4px;
            background: #0284c7;
            margin: 0 auto 50px;
            border-radius: 2px;
        }
        .cover-meta {
            margin-top: 80px;
            font-size: 9.5pt;
            color: #64748b;
            line-height: 1.8;
            border-top: 1px solid #e2e8f0;
            padding-top: 25px;
        }

        /* Daftar Isi & Bab */
        h1.toc-heading {
            font-size: 16pt;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 8px;
            margin-bottom: 25px;
        }
        .toc-list {
            list-style: none;
            padding-left: 0;
            margin-bottom: 30px;
        }
        .toc-item {
            margin-bottom: 12px;
            font-size: 10pt;
            display: block;
            border-bottom: 1px dotted #cbd5e1;
            padding-bottom: 4px;
        }
        .toc-chapter {
            font-weight: bold;
            color: #0f172a;
        }
        .toc-sub {
            padding-left: 20px;
            color: #475569;
            font-size: 9.5pt;
        }

        /* Chapter Headings */
        .chapter-title {
            font-size: 12.5pt;
            font-weight: bold;
            color: #0369a1;
            background: #f0f9ff;
            border-left: 4px solid #0284c7;
            padding: 8px 12px;
            margin-top: 20px;
            margin-bottom: 14px;
        }
        .sub-title {
            font-size: 10.5pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 16px;
            margin-bottom: 8px;
        }
        p, li {
            font-size: 9.5pt;
            color: #334155;
            text-align: justify;
        }
        ul, ol {
            margin-top: 6px;
            margin-bottom: 14px;
            padding-left: 22px;
        }
        li {
            margin-bottom: 5px;
        }
        .note-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin: 14px 0;
            font-size: 9pt;
            color: #475569;
        }
        table.guide-table {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0;
            font-size: 9pt;
        }
        table.guide-table th, table.guide-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
        }
        table.guide-table th {
            background: #f1f5f9;
            font-weight: bold;
            color: #0f172a;
        }
    </style>
</head>
<body>
    ' . $html_content . '
</body>
</html>
';

// Render PDF menggunakan pustaka Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Buka langsung PDF di browser (inline preview)
$dompdf->stream($stream_filename, ["Attachment" => false]);
exit();
