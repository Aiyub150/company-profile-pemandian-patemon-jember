<?php
/**
 * Modul Log History & Sampah Transaksi (Audit Trail & Soft-Delete Recovery)
 * Role: Khusus Super Admin (Level 1)
 * Pemandian Patemon
 * Mendukung Soft Delete: Transaksi, Pengguna (User), dan Kritik & Saran (Ulasan)
 */
require_once __DIR__ . '/../../app/config.php';
check_auth([1]);

$active_menu     = 'settings_history_log';
$page_title      = 'Log History & Sampah Sistem - Pengaturan Sistem';
$page_heading    = 'Log History & Pemulihan Data';
$page_subheading = 'Audit trail perubahan data sistem serta pemantauan & pemulihan data yang dihapus (Soft Delete).';

$csrf_token = get_csrf_token();
$current_tab = $_GET['tab'] ?? 'audit';
if (!in_array($current_tab, ['audit', 'trash', 'trash_user', 'trash_ulasan'], true)) {
    $current_tab = 'audit';
}

$msg_success = '';
$msg_error   = '';

if (isset($_GET['restored']) && $_GET['restored'] == 1) {
    $msg_success = 'Data berhasil dipulihkan kembali ke status aktif.';
}

// -------------------------------------------------------------
// POST HANDLERS: PURGE PERMANENT
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg_error = 'Token CSRF tidak valid atau sesi telah kedaluwarsa.';
    } else {
        $action = $_POST['action'];

        // 1. Purge Transaksi
        if ($action === 'purge_transaction') {
            $trx_id = (int)($_POST['id_transaksi'] ?? 0);
            if ($trx_id > 0) {
                $del_d = $conn->prepare("DELETE FROM detail_transaksi WHERE id_transaksi = ?");
                $del_d->bind_param("i", $trx_id);
                $del_d->execute();
                $del_d->close();

                $stmt = $conn->prepare("DELETE FROM transaksi WHERE id_transaksi = ? AND deleted_at IS NOT NULL");
                $stmt->bind_param("i", $trx_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    log_activity('PURGE', 'transaksi', "Menghapus transaksi ID #{$trx_id} secara permanen dari basis data.");
                    $msg_success = "Transaksi ID #{$trx_id} berhasil dihapus secara permanen.";
                } else {
                    $msg_error = "Gagal menghapus transaksi dari tempat sampah.";
                }
                $stmt->close();
            }
        }
        // 2. Purge Pengguna (User)
        elseif ($action === 'purge_user') {
            $u_id = (int)($_POST['id_user'] ?? 0);
            if ($u_id > 0) {
                // Proteksi integritas keuangan: cegah penghapusan user yang memiliki transaksi
                $chk_tx = $conn->prepare("SELECT COUNT(*) as cnt FROM transaksi WHERE id_user = ?");
                $chk_tx->bind_param("i", $u_id);
                $chk_tx->execute();
                $tx_c = (int)($chk_tx->get_result()->fetch_assoc()['cnt'] ?? 0);
                $chk_tx->close();

                if ($tx_c > 0) {
                    $msg_error = "Pengguna tidak dapat dihapus permanen karena memiliki {$tx_c} riwayat transaksi loket/keuangan. Pengguna tetap disimpan di tempat sampah sebagai arsip audit.";
                } else {
                    $stmt = $conn->prepare("DELETE FROM users WHERE id_user = ? AND deleted_at IS NOT NULL");
                    $stmt->bind_param("i", $u_id);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        log_activity('PURGE', 'user', "Menghapus akun pengguna ID #{$u_id} secara permanen dari basis data.");
                        $msg_success = "Akun pengguna ID #{$u_id} berhasil dihapus secara permanen.";
                    } else {
                        $msg_error = "Gagal menghapus pengguna dari tempat sampah.";
                    }
                    $stmt->close();
                }
            }
        }
        // 3. Purge Kritik & Saran (Ulasan)
        elseif ($action === 'purge_ulasan') {
            $ul_id = (int)($_POST['id_ulasan'] ?? 0);
            if ($ul_id > 0) {
                $stmt = $conn->prepare("DELETE FROM ulasan WHERE id_ulasan = ? AND deleted_at IS NOT NULL");
                $stmt->bind_param("i", $ul_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    log_activity('PURGE', 'ulasan', "Menghapus ulasan ID #{$ul_id} secara permanen dari basis data.");
                    $msg_success = "Ulasan ID #{$ul_id} berhasil dihapus secara permanen.";
                } else {
                    $msg_error = "Gagal menghapus ulasan dari tempat sampah.";
                }
                $stmt->close();
            }
        }
    }
}

