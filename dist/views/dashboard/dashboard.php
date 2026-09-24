<?php
require '../../app/config.php';

// Hanya Administrator (Level 1) yang mengelola dashboard ringkasan statistik
check_auth([1]);

$active_menu = 'dashboard';
$base_view = '..';

// 1. Total Omzet Penjualan
$res_total = $conn->query("SELECT SUM(total_harga) as total_omzet FROM transaksi WHERE status = 'done'");
$row_total = $res_total ? $res_total->fetch_assoc() : null;
$total_omzet = (float)($row_total['total_omzet'] ?? 0);

// 2. Total Pengguna Terdaftar
$res_user = $conn->query("SELECT COUNT(*) as total_users FROM users");
$row_user = $res_user ? $res_user->fetch_assoc() : null;
$total_users = (int)($row_user['total_users'] ?? 0);

// 3. Statistik Penjualan 12 Bulan (Tahun Berjalan)
$labels_bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$transaksi_per_bulan = array_fill(0, 12, 0);

$res_monthly = $conn->query("SELECT MONTH(tgl_pemesanan) as bln, COUNT(*) as jml FROM transaksi WHERE YEAR(tgl_pemesanan) = YEAR(CURDATE()) GROUP BY MONTH(tgl_pemesanan)");
if ($res_monthly) {
    while ($m = $res_monthly->fetch_assoc()) {
        $idx = (int)$m['bln'] - 1;
        if ($idx >= 0 && $idx < 12) {
            $transaksi_per_bulan[$idx] = (int)$m['jml'];
        }
    }
}

// 4. Rekap Penjualan Tiket per Jenis (Dewasa & Anak)
$qty_dewasa = 0; $subtotal_dewasa = 0;
$qty_anak   = 0; $subtotal_anak   = 0;

$res_tiket = $conn->query("SELECT jenis_tiket, SUM(quantity) as total_qty, SUM(sub_total) as total_sub FROM detail_transaksi GROUP BY jenis_tiket");
if ($res_tiket) {
    while ($t = $res_tiket->fetch_assoc()) {
        if ($t['jenis_tiket'] === 'Dewasa') {
            $qty_dewasa = (int)$t['total_qty'];
            $subtotal_dewasa = (float)$t['total_sub'];
        } elseif ($t['jenis_tiket'] === 'Anak-Anak') {
            $qty_anak = (int)$t['total_qty'];
            $subtotal_anak = (float)$t['total_sub'];
        }
    }
}
$total_tiket_terjual = $qty_dewasa + $qty_anak;

