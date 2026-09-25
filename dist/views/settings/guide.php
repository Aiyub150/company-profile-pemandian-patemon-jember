<?php
/**
 * Buku Panduan Penggunaan Sistem (Operational Manual)
 * Mengikuti Konsep SIM-ASET Guide
 */
require_once __DIR__ . '/../../app/config.php';

// Dapat diakses oleh semua pengguna login (Super Admin, Admin, Staf Kasir)
check_auth([0, 1, 2, 3], route_url('login'));

$active_menu = 'guide';
$page_title = 'Buku Panduan Penggunaan Sistem - Pemandian Patemon';
$page_heading = 'Buku Panduan Operasional Sistem';
$page_subheading = 'Pedoman standar operasional (SOP) kasir, loket, pemesanan tiket, validasi barcode, dan pelaporan akuntabilitas.';

include __DIR__ . '/../../app/layouts/admin_header.php';
?>

<div class="row g-4">
    <!-- Quick Action Card -->
    <div class="col-12">
        <div class="modern-card p-4 d-flex justify-content-between align-items-center flex-wrap gap-3" style="background: linear-gradient(135deg, #0284c7, #0369a1); color: #fff;">
            <div>
                <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-book-bookmark me-2"></i> Panduan Pengoperasian Wisata Pemandian Patemon</h4>
                <p class="text-white-50 mb-0">Dokumentasi alur kerja terpadu untuk pengelola, staf loket, dan pengunjung.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= route_url('guide_preview_pdf') ?>" class="btn btn-light text-primary fw-bold">
                    <i class="fa-solid fa-file-pdf me-1"></i> Buka Dokumen PDF Panduan
                </a>
            </div>
        </div>
    </div>

    <!-- Bab 1: Alur Pemesanan Tiket Online Pengunjung -->
    <div class="col-12 col-lg-6">
        <div class="modern-card h-100">
            <div class="modern-card-header">
                <span class="fw-bold" style="color: #0f172a;"><i class="fa-solid fa-globe text-primary me-2"></i> 1. Alur Pemesanan Tiket Online (Pengunjung)</span>
            </div>
            <div class="p-4">
                <ol class="ps-3 mb-0" style="line-height: 1.8; color: #334155; font-size: 0.915rem;">
                    <li>Pengunjung membuka website dan memilih tombol <strong>Pesan Tiket</strong>.</li>
                    <li>Pengunjung menentukan jumlah lembar tiket untuk tiap kategori (Dewasa, Anak-Anak, Lansia, dll.).</li>
                    <li>Sistem secara otomatis menghitung total biaya di sisi server (*server-side authoritative calculation*).</li>
                    <li>Pengunjung memilih metode pembayaran:
                        <ul>
                            <li><strong>Scan QRIS</strong>: Scan gambar QRIS resmi dari e-wallet/mobile banking lalu unggah bukti transfer.</li>
                            <li><strong>Transfer Bank</strong>: Salin nomor rekening resmi, lakukan transfer, lalu unggah struk mutasi.</li>
                            <li><strong>Bayar di Loket</strong>: Bayar tunai setibanya di loket pintu masuk pemandian.</li>
                        </ul>
                    </li>
                    <li>Setelah form dikirim, sistem otomatis menerbitkan <strong>Nota Digital Ber-Barcode</strong> unik.</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Bab 2: SOP Staf Kasir & Loket POS -->
    <div class="col-12 col-lg-6">
        <div class="modern-card h-100">
            <div class="modern-card-header">
                <span class="fw-bold" style="color: #0f172a;"><i class="fa-solid fa-cash-register text-success me-2"></i> 2. SOP Staf Kasir Loket (Input POS)</span>
            </div>
            <div class="p-4">
                <ol class="ps-3 mb-0" style="line-height: 1.8; color: #334155; font-size: 0.915rem;">
                    <li>Staf masuk ke menu <strong>Panel Kasir</strong> atau <strong>Input POS Baru</strong>.</li>
                    <li>Pilih nama pembeli atau masukkan kategori pengunjung rombongan/umum.</li>
                    <li>Input kuantitas lembar tiket yang dibeli secara langsung di tempat.</li>
                    <li>Terima uang tunai atau verifikasi transfer/QRIS pengunjung secara teliti.</li>
                    <li>Klik <strong>Simpan & Cetak Struk Tiket</strong> untuk mencetak struk masuk dengan barcode.</li>
                    <li>Serahkan struk kepada pengunjung sebagai tiket akses kolam pemandian.</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Bab 3: Validasi Barcode di Pintu Masuk -->
    <div class="col-12 col-lg-6">
        <div class="modern-card h-100">
            <div class="modern-card-header">
                <span class="fw-bold" style="color: #0f172a;"><i class="fa-solid fa-qrcode text-warning me-2"></i> 3. Prosedur Validasi Barcode di Pintu Masuk</span>
            </div>
            <div class="p-4">
                <ol class="ps-3 mb-0" style="line-height: 1.8; color: #334155; font-size: 0.915rem;">
                    <li>Petugas loket membuka tab <strong>Scan Barcode Nota</strong> pada menu Transaksi Kasir.</li>
                    <li>Arahkan kamera perangkat atau barcode scanner ke barcode pada struk pengunjung.</li>
                    <li>Sistem langsung mencocokkan kode unik transaksi (contoh: <code>TRX-20260924-0001</code>).</li>
                    <li>Jika status pembayaran <strong>Done</strong>, izinkan pengunjung masuk ke area kolam.</li>
                    <li>Jika status masih <strong>Not Yet (Belum Lunas)</strong>, mintakan pelunasan tunai di loket.</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Bab 4: Pelaporan Akuntabilitas Standar Pemkab Jember -->
    <div class="col-12 col-lg-6">
        <div class="modern-card h-100">
            <div class="modern-card-header">
                <span class="fw-bold" style="color: #0f172a;"><i class="fa-solid fa-file-invoice-dollar text-purple me-2"></i> 4. Laporan Retribusi Standar Pemkab Jember</span>
            </div>
            <div class="p-4">
                <ol class="ps-3 mb-0" style="line-height: 1.8; color: #334155; font-size: 0.915rem;">
                    <li>Administrator mengakses menu <strong>Laporan Omzet</strong> (Harian, Bulanan, Tahunan).</li>
                    <li>Tentukan filter tanggal atau periode bulan yang ingin direkapitulasi.</li>
                    <li>Klik <strong>Pratinjau Dokumen Pemkab</strong> untuk melihat format resmi naskah dinas.</li>
                    <li>Periksa kesesuaian rincian lembar tiket, akumulasi nominal pendapatan, dan tanda tangan pimpinan.</li>
                    <li>Cetak dokumen pada kertas ukuran A4 atau ekspor ke format PDF untuk arsip resmi dinas.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../app/layouts/admin_footer.php';
?>
