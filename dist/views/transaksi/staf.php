<?php
require '../../app/config.php';

// Hak akses: Admin (1) dan Staff (2)
check_auth([1, 2]);

$active_menu = 'staf';
$base_view = '..';

$searchInput = isset($_GET['id_transaksi']) ? (int)$_GET['id_transaksi'] : 0;

if ($searchInput > 0) {
    $stmt = $conn->prepare("SELECT transaksi.*, users.nama FROM transaksi INNER JOIN users ON transaksi.id_user = users.id_user WHERE transaksi.id_transaksi = ? ORDER BY tgl_pemesanan DESC");
    $stmt->bind_param("i", $searchInput);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT transaksi.*, users.nama FROM transaksi INNER JOIN users ON transaksi.id_user = users.id_user ORDER BY tgl_pemesanan DESC, id_transaksi DESC";
    $result = $conn->query($sql);
}

// Rekap penjualan staf
$today_omzet = 0;
$today_tickets = 0;
$recap = $conn->query("SELECT SUM(total_harga) as omzet, COUNT(*) as cnt FROM transaksi WHERE DATE(tgl_pemesanan) = CURDATE() AND status = 'done'");
if ($recap && $rc = $recap->fetch_assoc()) {
    $today_omzet = (float)($rc['omzet'] ?? 0);
    $today_tickets = (int)$rc['cnt'];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Loket Staf - Pemandian Patemon</title>

    <link rel="icon" type="image/x-icon" href="../../../public/img/icon.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/assets/css/main/app.css">
    <link rel="stylesheet" href="../../../public/css/modern-theme.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div id="app">
        <?php include '../../app/partials/sidebar.php'; ?>

        <div id="main">
            <!-- Header Topbar -->
            <header class="mb-4 d-flex justify-content-between align-items-center">
                <a href="#" class="burger-btn d-block d-xl-none text-dark">
                    <i class="fa-solid fa-bars fs-3"></i>
                </a>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <span class="badge badge-modern-success"><i class="fa-solid fa-circle-dot"></i> Kasir Online</span>
                </div>
            </header>

            <div class="page-heading mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold text-dark mb-1" style="font-size: 1.75rem;">Panel Kasir Loket</h2>
                        <p class="text-muted mb-0">Selamat bertugas, <strong><?= e($_SESSION['nama']) ?></strong>! Kelola tiket masuk pengunjung pemandian.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="tambah.php" class="btn btn-brand">
                            <i class="fa-solid fa-cash-register me-1"></i> Input Transaksi Loket (POS)
                        </a>
                        <button onclick="printTable('dataTable', 'Laporan Kasir Loket')" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-print me-1"></i> Cetak
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Hari Ini -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6">
                    <div class="modern-card p-3 d-flex align-items-center gap-3">
                        <div class="metric-icon-box green" style="width: 48px; height: 48px; font-size: 1.25rem;">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Omzet Loket Hari Ini</div>
                            <div class="fw-bold text-success" style="font-size: 1.35rem;"><?= format_rupiah($today_omzet) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="modern-card p-3 d-flex align-items-center gap-3">
                        <div class="metric-icon-box blue" style="width: 48px; height: 48px; font-size: 1.25rem;">
                            <i class="fa-solid fa-ticket"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Transaksi Lunas Hari Ini</div>
                            <div class="fw-bold text-primary" style="font-size: 1.35rem;"><?= $today_tickets ?> Transaksi</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-content">
                <div class="modern-card">
                    <!-- Search Toolbar -->
                    <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2" style="max-width: 420px; width: 100%;">
                            <div class="input-icon-group flex-grow-1">
                                <i class="fa-solid fa-magnifying-glass input-icon"></i>
                                <input 
                                    class="form-control-modern" 
                                    type="number" 
                                    id="searchInput" 
                                    placeholder="Cari ID transaksi..." 
                                    value="<?= $searchInput > 0 ? $searchInput : '' ?>"
                                >
                            </div>
                            <button onclick="searchData()" class="btn btn-brand">Cari</button>
                            <?php if ($searchInput > 0): ?>
                                <a href="staf.php" class="btn btn-outline-secondary">Reset</a>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button class="btn btn-soft-primary" type="button" data-bs-toggle="collapse" data-bs-target="#scannerCollapse">
                                <i class="fa-solid fa-qrcode me-1"></i> Scan Barcode Struk
                            </button>
                        </div>
                    </div>

                    <!-- Barcode Scanner Collapse Area -->
                    <div class="collapse p-4 border-bottom bg-white" id="scannerCollapse">
                        <div class="text-center" style="max-width: 500px; margin: auto;">
                            <h5 class="fw-bold mb-2">Pindai Barcode Nota Pengunjung</h5>
                            <p class="text-muted small mb-3">Arahkan barcode nota struk loket ke kamera.</p>
                            <div id="my-qr-reader" class="rounded-3 overflow-hidden shadow-sm border"></div>
                            <div id="your-qr-result" class="mt-3 text-success fw-bold"></div>
                        </div>
                    </div>

                    <!-- Table Data -->
                    <div class="table-responsive">
                        <table class="table-modern" id="dataTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama Pengunjung</th>
                                    <th>Tgl Transaksi</th>
                                    <th>Metode</th>
                                    <th>Total Bayar</th>
                                    <th>Status</th>
                                    <th class="action-column text-center" style="width: 140px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong class="text-primary">#<?= (int)$row["id_transaksi"] ?></strong></td>
                                        <td class="fw-semibold text-dark"><?= e($row["nama"]) ?></td>
                                        <td class="text-muted"><?= date('d M Y', strtotime($row["tgl_pemesanan"])) ?></td>
                                        <td>
                                            <span class="badge" style="background: #f1f5f9; color: #334155; font-weight: 600; font-size: 0.75rem;">
                                                <?= strtoupper(e($row["metode_pembayaran"] ?? 'TUNAI')) ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-dark"><?= format_rupiah($row["total_harga"]) ?></td>
                                        <td>
                                            <?php if ($row["status"] === 'done'): ?>
                                                <span class="badge-modern badge-modern-success">
                                                    <i class="fa-solid fa-circle-check"></i> Selesai
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-modern badge-modern-warning">
                                                    <i class="fa-solid fa-clock"></i> <?= e($row["status"]) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="action-column text-center">
                                            <div class="d-inline-flex gap-1">
                                                <a class="btn btn-sm btn-soft-primary" title="Cetak Nota" href="../tiket/nota.php?id_transaksi=<?= $row["id_transaksi"] ?>" target="_blank">
                                                    <i class="fa-solid fa-print"></i>
                                                </a>
                                                <a class="btn btn-sm btn-soft-primary" title="Detail Tiket" href="../detail_transaksi/detail_transaksi.php?id=<?= $row["id_transaksi"] ?>">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                                <a class="btn btn-sm btn-soft-primary" title="Edit Transaksi" href="update.php?id=<?= $row["id_transaksi"] ?>">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fa-regular fa-folder-open fs-2 mb-2 d-block text-secondary"></i>
                                        Tidak ada data transaksi loket.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <?php include '../../app/partials/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../../public/assets/js/bootstrap.js"></script>
    <script src="../../../public/js/print.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>

    <script>
    function searchData() {
        var searchInput = document.getElementById('searchInput').value.trim();
        window.location.href = 'staf.php' + (searchInput ? '?id_transaksi=' + encodeURIComponent(searchInput) : '');
    }

    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchData();
        }
    });

    // Barcode Scanner
    document.addEventListener("DOMContentLoaded", function () {
        var scannerElement = document.getElementById('my-qr-reader');
        if (!scannerElement) return;

        var lastResult;
        function onScanSuccess(decodeText) {
            if (decodeText !== lastResult) {
                lastResult = decodeText;
                document.getElementById('searchInput').value = decodeText;
                document.getElementById('your-qr-result').innerHTML = "Barcode Terdeteksi: <strong>#" + decodeText + "</strong>";
                setTimeout(function() {
                    window.location.href = 'staf.php?id_transaksi=' + encodeURIComponent(decodeText);
                }, 500);
            }
        }

        var htmlscanner = new Html5QrcodeScanner("my-qr-reader", { fps: 10, qrbox: 250 });
        htmlscanner.render(onScanSuccess);
    });
    </script>
</body>
</html>