// -------------------------------------------------------------
// TAB 1: AUDIT TRAIL LOGS
// -------------------------------------------------------------
$audit_search = trim($_GET['search'] ?? '');
$audit_module = trim($_GET['module'] ?? '');
$audit_action = trim($_GET['action_type'] ?? '');
$audit_page   = max(1, (int)($_GET['page'] ?? 1));
$audit_limit  = 30;
$audit_offset = ($audit_page - 1) * $audit_limit;

$a_where = [];
$a_params = [];
$a_types  = '';

if (!empty($audit_module)) {
    $a_where[] = "module = ?";
    $a_params[] = $audit_module;
    $a_types .= 's';
}
if (!empty($audit_action)) {
    $a_where[] = "action = ?";
    $a_params[] = $audit_action;
    $a_types .= 's';
}
if (!empty($audit_search)) {
    $a_where[] = "(description LIKE ? OR username LIKE ?)";
    $like = '%' . $audit_search . '%';
    $a_params[] = $like;
    $a_params[] = $like;
    $a_types .= 'ss';
}

$a_where_sql = !empty($a_where) ? "WHERE " . implode(" AND ", $a_where) : "";

$q_count_audit = "SELECT COUNT(*) as total FROM activity_logs $a_where_sql";
if (!empty($a_params)) {
    $stmt_ca = $conn->prepare($q_count_audit);
    $stmt_ca->bind_param($a_types, ...$a_params);
    $stmt_ca->execute();
    $total_audit = (int)($stmt_ca->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt_ca->close();
} else {
    $ca_res = $conn->query($q_count_audit);
    $total_audit = (int)($ca_res->fetch_assoc()['total'] ?? 0);
}
$audit_total_pages = ceil($total_audit / $audit_limit);

$q_data_audit = "SELECT id, id_user, username, action, module, description, ip_address, created_at FROM activity_logs $a_where_sql ORDER BY id DESC LIMIT ? OFFSET ?";
$audit_d_types = $a_types . 'ii';
$audit_d_params = array_merge($a_params, [$audit_limit, $audit_offset]);

$stmt_da = $conn->prepare($q_data_audit);
$stmt_da->bind_param($audit_d_types, ...$audit_d_params);
$stmt_da->execute();
$audit_result = $stmt_da->get_result();

$distinct_modules = [];
$dm_res = $conn->query("SELECT DISTINCT module FROM activity_logs ORDER BY module ASC");
if ($dm_res) {
    while ($m_row = $dm_res->fetch_assoc()) {
        if (!empty($m_row['module'])) $distinct_modules[] = $m_row['module'];
    }
}

// -------------------------------------------------------------
// TAB 2: TRASH TRANSACTIONS (SOFT DELETED)
// -------------------------------------------------------------
$q_trash_count = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(total_harga), 0) as total_nominal FROM transaksi WHERE deleted_at IS NOT NULL");
$trash_stat = $q_trash_count ? $q_trash_count->fetch_assoc() : ['cnt' => 0, 'total_nominal' => 0];
$total_trash_items   = (int)($trash_stat['cnt'] ?? 0);
$total_trash_nominal = (float)($trash_stat['total_nominal'] ?? 0);

$trash_search = trim($_GET['trash_search'] ?? '');
$trash_sql = "
    SELECT t.*, u.nama as nama_user, u.username 
    FROM transaksi t 
    LEFT JOIN users u ON t.id_user = u.id_user 
    WHERE t.deleted_at IS NOT NULL 
