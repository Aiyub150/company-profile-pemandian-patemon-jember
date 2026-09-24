<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config.php';

use Picqer\Barcode\BarcodeGeneratorHTML;

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

// Ambil transaksi menggunakan Prepared Statement
$user_lvl = (int)($_SESSION['level'] ?? 0);
$stmt = $conn->prepare("SELECT transaksi.*, users.nama as user_nama, users.no_telepon, users.email FROM transaksi LEFT JOIN users ON transaksi.id_user = users.id_user WHERE id_transaksi = ? AND (transaksi.id_user = ? OR ? = 1 OR ? = 2 OR ? = 3) LIMIT 1");
$stmt->bind_param("iiiii", $id_transaksi, $id_user, $user_lvl, $user_lvl, $user_lvl);
$stmt->execute();
$transaksi_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaksi_data) {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h2>Nota Transaksi Tidak Ditemukan</h2><p>Anda tidak memiliki akses ke transaksi ini atau nomor ID salah.</p><a href='" . route_url('home') . "'>Kembali ke Beranda</a></div>");
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

// Generate Barcode
$barcodeHTML = '';
try {
    $generator = new BarcodeGeneratorHTML();
    $barcodeHTML = $generator->getBarcode((string)$id_transaksi, $generator::TYPE_CODE_128);
} catch (Throwable $e) {
    $barcodeHTML = "<div style='letter-spacing: 4px; font-weight: bold;'>*" . e($kode_transaksi) . "*</div>";
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
            width: 48px;
            height: auto;
            margin-bottom: 0.5rem;
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
            opacity: 0.9;
            margin: 0.25rem 0 0;
        }

        .voucher-body {
            padding: 1.75rem;
            background: #ffffff;
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
        <img src="<?= public_url('img/icon.png') ?>" alt="Logo">
        <h1>PEMANDIAN PATEMON</h1>
        <p>Jl. Patemon, Tanggul Kulon, Kec. Tanggul, Jember, Jawa Timur</p>
    </div>

    <!-- Voucher Body -->
    <div class="voucher-body">
        <!-- Barcode Section -->
        <div class="barcode-section">
            <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">NOMOR REFERENSI TRANSAKSI</div>
            <div class="fw-bold fs-4 text-dark mt-1"><?= e($kode_transaksi) ?></div>
            <div class="barcode-wrapper">
                <?= $barcodeHTML ?>
            </div>
            <div class="small text-muted">Tunjukkan barcode ini kepada petugas loket pintu masuk</div>
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

        <!-- Rincian Tiket Dinamis -->
        <div style="font-size: 0.775rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">
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

        <!-- Status Lunas -->
        <div class="text-center mt-3 pt-2">
            <?php if ($transaksi_data['status'] === 'done'): ?>
                <span class="badge-modern badge-modern-success py-2 px-3 fs-6">
                    <i class="fa-solid fa-circle-check me-1"></i> LUNAS / TERVERIFIKASI
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
    $back_url = route_url('home');
    if ($user_lvl === 1 || $user_lvl === 2) $back_url = route_url('transaksi');
    if ($user_lvl === 3) $back_url = route_url('kasir');
    ?>
    <a href="<?= $back_url ?>" class="btn btn-outline-secondary px-3 py-2">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
</div>

</body>
</html>