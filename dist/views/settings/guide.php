<?php
/**
 * Buku Panduan Penggunaan Sistem (Operational Manual)
 * Pemandian Patemon - Pemkab Jember (Universal Open-Source Edition)
 * Mendukung Dwibahasa: Bahasa Indonesia & English (Feedback-8 Poin 6)
 */
require_once __DIR__ . '/../../app/config.php';

// Dapat diakses oleh semua pengguna login (Super Admin, Admin, Staf Kasir, Pengunjung)
check_auth([0, 1, 2, 3], route_url('login'));

$active_menu = 'guide';
$page_title = 'Buku Panduan Penggunaan Sistem - Pemandian Patemon';
$page_heading = 'Buku Panduan Operasional Sistem';
$page_subheading = 'Pedoman standar operasional (SOP) kasir, loket POS, validasi barcode, pelaporan akuntabilitas, dan tata kelola destinasi.';

$cur_lang = trim($_GET['lang'] ?? '');
if (!in_array($cur_lang, ['id', 'en'], true)) {
    $cur_lang = 'id';
}

include __DIR__ . '/../../app/layouts/admin_header.php';
?>

<div class="row g-4">
    <!-- Quick Action Card with Language Switcher -->
    <div class="col-12">
        <div class="modern-card p-4 d-flex justify-content-between align-items-center flex-wrap gap-3" style="background: linear-gradient(135deg, #0284c7, #0369a1); color: #fff; border-radius: 16px;">
            <div>
                <h4 class="fw-bold text-white mb-1">
                    <i class="fa-solid fa-book-bookmark me-2"></i> 
                    <span id="guideBannerTitle">Panduan Pengoperasian Wisata Pemandian Patemon</span>
                </h4>
                <p class="text-white-50 mb-0" id="guideBannerSub">
                    Dokumentasi alur kerja terpadu untuk pengelola, staf loket, dan pengunjung.
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-warning text-dark fw-bold" onclick="showGuideLangPickerModal()">
                    <i class="fa-solid fa-language me-1"></i> <span id="btnLangPickerLabel">Pilih Bahasa / Language</span>
                </button>
                <a id="btnPdfPreview" href="<?= route_url('guide_preview_pdf', ['lang' => $cur_lang]) ?>" class="btn btn-light text-primary fw-bold" target="_blank">
                    <i class="fa-solid fa-file-pdf me-1"></i> <span id="btnPdfLabel">Buka Dokumen PDF Panduan</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Container Bahasa Indonesia (Default) -->
    <div id="guideSectionID" class="col-12">
        <div class="row g-4">
            <!-- Bab 1: Alur Pemesanan Tiket Online Pengunjung -->
            <div class="col-12 col-lg-6">
                <div class="modern-card h-100">
                    <div class="modern-card-header">
                        <span class="fw-bold text-dark"><i class="fa-solid fa-globe text-primary me-2"></i> 1. Alur Pemesanan Tiket Online (Pengunjung)</span>
                    </div>
                    <div class="p-4">
                        <ol class="ps-3 mb-0" style="line-height: 1.8; color: #334155; font-size: 0.915rem;">
                            <li>Pengunjung membuka website dan memilih tombol <strong>Pesan Tiket</strong>.</li>
                            <li>Tentukan kuantitas lembar tiket untuk tiap kategori (Dewasa, Anak-Anak, dll.).</li>
                            <li>Sistem otomatis menghitung total biaya di sisi server (*server-side authoritative calculation*).</li>
                            <li>Pilih metode pembayaran yang tersedia:
                                <ul>
                                    <li><strong>Scan QRIS</strong>: Pindai gambar QRIS resmi dari e-wallet/m-banking lalu unggah bukti transfer.</li>
                                    <li><strong>Transfer Bank</strong>: Salin nomor rekening resmi, lakukan transfer, lalu unggah struk mutasi.</li>
                                    <li><strong>Bayar di Loket</strong>: Bayar tunai setibanya di loket pintu masuk wisata.</li>
                                </ul>
                            </li>
                            <li>Setelah formulir dikirim, sistem menerbitkan <strong>Nota Digital Ber-Barcode</strong> unik untuk verifikasi masuk.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Bab 2: SOP Staf Kasir & Loket POS -->
            <div class="col-12 col-lg-6">
                <div class="modern-card h-100">
                    <div class="modern-card-header">
                        <span class="fw-bold text-dark"><i class="fa-solid fa-cash-register text-success me-2"></i> 2. SOP Staf Kasir Loket (Input POS)</span>
                    </div>
                    <div class="p-4">
                        <ol class="ps-3 mb-0" style="line-height: 1.8; color: #334155; font-size: 0.915rem;">
                            <li>Staf masuk ke menu <strong>Panel Kasir</strong> atau <strong>Input POS Baru</strong>.</li>
                            <li>Pilih jenis pelanggan: <em>Pengunjung Langsung (Tamu Loket)</em> dengan nama & nomor telepon/WhatsApp yang bisa dihubungi, atau <em>Akun Terdaftar</em>.</li>
                            <li>Input kuantitas lembar tiket yang dibeli secara langsung di tempat.</li>
                            <li>Pilih metode pembayaran (Tunai / QRIS / Transfer). Untuk non-tunai, ambil foto bukti struk menggunakan kamera WebRTC langsung atau unggah dari perangkat.</li>
                            <li>Ketik nominal uang pembayaran jika tunai; sistem otomatis menghitung uang kembalian.</li>
                            <li>Klik <strong>Proses & Cetak Nota</strong> untuk mencetak struk roll termal 80mm dengan barcode dan QR Code.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Bab 3: Validasi Barcode di Pintu Masuk -->
            <div class="col-12 col-lg-6">
                <div class="modern-card h-100">
                    <div class="modern-card-header">
                        <span class="fw-bold text-dark"><i class="fa-solid fa-qrcode text-warning me-2"></i> 3. Prosedur Validasi Barcode di Pintu Masuk</span>
                    </div>
                    <div class="p-4">
                        <ol class="ps-3 mb-0" style="line-height: 1.8; color: #334155; font-size: 0.915rem;">
                            <li>Petugas membuka tab <strong>Scan Barcode Nota</strong> pada menu Transaksi Kasir.</li>
                            <li>Arahkan kamera scanner atau barcode scanner fisik ke barcode struk pengunjung.</li>
                            <li>Sistem langsung mencocokkan kode unik transaksi (contoh: <code>TRX-20260928-0001</code>).</li>
                            <li>Jika status pembayaran <strong>Lunas (Done)</strong>, izinkan pengunjung masuk ke area kolam.</li>
                            <li>Jika status masih <strong>Pending (Belum Lunas)</strong>, mintakan pelunasan di loket.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Bab 4: Pelaporan Akuntabilitas Standar Kedinasan -->
            <div class="col-12 col-lg-6">
                <div class="modern-card h-100">
                    <div class="modern-card-header">
                        <span class="fw-bold text-dark"><i class="fa-solid fa-file-invoice-dollar text-purple me-2"></i> 4. Pelaporan Retribusi & Akuntabilitas Pendapatan</span>
                    </div>
                    <div class="p-4">
                        <ol class="ps-3 mb-0" style="line-height: 1.8; color: #334155; font-size: 0.915rem;">
                            <li>Administrator membuka menu <strong>Laporan Omzet</strong> (Harian, Bulanan, atau Tahunan).</li>
                            <li>Tentukan filter rentang tanggal atau bulan rekapitulasi.</li>
                            <li>Klik <strong>Pratinjau Dokumen Cetak</strong> untuk melihat format resmi naskah dinas dengan kop resmi, angka terbilang, dan tanda tangan bertingkat.</li>
                            <li>Dokumen dapat dicetak ke printer atau diekspor ke format PDF dan Excel untuk arsip akuntabilitas.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Container English Version -->
    <div id="guideSectionEN" class="col-12" style="display: none;">
        <div class="row g-4">
            <!-- Chapter 1: Online Ticket Booking Flow -->
            <div class="col-12 col-lg-6">
                <div class="modern-card h-100">
                    <div class="modern-card-header">
                        <span class="fw-bold text-dark"><i class="fa-solid fa-globe text-primary me-2"></i> 1. Online Ticket Reservation Flow (Visitors)</span>
                    </div>
                    <div class="p-4">
                        <ol class="ps-3 mb-0" style="line-height: 1.8; color: #334155; font-size: 0.915rem;">
                            <li>Visitors access the portal and click the <strong>Book Tickets</strong> button.</li>
                            <li>Select the quantity of tickets for each desired category (Adults, Children, etc.).</li>
                            <li>The system calculates the authoritative bill total on the server side.</li>
                            <li>Select a payment method:
                                <ul>
                                    <li><strong>QRIS Scan</strong>: Scan official static/dynamic QRIS with e-wallet or mobile banking, then upload transfer proof.</li>
                                    <li><strong>Bank Transfer</strong>: Copy the official bank account number, transfer funds, and upload the bank receipt.</li>
                                    <li><strong>Pay at Counter</strong>: Pay with cash upon arrival at the entrance ticket booth.</li>
                                </ul>
                            </li>
                            <li>Upon form submission, the system issues a <strong>Digital Receipt with Unique Barcode & QR Code</strong> for entrance validation.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Chapter 2: POS Cashier Standard Operating Procedure -->
            <div class="col-12 col-lg-6">
                <div class="modern-card h-100">
                    <div class="modern-card-header">
                        <span class="fw-bold text-dark"><i class="fa-solid fa-cash-register text-success me-2"></i> 2. Cashier Counter SOP (Point of Sale)</span>
                    </div>
                    <div class="p-4">
                        <ol class="ps-3 mb-0" style="line-height: 1.8; color: #334155; font-size: 0.915rem;">
                            <li>Cashier staff opens the <strong>Cashier Panel</strong> or <strong>New POS Order</strong> menu.</li>
                            <li>Select customer type: <em>Walk-in Guest (Counter Tamu)</em> with contact phone number for digital receipt, or <em>Registered User Account</em>.</li>
                            <li>Enter ticket quantities using the interactive stepper counters.</li>
                            <li>Choose payment method (Cash / QRIS / Transfer). For non-cash methods, capture instant photo proof using the integrated WebRTC camera or file upload.</li>
                            <li>Input cash payment amount; the system calculates exact change in real time.</li>
                            <li>Click <strong>Process & Print Receipt</strong> to output an authentic 80mm thermal receipt roll with barcodes.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Chapter 3: Barcode Verification at Entrance -->
            <div class="col-12 col-lg-6">
                <div class="modern-card h-100">
                    <div class="modern-card-header">
                        <span class="fw-bold text-dark"><i class="fa-solid fa-qrcode text-warning me-2"></i> 3. Barcode Verification at Entrance Gate</span>
                    </div>
                    <div class="p-4">
                        <ol class="ps-3 mb-0" style="line-height: 1.8; color: #334155; font-size: 0.915rem;">
                            <li>Gate staff opens the <strong>Scan Barcode Nota</strong> tab in the Transaction menu.</li>
                            <li>Point the device camera or laser barcode scanner at the visitor's receipt barcode.</li>
                            <li>The system matches the unique transaction reference (e.g. <code>TRX-20260928-0001</code>).</li>
                            <li>If status is <strong>Paid (Done)</strong>, grant admission to the pools and facilities.</li>
                            <li>If status is <strong>Pending</strong>, instruct the visitor to complete payment at the counter.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Chapter 4: Financial Accountability & Audit Reporting -->
            <div class="col-12 col-lg-6">
                <div class="modern-card h-100">
                    <div class="modern-card-header">
                        <span class="fw-bold text-dark"><i class="fa-solid fa-file-invoice-dollar text-purple me-2"></i> 4. Revenue Accountability & Audit Reporting</span>
                    </div>
                    <div class="p-4">
                        <ol class="ps-3 mb-0" style="line-height: 1.8; color: #334155; font-size: 0.915rem;">
                            <li>Administrators open the <strong>Revenue Reports</strong> menu (Daily, Monthly, or Annual).</li>
                            <li>Filter by date range, month, or transaction status.</li>
                            <li>Click <strong>Print Official Document</strong> to review the standardized government report layout featuring letterhead, spelled-out currency words, and tiered sign-offs.</li>
                            <li>Print directly or export to PDF/Excel for permanent institutional records.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showGuideLangPickerModal() {
    Swal.fire({
        title: 'Pilih Bahasa Panduan / Select Manual Language',
        text: 'Silakan pilih versi bahasa buku panduan yang ingin Anda baca:',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '🇮🇩 Bahasa Indonesia',
        cancelButtonText: '🇬🇧 English Version',
        confirmButtonColor: '#0284c7',
        cancelButtonColor: '#0f172a',
        reverseButtons: true,
        allowOutsideClick: true
    }).then((result) => {
        if (result.isConfirmed) {
            setGuideLanguage('id');
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            setGuideLanguage('en');
        }
    });
}