";
if (!empty($trash_search)) {
    $like_tr = '%' . $trash_search . '%';
    $trash_sql .= " AND (t.id_transaksi LIKE '{$like_tr}' OR t.nama_pemesan LIKE '{$like_tr}' OR u.nama LIKE '{$like_tr}' OR t.metode_pembayaran LIKE '{$like_tr}')";
}
$trash_sql .= " ORDER BY t.deleted_at DESC";
$trash_result = $conn->query($trash_sql);

// -------------------------------------------------------------
// TAB 3: TRASH USERS (SOFT DELETED PENGGUNA)
// -------------------------------------------------------------
$q_tu_count = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE deleted_at IS NOT NULL");
$total_trash_users = (int)($q_tu_count ? $q_tu_count->fetch_assoc()['cnt'] : 0);

$tu_search = trim($_GET['user_search'] ?? '');
$tu_sql = "SELECT * FROM users WHERE deleted_at IS NOT NULL";
if (!empty($tu_search)) {
    $like_u = '%' . $tu_search . '%';
    $tu_sql .= " AND (nama LIKE '{$like_u}' OR username LIKE '{$like_u}' OR email LIKE '{$like_u}')";
}
$tu_sql .= " ORDER BY deleted_at DESC";
$trash_users_res = $conn->query($tu_sql);

// -------------------------------------------------------------
// TAB 4: TRASH ULASAN (SOFT DELETED KRITIK & SARAN)
// -------------------------------------------------------------
$q_tul_count = $conn->query("SELECT COUNT(*) as cnt FROM ulasan WHERE deleted_at IS NOT NULL");
$total_trash_ulasan = (int)($q_tul_count ? $q_tul_count->fetch_assoc()['cnt'] : 0);

$tul_search = trim($_GET['ulasan_search'] ?? '');
$tul_sql = "SELECT * FROM ulasan WHERE deleted_at IS NOT NULL";
if (!empty($tul_search)) {
    $like_ul = '%' . $tul_search . '%';
    $tul_sql .= " AND (username LIKE '{$like_ul}' OR email LIKE '{$like_ul}' OR ulasan LIKE '{$like_ul}')";
}
$tul_sql .= " ORDER BY deleted_at DESC";
$trash_ulasan_res = $conn->query($tul_sql);

require_once __DIR__ . '/../../app/layouts/admin_header.php';
?>

<!-- Tab Navigation Pills -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <ul class="nav nav-pills" id="historyTab" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= ($current_tab === 'audit') ? 'active' : '' ?> fw-semibold px-4 py-2" href="<?= route_url('settings_history_log', ['tab' => 'audit']) ?>" style="border-radius: 10px;">
                <i class="fa-solid fa-list-check me-2"></i> Log Aktivitas (Audit Trail)
                <span class="badge bg-light text-dark ms-2"><?= number_format($total_audit) ?></span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= ($current_tab === 'trash') ? 'active' : '' ?> fw-semibold px-4 py-2" href="<?= route_url('settings_history_log', ['tab' => 'trash']) ?>" style="border-radius: 10px;">
                <i class="fa-solid fa-receipt me-2 text-danger"></i> Sampah Transaksi
                <?php if ($total_trash_items > 0): ?>
                    <span class="badge bg-danger ms-2"><?= number_format($total_trash_items) ?></span>
                <?php else: ?>
                    <span class="badge bg-light text-secondary ms-2">0</span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= ($current_tab === 'trash_user') ? 'active' : '' ?> fw-semibold px-4 py-2" href="<?= route_url('settings_history_log', ['tab' => 'trash_user']) ?>" style="border-radius: 10px;">
                <i class="fa-solid fa-users-slash me-2 text-warning"></i> Sampah Pengguna
                <?php if ($total_trash_users > 0): ?>
                    <span class="badge bg-warning text-dark ms-2"><?= number_format($total_trash_users) ?></span>
                <?php else: ?>
                    <span class="badge bg-light text-secondary ms-2">0</span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= ($current_tab === 'trash_ulasan') ? 'active' : '' ?> fw-semibold px-4 py-2" href="<?= route_url('settings_history_log', ['tab' => 'trash_ulasan']) ?>" style="border-radius: 10px;">
                <i class="fa-solid fa-comments me-2 text-info"></i> Sampah Kritik & Saran
                <?php if ($total_trash_ulasan > 0): ?>
                    <span class="badge bg-info ms-2"><?= number_format($total_trash_ulasan) ?></span>
                <?php else: ?>
                    <span class="badge bg-light text-secondary ms-2">0</span>
                <?php endif; ?>
            </a>
        </li>
    </ul>
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

