<?php
require '../../../vendor/autoload.php';
require '../../app/config.php';

use Picqer\Barcode\BarcodeGeneratorHTML;

if (!isset($_SESSION['id_user'])) {
    header('Location: ../login.php');
    exit();
}

$id_user = (int)$_SESSION['id_user'];
$nama = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Pelanggan';
$id_transaksi = (int)($_GET['id_transaksi'] ?? ($_GET['id'] ?? ($_SESSION['id_transaksi'] ?? 0)));

if ($id_transaksi <= 0) {
    header('Location: ../index.php');
    exit();
}

// Ambil transaksi menggunakan Prepared Statement
$stmt = $conn->prepare("SELECT transaksi.*, users.nama as user_nama FROM transaksi LEFT JOIN users ON transaksi.id_user = users.id_user WHERE id_transaksi = ? AND (transaksi.id_user = ? OR ? = 1 OR ? = 2) LIMIT 1");
$user_lvl = (int)($_SESSION['level'] ?? 0);
$stmt->bind_param("iiii", $id_transaksi, $id_user, $user_lvl, $user_lvl);
$stmt->execute();
$transaksi_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaksi_data) {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h2>Nota Transaksi Tidak Ditemukan</h2><p>Anda tidak memiliki akses ke transaksi ini atau nomor ID salah.</p><a href='../index.php'>Kembali ke Beranda</a></div>");
}

// Ambil detail tiket Dewasa dan Anak-Anak
$stmt_d = $conn->prepare("SELECT quantity, sub_total FROM detail_transaksi WHERE id_transaksi = ? AND jenis_tiket = 'Dewasa' LIMIT 1");
$stmt_d->bind_param("i", $id_transaksi);
$stmt_d->execute();
$dewasa = $stmt_d->get_result()->fetch_assoc();
$stmt_d->close();

$stmt_a = $conn->prepare("SELECT quantity, sub_total FROM detail_transaksi WHERE id_transaksi = ? AND jenis_tiket = 'Anak-Anak' LIMIT 1");
$stmt_a->bind_param("i", $id_transaksi);
$stmt_a->execute();
$anak = $stmt_a->get_result()->fetch_assoc();
$stmt_a->close();

$dewasa_qty = (int)($dewasa['quantity'] ?? 0);
$dewasa_sub = (float)($dewasa['sub_total'] ?? 0);

$anak_qty   = (int)($anak['quantity'] ?? 0);
$anak_sub   = (float)($anak['sub_total'] ?? 0);

$total_tiket = $dewasa_qty + $anak_qty;

