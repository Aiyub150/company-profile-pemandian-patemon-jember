<?php
require '../../app/config.php';

// Hak akses: Admin (1) dan Staff (2)
check_auth([1, 2]);

$active_menu = 'transaksi';
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

// Hitung rekap cepat
$total_count = 0;
$total_omzet = 0;
$total_done = 0;
$recap_res = $conn->query("SELECT COUNT(*) as cnt, SUM(total_harga) as omzet, SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_cnt FROM transaksi");
if ($recap_res && $recap = $recap_res->fetch_assoc()) {
    $total_count = (int)$recap['cnt'];
    $total_omzet = (float)($recap['omzet'] ?? 0);
    $total_done  = (int)$recap['done_cnt'];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Kasir - Pemandian Patemon</title>

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
                    <span class="badge badge-modern-primary">Loket Kasir #1</span>
                </div>
            </header>

            <div class="page-heading mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold mb-1" style="font-size: 1.75rem; color: #0f172a;">Kelola Transaksi Tiket</h2>
                        <p class="text-muted mb-0">Riwayat penjualan tiket loket, validasi bukti transfer, dan cetak struk nota.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="tambah.php" class="btn btn-brand">
                            <i class="fa-solid fa-plus me-1"></i> Transaksi Baru (POS)
                        </a>
                        <button onclick="printTable('dataTable', 'Laporan Transaksi Kasir')" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-print me-1"></i> Cetak
                        </button>
                        <button onclick="exportToExcel('dataTable', 'transaksi_patemon')" class="btn btn-soft-success">
                            <i class="fa-solid fa-file-excel me-1"></i> Excel
                        </button>
                        <button onclick="exportToPDF('dataTable', 'Laporan Transaksi Kasir')" class="btn btn-soft-danger">
                            <i class="fa-solid fa-file-pdf me-1"></i> PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mini Overview Stats -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-4">
                    <div class="modern-card p-3 d-flex align-items-center gap-3">
                        <div class="metric-icon-box blue" style="width: 48px; height: 48px; font-size: 1.25rem;">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Total Transaksi</div>
                            <div class="fw-bold" style="font-size: 1.35rem; color: #0f172a;"><?= number_format($total_count, 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <div class="modern-card p-3 d-flex align-items-center gap-3">
                        <div class="metric-icon-box green" style="width: 48px; height: 48px; font-size: 1.25rem;">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Transaksi Lunas</div>
                            <div class="fw-bold text-success" style="font-size: 1.35rem;"><?= number_format($total_done, 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <div class="modern-card p-3 d-flex align-items-center gap-3">
                        <div class="metric-icon-box amber" style="width: 48px; height: 48px; font-size: 1.25rem;">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Total Pendapatan</div>
                            <div class="fw-bold text-primary" style="font-size: 1.35rem;"><?= format_rupiah($total_omzet) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-content">
                <div class="modern-card">
                    <!-- Search & Scanner Toolbar -->
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
                                <a href="transaksi.php" class="btn btn-outline-secondary">Reset</a>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button class="btn btn-soft-primary" type="button" data-bs-toggle="collapse" data-bs-target="#scannerCollapse">
                                <i class="fa-solid fa-qrcode me-1"></i> Scan Barcode Nota
                            </button>
                        </div>
                    </div>

                    <!-- Barcode Scanner Collapse Area -->
                    <div class="collapse p-4 border-bottom bg-white" id="scannerCollapse">
                        <div class="text-center" style="max-width: 500px; margin: auto;">
                            <h5 class="fw-bold mb-2">Pindai Barcode / QR Code Nota</h5>
                            <p class="text-muted small mb-3">Arahkan barcode nota struk loket ke kamera untuk mencari transaksi secara instan.</p>
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
                                    <th>Nama Pemesan</th>
                                    <th>Tgl Transaksi</th>
                                    <th>Metode</th>
                                    <th>Total Bayar</th>
                                    <th>Bukti Pembayaran</th>
                                    <th>Status</th>
                                    <th class="action-column text-center" style="width: 160px;">Aksi</th>
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
                                            <?php 
                                            $bukti = $row["bukti_pembayaran"];
                                            if (!empty($bukti) && strtolower($bukti) !== 'bayar di loket' && file_exists(__DIR__ . '/../../app/payment/' . $bukti)): 
                                            ?>
                                                <a href="../../app/payment/<?= e($bukti) ?>" target="_blank" title="Lihat Bukti Transfer">
                                                    <img src="../../app/payment/<?= e($bukti) ?>" alt="Bukti" style="width: 44px; height: 44px; object-fit: cover; border-radius: 8px; border: 1.5px solid #e2e8f0;">
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border" style="font-weight: 500;">Loket Kasir</span>
                                            <?php endif; ?>
                                        </td>
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
                                                <a class="btn btn-sm btn-soft-primary btn-action-icon" title="Cetak Struk Nota" href="../tiket/nota.php?id_transaksi=<?= $row["id_transaksi"] ?>" target="_blank">
                                                    <i class="fa-solid fa-print"></i>
                                                </a>
                                                <a class="btn btn-sm btn-soft-primary btn-action-icon" title="Detail Tiket" href="../detail_transaksi/detail_transaksi.php?id=<?= $row["id_transaksi"] ?>">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                                <a class="btn btn-sm btn-soft-primary btn-action-icon" title="Edit Transaksi" href="update.php?id=<?= $row["id_transaksi"] ?>">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-soft-danger btn-action-icon" title="Hapus" onclick="confirmDelete(<?= (int)$row['id_transaksi'] ?>)">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fa-regular fa-folder-open fs-2 mb-2 d-block text-secondary"></i>
                                        Tidak ada data transaksi ditemukan.
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script src="../../../public/js/exportToExcel.js"></script>
    <script src="../../../public/js/exportToPDF.js"></script>
    <script src="../../../public/js/print.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>

    <script>
    function searchData() {
        var searchInput = document.getElementById('searchInput').value.trim();
        window.location.href = 'transaksi.php' + (searchInput ? '?id_transaksi=' + encodeURIComponent(searchInput) : '');
    }

    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchData();
        }
    });

    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Transaksi #' + id + '?',
            text: 'Data tiket dan transaksi yang dihapus tidak dapat dipulihkan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus Data',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete.php?id=' + id + '&csrf=<?= csrf_token() ?>';
            }
        });
    }

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
                    window.location.href = 'transaksi.php?id_transaksi=' + encodeURIComponent(decodeText);
                }, 500);
            }
        }

        var htmlscanner = new Html5QrcodeScanner("my-qr-reader", { fps: 10, qrbox: 250 });
        htmlscanner.render(onScanSuccess);
    });
    </script>
</body>
</html>
