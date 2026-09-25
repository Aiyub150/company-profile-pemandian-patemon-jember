<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config.php';

use Picqer\Barcode\BarcodeGeneratorSVG;

if (!isset($_SESSION['id_user'])) {
    header('Location: ' . route_url('login'));
    exit();
}

$id_user = (int)$_SESSION['id_user'];
$nama = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Pelanggan';
$id_transaksi = (int)($_GET['id_transaksi'] ?? ($_GET['id'] ?? ($_SESSION['id_transaksi'] ?? 0)));

if ($id_transaksi <= 0) {
    header('Location: ' . route_url('home'));
    exit();
}

// Ambil transaksi menggunakan Prepared Statement (mendukung semua role level: 1=Super Admin, 2=Admin, 3=Kasir, 0=Pengunjung)
$user_lvl = (int)($_SESSION['level'] ?? 0);
$session_tx_id = (int)($_SESSION['id_transaksi'] ?? 0);
$is_own_session = ($session_tx_id > 0 && $session_tx_id === $id_transaksi) ? 1 : 0;

$stmt = $conn->prepare("SELECT transaksi.*, users.nama as user_nama, users.no_telepon, users.email FROM transaksi LEFT JOIN users ON transaksi.id_user = users.id_user WHERE id_transaksi = ? AND (transaksi.id_user = ? OR ? = 1 OR ? = 2 OR ? = 3 OR ? = 1) LIMIT 1");
$stmt->bind_param("iiiiii", $id_transaksi, $id_user, $user_lvl, $user_lvl, $user_lvl, $is_own_session);
$stmt->execute();
$transaksi_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaksi_data) {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h2>Nota Transaksi Tidak Ditemukan</h2><p>Anda tidak memiliki akses ke transaksi ini atau nomor ID salah.</p><a href='" . route_url('home') . "' style='color: #0284c7; text-decoration: none; font-weight: bold;'>Kembali ke Beranda</a></div>");
}

// Standardized Transaction Code
$kode_transaksi = format_kode_transaksi($id_transaksi, $transaksi_data['tgl_pemesanan']);

// Ambil seluruh rincian tiket yang dipesan
$stmt_d = $conn->prepare("SELECT dt.jenis_tiket, dt.quantity, dt.sub_total, t.ikon FROM detail_transaksi dt LEFT JOIN tiket t ON dt.jenis_tiket = t.nama_tiket WHERE dt.id_transaksi = ?");
$stmt_d->bind_param("i", $id_transaksi);
$stmt_d->execute();
$res_items = $stmt_d->get_result();

$items = [];
$total_tiket = 0;
while ($row = $res_items->fetch_assoc()) {
    $items[] = $row;
    $total_tiket += (int)$row['quantity'];
}
$stmt_d->close();

// Generate Barcode 1D menggunakan BarcodeGeneratorSVG (vector SVG presisi tinggi)
$barcodeSVG = '';
try {
    $generator = new BarcodeGeneratorSVG();
    $barcodeRaw = $generator->getBarcode($kode_transaksi, $generator::TYPE_CODE_128, 2, 60);
    $barcodeRaw = preg_replace('/<\?xml.*?\?>/s', '', $barcodeRaw);
    $barcodeSVG = preg_replace('/<!DOCTYPE.*?>/s', '', $barcodeRaw);
} catch (Throwable $e) {
    $barcodeSVG = "<div style='letter-spacing: 4px; font-weight: bold; padding: 12px 0;'>*" . e($kode_transaksi) . "*</div>";
}