// Generate Barcode
$barcodeHTML = '';
try {
    $generator = new BarcodeGeneratorHTML();
    $barcodeHTML = $generator->getBarcode((string)$id_transaksi, $generator::TYPE_CODE_128);
} catch (Exception $e) {
    $barcodeHTML = "<div style='letter-spacing: 4px; font-weight: bold;'>*" . str_pad($id_transaksi, 8, '0', STR_PAD_LEFT) . "*</div>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Tiket #<?= $id_transaksi ?> - Pemandian Patemon</title>
    <link rel="icon" type="image/x-icon" href="../../../public/img/icon.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/css/modern-theme.css">
    <style>
        body {
            background-color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            margin: 0;
        }

        .ticket-voucher {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .voucher-header {
            background: linear-gradient(135deg, #0284c7, #0ea5e9);
            color: #ffffff;
            padding: 2rem 1.75rem 1.5rem;
            text-align: center;
            position: relative;
        }

        .voucher-header img {
            height: 48px;
            width: auto;
            max-width: 68px;
            object-fit: contain;
            border-radius: 12px;
            background: #ffffff;
            padding: 4px;
            margin-bottom: 0.75rem;
        }

        .voucher-header h1 {
            font-size: 1.35rem;
            font-weight: 800;
            margin: 0 0 0.25rem;
            letter-spacing: 0.02em;
        }

        .voucher-header p {
            font-size: 0.825rem;
            color: #e0f2fe;
            margin: 0;
        }

        .voucher-body {
            padding: 1.75rem;
        }

        .barcode-section {
            text-align: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px dashed #e2e8f0;
        }

        .barcode-wrapper {
            display: inline-block;
            max-width: 260px;
            margin: 0.5rem auto;
        }

        .barcode-wrapper div {
            margin: 0 auto;
        }

        .perforated-divider {
            position: relative;
            border-top: 2px dashed #cbd5e1;
            margin: 1.5rem -1.75rem;
        }

        .perforated-divider::before, .perforated-divider::after {
            content: '';
            position: absolute;
            top: -10px;
            width: 20px;
            height: 20px;
            background-color: #f1f5f9;
            border-radius: 50%;
        }

        .perforated-divider::before {
            left: -10px;
        }

        .perforated-divider::after {
            right: -10px;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            font-size: 0.925rem;
        }

        .action-bar {
            margin-top: 1.5rem;
            display: flex;
            gap: 0.75rem;
            width: 100%;
            max-width: 440px;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .action-bar, .no-print {
                display: none !important;
            }
            .ticket-voucher {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                border-radius: 0 !important;
            }
            .voucher-header {
                background: #0284c7 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<div class="ticket-voucher" id="ticketPrintArea">
    <!-- Header -->
    <div class="voucher-header">
        <img src="../../../public/img/icon.png" alt="Logo">
        <h1>PEMANDIAN PATEMON</h1>
        <p>Jl. Patemon, Tanggul Kulon, Kec. Tanggul, Jember, Jawa Timur</p>
    </div>

    <!-- Voucher Body -->
    <div class="voucher-body">
        <!-- Barcode Section -->
        <div class="barcode-section">
            <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">KODE TRANSAKSI TIKET</div>
            <div class="fw-bold fs-4 text-dark mt-1">#<?= str_pad($id_transaksi, 6, '0', STR_PAD_LEFT) ?></div>
            <div class="barcode-wrapper">
                <?= $barcodeHTML ?>
            </div>
            <div class="small text-muted">Arahkan barcode ke alat pemindai pintu loket</div>
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
            <span class="badge bg-light text-dark border"><?= strtoupper(e($transaksi_data['metode_pembayaran'] ?? 'TUNAI')) ?></span>
        </div>

        <div class="perforated-divider"></div>

        <!-- Rincian Tiket -->
        <div style="font-size: 0.775rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">
            RINCIAN TIKET MASUK
        </div>

        <?php if ($dewasa_qty > 0): ?>
            <div class="item-row">
                <span>Tiket Dewasa (<?= $dewasa_qty ?>x)</span>
                <strong><?= format_rupiah($dewasa_sub) ?></strong>
            </div>
        <?php endif; ?>

        <?php if ($anak_qty > 0): ?>
            <div class="item-row">
                <span>Tiket Anak-Anak (<?= $anak_qty ?>x)</span>
                <strong><?= format_rupiah($anak_sub) ?></strong>
            </div>
        <?php endif; ?>

        <div class="item-row pt-2 border-top mt-2">
            <span class="fw-bold fs-6 text-dark">Total Pembayaran:</span>
            <strong class="text-primary fs-5"><?= format_rupiah($transaksi_data['total_harga']) ?></strong>
        </div>

        <!-- Status Lunas -->
        <div class="text-center mt-3 pt-2">
            <?php if ($transaksi_data['status'] === 'done'): ?>
                <span class="badge-modern badge-modern-success py-2 px-3 fs-6">
                    <i class="fa-solid fa-circle-check me-1"></i> LUNAS / SUDAH DIBAYAR
                </span>
            <?php else: ?>
                <span class="badge-modern badge-modern-warning py-2 px-3 fs-6">
                    <i class="fa-solid fa-clock me-1"></i> MENUNGGU PEMBAYARAN
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
<div class="action-bar">
    <button onclick="window.print()" class="btn btn-brand flex-grow-1 py-2">
        <i class="fa-solid fa-print me-1"></i> Cetak Struk Nota
    </button>
    <?php
    $back_url = '../index.php';
    if ($_SESSION['level'] == 1) $back_url = '../transaksi/transaksi.php';
    if ($_SESSION['level'] == 2) $back_url = '../transaksi/staf.php';
    ?>
    <a href="<?= $back_url ?>" class="btn btn-outline-secondary px-3 py-2">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
</div>

</body>
</html>