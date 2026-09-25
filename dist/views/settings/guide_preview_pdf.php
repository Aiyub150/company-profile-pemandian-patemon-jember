<?php
/**
 * Buku Panduan Pengguna Sistem (Manual Book Format Standar SIM-ASET)
 * Pemandian Patemon - Pemkab Jember
 * Format standar buku manual dengan Cover, Daftar Isi, Bab Pembahasan (Feedback-2 Poin 6)
 * Membuka langsung PDF preview inline di browser (Feedback-2 Poin 6 & 7)
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

check_auth([0, 1, 2, 3], route_url('login'));

// Load logo panjang Pemandian Patemon untuk halaman cover (JPEG untuk kompatibilitas Dompdf tanpa ekstensi GD)
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

// Siapkan konten HTML buku panduan standar
$html = '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Buku Panduan Operasional - Pemandian Patemon</title>
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
            font-size: 24pt;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.25;
            margin: 0 0 15px;
            letter-spacing: -0.5px;
        }
        .cover-subtitle {
            font-size: 13pt;
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
            font-size: 13pt;
            font-weight: bold;
            color: #0369a1;
            background: #f0f9ff;
            border-left: 4px solid #0284c7;
            padding: 8px 12px;
            margin-top: 20px;
            margin-bottom: 14px;
        }
        .sub-title {
            font-size: 11pt;
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

    <!-- HALAMAN COVER RESMI -->
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
            <strong>Edisi Sistem:</strong> Versi 2.0.0 (Enterprise)<br>
            <strong>Waktu Rilis:</strong> September 2026<br>
            <strong>Lokasi Operasional:</strong> Tanggul, Kabupaten Jember, Jawa Timur
        </div>
    </div>

    <div class="page-break"></div>

    <!-- HALAMAN DAFTAR ISI -->
    <div>
        <h1 class="toc-heading">DAFTAR ISI</h1>
        <div class="toc-list">
            <div class="toc-item toc-chapter">BAB I &bull; PENDAHULUAN & ARSITEKTUR HAK AKSES</div>
            <div class="toc-item toc-sub">1.1 Gambaran Umum Sistem & Regulasi Pemda</div>
            <div class="toc-item toc-sub">1.2 Hak Akses 4 Tingkat (Super Admin, Admin, Staf, Pengunjung)</div>

            <div class="toc-item toc-chapter" style="margin-top: 15px;">BAB II &bull; OPERASIONAL KASIR LOKET & TRANSAKSI (POS)</div>
            <div class="toc-item toc-sub">2.1 Alur Transaksi Kasir Loket</div>
            <div class="toc-item toc-sub">2.2 Metode Pembayaran (Tunai, QRIS Dinamis, Transfer Bank)</div>
            <div class="toc-item toc-sub">2.3 Penerbitan Nota Struk Berbarcode & Validasi</div>

            <div class="toc-item toc-chapter" style="margin-top: 15px;">BAB III &bull; MANAJEMEN TIKET & TARIF RETRIBUSI</div>
            <div class="toc-item toc-sub">3.1 Penambahan & Penyesuaian Tarif Tiket</div>
            <div class="toc-item toc-sub">3.2 Konfigurasi Ikon Visual Kategori Tiket</div>

            <div class="toc-item toc-chapter" style="margin-top: 15px;">BAB IV &bull; MODUL LAPORAN & AKUNTABILITAS RETRIBUSI</div>
            <div class="toc-item toc-sub">4.1 Filter Laporan Terpadu (Harian, Mingguan, Bulanan, Tahunan)</div>
            <div class="toc-item toc-sub">4.2 Ekspor Dokumen Resmi PDF Standar & Excel</div>
            <div class="toc-item toc-sub">4.3 Isolasi Pelaporan Kinerja Staf Kasir</div>

            <div class="toc-item toc-chapter" style="margin-top: 15px;">BAB V &bull; PENGATURAN PROFIL & KEAMANAN AKUN</div>
            <div class="toc-item toc-sub">5.1 Pembaruan Profil & Unggah Foto Avatar</div>
            <div class="toc-item toc-sub">5.2 Kebijakan Keamanan Kata Sandi & Proteksi Akun</div>
        </div>
    </div>

    <div class="page-break"></div>

    <!-- BAB I -->
    <div class="chapter-title">BAB I &bull; PENDAHULUAN & ARSITEKTUR HAK AKSES</div>
    <div class="sub-title">1.1 Gambaran Umum Sistem & Regulasi Pemda</div>
    <p>
        Aplikasi Sistem Kasir & Portofolio Wisata Pemandian Patemon merupakan platform digital terpadu milik Pemerintah Kabupaten Jember yang dikelola oleh Dinas Pariwisata dan Kebudayaan. Sistem ini berfungsi mendigitalisasi pencatatan retribusi pengunjung loket, pemesanan tiket masuk wisatawan, serta pelaporan keuangan daerah secara transparan dan akuntabel.
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
                <td>Melayani input transaksi loket (POS), mencetak struk nota pengunjung, melihat performa loket pribadi pada dashboard staf, dan mencetak laporan transaksi atas namanya sendiri.</td>
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
        <li>Tentukan nama pemesan atau gunakan identitas *Pengunjung Loket*.</li>
        <li>Pilih jumlah lembar tiket untuk tiap kategori (Dewasa, Anak-Anak, Lansia, dll.). Nilai total harga dihitung otomatis oleh sistem.</li>
        <li>Pilih metode pembayaran (Tunai, QRIS, atau Transfer Bank).</li>
        <li>Klik <strong>Simpan Transaksi</strong>. Sistem langsung menerbitkan nota struk pembayaran.</li>
    </ol>

    <div class="sub-title">2.2 Metode Pembayaran</div>
    <ul>
        <li><strong>Tunai (Cash):</strong> Pembayaran tunai di tempat. Status transaksi langsung terverifikasi *done* (Lunas).</li>
        <li><strong>Scan QRIS:</strong> Menggunakan QR code standar BI yang didukung seluruh aplikasi mobile banking dan e-wallet.</li>
        <li><strong>Transfer Bank:</strong> Ditujukan ke rekening kas resmi daerah Pemerintah Kabupaten Jember.</li>
    </ul>

    <div class="sub-title">2.3 Pencetakan Struk Nota</div>
    <p>
        Nota transaksi dilengkapi dengan kode unik referensi <code>TRX-YYYYMMDD-XXXX</code> serta barcode Code-128 standar industri. Barcode dapat dipindai oleh petugas penjaga pintu kolam untuk memvalidasi keabsahan tiket.
    </p>

    <!-- BAB III -->
    <div class="chapter-title">BAB III &bull; MANAJEMEN TIKET & TARIF RETRIBUSI</div>
    <p>
        Pengaturan harga tiket disesuaikan dengan Peraturan Daerah (Perda) Retribusi Wisata Kabupaten Jember. Administrator dapat menambahkan jenis tiket baru, mengatur nominal tarif, dan menetapkan ikon visual untuk mempermudah identifikasi staf kasir.
    </p>

    <!-- BAB IV -->
    <div class="chapter-title">BAB IV &bull; MODUL LAPORAN & AKUNTABILITAS RETRIBUSI</div>
    <div class="sub-title">4.1 Filter Laporan Terpadu</div>
    <p>
        Menu Laporan menyediakan satu halaman konsolidasi yang memuat rekapitulasi data berdasarkan 4 periode waktu:
    </p>
    <ul>
        <li><strong>Harian:</strong> Rekap penjualan tiket pada tanggal spesifik.</li>
        <li><strong>Mingguan:</strong> Rekap kinerja operasional dalam rentang 7 hari kalender.</li>
        <li><strong>Bulanan:</strong> Laporan bulanan resmi untuk keperluan buku kas penerimaan.</li>
        <li><strong>Tahunan:</strong> Ringkasan akumulasi penerimaan retribusi selama satu tahun anggaran.</li>
    </ul>

    <div class="sub-title">4.2 Ekspor Dokumen Resmi PDF & Excel</div>
    <p>
        Tombol <strong>PDF</strong> pada halaman laporan akan merender dokumen cetak resmi dan membukanya langsung di browser tanpa perlu mengunduh file sementara. Tombol <strong>Excel</strong> memungkinkan ekspor cepat ke spreadsheet untuk audit internal.
    </p>

    <div class="sub-title">4.3 Isolasi Pelaporan Staf Kasir</div>
    <p>
        Bagi pengguna berlevel Staf Kasir, sistem secara otomatis mengunci cakupan laporan hanya pada transaksi yang dilayani oleh staf tersebut. Ini menjamin akuntabilitas setoran kasir pergantian shift.
    </p>

    <!-- BAB V -->
    <div class="chapter-title">BAB V &bull; PENGATURAN PROFIL & KEAMANAN AKUN</div>
    <p>
        Setiap pengguna sistem dapat mengelola profil pribadi, mengunggah foto avatar resmi dalam format JPG/PNG, serta memperbarui kata sandi secara berkala dengan perlindungan enkripsi Bcrypt.
    </p>
    <div class="note-box">
        <strong>Pemberitahuan Keamanan:</strong> Jangan membagikan kata sandi akun Anda kepada pihak lain. Segera hubungi Super Admin apabila terdapat aktivitas mencurigakan pada akun loket Anda.
    </div>

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

// Buka langsung PDF di browser (inline preview, Feedback-2 Poin 6)
$dompdf->stream("Buku_Panduan_Pemandian_Patemon.pdf", ["Attachment" => false]);
exit();