// Logo base64 encode untuk memastikan 100% offline support & bebas CORS saat ditangkap html2canvas
$logo_file = __DIR__ . '/../../../public/img/icon.png';
$logo_src = '';
if (file_exists($logo_file)) {
    $logo_src = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_file));
} else {
    $logo_src = public_url('img/icon.png');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Tiket <?= e($kode_transaksi) ?> - Pemandian Patemon</title>
    <link rel="icon" type="image/x-icon" href="<?= public_url('img/icon.png') ?>" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= public_url('css/modern-theme.css') ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="<?= public_url('js/patemon-i18n.js') ?>"></script>
    <script>
        (function() {
            var theme = localStorage.getItem('patemon_theme') || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.add('theme-dark');
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            } else {
                document.documentElement.classList.remove('theme-dark');
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>
    <style>
        body {
            background-color: var(--bg-body, #f1f5f9);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #0f172a;
        }

        .ticket-voucher {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid #e2e8f0;
            position: relative;
            box-sizing: border-box;
        }

        .voucher-header {
            background: linear-gradient(135deg, #0284c7, #0ea5e9);
            color: #ffffff;
            padding: 1.75rem 1.5rem 1.4rem;
            text-align: center;
            position: relative;
        }

        .voucher-logo {
            width: 52px;
            height: 52px;
            object-fit: contain;
            margin: 0 auto 0.6rem auto;
            display: block;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.15));
        }

        .voucher-header h1 {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            margin: 0;
            text-transform: uppercase;
        }

        .voucher-header p {
            font-size: 0.75rem;
            opacity: 0.92;
            margin: 0.25rem 0 0;
            letter-spacing: 0.01em;
        }

        .voucher-body {
            padding: 1.75rem 1.5rem;
            background: #ffffff;
        }

        /* Barcode Section Presisi Tengah & Responsif */
        .barcode-section {
            text-align: center;
            margin-bottom: 1.25rem;
            padding-bottom: 1.25rem;
            border-bottom: 2px dashed #e2e8f0;
        }

        .barcode-label {
            font-size: 0.725rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.25rem;
        }

        .barcode-code {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.04em;
            font-family: 'Courier New', Courier, monospace, sans-serif;
            margin-bottom: 0.65rem;
        }

        .dual-code-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            margin: 0.5rem auto 0.75rem auto;
        }

        .qr-code-box {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 8px;
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
            margin: 0 auto;
        }

        .qr-code-box img, .qr-code-box canvas {
            display: block;
            margin: 0 auto;
        }

        .barcode-wrapper {
            width: 100%;
            max-width: 320px;
            margin: 0 auto;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #ffffff;
            padding: 6px 12px;
            box-sizing: border-box;
            border-radius: 8px;
        }

        .barcode-wrapper svg {
            width: 100% !important;
            height: 52px !important;
            display: block;
            margin: 0 auto;
        }

        .barcode-note {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.45rem;
            line-height: 1.35;
        }

        .perforated-divider {
            position: relative;
            border-top: 2px dashed #cbd5e1;
            margin: 1.5rem -1.5rem;
        }

        .perforated-divider::before, .perforated-divider::after {
            content: '';
            position: absolute;
            top: -10px;
            width: 20px;
            height: 20px;
            background-color: #f1f5f9;
            border-radius: 50%;
            transition: background-color 0.2s ease;
        }

        .perforated-divider::before {
            left: -10px;
        }

        .perforated-divider::after {
            right: -10px;
        }

        body.theme-dark .perforated-divider::before,
        body.theme-dark .perforated-divider::after,
        html.theme-dark .perforated-divider::before,
        html.theme-dark .perforated-divider::after {
            background-color: #0b1120 !important;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            font-size: 0.925rem;
            color: #0f172a !important;
        }

        .item-row span {
            color: #1e293b !important;
        }

        .item-row span.text-muted {
            color: #475569 !important;
        }

        .item-row strong {
            color: #0f172a !important;
        }

        /* Strict High-Contrast Light Surface Overrides for Ticket */
        .ticket-voucher,
        body.theme-dark .ticket-voucher,
        html.theme-dark .ticket-voucher {
            background: #ffffff !important;
            background-color: #ffffff !important;
            color: #0f172a !important;
            border: 1px solid #cbd5e1 !important;
        }

        .ticket-voucher .voucher-body,
        body.theme-dark .ticket-voucher .voucher-body,
        html.theme-dark .ticket-voucher .voucher-body {
            background: #ffffff !important;
            background-color: #ffffff !important;
            color: #0f172a !important;
        }

        .ticket-voucher div,
        .ticket-voucher span,
        .ticket-voucher p,
        .ticket-voucher strong,
        .ticket-voucher b,
        body.theme-dark .ticket-voucher div,
        body.theme-dark .ticket-voucher span,
        body.theme-dark .ticket-voucher p,
        body.theme-dark .ticket-voucher strong,
        body.theme-dark .ticket-voucher b,
        html.theme-dark .ticket-voucher div,
        html.theme-dark .ticket-voucher span,
        html.theme-dark .ticket-voucher p,
        html.theme-dark .ticket-voucher strong,
        html.theme-dark .ticket-voucher b {
            color: #0f172a;
        }

        .ticket-voucher .text-muted,
        body.theme-dark .ticket-voucher .text-muted,
        html.theme-dark .ticket-voucher .text-muted {
            color: #475569 !important;
        }

        .ticket-voucher .text-dark,
        body.theme-dark .ticket-voucher .text-dark,
        html.theme-dark .ticket-voucher .text-dark,
        .ticket-voucher strong,
        body.theme-dark .ticket-voucher strong,
        html.theme-dark .ticket-voucher strong {
            color: #0f172a !important;
        }

        .ticket-voucher .text-primary,
        body.theme-dark .ticket-voucher .text-primary,
        html.theme-dark .ticket-voucher .text-primary {
            color: #0284c7 !important;
        }

        .ticket-voucher .section-title,
        body.theme-dark .ticket-voucher .section-title,
        html.theme-dark .ticket-voucher .section-title {
            color: #475569 !important;
        }

        .fw-bold { font-weight: 700 !important; }
        .fs-4 { font-size: 1.35rem !important; }
        .fs-5 { font-size: 1.15rem !important; }
        .fs-6 { font-size: 0.95rem !important; }
        .text-center { text-align: center !important; }
        .mt-1 { margin-top: 0.25rem !important; }
        .mt-2 { margin-top: 0.5rem !important; }
        .mt-3 { margin-top: 0.75rem !important; }
        .mt-4 { margin-top: 1rem !important; }
        .pt-2 { padding-top: 0.5rem !important; }
        .me-1 { margin-right: 0.25rem !important; }
        .border-top { border-top: 1px solid #e2e8f0 !important; }

        .badge-payment {
            display: inline-block;
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 700;
            background: #f1f5f9 !important;
            color: #0f172a !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px;
            letter-spacing: 0.02em;
        }

        .badge-modern {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            font-weight: 700;
            border-radius: 8px;
            font-size: 0.85rem;
            padding: 0.55rem 1.15rem;
            line-height: 1.2;
        }

        .badge-modern-success {
            background-color: #dcfce7 !important;
            color: #15803d !important;
            border: 1px solid #86efac !important;
        }

        .badge-modern-warning {
            background-color: #fef3c7 !important;
            color: #b45309 !important;
            border: 1px solid #fde68a !important;
        }

        /* Action Buttons: Desain Selaras & Modern */
        .action-bar {
            margin-top: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            width: 100%;
            max-width: 440px;
        }

        .action-row {
            display: flex;
            gap: 0.75rem;
            width: 100%;
        }

        .action-controls-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            margin-bottom: 0.15rem;
        }

        .btn-nota-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            padding: 0.85rem 1.25rem;
            font-weight: 700;
            font-size: 0.925rem;
            border-radius: 14px;
            border: none;
            outline: none;
            text-decoration: none !important;
            cursor: pointer;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
            letter-spacing: -0.01em;
            line-height: 1.2;
            box-sizing: border-box;
        }

        .btn-nota-action i {
            font-size: 1rem;
        }

        .btn-nota-action:active {
            transform: translateY(1px);
        }

        .btn-nota-download {
            width: 100%;
            background: linear-gradient(135deg, #0284c7, #0ea5e9);
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35);
        }

        .btn-nota-download:hover {
            background: linear-gradient(135deg, #0369a1, #0284c7);
            color: #ffffff !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(2, 132, 199, 0.45);
        }

        .btn-nota-print {
            flex: 1;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.32);
        }

        .btn-nota-print:hover {
            background: linear-gradient(135deg, #047857, #059669);
            color: #ffffff !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(16, 185, 129, 0.42);
        }

        .btn-nota-back {
            flex: 1;
            background: linear-gradient(135deg, #475569, #64748b);
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(71, 85, 105, 0.28);
        }

        .btn-nota-back:hover {
            background: linear-gradient(135deg, #334155, #475569);
            color: #ffffff !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(71, 85, 105, 0.38);
        }

        body.theme-dark .btn-nota-back,
        html.theme-dark .btn-nota-back {
            background: linear-gradient(135deg, #334155, #475569) !important;
            color: #ffffff !important;
        }

        @media print {
            body, body.theme-dark, html.theme-dark body {
                background: #ffffff !important;
                background-color: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .action-bar, .no-print {
                display: none !important;
            }
            .ticket-voucher,
            body.theme-dark .ticket-voucher,
            html.theme-dark .ticket-voucher {
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                border-radius: 0 !important;
                background: #ffffff !important;
                background-color: #ffffff !important;
                color: #000000 !important;
            }
            .ticket-voucher div,
            .ticket-voucher span,
            .ticket-voucher p,
            .ticket-voucher strong,
            .ticket-voucher b,
            .ticket-voucher td,
            .ticket-voucher th,
            .ticket-voucher * {
                color: #000000 !important;
            }
            .ticket-voucher .text-muted,
            .ticket-voucher .barcode-label,
            .ticket-voucher .barcode-note,
            .ticket-voucher .section-title {
                color: #334155 !important;
            }
            .ticket-voucher .text-primary {
                color: #0284c7 !important;
            }
            .voucher-header,
            body.theme-dark .voucher-header,
            html.theme-dark .voucher-header {
                background: #0284c7 !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .voucher-header h1, .voucher-header p {
                color: #ffffff !important;
            }
            .ticket-voucher .perforated-divider::before, 
            .ticket-voucher .perforated-divider::after {
                background-color: #ffffff !important;
            }
        }
    </style>
</head>
<body>

<div class="ticket-voucher" id="ticketPrintArea">
    <!-- Header Struk -->
    <div class="voucher-header">
        <img src="<?= $logo_src ?>" alt="Logo Patemon" class="voucher-logo">
        <h1>PEMANDIAN PATEMON</h1>
        <p>Jl. Patemon, Tanggul Kulon, Kec. Tanggul, Jember, Jawa Timur</p>
    </div>

    <!-- Voucher Body -->
    <div class="voucher-body">
        <!-- Barcode & QR Code Section Presisi Tengah -->
        <div class="barcode-section">
            <div class="barcode-label">NOMOR REFERENSI TRANSAKSI</div>
            <div class="barcode-code"><?= e($kode_transaksi) ?></div>
            <div class="dual-code-container">
                <!-- QR Code untuk Kamera Webcam / HP Kasir -->
                <div class="qr-code-box" id="qrcode"></div>
                <!-- 1D Barcode untuk Barcode Scanner Garis Loket -->
                <div class="barcode-wrapper">
                    <?= $barcodeSVG ?>
                </div>
            </div>
            <div class="barcode-note">Tunjukkan QR Code / Barcode ini kepada petugas loket untuk verifikasi tiket masuk</div>
        </div>

        <!-- Detail Pemesanan -->
        <div class="item-row">
            <span class="text-muted">Nama Pengunjung:</span>
            <strong class="text-dark"><?= e($transaksi_data['user_nama'] ?? $transaksi_data['nama_pemesan'] ?? $nama) ?></strong>
        </div>
        <div class="item-row">
            <span class="text-muted">Tanggal Kunjungan:</span>
            <strong><?= date('d M Y', strtotime($transaksi_data['tgl_pemesanan'])) ?></strong>
        </div>
        <div class="item-row">
            <span class="text-muted">Metode Pembayaran:</span>
            <span class="badge-payment"><?= strtoupper(e($transaksi_data['metode_pembayaran'] ?? 'TUNAI')) ?></span>
        </div>

        <div class="perforated-divider"></div>

        <!-- Rincian Tiket Dinamis -->
        <div class="section-title" style="font-size: 0.775rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">
            RINCIAN TIKET MASUK (TOTAL <?= $total_tiket ?> TIKET)
        </div>

        <?php if (!empty($items)): ?>
            <?php foreach ($items as $item): 
                $icon = get_ticket_icon($item['jenis_tiket'], $item['ikon'] ?? null);
            ?>
                <div class="item-row">
                    <span>
                        <i class="fa-solid <?= e($icon) ?> me-1 text-primary"></i>
                        <?= e($item['jenis_tiket']) ?> (<?= (int)$item['quantity'] ?>x)
                    </span>
                    <strong><?= format_rupiah($item['sub_total']) ?></strong>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="item-row">
                <span>Tiket Masuk</span>
                <strong><?= format_rupiah($transaksi_data['total_harga']) ?></strong>
            </div>
        <?php endif; ?>

        <div class="item-row pt-2 border-top mt-2">
            <span class="fw-bold fs-6 text-dark">Total Pembayaran:</span>
            <strong class="text-primary fs-5"><?= format_rupiah($transaksi_data['total_harga']) ?></strong>
        </div>

        <!-- Status Lunas / Verifikasi -->
        <div class="text-center mt-3 pt-2">
            <?php if ($transaksi_data['status'] === 'done'): ?>
                <span class="badge-modern badge-modern-success">
                    <i class="fa-solid fa-circle-check"></i> LUNAS / TERVERIFIKASI
                </span>
            <?php else: ?>
                <span class="badge-modern badge-modern-warning">
                    <i class="fa-solid fa-clock"></i> MENUNGGU PEMBAYARAN
                </span>
            <?php endif; ?>
        </div>

        <div class="text-center text-muted mt-4" style="font-size: 0.75rem; line-height: 1.4;">
            Terima kasih atas kunjungan Anda di Pemandian Patemon.<br>
            Harap simpan struk ini sebagai bukti akses masuk kolam.
        </div>
    </div>
</div>

<!-- Floating Action Buttons -->
<div class="action-bar no-print">
    <!-- Utility Controls: Language & Theme -->
    <div class="action-controls-row">
        <button type="button" class="btn-lang-switcher" onclick="togglePatemonLanguage()" title="Beralih Bahasa / Switch Language">
            <svg class="flag-icon-svg" viewBox="0 0 640 480" width="18" height="13" style="border-radius:2px; vertical-align:middle; display:inline-block; box-shadow:0 0 1px rgba(0,0,0,0.5); margin-right:4px;"><g fill-rule="evenodd" stroke-width="1pt"><path fill="#e70011" d="M0 0h640v240H0z"/><path fill="#ffffff" d="M0 240h640v240H0z"/></g></svg><strong>ID</strong>
        </button>
        <button type="button" id="themeToggleBtn" class="btn-theme-switcher" onclick="togglePatemonTheme()" title="Beralih Mode Gelap / Terang">
            <span class="theme-icon-moon"><i class="fa-solid fa-moon"></i></span>
            <span class="theme-icon-sun"><i class="fa-solid fa-sun"></i></span>
            <span class="d-none d-sm-inline ms-1" id="themeLabelText">Tema</span>
        </button>
    </div>
    <button type="button" id="btnDownloadNota" onclick="downloadNotaImage()" class="btn-nota-action btn-nota-download" title="Unduh nota dalam format gambar PNG">
        <i class="fa-solid fa-download"></i> Download Gambar Nota
    </button>
    <div class="action-row">
        <button type="button" onclick="window.print()" class="btn-nota-action btn-nota-print" title="Cetak langsung ke printer atau simpan PDF">
            <i class="fa-solid fa-print"></i> Cetak
        </button>
        <?php
        $back_url = route_url('home');
        if ($user_lvl === 1 || $user_lvl === 2) {
            $back_url = route_url('transaksi');
        } elseif ($user_lvl === 3) {
            $back_url = route_url('kasir');
        } else {
            $back_url = route_url('home');
        }
        ?>
        <a href="<?= $back_url ?>" class="btn-nota-action btn-nota-back" title="Kembali ke halaman sebelumnya">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<!-- Local html2canvas library dengan CDN Fallback -->
<script src="<?= public_url('js/html2canvas.min.js') ?>"></script>
<script>
if (typeof html2canvas === 'undefined') {
    document.write('<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"><\/script>');
}
</script>

<script>
function downloadNotaImage() {
    const btn = document.getElementById('btnDownloadNota');
    if (!btn) return;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Menyiapkan Gambar...';

    const ticket = document.getElementById('ticketPrintArea');
    if (!ticket) {
        alert('Area struk nota tidak ditemukan.');
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }

    if (typeof html2canvas !== 'function') {
        alert('Modul pembuat gambar sedang dimuat atau diblokir peramban. Silakan gunakan tombol Cetak.');
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }

    // Scroll ke atas agar koordinat render html2canvas presisi
    window.scrollTo({ top: 0, behavior: 'instant' });

    html2canvas(ticket, {
        scale: 2.5,
        useCORS: true,
        allowTaint: false,
        backgroundColor: '#ffffff',
        logging: false,
        scrollX: 0,
        scrollY: 0
    }).then(function(canvas) {
        const fileName = 'Nota_Patemon_<?= e($kode_transaksi) ?>.png';

        if (canvas.toBlob) {
            canvas.toBlob(function(blob) {
                if (blob) {
                    const blobUrl = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.download = fileName;
                    link.href = blobUrl;
                    document.body.appendChild(link);
                    link.click();
                    setTimeout(function() {
                        if (document.body.contains(link)) document.body.removeChild(link);
                        URL.revokeObjectURL(blobUrl);
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }, 500);
                    return;
                }
                fallbackDownload(canvas, fileName, btn, originalText);
            }, 'image/png');
        } else {
            fallbackDownload(canvas, fileName, btn, originalText);
        }
    }).catch(function(err) {
        console.error('Error html2canvas:', err);
        alert('Gagal membuat gambar nota. Silakan coba kembali atau gunakan tombol Cetak.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function fallbackDownload(canvas, fileName, btn, originalText) {
    try {
        const dataUrl = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.download = fileName;
        link.href = dataUrl;
        document.body.appendChild(link);
        link.click();
        setTimeout(function() {
            if (document.body.contains(link)) document.body.removeChild(link);
        }, 300);
    } catch (e) {
        console.error('Fallback download error:', e);
        alert('Peramban membatasi unduhan otomatis. Silakan gunakan tombol Cetak.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
// Theme Controller
function togglePatemonTheme() {
    const isDark = document.body.classList.contains('theme-dark') || document.documentElement.classList.contains('theme-dark');
    const newTheme = isDark ? 'light' : 'dark';
    applyPatemonTheme(newTheme);
    localStorage.setItem('patemon_theme', newTheme);
}

function applyPatemonTheme(theme) {
    const btn = document.getElementById('themeToggleBtn');
    const label = document.getElementById('themeLabelText');
    if (theme === 'dark') {
        document.body.classList.add('theme-dark');
        document.documentElement.classList.add('theme-dark');
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        if (btn) {
            btn.classList.add('active-dark');
            btn.setAttribute('title', 'Beralih ke Mode Terang');
        }
        if (label) label.textContent = 'Gelap';
    } else {
        document.body.classList.remove('theme-dark');
        document.documentElement.classList.remove('theme-dark');
        document.documentElement.setAttribute('data-bs-theme', 'light');
        if (btn) {
            btn.classList.remove('active-dark');
            btn.setAttribute('title', 'Beralih ke Mode Gelap');
        }
        if (label) label.textContent = 'Terang';
    }
}

// Render QR Code untuk scan cepat di kamera loket / smartphone
document.addEventListener("DOMContentLoaded", function() {
    const currentTheme = localStorage.getItem('patemon_theme') || 'light';
    applyPatemonTheme(currentTheme);

    const qrEl = document.getElementById("qrcode");
    if (qrEl && typeof QRCode !== "undefined") {
        new QRCode(qrEl, {
            text: "<?= e($kode_transaksi) ?>",
            width: 120,
            height: 120,
            colorDark: "#0f172a",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
    }
});
</script>

</body>
</html>