<?php
/**
 * Modul Log Server & HTTP Traffic Monitor
 * Role: Khusus Super Admin (Level 1)
 * Pemandian Patemon
 */
require_once __DIR__ . '/../../app/config.php';
check_auth([1]);

$active_menu     = 'settings_server_log';
$page_title      = 'Log Server & Trafik HTTP - Pengaturan Sistem';
$page_heading    = 'Log Server & Trafik Jaringan';
$page_subheading = 'Pemantauan real-time aktivitas permintaan HTTP, respons status server, dan latency.';

$csrf_token = get_csrf_token();
$msg_success = '';
$msg_error   = '';

// Handle Clear Logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg_error = 'Token keamanan tidak valid atau telah kadaluarsa.';
    } else {
        $conn->query("TRUNCATE TABLE server_logs");
        log_activity('server_logs', 'hapus', "Membersihkan seluruh riwayat log server.");
        $msg_success = 'Seluruh riwayat log server berhasil dibersihkan.';
    }
}

// Metrics
$stat_total = 0;
$stat_2xx   = 0;
$stat_4xx   = 0;
$stat_5xx   = 0;
$stat_avg_ms = 0;

$q_stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 ELSE 0 END) as cnt_2xx,
        SUM(CASE WHEN status_code >= 400 AND status_code < 500 THEN 1 ELSE 0 END) as cnt_4xx,
        SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) as cnt_5xx,
        AVG(response_time_ms) as avg_ms
    FROM server_logs
");
if ($q_stats) {
    $row_stats = $q_stats->fetch_assoc();
    $stat_total = (int)($row_stats['total'] ?? 0);
    $stat_2xx   = (int)($row_stats['cnt_2xx'] ?? 0);
    $stat_4xx   = (int)($row_stats['cnt_4xx'] ?? 0);
    $stat_5xx   = (int)($row_stats['cnt_5xx'] ?? 0);
    $stat_avg_ms = round((float)($row_stats['avg_ms'] ?? 0), 2);
}

