<?php
require '../../app/config.php';

// Hak akses: Super Admin (1), Admin (2), dan Staf Kasir (3)
check_auth([1, 2, 3]);

// Cek permintaan AJAX kalender (Standar SIM-ASET)
if (isset($_GET['ajax_calendar'])) {
    $cal_year  = (int)($_GET['cal_year'] ?? date('Y'));
    $cal_month = (int)($_GET['cal_month'] ?? date('n'));
    include __DIR__ . '/../../app/partials/calendar_widget.php';
    exit();
}

$user_id    = (int)($_SESSION['id_user'] ?? 0);
$user_level = (int)($_SESSION['level'] ?? 0);
$user_name  = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Petugas';

// Ambil profil lengkap dari basis data
$u_stmt = $conn->prepare("SELECT id_user, nama, username, email, no_telepon, avatar, level FROM users WHERE id_user = ? LIMIT 1");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$current_user_profile = $u_stmt->get_result()->fetch_assoc();
$u_stmt->close();

$role_name_display = get_role_name($user_level);

$avatar_path = '';
if (!empty($current_user_profile['avatar'])) {
    $avatar_file = __DIR__ . '/../../../public/img/avatars/' . $current_user_profile['avatar'];
    if (file_exists($avatar_file)) {
        $avatar_path = public_url('img/avatars/' . $current_user_profile['avatar']);
    }
}

$active_menu = 'dashboard';
if ($user_level === 3) {
    $page_title      = 'Dashboard Staf Kasir - Pemandian Patemon';
    $page_heading    = 'Dashboard Staf Kasir';
    $page_subheading = 'Pantau transaksi loket tiket yang Anda layani secara personal.';
} else {
    $page_title      = 'Dashboard Ringkasan Eksekutif - Pemandian Patemon';
    $page_heading    = 'Dashboard Ringkasan Eksekutif';
    $page_subheading = 'Pantau aktivitas operasional loket, pendapatan kasir, prediksi hari libur, dan performa wisata.';
}

// Tombol header dihilangkan sesuai Feedback-2 Point 1
$header_actions = '';

// 1. Total Omzet Penjualan (Khusus Staf = Omzet Transaksi Diri Sendiri)
if ($user_level === 3) {
    $stmt_omzet = $conn->prepare("SELECT SUM(total_harga) as total_omzet FROM transaksi WHERE status = 'done' AND id_user = ?");
    $stmt_omzet->bind_param("i", $user_id);
    $stmt_omzet->execute();
    $row_total = $stmt_omzet->get_result()->fetch_assoc();
    $stmt_omzet->close();
} else {
    $res_total = $conn->query("SELECT SUM(total_harga) as total_omzet FROM transaksi WHERE status = 'done'");
    $row_total = $res_total ? $res_total->fetch_assoc() : null;
}
$total_omzet = (float)($row_total['total_omzet'] ?? 0);

// 2. Total Pengguna Terdaftar (Untuk Staf: Total Transaksi Pribadi yang Dilayani)
if ($user_level === 3) {
    $stmt_u = $conn->prepare("SELECT COUNT(*) as total_users FROM transaksi WHERE id_user = ?");
    $stmt_u->bind_param("i", $user_id);
    $stmt_u->execute();
    $row_user = $stmt_u->get_result()->fetch_assoc();
    $stmt_u->close();
} else {
    $res_user = $conn->query("SELECT COUNT(*) as total_users FROM users");
    $row_user = $res_user ? $res_user->fetch_assoc() : null;
}
$total_users = (int)($row_user['total_users'] ?? 0);

// 3. Statistik Penjualan 12 Bulan (Tahun Berjalan)
$labels_bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$transaksi_per_bulan = array_fill(0, 12, 0);

