<?php
/**
 * Modul Laporan Retribusi & Penjualan Terpadu
 * Pemandian Patemon
 * Mendukung filter Harian, Mingguan, Bulanan, dan Tahunan (Feedback-2 Poin 3)
 * Mendukung isolasi data otomatis untuk Staf Kasir (Feedback-2 Poin 9)
 */
require_once __DIR__ . '/../../app/config.php';

// Hak Akses: Super Admin (1), Admin (2), dan Staf Kasir (3)
check_auth([1, 2, 3]);

$user_id    = (int)($_SESSION['id_user'] ?? 0);
$user_level = (int)($_SESSION['level'] ?? 0);
$user_name  = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Petugas';

// Filter Tipe: harian, mingguan, bulanan, tahunan (default: bulanan)
$tipe = $_GET['tipe'] ?? 'bulanan';
if (!in_array($tipe, ['harian', 'mingguan', 'bulanan', 'tahunan'], true)) {
    $tipe = 'bulanan';
}

// Parameter Filter Tanggal
$filter_date  = $_GET['date'] ?? date('Y-m-d');
$filter_start = $_GET['start'] ?? date('Y-m-d', strtotime('-6 days'));
$filter_end   = $_GET['end'] ?? date('Y-m-d');
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Isolasi Staf: Staf (Level 3) hanya melihat transaksinya sendiri
if ($user_level === 3) {
    $selected_staff = $user_id;
} else {
    $selected_staff = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
}

// Ambil daftar petugas untuk filter Admin / Super Admin
$staff_list = [];
if ($user_level !== 3) {
    $res_staff = $conn->query("SELECT id_user, nama, username, level FROM users WHERE level IN (1, 2, 3) ORDER BY nama ASC");
    if ($res_staff) {
        while ($s = $res_staff->fetch_assoc()) {
            $staff_list[] = $s;
        }
    }
}

// Susun Kondisi SQL Berdasarkan Filter Periode
$where_clauses = ["t.status = 'done'", "t.deleted_at IS NULL"];
$params = [];
$types = "";

if ($tipe === 'harian') {
    $where_clauses[] = "t.tgl_pemesanan = ?";
    $params[] = $filter_date;
    $types .= "s";
    $periode_label = "Tanggal: " . format_tanggal_indonesia($filter_date, true);
} elseif ($tipe === 'mingguan') {
    $where_clauses[] = "t.tgl_pemesanan BETWEEN ? AND ?";
    $params[] = $filter_start;
    $params[] = $filter_end;
    $types .= "ss";
    $periode_label = "Rentang: " . format_tanggal_indonesia($filter_start) . " s.d. " . format_tanggal_indonesia($filter_end);
} elseif ($tipe === 'tahunan') {
    $where_clauses[] = "YEAR(t.tgl_pemesanan) = ?";
    $params[] = $filter_year;
    $types .= "i";
    $periode_label = "Tahun Anggaran: " . $filter_year;
} else {
    // Default Bulanan
    $tipe = 'bulanan';
    $where_clauses[] = "DATE_FORMAT(t.tgl_pemesanan, '%Y-%m') = ?";
    $params[] = $filter_month;
    $types .= "s";
    $periode_label = "Bulan: " . format_bulan_indonesia($filter_month);
}

// Tambahkan Filter Petugas Kasir jika dipilih / jika Staf
if ($selected_staff > 0) {
    $where_clauses[] = "t.id_user = ?";
    $params[] = $selected_staff;
    $types .= "i";
}

$where_sql = implode(" AND ", $where_clauses);

// 1. Ambil Rekapitulasi Per Kategori Tiket
$query_cat = "
    SELECT dt.jenis_tiket, SUM(dt.quantity) as total_qty, SUM(dt.sub_total) as total_sub
    FROM detail_transaksi dt
    INNER JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
    WHERE {$where_sql}
    GROUP BY dt.jenis_tiket
    ORDER BY total_sub DESC
";
$stmt_cat = $conn->prepare($query_cat);
if (!empty($types)) {
    $stmt_cat->bind_param($types, ...$params);
}
$stmt_cat->execute();
$res_cat = $stmt_cat->get_result();

$rekap_kategori = [];
$total_omzet = 0;
$total_lembar_tiket = 0;

while ($c = $res_cat->fetch_assoc()) {
    $rekap_kategori[] = $c;
    $total_omzet += (float)$c['total_sub'];
    $total_lembar_tiket += (int)$c['total_qty'];
}
$stmt_cat->close();

// 2. Ambil Daftar Transaksi Rinci
$query_trans = "
    SELECT t.id_transaksi, t.id_user, t.tgl_pemesanan, t.total_harga, t.metode_pembayaran, t.status, 
           u.nama as user_nama
    FROM transaksi t
    LEFT JOIN users u ON t.id_user = u.id_user
    WHERE {$where_sql}
    ORDER BY t.tgl_pemesanan DESC, t.id_transaksi DESC