// Filtering
$filter_method = trim($_GET['method'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 40;
$offset = ($page - 1) * $limit;

$where_clauses = [];
$params = [];
$types = '';

if (!empty($filter_method)) {
    $where_clauses[] = "method = ?";
    $params[] = strtoupper($filter_method);
    $types .= 's';
}

if (!empty($filter_status)) {
    if ($filter_status === '2xx') {
        $where_clauses[] = "status_code >= 200 AND status_code < 300";
    } elseif ($filter_status === '3xx') {
        $where_clauses[] = "status_code >= 300 AND status_code < 400";
    } elseif ($filter_status === '4xx') {
        $where_clauses[] = "status_code >= 400 AND status_code < 500";
    } elseif ($filter_status === '5xx') {
        $where_clauses[] = "status_code >= 500";
    } elseif (is_numeric($filter_status)) {
        $where_clauses[] = "status_code = ?";
        $params[] = (int)$filter_status;
        $types .= 'i';
    }
}

if (!empty($filter_search)) {
    $where_clauses[] = "(path LIKE ? OR ip_address LIKE ?)";
    $like = '%' . $filter_search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count Query
$count_query = "SELECT COUNT(*) as total FROM server_logs $where_sql";
if (!empty($params)) {
    $stmt_c = $conn->prepare($count_query);
    $stmt_c->bind_param($types, ...$params);
    $stmt_c->execute();
    $total_filtered = (int)($stmt_c->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt_c->close();
} else {
    $c_res = $conn->query($count_query);
    $total_filtered = (int)($c_res->fetch_assoc()['total'] ?? 0);
}

$total_pages = ceil($total_filtered / $limit);

// Data Query
$data_query = "SELECT id, ip_address, method, path, status_code, response_time_ms, user_agent, created_at FROM server_logs $where_sql ORDER BY id DESC LIMIT ? OFFSET ?";
$data_types = $types . 'ii';
$data_params = array_merge($params, [$limit, $offset]);

$stmt_d = $conn->prepare($data_query);
$stmt_d->bind_param($data_types, ...$data_params);
$stmt_d->execute();
$logs_result = $stmt_d->get_result();

$header_actions = '
    <div class="d-flex gap-2">
        <a href="' . route_url('settings_server_log') . '" class="btn btn-outline-primary" style="border-radius: 10px;" title="Refresh Log">
            <i class="fa-solid fa-arrows-rotate me-1"></i> Refresh
        </a>
        <button type="button" class="btn btn-outline-danger" style="border-radius: 10px;" onclick="confirmClearLogs()">
            <i class="fa-solid fa-broom me-1"></i> Bersihkan Log
        </button>
    </div>
';

require_once __DIR__ . '/../../app/layouts/admin_header.php';
?>

<!-- Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Request</span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill p-2"><i class="fa-solid fa-server"></i></span>
                </div>
                <h3 class="fw-bold mb-1 text-primary"><?= number_format($stat_total) ?></h3>
                <div class="text-muted small">Permintaan terekam</div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Status 2xx (Sukses)</span>
                    <span class="badge bg-success-subtle text-success rounded-pill p-2"><i class="fa-solid fa-check"></i></span>
                </div>
                <h3 class="fw-bold mb-1 text-success"><?= number_format($stat_2xx) ?></h3>
                <div class="text-muted small"><?= $stat_total > 0 ? round(($stat_2xx / $stat_total) * 100, 1) : 0 ?>% dari total trafik</div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Status 4xx (Client Error)</span>
                    <span class="badge bg-warning-subtle text-warning rounded-pill p-2"><i class="fa-solid fa-triangle-exclamation"></i></span>
                </div>
                <h3 class="fw-bold mb-1 text-warning"><?= number_format($stat_4xx) ?></h3>
                <div class="text-muted small">Termasuk percobaan ilegal & 404</div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Rata-rata Respon</span>
                    <span class="badge bg-info-subtle text-info rounded-pill p-2"><i class="fa-solid fa-stopwatch"></i></span>
                </div>
                <h3 class="fw-bold mb-1 text-info"><?= $stat_avg_ms ?> <span class="fs-6 fw-normal text-muted">ms</span></h3>
                <div class="text-muted small">Kecepatan server rata-rata</div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($msg_success)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert" style="border-radius: 12px;">
        <i class="fa-solid fa-circle-check me-2"></i> <?= e($msg_success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($msg_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert" style="border-radius: 12px;">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= e($msg_error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Filter & Log Table -->
<div class="card border-0 shadow-sm" style="border-radius: 16px;">
    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-3">
        <form action="" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Cari path URI atau IP..." value="<?= e($filter_search) ?>">
                </div>
            </div>
            
            <div class="col-6 col-md-2">
                <select name="method" class="form-select">
                    <option value="">Semua Method</option>
                    <option value="GET" <?= $filter_method === 'GET' ? 'selected' : '' ?>>GET</option>
                    <option value="POST" <?= $filter_method === 'POST' ? 'selected' : '' ?>>POST</option>
                    <option value="HEAD" <?= $filter_method === 'HEAD' ? 'selected' : '' ?>>HEAD</option>
                </select>
            </div>
            
            <div class="col-6 col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status Kode</option>
                    <option value="2xx" <?= $filter_status === '2xx' ? 'selected' : '' ?>>2xx (Berhasil)</option>
                    <option value="3xx" <?= $filter_status === '3xx' ? 'selected' : '' ?>>3xx (Redirect)</option>
                    <option value="4xx" <?= $filter_status === '4xx' ? 'selected' : '' ?>>4xx (Client Error / 403 / 404)</option>
                    <option value="5xx" <?= $filter_status === '5xx' ? 'selected' : '' ?>>5xx (Server Error)</option>
                    <option value="200" <?= $filter_status === '200' ? 'selected' : '' ?>>200 OK</option>
                    <option value="302" <?= $filter_status === '302' ? 'selected' : '' ?>>302 Found</option>
                    <option value="403" <?= $filter_status === '403' ? 'selected' : '' ?>>403 Forbidden</option>
                    <option value="404" <?= $filter_status === '404' ? 'selected' : '' ?>>404 Not Found</option>
                </select>
            </div>
            
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1" style="border-radius: 10px;">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <?php if (!empty($filter_method) || !empty($filter_status) || !empty($filter_search)): ?>
                    <a href="<?= route_url('settings_server_log') ?>" class="btn btn-outline-secondary" style="border-radius: 10px;">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4" style="width: 85px;">Method</th>
                        <th style="width: 90px;">Status</th>
                        <th>Path URI Permintaan</th>
                        <th style="width: 140px;">Alamat IP</th>
                        <th style="width: 100px;">Latency</th>
                        <th style="width: 180px;">Waktu Request</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs_result && $logs_result->num_rows > 0): ?>
                        <?php while ($log = $logs_result->fetch_assoc()): 
                            $method = strtoupper($log['method']);
                            $method_badge = 'bg-secondary';
                            if ($method === 'GET') $method_badge = 'bg-primary-subtle text-primary border border-primary-subtle';
                            elseif ($method === 'POST') $method_badge = 'bg-success-subtle text-success border border-success-subtle';
                            elseif ($method === 'DELETE') $method_badge = 'bg-danger-subtle text-danger border border-danger-subtle';
                            
                            $code = (int)$log['status_code'];
                            $code_badge = 'bg-secondary';
                            if ($code >= 200 && $code < 300) $code_badge = 'badge-modern badge-modern-success';
                            elseif ($code >= 300 && $code < 400) $code_badge = 'badge-modern badge-modern-info';
                            elseif ($code >= 400 && $code < 500) $code_badge = 'badge-modern badge-modern-warning';
                            elseif ($code >= 500) $code_badge = 'badge-modern badge-modern-danger';
                        ?>
                        <tr>
                            <td class="ps-4">
                                <span class="badge <?= $method_badge ?> font-monospace fw-bold px-2 py-1" style="font-size: 0.75rem; border-radius: 6px;">
                                    <?= e($method) ?>
                                </span>
                            </td>
                            <td>
                                <span class="<?= $code_badge ?>" style="font-size: 0.775rem;">
                                    <?= $code ?>
                                </span>
                            </td>
                            <td>
                                <div class="font-monospace text-truncate text-break" style="max-width: 480px;" title="<?= e($log['path']) ?>">
                                    <?= e($log['path']) ?>
                                </div>
                                <?php if (!empty($log['user_agent'])): ?>
                                    <div class="text-muted small text-truncate" style="max-width: 480px; font-size: 0.75rem;" title="<?= e($log['user_agent']) ?>">
                                        <?= e(substr($log['user_agent'], 0, 70)) . (strlen($log['user_agent']) > 70 ? '...' : '') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="font-monospace small text-muted">
                                    <i class="fa-solid fa-network-wired me-1 opacity-50"></i> <?= e($log['ip_address']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.75rem;">
                                    <?= round((float)$log['response_time_ms'], 1) ?> ms
                                </span>
                            </td>
                            <td class="text-muted small">
                                <?= date('d M Y H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-network-wired fs-2 text-secondary mb-2 d-block"></i>
                                Tidak ada catatan log server yang sesuai dengan filter.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <div class="card-footer bg-transparent border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="text-muted small">Menampilkan <?= min($total_filtered, $offset + 1) ?> - <?= min($total_filtered, $offset + $limit) ?> dari <?= $total_filtered ?> log</span>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php 
                $start_p = max(1, $page - 3);
                $end_p   = min($total_pages, $page + 3);
                $query_params = $_GET;
                ?>
                <?php if ($start_p > 1): ?>
                    <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($query_params, ['page' => 1])) ?>">1</a></li>
                    <?php if ($start_p > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_p; $i <= $end_p; $i++): ?>
                    <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($query_params, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($end_p < $total_pages): ?>
                    <?php if ($end_p < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($query_params, ['page' => $total_pages])) ?>"><?= $total_pages ?></a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Clear Log Form -->
<form id="clearLogForm" action="" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
    <input type="hidden" name="action" value="clear">
</form>

<?php
$extra_js = '
<script>
function confirmClearLogs() {
    Swal.fire({
        title: "Bersihkan Seluruh Log Server?",
        text: "Semua riwayat trafik HTTP yang tersimpan di database akan dihapus permanen.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Bersihkan!",
        cancelButtonText: "Batal",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById("clearLogForm").submit();
        }
    });
}
</script>
';

require_once __DIR__ . '/../../app/layouts/admin_footer.php';
?>