<?php if ($current_tab === 'audit'): ?>
    <!-- TAB 1 CONTENT: AUDIT TRAIL -->
    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-3">
            <form action="" method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="audit">
                
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Cari keterangan / username..." value="<?= e($audit_search) ?>">
                    </div>
                </div>
                
                <div class="col-6 col-md-3">
                    <select name="module" class="form-select">
                        <option value="">Semua Modul</option>
                        <?php foreach ($distinct_modules as $mod): ?>
                            <option value="<?= e($mod) ?>" <?= $audit_module === $mod ? 'selected' : '' ?>><?= e(ucfirst($mod)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-6 col-md-2">
                    <select name="action_type" class="form-select">
                        <option value="">Semua Aksi</option>
                        <option value="tambah" <?= $audit_action === 'tambah' ? 'selected' : '' ?>>Tambah</option>
                        <option value="update" <?= $audit_action === 'update' ? 'selected' : '' ?>>Update / Edit</option>
                        <option value="hapus" <?= $audit_action === 'hapus' ? 'selected' : '' ?>>Hapus</option>
                        <option value="SOFT_DELETE" <?= $audit_action === 'SOFT_DELETE' ? 'selected' : '' ?>>Soft Delete</option>
                        <option value="RESTORE" <?= $audit_action === 'RESTORE' ? 'selected' : '' ?>>Restore / Pulihkan</option>
                        <option value="PURGE" <?= $audit_action === 'PURGE' ? 'selected' : '' ?>>Hapus Permanen</option>
                        <option value="login" <?= $audit_action === 'login' ? 'selected' : '' ?>>Login</option>
                    </select>
                </div>
                
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" style="border-radius: 10px;">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <?php if (!empty($audit_search) || !empty($audit_module) || !empty($audit_action)): ?>
                        <a href="<?= route_url('settings_history_log', ['tab' => 'audit']) ?>" class="btn btn-outline-secondary" style="border-radius: 10px;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                        <tr>
                            <th class="ps-4" style="width: 170px;">Waktu Kejadian</th>
                            <th style="width: 140px;">Pengguna</th>
                            <th style="width: 120px;">Aksi</th>
                            <th style="width: 130px;">Modul</th>
                            <th>Keterangan Aktivitas</th>
                            <th class="pe-4 text-end" style="width: 130px;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($audit_result && $audit_result->num_rows > 0): ?>
                            <?php while ($log = $audit_result->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 text-muted small">
                                        <i class="fa-regular fa-clock me-1"></i>
                                        <?= date('d M Y, H:i:s', strtotime($log['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($log['username'] ?? 'Tamu / Anonim') ?></div>
                                        <?php if (!empty($log['id_user'])): ?>
                                            <span class="badge bg-light text-secondary border" style="font-size: 0.7rem;">UID: <?= (int)$log['id_user'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $act = strtoupper($log['action']);
                                        $act_badge = 'bg-secondary';
                                        if (str_contains($act, 'TAMBAH') || str_contains($act, 'CREATE')) $act_badge = 'bg-success';
                                        elseif (str_contains($act, 'UPDATE') || str_contains($act, 'EDIT')) $act_badge = 'bg-warning text-dark';
                                        elseif (str_contains($act, 'HAPUS') || str_contains($act, 'DELETE') || str_contains($act, 'PURGE')) $act_badge = 'bg-danger';
                                        elseif (str_contains($act, 'RESTORE')) $act_badge = 'bg-info text-dark';
                                        elseif (str_contains($act, 'LOGIN')) $act_badge = 'bg-primary';
                                        ?>
                                        <span class="badge <?= $act_badge ?> px-2 py-1" style="font-size: 0.75rem; border-radius: 6px;">
                                            <?= e($log['action']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= e(strtoupper($log['module'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-secondary small" style="line-height: 1.5;">
                                            <?= e($log['description']) ?>
                                        </div>
                                    </td>
                                    <td class="pe-4 text-end text-muted small font-monospace">
                                        <?= e($log['ip_address']) ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open fs-2 text-secondary mb-2 d-block"></i>
                                    Tidak ada catatan audit yang sesuai dengan filter.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($audit_total_pages > 1): ?>
            <div class="card-footer bg-transparent border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">
                    Menampilkan <?= ($audit_offset + 1) ?> - <?= min($audit_offset + $audit_limit, $total_audit) ?> dari <?= number_format($total_audit) ?> log aktivitas
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $audit_total_pages; $p++): ?>
                            <?php if ($p == 1 || $p == $audit_total_pages || abs($p - $audit_page) <= 2): ?>
                                <li class="page-item <?= $p == $audit_page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= route_url('settings_history_log', array_merge($_GET, ['page' => $p])) ?>">
                                        <?= $p ?>
                                    </a>
                                </li>
                            <?php elseif (abs($p - $audit_page) == 3): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($current_tab === 'trash'): ?>
    <!-- TAB 2 CONTENT: TRASH TRANSACTIONS (SOFT DELETED) -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
                <div class="card-body p-4 d-flex align-items-center gap-3">
                    <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-4 fs-3">
                        <i class="fa-solid fa-trash-can"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Sampah Transaksi</div>
                        <h3 class="mb-0 fw-bold text-dark"><?= number_format($total_trash_items) ?> <small class="fs-6 text-muted">Trx</small></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
                <div class="card-body p-4 d-flex align-items-center gap-3">
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-4 fs-3">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Nominal Nilai Terhapus</div>
                        <h3 class="mb-0 fw-bold text-dark">Rp <?= number_format($total_trash_nominal, 0, ',', '.') ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-recycle me-2 text-danger"></i> Daftar Transaksi di Tempat Sampah
                </h5>
                <form action="" method="GET" class="d-flex gap-2">
                    <input type="hidden" name="tab" value="trash">
                    <div class="input-group">
                        <input type="text" name="trash_search" class="form-control" placeholder="Cari ID / Nama Pemesan..." value="<?= e($trash_search) ?>" style="border-radius: 8px 0 0 8px;">
                        <button type="submit" class="btn btn-primary" style="border-radius: 0 8px 8px 0;">
                            <i class="fa-solid fa-search"></i>
                        </button>
                    </div>
                    <?php if (!empty($trash_search)): ?>
                        <a href="<?= route_url('settings_history_log', ['tab' => 'trash']) ?>" class="btn btn-outline-secondary" style="border-radius: 8px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                        <tr>
                            <th class="ps-4" style="width: 130px;">Kode Trx</th>
                            <th>Tanggal Pemesanan</th>
                            <th>Pemesan / Petugas</th>
                            <th>Metode Pembayaran</th>
                            <th>Total Nominal</th>
                            <th>Dihapus Pada</th>
                            <th class="text-center pe-4 action-column" style="width: 170px;">Aksi Pemulihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($trash_result && $trash_result->num_rows > 0): ?>
                            <?php while ($trx = $trash_result->fetch_assoc()): 
                                $kode_trx = 'TRX-' . str_pad($trx['id_transaksi'], 5, '0', STR_PAD_LEFT);
                                $pemesan  = !empty($trx['nama_pemesan']) ? $trx['nama_pemesan'] : ($trx['nama_user'] ?? 'Loket Kasir');
                                $trx_date = $trx['tgl_pemesanan'] ?? $trx['created_at'] ?? $trx['deleted_at'] ?? null;
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge bg-light text-dark border font-monospace fw-bold">
                                        <?= e($kode_trx) ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <i class="fa-regular fa-calendar me-1"></i>
                                    <?= !empty($trx_date) ? date('d M Y', strtotime($trx_date)) : '-' ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($pemesan) ?></div>
                                    <?php if (!empty($trx['no_telepon'])): ?>
                                        <div class="text-muted small"><?= e($trx['no_telepon']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border">
                                        <i class="fa-solid fa-credit-card me-1"></i> <?= e(ucwords(str_replace('_', ' ', $trx['metode_pembayaran'] ?? 'Loket'))) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark">
                                        Rp <?= number_format($trx['total_harga'], 0, ',', '.') ?>
                                    </span>
                                </td>
                                <td class="text-danger small fw-semibold">
                                    <i class="fa-regular fa-calendar-xmark me-1"></i>
                                    <?= !empty($trx['deleted_at']) ? date('d M Y, H:i', strtotime($trx['deleted_at'])) : '-' ?>
                                </td>
                                <td class="text-center pe-4 action-column">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-success px-2 py-1" title="Pulihkan Transaksi Kembali Aktif" onclick="confirmRestore(<?= (int)$trx['id_transaksi'] ?>, '<?= e($kode_trx) ?>')" style="border-radius: 8px; font-size: 0.8rem;">
                                            <i class="fa-solid fa-trash-can-arrow-up me-1"></i> Pulihkan
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-action-icon" title="Hapus Permanen Dari Database" onclick="confirmPurge(<?= (int)$trx['id_transaksi'] ?>, '<?= e($kode_trx) ?>')">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-box-open fs-2 text-secondary mb-2 d-block"></i>
                                    <?= !empty($trash_search) ? 'Tidak ditemukan transaksi terhapus yang cocok dengan pencarian.' : 'Tempat sampah transaksi kosong. Tidak ada data transaksi yang dihapus.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($current_tab === 'trash_user'): ?>
    <!-- TAB 3 CONTENT: TRASH USERS (SOFT DELETED) -->
    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-users-slash me-2 text-warning"></i> Pengguna yang Dinonaktifkan (Tempat Sampah)
                </h5>
                <form action="" method="GET" class="d-flex gap-2">
                    <input type="hidden" name="tab" value="trash_user">
                    <div class="input-group">
                        <input type="text" name="user_search" class="form-control" placeholder="Cari nama / username / email..." value="<?= e($tu_search) ?>" style="border-radius: 8px 0 0 8px;">
                        <button type="submit" class="btn btn-primary" style="border-radius: 0 8px 8px 0;">
                            <i class="fa-solid fa-search"></i>
                        </button>
                    </div>
                    <?php if (!empty($tu_search)): ?>
                        <a href="<?= route_url('settings_history_log', ['tab' => 'trash_user']) ?>" class="btn btn-outline-secondary" style="border-radius: 8px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                        <tr>
                            <th class="ps-4" style="width: 70px;">ID</th>
                            <th>Pengguna</th>
                            <th>Kontak</th>
                            <th>Peran / Level</th>
                            <th>Dinonaktifkan Pada</th>
                            <th class="text-center pe-4 action-column" style="width: 170px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($trash_users_res && $trash_users_res->num_rows > 0): ?>
                            <?php while ($u = $trash_users_res->fetch_assoc()): 
                                $lvl_label = 'Pengunjung';
                                $lvl_badge = 'bg-secondary';
                                if ($u['level'] == 1) { $lvl_label = 'Super Admin'; $lvl_badge = 'bg-danger'; }
                                elseif ($u['level'] == 2) { $lvl_label = 'Admin'; $lvl_badge = 'bg-primary'; }
                                elseif ($u['level'] == 3) { $lvl_label = 'Staf Kasir'; $lvl_badge = 'bg-success'; }
                            ?>
                            <tr>
                                <td class="ps-4 text-muted small font-monospace">#<?= (int)$u['id_user'] ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($u['nama'] ?? $u['username']) ?></div>
                                    <div class="text-muted small">@<?= e($u['username']) ?></div>
                                </td>
                                <td>
                                    <div class="text-dark small"><i class="fa-regular fa-envelope me-1 text-muted"></i> <?= e($u['email']) ?></div>
                                    <?php if (!empty($u['no_telepon'])): ?>
                                        <div class="text-muted small"><i class="fa-solid fa-phone me-1"></i> <?= e($u['no_telepon']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $lvl_badge ?> px-2 py-1" style="border-radius: 6px;">
                                        <?= e($lvl_label) ?>
                                    </span>
                                </td>
                                <td class="text-danger small fw-semibold">
                                    <i class="fa-regular fa-calendar-xmark me-1"></i>
                                    <?= !empty($u['deleted_at']) ? date('d M Y, H:i', strtotime($u['deleted_at'])) : '-' ?>
                                </td>
                                <td class="text-center pe-4 action-column">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-success px-2 py-1" title="Pulihkan Pengguna Kembali Aktif" onclick="confirmRestoreUser(<?= (int)$u['id_user'] ?>, '<?= e($u['username']) ?>')" style="border-radius: 8px; font-size: 0.8rem;">
                                            <i class="fa-solid fa-trash-can-arrow-up me-1"></i> Pulihkan
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-action-icon" title="Hapus Permanen" onclick="confirmPurgeUser(<?= (int)$u['id_user'] ?>, '<?= e($u['username']) ?>')">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-user-check fs-2 text-secondary mb-2 d-block"></i>
                                    <?= !empty($tu_search) ? 'Tidak ditemukan pengguna terhapus yang cocok dengan pencarian.' : 'Tempat sampah pengguna kosong. Semua akun dalam keadaan aktif.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($current_tab === 'trash_ulasan'): ?>
    <!-- TAB 4 CONTENT: TRASH ULASAN (SOFT DELETED) -->
    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-comments me-2 text-info"></i> Kritik & Saran Terhapus (Tempat Sampah)
                </h5>
                <form action="" method="GET" class="d-flex gap-2">
                    <input type="hidden" name="tab" value="trash_ulasan">
                    <div class="input-group">
                        <input type="text" name="ulasan_search" class="form-control" placeholder="Cari isi ulasan / pengirim..." value="<?= e($tul_search) ?>" style="border-radius: 8px 0 0 8px;">
                        <button type="submit" class="btn btn-primary" style="border-radius: 0 8px 8px 0;">
                            <i class="fa-solid fa-search"></i>
                        </button>
                    </div>
                    <?php if (!empty($tul_search)): ?>
                        <a href="<?= route_url('settings_history_log', ['tab' => 'trash_ulasan']) ?>" class="btn btn-outline-secondary" style="border-radius: 8px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                        <tr>
                            <th class="ps-4" style="width: 70px;">ID</th>
                            <th style="width: 200px;">Pengirim</th>
                            <th>Isi Kritik & Saran Pengunjung</th>
                            <th style="width: 150px;">Tanggal Ulasan</th>
                            <th style="width: 150px;">Dihapus Pada</th>
                            <th class="text-center pe-4 action-column" style="width: 170px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($trash_ulasan_res && $trash_ulasan_res->num_rows > 0): ?>
                            <?php while ($ul = $trash_ulasan_res->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 text-muted small font-monospace">#<?= (int)$ul['id_ulasan'] ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($ul['username']) ?></div>
                                    <div class="text-muted small"><?= e($ul['email']) ?></div>
                                </td>
                                <td>
                                    <div class="text-dark small" style="line-height: 1.5;">
                                        <?= nl2br(e($ul['ulasan'])) ?>
                                    </div>
                                </td>
                                <td class="text-muted small">
                                    <i class="fa-regular fa-calendar me-1"></i>
                                    <?= date('d M Y', strtotime($ul['tgl_ulasan'])) ?>
                                </td>
                                <td class="text-danger small fw-semibold">
                                    <i class="fa-regular fa-calendar-xmark me-1"></i>
                                    <?= !empty($ul['deleted_at']) ? date('d M Y, H:i', strtotime($ul['deleted_at'])) : '-' ?>
                                </td>
                                <td class="text-center pe-4 action-column">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-success px-2 py-1" title="Pulihkan Ulasan Kembali Aktif" onclick="confirmRestoreUlasan(<?= (int)$ul['id_ulasan'] ?>, '<?= e($ul['username']) ?>')" style="border-radius: 8px; font-size: 0.8rem;">
                                            <i class="fa-solid fa-trash-can-arrow-up me-1"></i> Pulihkan
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-action-icon" title="Hapus Permanen" onclick="confirmPurgeUlasan(<?= (int)$ul['id_ulasan'] ?>, '<?= e($ul['username']) ?>')">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-comment-dots fs-2 text-secondary mb-2 d-block"></i>
                                    <?= !empty($tul_search) ? 'Tidak ditemukan kritik/saran terhapus yang cocok dengan pencarian.' : 'Tempat sampah kritik & saran kosong. Tidak ada ulasan yang dibungkam atau dihapus.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Hidden Forms for Actions -->
<form id="restoreForm" action="<?= route_url('transaksi_restore') ?>" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
    <input type="hidden" name="id" id="restoreId" value="">
</form>

<form id="purgeForm" action="" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
    <input type="hidden" name="action" id="purgeAction" value="purge_transaction">
    <input type="hidden" name="id_transaksi" id="purgeId" value="">
    <input type="hidden" name="id_user" id="purgeUserId" value="">
    <input type="hidden" name="id_ulasan" id="purgeUlasanId" value="">
</form>

<form id="restoreUserForm" action="<?= route_url('users_restore') ?>" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
    <input type="hidden" name="id" id="restoreUserId" value="">
</form>

<form id="restoreUlasanForm" action="<?= route_url('ulasan_restore') ?>" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
    <input type="hidden" name="id" id="restoreUlasanId" value="">
</form>

<?php
$extra_js = '
<script>
// Transaksi
function confirmRestore(id, kode) {
    Swal.fire({
        title: "Pulihkan Transaksi " + kode + "?",
        text: "Transaksi akan dikembalikan ke status aktif dan muncul kembali di kasir serta laporan omzet.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#10b981",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Pulihkan Data!",
        cancelButtonText: "Batal",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById("restoreId").value = id;
            document.getElementById("restoreForm").submit();
        }
    });
}

function confirmPurge(id, kode) {
    Swal.fire({
        title: "Hapus Permanen " + kode + "?",
        text: "PERINGATAN: Data transaksi dan tiket detail akan dihapus permanen dari basis data dan TIDAK DAPAT dipulihkan lagi!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Hapus Permanen!",
        cancelButtonText: "Batal",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById("purgeAction").value = "purge_transaction";
            document.getElementById("purgeId").value = id;
            document.getElementById("purgeForm").submit();
        }
    });
}

// User
function confirmRestoreUser(id, username) {
    Swal.fire({
        title: "Pulihkan Pengguna @" + username + "?",
        text: "Akun pengguna ini akan diaktifkan kembali dan dapat masuk ke sistem.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#10b981",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Aktifkan Kembali!",
        cancelButtonText: "Batal",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById("restoreUserId").value = id;
            document.getElementById("restoreUserForm").submit();
        }
    });
}

function confirmPurgeUser(id, username) {
    Swal.fire({
        title: "Hapus Permanen @" + username + "?",
        text: "Akun pengguna ini akan dihapus permanen dari sistem. Jika pengguna memiliki riwayat transaksi keuangan, penghapusan fisik akan dicegah demi integritas audit.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Hapus Permanen!",
        cancelButtonText: "Batal",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById("purgeAction").value = "purge_user";
            document.getElementById("purgeUserId").value = id;
            document.getElementById("purgeForm").submit();
        }
    });
}

// Ulasan
function confirmRestoreUlasan(id, author) {
    Swal.fire({
        title: "Pulihkan Ulasan dari " + author + "?",
        text: "Ulasan ini akan dipulihkan dan ditampilkan kembali di daftar kritik & saran aktif.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#10b981",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Pulihkan Ulasan!",
        cancelButtonText: "Batal",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById("restoreUlasanId").value = id;
            document.getElementById("restoreUlasanForm").submit();
        }
    });
}

function confirmPurgeUlasan(id, author) {
    Swal.fire({
        title: "Hapus Permanen Ulasan dari " + author + "?",
        text: "Ulasan ini akan dihapus permanen dari basis data dan tidak dapat dikembalikan.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Hapus Permanen!",
        cancelButtonText: "Batal",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById("purgeAction").value = "purge_ulasan";
            document.getElementById("purgeUlasanId").value = id;
            document.getElementById("purgeForm").submit();
        }
    });
}
</script>
';
?>

<?php require_once __DIR__ . '/../../app/layouts/admin_footer.php'; ?>