";
$stmt_tr = $conn->prepare($query_trans);
if (!empty($types)) {
    $stmt_tr->bind_param($types, ...$params);
}
$stmt_tr->execute();
$res_tr = $stmt_tr->get_result();
$transaksi_list = [];
$total_transaksi = 0;

while ($tr = $res_tr->fetch_assoc()) {
    $transaksi_list[] = $tr;
    $total_transaksi++;
}
$stmt_tr->close();

// Ambil item tiket detail untuk transaksi yang ditampilkan
$trans_ids = array_column($transaksi_list, 'id_transaksi');
$trans_items = [];
if (!empty($trans_ids)) {
    $in_ids = implode(',', array_map('intval', $trans_ids));
    $res_items = $conn->query("SELECT id_transaksi, jenis_tiket, quantity FROM detail_transaksi WHERE id_transaksi IN ($in_ids)");
    if ($res_items) {
        while ($it = $res_items->fetch_assoc()) {
            $trans_items[$it['id_transaksi']][] = $it['jenis_tiket'] . ' (' . $it['quantity'] . 'x)';
        }
    }
}

$rata_rata_transaksi = $total_transaksi > 0 ? ($total_omzet / $total_transaksi) : 0;

// Setup Halaman
$active_menu = 'laporan';
if ($user_level === 3) {
    $page_title      = 'Laporan Penjualan Saya - Pemandian Patemon';
    $page_heading    = 'Laporan Penjualan Saya';
    $page_subheading = 'Rekapitulasi penjualan tiket loket atas nama ' . e($user_name) . '.';
} else {
    $page_title      = 'Laporan Transaksi & Retribusi Terpadu - Pemandian Patemon';
    $page_heading    = 'Laporan Transaksi & Retribusi Terpadu';
    $page_subheading = 'Pantau rekapitulasi pendapatan tiket dan retribusi secara harian, mingguan, bulanan, maupun tahunan.';
}

require_once __DIR__ . '/../../app/layouts/admin_header.php';
?>