if ($user_level === 3) {
    $stmt_m = $conn->prepare("SELECT MONTH(tgl_pemesanan) as bln, COUNT(*) as jml FROM transaksi WHERE YEAR(tgl_pemesanan) = YEAR(CURDATE()) AND id_user = ? GROUP BY MONTH(tgl_pemesanan)");
    $stmt_m->bind_param("i", $user_id);
    $stmt_m->execute();
    $res_monthly = $stmt_m->get_result();
} else {
    $res_monthly = $conn->query("SELECT MONTH(tgl_pemesanan) as bln, COUNT(*) as jml FROM transaksi WHERE YEAR(tgl_pemesanan) = YEAR(CURDATE()) GROUP BY MONTH(tgl_pemesanan)");
}

if ($res_monthly) {
    while ($m = $res_monthly->fetch_assoc()) {
        $idx = (int)$m['bln'] - 1;
        if ($idx >= 0 && $idx < 12) {
            $transaksi_per_bulan[$idx] = (int)$m['jml'];
        }
    }
}
if ($user_level === 3 && isset($stmt_m)) {
    $stmt_m->close();
}

// 4. Rekap Penjualan Tiket Seluruh Kategori
$ticket_labels = [];
$ticket_counts = [];
$total_tiket_terjual = 0;

if ($user_level === 3) {
    $stmt_t = $conn->prepare("SELECT dt.jenis_tiket, SUM(dt.quantity) as total_qty FROM detail_transaksi dt INNER JOIN transaksi t ON dt.id_transaksi = t.id_transaksi WHERE t.id_user = ? GROUP BY dt.jenis_tiket");
    $stmt_t->bind_param("i", $user_id);
    $stmt_t->execute();
    $res_tiket = $stmt_t->get_result();
} else {
    $res_tiket = $conn->query("SELECT jenis_tiket, SUM(quantity) as total_qty, SUM(sub_total) as total_sub FROM detail_transaksi GROUP BY jenis_tiket");
}

if ($res_tiket) {
    while ($t = $res_tiket->fetch_assoc()) {
        $ticket_labels[] = $t['jenis_tiket'];
        $qty = (int)$t['total_qty'];
        $ticket_counts[] = $qty;
        $total_tiket_terjual += $qty;
    }
}
if ($user_level === 3 && isset($stmt_t)) {
    $stmt_t->close();
}
if (empty($ticket_labels)) {
    $ticket_labels = ['Tiket'];
    $ticket_counts = [0];
}