function setGuideLanguage(lang) {
    lang = (lang === 'en') ? 'en' : 'id';
    localStorage.setItem('patemon_guide_lang', lang);

    const secID = document.getElementById('guideSectionID');
    const secEN = document.getElementById('guideSectionEN');
    const bannerTitle = document.getElementById('guideBannerTitle');
    const bannerSub = document.getElementById('guideBannerSub');
    const btnPdf = document.getElementById('btnPdfPreview');
    const btnPdfLabel = document.getElementById('btnPdfLabel');

    if (lang === 'en') {
        if (secID) secID.style.display = 'none';
        if (secEN) secEN.style.display = 'block';
        if (bannerTitle) bannerTitle.textContent = 'Patemon Tourism System Operations & User Manual';
        if (bannerSub) bannerSub.textContent = 'Standardized operating procedures (SOP) for administrators, cashiers, and visitors.';
        if (btnPdf) btnPdf.href = '<?= route_url('guide_preview_pdf') ?>?lang=en';
        if (btnPdfLabel) btnPdfLabel.textContent = 'Open English PDF Document';
    } else {
        if (secID) secID.style.display = 'block';
        if (secEN) secEN.style.display = 'none';
        if (bannerTitle) bannerTitle.textContent = 'Panduan Pengoperasian Wisata Pemandian Patemon';
        if (bannerSub) bannerSub.textContent = 'Dokumentasi alur kerja terpadu untuk pengelola, staf loket, dan pengunjung.';
        if (btnPdf) btnPdf.href = '<?= route_url('guide_preview_pdf') ?>?lang=id';
        if (btnPdfLabel) btnPdfLabel.textContent = 'Buka Dokumen PDF Panduan';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Check URL param first, then localStorage
    const urlParams = new URLSearchParams(window.location.search);
    const paramLang = urlParams.get('lang');
    const savedLang = paramLang || localStorage.getItem('patemon_guide_lang');

    if (savedLang) {
        setGuideLanguage(savedLang);
    } else {
        // First time opening guide: show SweetAlert language picker modal
        showGuideLangPickerModal();
    }
});
</script>

<?php
include __DIR__ . '/../../app/layouts/admin_footer.php';
?>