// 5. Ambil 5 Transaksi Terkini
$recent_trans = [];
$res_recent = $conn->query("SELECT t.id_transaksi, t.nama_pemesan, t.total_harga, t.tgl_pemesanan, t.status, t.metode_pembayaran 
                            FROM transaksi t 
                            ORDER BY t.id_transaksi DESC LIMIT 5");
if ($res_recent) {
    while ($r = $res_recent->fetch_assoc()) {
        $recent_trans[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Pemandian Patemon</title>

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
            <header class="mb-4 d-flex justify-content-between align-items-center">
                <a href="#" class="burger-btn d-block d-xl-none text-dark">
                    <i class="fa-solid fa-bars fs-3"></i>
                </a>
                <div class="d-flex align-items-center gap-3 ms-auto">
                    <div class="text-end d-none d-sm-block">
                        <div class="fw-bold" style="font-size: 0.95rem; color: #0f172a;"><?= e($_SESSION['nama']) ?></div>
                        <small class="text-muted">Administrator Loket</small>
                    </div>
                    <div style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, #0284c7, #38bdf8); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center;">
                        <?= strtoupper(substr($_SESSION['nama'] ?? 'A', 0, 1)) ?>
                    </div>
                </div>
            </header>

            <div class="page-heading mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h2 class="fw-bold text-dark mb-1" style="font-size: 1.75rem;">Dashboard Ringkasan</h2>
                        <p class="text-muted mb-0">Pantau aktivitas penjualan tiket, pendapatan loket, dan statistik operasional.</p>
                    </div>
                    <div>
                        <a href="<?= views_url('transaksi/transaksi.php') ?>" class="btn btn-brand">
                            <i class="fa-solid fa-cash-register me-1"></i> Buka Kasir Loket
                        </a>
                    </div>
                </div>
            </div>

            <div class="page-content">
                <!-- 4 Metrics Overview -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="metric-card">
                            <div class="metric-icon-box green">
                                <i class="fa-solid fa-sack-dollar"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Total Omzet Lunas</div>
                                <div class="metric-value text-success" style="font-size: 1.35rem;"><?= format_rupiah($total_omzet) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="metric-card">
                            <div class="metric-icon-box blue">
                                <i class="fa-solid fa-ticket"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Tiket Dewasa</div>
                                <div class="metric-value"><?= number_format($qty_dewasa, 0, ',', '.') ?> <small style="font-size: 0.85rem; font-weight: 500; color: #64748b;">lbr</small></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="metric-card">
                            <div class="metric-icon-box amber">
                                <i class="fa-solid fa-child-reaching"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Tiket Anak-Anak</div>
                                <div class="metric-value"><?= number_format($qty_anak, 0, ',', '.') ?> <small style="font-size: 0.85rem; font-weight: 500; color: #64748b;">lbr</small></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="metric-card">
                            <div class="metric-icon-box purple">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="metric-content">
                                <div class="metric-label">Pengguna Terdaftar</div>
                                <div class="metric-value"><?= number_format($total_users, 0, ',', '.') ?> <small style="font-size: 0.85rem; font-weight: 500; color: #64748b;">akun</small></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row g-4 mb-4">
                    <div class="col-12 col-xl-8">
                        <div class="modern-card">
                            <div class="modern-card-header">
                                <div class="fw-bold" style="font-size: 1.05rem; color: #0f172a;">
                                    <i class="fa-solid fa-chart-simple text-primary me-2"></i> Grafik Transaksi Bulanan (<?= date('Y') ?>)
                                </div>
                                <span class="badge badge-modern-primary">Real-time</span>
                            </div>
                            <div class="modern-card-body">
                                <canvas id="monthlyChart" height="120"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-4">
                        <div class="modern-card h-100">
                            <div class="modern-card-header">
                                <div class="fw-bold" style="font-size: 1.05rem; color: #0f172a;">
                                    <i class="fa-solid fa-chart-pie text-info me-2"></i> Proporsi Kategori Tiket
                                </div>
                            </div>
                            <div class="modern-card-body d-flex flex-column align-items-center justify-content-center">
                                <div style="max-width: 240px; width: 100%;">
                                    <canvas id="ticketPieChart" height="200"></canvas>
                                </div>
                                <div class="mt-3 text-center">
                                    <div class="text-muted" style="font-size: 0.875rem;">Total Tiket Terjual Keseluruhan:</div>
                                    <div class="fw-bold" style="font-size: 1.25rem; color: #0f172a;"><?= number_format($total_tiket_terjual, 0, ',', '.') ?> lembar</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="modern-card">
                            <div class="modern-card-header">
                                <div class="fw-bold" style="font-size: 1.05rem; color: #0f172a;">
                                    <i class="fa-solid fa-receipt text-warning me-2"></i> 5 Transaksi Terkini
                                </div>
                                <a href="<?= views_url('transaksi/transaksi.php') ?>" class="btn btn-sm btn-soft-primary">
                                    Lihat Semua Transaksi <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                            <div class="table-responsive">
                                <table class="table-modern">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama Pemesan</th>
                                            <th>Tanggal</th>
                                            <th>Metode</th>
                                            <th>Total Bayar</th>
                                            <th>Status</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_trans)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4 text-muted">Belum ada transaksi tercatat.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_trans as $rt): ?>
                                                <tr>
                                                    <td><strong class="text-primary">#<?= (int)$rt['id_transaksi'] ?></strong></td>
                                                    <td class="fw-semibold"><?= e($rt['nama_pemesan']) ?></td>
                                                    <td class="text-muted"><?= date('d M Y', strtotime($rt['tgl_pemesanan'])) ?></td>
                                                    <td>
                                                        <span class="badge" style="background: #f1f5f9; color: #334155; font-weight: 600;">
                                                            <?= strtoupper(e($rt['metode_pembayaran'] ?? 'TUNAI')) ?>
                                                        </span>
                                                    </td>
                                                    <td class="fw-bold text-dark"><?= format_rupiah($rt['total_harga']) ?></td>
                                                    <td>
                                                        <?php if ($rt['status'] === 'done'): ?>
                                                            <span class="badge-modern badge-modern-success">
                                                                <i class="fa-solid fa-circle-check"></i> Selesai
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge-modern badge-modern-warning">
                                                                <i class="fa-solid fa-clock"></i> <?= e($rt['status']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="<?= views_url('tiket/nota.php') ?>?id_transaksi=<?= (int)$rt['id_transaksi'] ?>" class="btn btn-sm btn-soft-primary" title="Cetak Nota" target="_blank">
                                                            <i class="fa-solid fa-print"></i> Nota
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <?php include '../../app/partials/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?= public_url('assets/js/bootstrap.js') ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <script>
    // 1. Modern Bar Chart
    const ctxBar = document.getElementById('monthlyChart').getContext('2d');
    const barGradient = ctxBar.createLinearGradient(0, 0, 0, 300);
    barGradient.addColorStop(0, '#0284c7');
    barGradient.addColorStop(1, '#38bdf8');

    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels_bulan) ?>,
            datasets: [{
                label: 'Jumlah Transaksi',
                data: <?= json_encode($transaksi_per_bulan) ?>,
                backgroundColor: barGradient,
                borderRadius: 8,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, font: { family: 'Plus Jakarta Sans' } },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    ticks: { font: { family: 'Plus Jakarta Sans' } },
                    grid: { display: false }
                }
            }
        }
    });

    // 2. Modern Doughnut Chart
    const ctxPie = document.getElementById('ticketPieChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: ['Dewasa', 'Anak-Anak'],
            datasets: [{
                data: [<?= $qty_dewasa ?>, <?= $qty_anak ?>],
                backgroundColor: ['#0284c7', '#10b981'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: { font: { family: 'Plus Jakarta Sans', weight: '600' }, boxWidth: 14 }
                }
            },
            cutout: '70%'
        }
    });
    </script>
</body>
</html>