// 5. Ambil 5 Transaksi Terkini
$recent_trans = [];
$res_recent = $conn->query("SELECT t.id_transaksi, t.nama_pemesan, t.total_harga, t.tgl_pemesanan, t.status, t.metode_pembayaran, u.nama as user_nama 
                            FROM transaksi t 
                            LEFT JOIN users u ON t.id_user = u.id_user 
                            ORDER BY t.id_transaksi DESC LIMIT 5");
if ($res_recent) {
    while ($r = $res_recent->fetch_assoc()) {
        $recent_trans[] = $r;
    }
}

// 6. Integrasi Hari Libur Nasional & Musim Liburan (Kemendesa API Cache)
$current_year = date('Y');
$holidays_data = get_national_holidays($current_year);
$holidays_list = $holidays_data['data'] ?? [];

$today_date = date('Y-m-d');
$upcoming_holidays = [];
$today_holiday = null;

foreach ($holidays_list as $h) {
    if ($h['date'] === $today_date) {
        $today_holiday = $h;
    }
    if ($h['date'] >= $today_date) {
        $upcoming_holidays[] = $h;
    }
}
// Ambil maksimal 4 libur nasional mendatang
$upcoming_holidays = array_slice($upcoming_holidays, 0, 4);

$extra_css = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';

require '../../app/layouts/admin_header.php';
?>

<div class="page-content">
    <!-- SIM-ASET Style Wide Profile Banner Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden position-relative" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 50%, #075985 100%); color: #ffffff;">
        <div class="position-absolute end-0 top-0 bottom-0 d-none d-md-block opacity-10 pe-4" style="pointer-events: none;">
            <i class="fa-solid fa-water-ladder" style="font-size: 11rem; line-height: 1;"></i>
        </div>
        <div class="card-body p-4 position-relative">
            <div class="row align-items-center g-3">
                <div class="col-auto">
                    <?php if (!empty($avatar_path)): ?>
                        <img src="<?= e($avatar_path) ?>" alt="Avatar" class="rounded-circle shadow" style="width: 76px; height: 76px; object-fit: cover; border: 3.5px solid rgba(255,255,255,0.85);">
                    <?php else: ?>
                        <div class="rounded-circle shadow d-flex align-items-center justify-content-center fw-bold" style="width: 76px; height: 76px; background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(8px); border: 3.5px solid rgba(255,255,255,0.85); font-size: 2rem; color: #ffffff;">
                            <?= strtoupper(substr($user_name, 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span class="badge fw-bold px-2.5 py-1" style="font-size: 0.75rem; letter-spacing: 0.03em; background: #ffffff !important; color: #0369a1 !important;">
                            <i class="fa-solid <?= ($user_level === 1) ? 'fa-shield-halved text-purple' : (($user_level === 2) ? 'fa-user-tie text-primary' : 'fa-cash-register text-success') ?> me-1"></i>
                            <?= e($role_name_display) ?>
                        </span>
                        <span class="badge" style="background: rgba(255,255,255,0.2); font-size: 0.75rem;">
                            ID Akun: #<?= $user_id ?>
                        </span>
                    </div>
                    <h3 class="fw-bold mb-1 text-white" style="letter-spacing: -0.02em;">Selamat Datang, <?= e($current_user_profile['nama'] ?? $user_name) ?>!</h3>
                    <div class="d-flex align-items-center gap-3 flex-wrap text-white-50 small mt-2">
                        <span><i class="fa-regular fa-envelope me-1 text-white"></i> <?= e($current_user_profile['email'] ?? '-') ?></span>
                        <span><i class="fa-solid fa-phone me-1 text-white"></i> <?= e($current_user_profile['no_telepon'] ?? '-') ?></span>
                        <span><i class="fa-regular fa-calendar-check me-1 text-white"></i> Hari Ini: <?= format_tanggal_indonesia(date('Y-m-d'), true) ?></span>
                    </div>
                </div>
                <div class="col-12 col-md-auto text-md-end mt-2 mt-md-0">
                    <a href="<?= route_url('profile') ?>" class="btn fw-bold px-3 py-2 shadow-sm" style="border-radius: 10px; background: #ffffff !important; color: #0284c7 !important;">
                        <i class="fa-solid fa-user-pen me-1"></i> Kelola Profil
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Today Holiday / High Season Alert Banner -->
    <?php if ($today_holiday): ?>
        <div class="alert alert-warning d-flex align-items-center gap-3 p-3 rounded-4 shadow-sm mb-4 border-2 border-warning">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="fa-solid fa-umbrella-beach"></i>
            </div>
            <div class="flex-grow-1">
                <span class="badge bg-danger text-white mb-1 text-uppercase">Peringatan High Season Loket</span>
                <h5 class="fw-bold mb-0 text-dark">Hari Ini: <?= e($today_holiday['name']) ?></h5>
                <p class="text-secondary small mb-0">Prediksi lonjakan pengunjung loket meningkat 200–300%. Pastikan staf loket dan tim pengawas siap siaga.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4 Metrics Overview -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card">
                <div class="metric-icon-box green">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-label"><?= ($user_level === 3) ? 'Omzet Transaksi Anda' : 'Total Omzet Lunas' ?></div>
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
                    <div class="metric-label"><?= ($user_level === 3) ? 'Tiket Anda Jual' : 'Total Tiket Terjual' ?></div>
                    <div class="metric-value"><?= number_format($total_tiket_terjual, 0, ',', '.') ?> <small class="text-muted" style="font-size: 0.85rem; font-weight: 500;">lbr</small></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card">
                <div class="metric-icon-box amber">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-label">Libur Nasional (<?= $current_year ?>)</div>
                    <div class="metric-value text-warning"><?= count($holidays_list) ?> <small class="text-muted" style="font-size: 0.85rem; font-weight: 500;">hari</small></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card">
                <div class="metric-icon-box purple">
                    <i class="fa-solid <?= ($user_level === 3) ? 'fa-receipt' : 'fa-users' ?>"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-label"><?= ($user_level === 3) ? 'Total Transaksi Anda' : 'Pengguna Terdaftar' ?></div>
                    <div class="metric-value"><?= number_format($total_users, 0, ',', '.') ?> <small class="text-muted" style="font-size: 0.85rem; font-weight: 500;"><?= ($user_level === 3) ? 'trx' : 'akun' ?></small></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts & Calendar Grid (Decoupled with align-items-start) -->
    <div class="row g-4 mb-4 align-items-start">
        <!-- Chart Transaksi Bulanan -->
        <div class="col-12 col-xl-8">
            <div class="modern-card">
                <div class="modern-card-header d-flex justify-content-between align-items-center">
                    <div class="fw-bold fs-6" style="color: #0f172a;">
                        <i class="fa-solid fa-chart-simple text-primary me-2"></i> Grafik Transaksi Bulanan Tahun <?= $current_year ?> <?= ($user_level === 3) ? '(Transaksi Anda)' : '' ?>
                    </div>
                    <span class="badge badge-modern-primary">Real-time</span>
                </div>
                <div class="modern-card-body">
                    <canvas id="monthlyChart" height="120"></canvas>
                </div>
            </div>
        </div>

        <!-- Kalender Standar SIM-ASET (Hari Libur Mendatang Dihilangkan Sesuai Feedback-2 Point 1) -->
        <div class="col-12 col-xl-4">
            <div class="modern-card">
                <div class="modern-card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold" style="color: #0f172a;">
                        <i class="fa-regular fa-calendar text-primary me-2"></i>Kalender & Info
                    </h6>
                    <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.75rem;">Kemendesa API</span>
                </div>
                <div class="modern-card-body text-center p-3">
                    <div class="text-start" id="admin-calendar-container">
                        <?php 
                        $cal_year  = (int)date('Y');
                        $cal_month = (int)date('n');
                        include __DIR__ . '/../../app/partials/calendar_widget.php'; 
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation Standard SIM-ASET -->
    <div class="row g-3 mb-4">
        <?php if ($user_level === 3): ?>
            <div class="col-6 col-md-3">
                <a href="<?= route_url('kasir') ?>" class="text-decoration-none">
                    <div class="modern-card p-3 text-center h-100 border hover-shadow" style="transition: all 0.2s;">
                        <i class="fa-solid fa-cash-register text-primary fs-2 mb-2"></i>
                        <div class="fw-bold text-dark small">Kasir Loket (POS)</div>
                        <div class="text-muted" style="font-size: 0.725rem;">Layani penjualan tiket</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= route_url('laporan') ?>" class="text-decoration-none">
                    <div class="modern-card p-3 text-center h-100 border hover-shadow" style="transition: all 0.2s;">
                        <i class="fa-solid fa-file-invoice-dollar text-success fs-2 mb-2"></i>
                        <div class="fw-bold text-dark small">Laporan Penjualan Saya</div>
                        <div class="text-muted" style="font-size: 0.725rem;">Rekap & cetak transaksi</div>
                    </div>
                </a>
            </div>
        <?php else: ?>
            <div class="col-6 col-md-3">
                <a href="<?= route_url('admin_tiket') ?>" class="text-decoration-none">
                    <div class="modern-card p-3 text-center h-100 border hover-shadow" style="transition: all 0.2s;">
                        <i class="fa-solid fa-tags text-primary fs-2 mb-2"></i>
                        <div class="fw-bold text-dark small">Kelola Tarif Tiket</div>
                        <div class="text-muted" style="font-size: 0.725rem;">Atur harga & kategori</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="<?= route_url('laporan') ?>" class="text-decoration-none">
                    <div class="modern-card p-3 text-center h-100 border hover-shadow" style="transition: all 0.2s;">
                        <i class="fa-solid fa-file-invoice-dollar text-success fs-2 mb-2"></i>
                        <div class="fw-bold text-dark small">Laporan Terpadu</div>
                        <div class="text-muted" style="font-size: 0.725rem;">Harian, Mingguan, Bulanan</div>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <div class="col-6 col-md-3">
            <a href="<?= route_url('guide') ?>" class="text-decoration-none">
                <div class="modern-card p-3 text-center h-100 border hover-shadow" style="transition: all 0.2s;">
                    <i class="fa-solid fa-book-bookmark text-warning fs-2 mb-2"></i>
                    <div class="fw-bold text-dark small">Buku Panduan</div>
                    <div class="text-muted" style="font-size: 0.725rem;">Buku manual kasir & POS</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?= route_url('version') ?>" class="text-decoration-none">
                <div class="modern-card p-3 text-center h-100 border hover-shadow" style="transition: all 0.2s;">
                    <i class="fa-solid fa-circle-info text-purple fs-2 mb-2" style="color: #7c3aed;"></i>
                    <div class="fw-bold text-dark small">Informasi Versi</div>
                    <div class="text-muted" style="font-size: 0.725rem;">Info rilis & audit teknis</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Tabel 5 Transaksi Terkini dinonaktifkan sementara (Sesuai Evaluasi Feedback-2 Point 1) -->
</div>

<?php
$dashboard_url = route_url('dashboard');
$extra_js = '
<script>
const DASHBOARD_URL = ' . json_encode($dashboard_url) . ';

// Modern Bar Chart with Dynamic Theme Adaptation
const chartEl = document.getElementById("monthlyChart");
if (chartEl) {
    const ctxBar = chartEl.getContext("2d");
    const barGradient = ctxBar.createLinearGradient(0, 0, 0, 300);
    barGradient.addColorStop(0, "#0284c7");
    barGradient.addColorStop(1, "#38bdf8");

    function getChartThemeColors() {
        const isDark = document.documentElement.classList.contains("theme-dark") || document.body.classList.contains("theme-dark");
        return {
            grid: isDark ? "rgba(255, 255, 255, 0.08)" : "#f1f5f9",
            text: isDark ? "#94a3b8" : "#64748b"
        };
    }

    const initialColors = getChartThemeColors();

    const monthlyChart = new Chart(ctxBar, {
        type: "bar",
        data: {
            labels: ' . json_encode($labels_bulan) . ',
            datasets: [{
                label: "Jumlah Transaksi",
                data: ' . json_encode($transaksi_per_bulan) . ',
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
                    ticks: { precision: 0, font: { family: "Inter" }, color: initialColors.text },
                    grid: { color: initialColors.grid }
                },
                x: {
                    ticks: { font: { family: "Inter" }, color: initialColors.text },
                    grid: { display: false }
                }
            }
        }
    });

    window.addEventListener("patemon_theme_changed", function(e) {
        if (monthlyChart && monthlyChart.options && monthlyChart.options.scales) {
            const colors = getChartThemeColors();
            if (monthlyChart.options.scales.y) {
                monthlyChart.options.scales.y.grid.color = colors.grid;
                monthlyChart.options.scales.y.ticks.color = colors.text;
            }
            if (monthlyChart.options.scales.x) {
                monthlyChart.options.scales.x.ticks.color = colors.text;
            }
            monthlyChart.update();
        }
    });
}

// SIM-ASET Calendar Async Navigation
window.loadCalendar = function(year, month) {
    document.querySelectorAll("#calendar-loading").forEach(el => el.classList.remove("d-none"));
    fetch(`${DASHBOARD_URL}?cal_year=${year}&cal_month=${month}&ajax_calendar=1`, {
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(res => res.text())
    .then(html => {
        document.querySelectorAll(".calendar-widget").forEach(el => {
            el.parentElement.innerHTML = html;
        });
        // Re-init tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    })
    .catch(err => {
        console.error("Error loading calendar:", err);
        document.querySelectorAll("#calendar-loading").forEach(el => el.classList.add("d-none"));
    });
};

document.addEventListener("DOMContentLoaded", function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
';
require '../../app/layouts/admin_footer.php';
?>