<div class="page-content">
    <!-- Header Filter & Action Card -->
    <div class="modern-card p-4 mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
            <div>
                <h3 class="fw-bold text-dark mb-1" style="letter-spacing: -0.02em;"><?= e($page_heading) ?></h3>
                <p class="text-muted small mb-0">
                    <i class="fa-regular fa-calendar-check text-primary me-1"></i> Periode Aktif: <strong class="text-dark"><?= e($periode_label) ?></strong>
                    <?php if ($selected_staff > 0 && $user_level !== 3): ?>
                        &bull; Petugas: <span class="badge bg-light text-primary border font-monospace">Petugas #<?= $selected_staff ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Action Buttons: PDF & Excel (Sesuai Feedback-2 Poin 2 & 3: Tombol PDF membuka dokumen inline di browser) -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php
                $pdf_params = [
                    'tipe'     => $tipe,
                    'date'     => $filter_date,
                    'start'    => $filter_start,
                    'end'      => $filter_end,
                    'month'    => (int)date('n', strtotime($filter_month . '-01')),
                    'year'     => ($tipe === 'tahunan') ? $filter_year : (int)date('Y', strtotime($filter_month . '-01')),
                    'staff_id' => $selected_staff
                ];
                $pdf_url = route_url('laporan_preview', $pdf_params);
                ?>
                <a href="<?= $pdf_url ?>" class="btn btn-soft-danger px-3 py-2 fw-semibold" title="Buka Dokumen PDF Standar">
                    <i class="fa-solid fa-file-pdf me-1"></i> PDF
                </a>
                <button onclick="exportToExcel('tableLaporan', 'Laporan_Patemon_<?= $tipe ?>')" class="btn btn-soft-success px-3 py-2 fw-semibold">
                    <i class="fa-solid fa-file-excel me-1"></i> Excel
                </button>
            </div>
        </div>

        <hr class="my-3">

        <!-- Navigasi Tabs Tipe Periode (Harian, Mingguan, Bulanan, Tahunan) -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <ul class="nav nav-pills gap-1" id="laporanTabs">
                <li class="nav-item">
                    <a class="nav-link <?= ($tipe === 'harian') ? 'active' : '' ?>" href="?tipe=harian&date=<?= e($filter_date) ?><?= $selected_staff ? '&staff_id='.$selected_staff : '' ?>" style="border-radius: 8px; font-weight: 600; font-size: 0.85rem;">
                        <i class="fa-regular fa-calendar-check me-1"></i> Harian
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($tipe === 'mingguan') ? 'active' : '' ?>" href="?tipe=mingguan&start=<?= e($filter_start) ?>&end=<?= e($filter_end) ?><?= $selected_staff ? '&staff_id='.$selected_staff : '' ?>" style="border-radius: 8px; font-weight: 600; font-size: 0.85rem;">
                        <i class="fa-solid fa-calendar-week me-1"></i> Mingguan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($tipe === 'bulanan') ? 'active' : '' ?>" href="?tipe=bulanan&month=<?= e($filter_month) ?><?= $selected_staff ? '&staff_id='.$selected_staff : '' ?>" style="border-radius: 8px; font-weight: 600; font-size: 0.85rem;">
                        <i class="fa-regular fa-calendar-days me-1"></i> Bulanan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($tipe === 'tahunan') ? 'active' : '' ?>" href="?tipe=tahunan&year=<?= e($filter_year) ?><?= $selected_staff ? '&staff_id='.$selected_staff : '' ?>" style="border-radius: 8px; font-weight: 600; font-size: 0.85rem;">
                        <i class="fa-regular fa-calendar me-1"></i> Tahunan
                    </a>
                </li>
            </ul>

            <!-- Dynamic Filter Form Based on Selected Tab -->
            <form method="GET" action="" class="d-flex align-items-center gap-2 flex-wrap">
                <input type="hidden" name="tipe" value="<?= e($tipe) ?>">

                <?php if ($tipe === 'harian'): ?>
                    <div class="input-group input-group-sm" style="width: auto;">
                        <span class="input-group-text bg-white"><i class="fa-regular fa-calendar text-primary"></i></span>
                        <input type="date" name="date" class="form-control form-control-sm" value="<?= e($filter_date) ?>" required>
                    </div>
                <?php elseif ($tipe === 'mingguan'): ?>
                    <div class="d-flex align-items-center gap-1">
                        <input type="date" name="start" class="form-control form-control-sm" value="<?= e($filter_start) ?>" required>
                        <span class="text-muted small">s.d.</span>
                        <input type="date" name="end" class="form-control form-control-sm" value="<?= e($filter_end) ?>" required>
                    </div>
                <?php elseif ($tipe === 'tahunan'): ?>
                    <select name="year" class="form-select form-select-sm" style="width: 120px;">
                        <?php for ($y = (int)date('Y'); $y >= 2022; $y--): ?>
                            <option value="<?= $y ?>" <?= ($filter_year === $y) ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                <?php else: ?>
                    <div class="input-group input-group-sm" style="width: auto;">
                        <span class="input-group-text bg-white"><i class="fa-regular fa-calendar-days text-primary"></i></span>
                        <input type="month" name="month" class="form-control form-control-sm" value="<?= e($filter_month) ?>" required>
                    </div>
                <?php endif; ?>

                <?php if ($user_level !== 3): ?>
                    <select name="staff_id" class="form-select form-select-sm" style="width: 170px;">
                        <option value="0">Semua Petugas Kasir</option>
                        <?php foreach ($staff_list as $st): ?>
                            <option value="<?= (int)$st['id_user'] ?>" <?= ($selected_staff === (int)$st['id_user']) ? 'selected' : '' ?>>
                                <?= e($st['nama']) ?> (<?= get_role_name($st['level']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <button type="submit" class="btn btn-brand btn-sm px-3">
                    <i class="fa-solid fa-filter me-1"></i> Terapkan
                </button>
            </form>
        </div>
    </div>

    <!-- 4 Summary KPI Metrics -->
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
                    <div class="metric-label">Total Lembar Tiket</div>
                    <div class="metric-value"><?= number_format($total_lembar_tiket, 0, ',', '.') ?> <small style="font-size: 0.85rem; font-weight: 500; color: #64748b;">lbr</small></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card">
                <div class="metric-icon-box purple">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-label">Total Transaksi Selesai</div>
                    <div class="metric-value"><?= number_format($total_transaksi, 0, ',', '.') ?> <small style="font-size: 0.85rem; font-weight: 500; color: #64748b;">trx</small></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="metric-card">
                <div class="metric-icon-box amber">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div class="metric-content">
                    <div class="metric-label">Rata-rata Nilai Trx</div>
                    <div class="metric-value text-warning" style="font-size: 1.25rem;"><?= format_rupiah($rata_rata_transaksi) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rekapitulasi Per Kategori Tiket -->
    <div class="modern-card mb-4">
        <div class="modern-card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark">
                <i class="fa-solid fa-layer-group text-primary me-2"></i> Rekapitulasi Penjualan Per Kategori Tiket
            </span>
            <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.75rem;">
                <?= count($rekap_kategori) ?> Kategori Aktif
            </span>
        </div>
        <div class="table-responsive">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th>Kategori / Jenis Tiket</th>
                        <th class="text-center" style="width: 180px;">Volume Penjualan</th>
                        <th class="text-end" style="width: 220px;">Subtotal Penerimaan</th>
                        <th class="text-center" style="width: 140px;">Kontribusi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rekap_kategori)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                Tidak ada penjualan tiket pada periode yang dipilih.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = 1;
                        foreach ($rekap_kategori as $rk): 
                            $pct = $total_omzet > 0 ? round(($rk['total_sub'] / $total_omzet) * 100, 1) : 0;
                            $cat_icon = get_ticket_icon($rk['jenis_tiket']);
                        ?>
                            <tr>
                                <td class="text-center text-muted fw-semibold"><?= $no++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="category-icon-box" style="width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.95rem;">
                                            <i class="fa-solid <?= e($cat_icon) ?>"></i>
                                        </div>
                                        <div class="fw-bold text-dark"><?= e($rk['jenis_tiket']) ?></div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <strong class="text-primary fs-6"><?= number_format($rk['total_qty'], 0, ',', '.') ?></strong>
                                    <small class="text-muted ms-1">lembar</small>
                                </td>
                                <td class="text-end fw-bold text-dark"><?= format_rupiah($rk['total_sub']) ?></td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <div class="progress flex-grow-1" style="height: 6px; max-width: 60px;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                        <span class="small fw-bold text-muted"><?= $pct ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light fw-bold" style="border-top: 2px solid var(--border-color, #cbd5e1);">
                            <td colspan="2" class="text-center text-uppercase">Total Penerimaan</td>
                            <td class="text-center text-primary fs-6"><?= number_format($total_lembar_tiket, 0, ',', '.') ?> lembar</td>
                            <td class="text-end text-success fs-5"><?= format_rupiah($total_omzet) ?></td>
                            <td class="text-center">100%</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Log Riwayat Transaksi Rinci -->
    <div class="modern-card">
        <div class="modern-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-bold text-dark">
                <i class="fa-solid fa-list-check text-primary me-2"></i> Log Transaksi Lengkap
            </span>
            <div class="input-icon-group" style="width: 250px;">
                <i class="fa-solid fa-search input-icon"></i>
                <input type="text" id="laporanSearch" class="form-control-modern form-control-sm" placeholder="Cari transaksi / pemesan...">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table-modern" id="tableLaporan">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th style="width: 170px;">Kode Referensi</th>
                        <th>Tanggal Transaksi</th>
                        <th>Petugas Kasir</th>
                        <th>Nama Pemesan</th>
                        <th>Rincian Tiket</th>
                        <th>Metode Bayar</th>
                        <th class="text-end">Total Bayar</th>
                        <th class="text-center" style="width: 80px;">Nota</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transaksi_list)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-regular fa-folder-open fs-2 mb-2 d-block text-secondary"></i>
                                Belum ada transaksi tercatat untuk filter ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = 1;
                        foreach ($transaksi_list as $t): 
                            $kode = format_kode_transaksi($t['id_transaksi'], $t['tgl_pemesanan']);
                            $kasir_nama = $t['user_nama'] ?: 'Loket Petugas';
                            $pemesan_nama = $t['user_nama'] ?: 'Pengunjung Loket';
                            $items_str = isset($trans_items[$t['id_transaksi']]) ? implode(', ', $trans_items[$t['id_transaksi']]) : 'Tiket Masuk';
                        ?>
                            <tr>
                                <td class="text-center text-muted fw-semibold"><?= $no++ ?></td>
                                <td>
                                    <strong class="text-primary font-monospace" style="font-size: 0.85rem;"><?= e($kode) ?></strong>
                                    <small class="text-muted d-block">ID: #<?= (int)$t['id_transaksi'] ?></small>
                                </td>
                                <td class="text-muted small"><?= date('d M Y', strtotime($t['tgl_pemesanan'])) ?></td>
                                <td class="fw-semibold">
                                    <span class="badge badge-payment-method">
                                        <i class="fa-solid fa-user-tag me-1 text-primary"></i> <?= e($kasir_nama) ?>
                                    </span>
                                </td>
                                <td class="fw-medium text-dark"><?= e($pemesan_nama) ?></td>
                                <td class="small text-muted" style="max-width: 220px;"><?= e($items_str) ?></td>
                                <td>
                                    <span class="badge badge-payment-method" style="font-weight: 600;">
                                        <?= strtoupper(e($t['metode_pembayaran'] ?? 'TUNAI')) ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold text-dark"><?= format_rupiah($t['total_harga']) ?></td>
                                <td class="text-center">
                                    <a href="<?= route_url('nota', ['id' => (int)$t['id_transaksi']]) ?>" class="btn btn-sm btn-soft-primary btn-action-icon" title="Lihat Nota">
                                        <i class="fa-solid fa-receipt"></i>
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

<?php
$extra_js = '
<script src="' . public_url('js/exportToExcel.js') . '"></script>
<script>
setupTableSearch("laporanSearch", "tableLaporan");
</script>
';
require_once __DIR__ . '/../../app/layouts/admin_footer.php';
?>
