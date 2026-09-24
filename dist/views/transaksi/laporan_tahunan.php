<?php
require '../../app/config.php';
check_auth([1]);

$active_menu = 'laporan_tahunan';
$base_view = '..';

$dateInput = isset($_GET['dateInput']) && preg_match('/^\d{4}$/', $_GET['dateInput']) ? (int)$_GET['dateInput'] : (int)date('Y');

// Fetch ticket category standard prices for clean reference
$prices = [];
$pr_res = $conn->query("SELECT nama_kategori, harga_tiket FROM kategori_tiket");
if ($pr_res) {
    while ($pr = $pr_res->fetch_assoc()) {
        $prices[$pr['nama_kategori']] = (float)$pr['harga_tiket'];
    }
}

$stmt = $conn->prepare("SELECT detail_transaksi.jenis_tiket, SUM(detail_transaksi.quantity) AS total_quantity, SUM(detail_transaksi.sub_total) AS total_sub, YEAR(transaksi.tgl_pemesanan) AS yr
    FROM detail_transaksi 
    INNER JOIN transaksi ON detail_transaksi.id_transaksi = transaksi.id_transaksi
    WHERE YEAR(transaksi.tgl_pemesanan) = ? AND transaksi.status = 'done'
    GROUP BY detail_transaksi.jenis_tiket");
$stmt->bind_param("i", $dateInput);
$stmt->execute();
$result = $stmt->get_result();

$chartData = [];
$total_omzet_tahun = 0;
$total_tiket_tahun = 0;

while ($row = $result->fetch_assoc()) {
    $qty = (int)$row['total_quantity'];
    $sub = (float)$row['total_sub'];
    $unit_price = isset($prices[$row['jenis_tiket']]) ? $prices[$row['jenis_tiket']] : ($qty > 0 ? ($sub / $qty) : 0);

    $chartData[] = [
        'jenis_tiket' => $row['jenis_tiket'],
        'total_quantity' => $qty,
        'total_sub' => $sub,
        'harga_satuan' => $unit_price,
    ];
    $total_omzet_tahun += $sub;
    $total_tiket_tahun += $qty;
}
$stmt->close();

$dewasa_count = 0;
$anak_count = 0;
foreach ($chartData as $cd) {
    if (stripos($cd['jenis_tiket'], 'dewasa') !== false) $dewasa_count += $cd['total_quantity'];
    if (stripos($cd['jenis_tiket'], 'anak') !== false) $anak_count += $cd['total_quantity'];
}

$rata_rata_tiket = $total_tiket_tahun > 0 ? ($total_omzet_tahun / $total_tiket_tahun) : 0;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Tahunan <?= $dateInput ?> - Pemandian Patemon</title>

    <link rel="icon" type="image/x-icon" href="<?= public_url('img/icon.png') ?>" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= public_url('assets/css/main/app.css') ?>">
    <link rel="stylesheet" href="<?= public_url('css/modern-theme.css') ?>">
</head>

<body>
    <div id="app">
        <?php include '../../app/partials/sidebar.php'; ?>

        <div id="main">
            <!-- Header Topbar -->
            <header class="mb-3 d-flex justify-content-between align-items-center">
                <a href="#" class="burger-btn d-block d-xl-none text-dark">
                    <i class="fa-solid fa-bars fs-3"></i>
                </a>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <span class="badge badge-modern-primary">Laporan Tahunan</span>
                </div>
            </header>

            <!-- Consolidated Modern Header & Action Toolbar -->
            <div class="modern-card p-4 mb-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge badge-modern-primary text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Laporan Omzet Tahunan</span>
                            <span class="text-success small fw-semibold"><i class="fa-solid fa-circle-check me-1"></i> Data Terverifikasi</span>
                        </div>
                        <h2 class="fw-bold text-dark mb-1" style="font-size: 1.65rem;">Laporan Penjualan Tahunan</h2>
                        <p class="text-muted small mb-0">
                            <i class="fa-regular fa-calendar-days text-primary me-1"></i> Tahun Buku: <strong class="text-dark"><?= $dateInput ?></strong>
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <!-- Compact Inline Year Filter -->
                        <form method="GET" action="" class="d-flex align-items-center gap-2 bg-light p-1 px-2 rounded-3 border">
                            <span class="text-muted small fw-semibold d-none d-sm-inline ps-1"><i class="fa-solid fa-calendar-days text-secondary me-1"></i> Tahun:</span>
                            <select id="dateInput" name="dateInput" class="form-select form-select-sm border-0 bg-transparent fw-bold text-dark" style="width: auto; box-shadow: none; cursor: pointer;">
                                <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 5; $y--): ?>
                                    <option value="<?= $y ?>" <?= ($dateInput === $y) ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="btn btn-brand btn-sm px-3 py-1">
                                <i class="fa-solid fa-filter me-1"></i> Filter
                            </button>
                        </form>

                        <div class="vr mx-1 d-none d-lg-block" style="height: 32px;"></div>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-2">
                            <a href="<?= route_url('laporan_preview', ['tipe' => 'tahunan', 'year' => (int)$dateInput]) ?>" class="btn btn-outline-secondary btn-sm px-3 shadow-sm bg-white" title="Pratinjau Dokumen Cetak Standar Pemkab">
                                <i class="fa-solid fa-file-pdf me-1 text-danger"></i> Pratinjau Dokumen PDF
                            </a>
                            <button onclick="exportToExcel('reportTable', 'Laporan_Tahunan_<?= $dateInput ?>')" class="btn btn-soft-success btn-sm px-3 shadow-sm" title="Ekspor ke File Excel">
                                <i class="fa-solid fa-file-excel me-1"></i> Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3 Balanced Metric KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="modern-card p-3 h-100 d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 52px; height: 52px; background: #ecfdf5; color: #059669; font-size: 1.4rem;">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                        <div class="overflow-hidden">
                            <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Omzet Tahun <?= $dateInput ?></div>
                            <div class="fs-4 fw-extrabold text-success text-truncate"><?= format_rupiah($total_omzet_tahun) ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;">Dari seluruh transaksi selesai</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="modern-card p-3 h-100 d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 52px; height: 52px; background: #e0f2fe; color: #0284c7; font-size: 1.4rem;">
                            <i class="fa-solid fa-ticket"></i>
                        </div>
                        <div class="overflow-hidden">
                            <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Tiket Terjual</div>
                            <div class="fs-4 fw-extrabold text-primary text-truncate"><?= number_format($total_tiket_tahun, 0, ',', '.') ?> <span class="fs-6 fw-normal text-muted">lembar</span></div>
                            <div class="text-muted" style="font-size: 0.75rem;">Akumulasi seluruh pengunjung</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="modern-card p-3 h-100 d-flex align-items-center gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 52px; height: 52px; background: #fef3c7; color: #d97706; font-size: 1.4rem;">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                        <div class="overflow-hidden">
                            <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Rata-rata / Lembar</div>
                            <div class="fs-4 fw-extrabold text-dark text-truncate"><?= format_rupiah($rata_rata_tiket) ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;">Nilai rata-rata per tiket</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Data: Table & Doughnut Chart -->
            <div class="page-content">
                <div class="row g-4">
                    <!-- Table Section -->
                    <div class="col-12 col-lg-7">
                        <div class="modern-card h-100 d-flex flex-column">
                            <div class="modern-card-header d-flex justify-content-between align-items-center py-3 px-4">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-circle p-2 bg-primary-subtle text-primary"><i class="fa-solid fa-table-list"></i></span>
                                    <span class="fw-bold text-dark">Rincian Penjualan Tiket Tahunan</span>
                                </div>
                                <span class="badge bg-light text-secondary border px-2 py-1 small"><?= count($chartData) ?> Kategori</span>
                            </div>
                            <div class="table-responsive flex-grow-1">
                                <table class="table table-hover align-middle mb-0" id="reportTable">
                                    <thead class="bg-light border-bottom">
                                        <tr>
                                            <th class="ps-4 text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Kategori Tiket</th>
                                            <th class="text-end text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Harga</th>
                                            <th class="text-center text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Terjual</th>
                                            <th class="text-end text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Total Omzet</th>
                                            <th class="pe-4 text-end text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px; width: 120px;">Porsi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($chartData)): ?>
                                        <?php foreach ($chartData as $row): 
                                            $pct = $total_omzet_tahun > 0 ? round(($row['total_sub'] / $total_omzet_tahun) * 100, 1) : 0;
                                            $is_dewasa = (stripos($row['jenis_tiket'], 'dewasa') !== false);
                                        ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge rounded-circle p-2 <?= $is_dewasa ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning' ?>">
                                                            <i class="fa-solid <?= $is_dewasa ? 'fa-user' : 'fa-child' ?>"></i>
                                                        </span>
                                                        <span class="fw-bold text-dark"><?= e($row['jenis_tiket']) ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-end text-muted font-monospace small">
                                                    <?= format_rupiah($row['harga_satuan']) ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark border px-3 py-1 fw-bold">
                                                        <?= number_format($row['total_quantity'], 0, ',', '.') ?> lembar
                                                    </span>
                                                </td>
                                                <td class="text-end fw-bold text-dark font-monospace">
                                                    <?= format_rupiah($row['total_sub']) ?>
                                                </td>
                                                <td class="pe-4 text-end">
                                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                                        <div class="progress flex-grow-1" style="height: 6px; max-width: 60px;">
                                                            <div class="progress-bar <?= $is_dewasa ? 'bg-primary' : 'bg-warning' ?>" style="width: <?= $pct ?>%;"></div>
                                                        </div>
                                                        <span class="small fw-bold text-secondary" style="min-width: 38px;"><?= $pct ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-5">
                                                <div class="py-4">
                                                    <i class="fa-solid fa-receipt text-muted opacity-50 fs-1 mb-2"></i>
                                                    <div class="fw-semibold text-dark">Belum Ada Transaksi</div>
                                                    <p class="text-muted small mb-0">Tidak ada tiket yang terjual pada tahun ini.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                    <?php if (!empty($chartData)): ?>
                                    <tfoot class="border-top bg-light-subtle">
                                        <tr class="fw-bold">
                                            <td class="ps-4 text-uppercase text-secondary small">Total Keseluruhan</td>
                                            <td></td>
                                            <td class="text-center">
                                                <span class="badge bg-primary-subtle text-primary px-3 py-1 fs-6">
                                                    <?= number_format($total_tiket_tahun, 0, ',', '.') ?> lembar
                                                </span>
                                            </td>
                                            <td class="text-end text-success fs-6 font-monospace">
                                                <?= format_rupiah($total_omzet_tahun) ?>
                                            </td>
                                            <td class="pe-4 text-end text-muted small">100%</td>
                                        </tr>
                                    </tfoot>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Doughnut Chart Section -->
                    <div class="col-12 col-lg-5">
                        <div class="modern-card h-100 d-flex flex-column">
                            <div class="modern-card-header d-flex justify-content-between align-items-center py-3 px-4">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-circle p-2 bg-info-subtle text-info"><i class="fa-solid fa-chart-pie"></i></span>
                                    <span class="fw-bold text-dark">Proporsi Penjualan Tiket</span>
                                </div>
                                <span class="badge bg-light text-muted border small">Persentase</span>
                            </div>
                            <div class="p-4 flex-grow-1 d-flex flex-column align-items-center justify-content-center">
                                <?php if ($total_tiket_tahun > 0): ?>
                                    <div style="position: relative; width: 100%; max-width: 210px; height: 210px; margin: auto;">
                                        <canvas id="yearlyChart"></canvas>
                                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                                            <div class="fw-extrabold text-dark fs-3 lh-1"><?= $total_tiket_tahun ?></div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">Total Tiket</div>
                                        </div>
                                    </div>
                                    <!-- Custom Clean Legend Pills -->
                                    <div class="w-100 mt-4 pt-3 border-top d-flex justify-content-around gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="rounded-circle" style="width: 10px; height: 10px; background-color: #0284c7;"></span>
                                            <span class="small fw-semibold text-dark">Dewasa: <strong><?= $dewasa_count ?></strong></span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="rounded-circle" style="width: 10px; height: 10px; background-color: #f59e0b;"></span>
                                            <span class="small fw-semibold text-dark">Anak-Anak: <strong><?= $anak_count ?></strong></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fa-solid fa-chart-pie text-muted opacity-50 fs-1 mb-2"></i>
                                        <div class="fw-semibold text-dark">Grafik Belum Tersedia</div>
                                        <p class="text-muted small mb-0">Data grafik akan muncul saat transaksi tersedia.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <?php include '../../app/partials/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?= public_url('assets/js/bootstrap.js') ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="<?= public_url('js/exportToExcel.js') ?>"></script>
    <script src="<?= public_url('js/print.js') ?>"></script>

    <?php if ($total_tiket_tahun > 0): ?>
    <script>
    const ctx = document.getElementById('yearlyChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Dewasa', 'Anak-Anak'],
                datasets: [{
                    data: [<?= $dewasa_count ?>, <?= $anak_count ?>],
                    backgroundColor: ['#0284c7', '#f59e0b'],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ' + context.label + ': ' + context.raw + ' lembar';
                            }
                        }
                    }
                }
            }
        });
    }
    </script>
    <?php endif; ?>
</body>
</html>
