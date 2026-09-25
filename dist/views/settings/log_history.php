<?php
/**
 * Modul Log History & Sampah Transaksi (Audit Trail & Soft-Delete Recovery)
 * Role: Khusus Super Admin (Level 1)
 * Pemandian Patemon
 */
require_once __DIR__ . '/../../app/config.php';
check_auth([1]);

$active_menu     = 'settings_history_log';
$page_title      = 'Log History & Sampah Transaksi - Pengaturan Sistem';
$page_heading    = 'Log History & Pemulihan Data';
$page_subheading = 'Audit trail perubahan data sistem serta pemantauan & pemulihan transaksi yang dihapus.';

$csrf_token = get_csrf_token();
$current_tab = $_GET['tab'] ?? 'audit';
if (!in_array($current_tab, ['audit', 'trash'], true)) {
    $current_tab = 'audit';
}

$msg_success = '';
$msg_error   = '';

if (isset($_GET['restored']) && $_GET['restored'] == 1) {
    $msg_success = 'Transaksi berhasil dipulihkan kembali ke data aktif.';
}

// Handle Permanently Delete Transaksi (Purge from Trash) if needed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purge_transaction') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg_error = 'Token CSRF tidak valid.';
    } else {
        $trx_id = (int)($_POST['id_transaksi'] ?? 0);
        if ($trx_id > 0) {
            // Delete related detail first
            $conn->query("DELETE FROM transaksi_detail WHERE id_transaksi = {$trx_id}");
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

// Distinct modules for filter
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
                <i class="fa-solid fa-trash-can-arrow-up me-2 text-danger"></i> Sampah Transaksi (Soft Delete)
                <?php if ($total_trash_items > 0): ?>
                    <span class="badge bg-danger ms-2"><?= number_format($total_trash_items) ?></span>
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
                        <option value="RESTORE" <?= $audit_action === 'RESTORE' ? 'selected' : '' ?>>Restore / Pulihkan</option>
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
                <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4" style="width: 70px;">ID</th>
                            <th style="width: 150px;">Waktu & Tanggal</th>
                            <th style="width: 140px;">Pelaku (User)</th>
                            <th style="width: 100px;">Aksi</th>
                            <th style="width: 120px;">Modul</th>
                            <th>Keterangan Perubahan</th>
                            <th style="width: 130px;">Alamat IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($audit_result && $audit_result->num_rows > 0): ?>
                            <?php while ($log = $audit_result->fetch_assoc()): 
                                $act = strtolower($log['action']);
                                $act_badge = 'bg-secondary';
                                if (in_array($act, ['tambah', 'insert', 'create'])) $act_badge = 'bg-success-subtle text-success border border-success-subtle';
                                elseif (in_array($act, ['update', 'edit'])) $act_badge = 'bg-info-subtle text-info border border-info-subtle';
                                elseif (in_array($act, ['hapus', 'delete', 'soft_delete', 'purge'])) $act_badge = 'bg-danger-subtle text-danger border border-danger-subtle';
                                elseif (in_array($act, ['restore'])) $act_badge = 'bg-primary-subtle text-primary border border-primary-subtle';
                                elseif (in_array($act, ['login'])) $act_badge = 'bg-dark-subtle text-dark border border-dark-subtle';
                            ?>
                            <tr>
                                <td class="ps-4 text-muted small font-monospace">#<?= $log['id'] ?></td>
                                <td class="text-muted small">
                                    <?= date('d M Y, H:i', strtotime($log['created_at'])) ?>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">
                                        <i class="fa-solid fa-user-circle text-primary me-1"></i> <?= e($log['username']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $act_badge ?> font-monospace px-2 py-1" style="font-size: 0.75rem; border-radius: 6px;">
                                        <?= e(strtoupper($log['action'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.775rem;">
                                        <?= e(strtoupper($log['module'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-break" style="max-width: 500px;">
                                        <?= e($log['description']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="font-monospace small text-muted">
                                        <?= e($log['ip_address']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-clock-rotate-left fs-2 text-secondary mb-2 d-block"></i>
                                    Tidak ada catatan log aktivitas yang ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($audit_total_pages > 1): ?>
        <div class="card-footer bg-transparent border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small">Menampilkan <?= min($total_audit, $audit_offset + 1) ?> - <?= min($total_audit, $audit_offset + $audit_limit) ?> dari <?= $total_audit ?> aktivitas</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php 
                    $q_params = $_GET;
                    for ($p = 1; $p <= $audit_total_pages; $p++): 
                        $q_params['page'] = $p;
                    ?>
                        <li class="page-item <?= ($audit_page === $p) ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query($q_params) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- TAB 2 CONTENT: SAMPAH TRANSAKSI (SOFT DELETED) -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm" style="border-radius: 16px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(220, 38, 38, 0.02)); border-left: 4px solid #ef4444 !important;">
                <div class="card-body p-4 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px; background: rgba(239, 68, 68, 0.15); color: #dc2626;">
                        <i class="fa-solid fa-trash-can fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Jumlah Transaksi Dihapus</div>
                        <h3 class="fw-bold mb-0 text-danger"><?= number_format($total_trash_items) ?> Data</h3>
                        <div class="text-muted small">Tersimpan di arsip soft delete & dapat dipulihkan</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm" style="border-radius: 16px; background: linear-gradient(135deg, rgba(14, 165, 233, 0.08), rgba(2, 132, 199, 0.02)); border-left: 4px solid #0284c7 !important;">
                <div class="card-body p-4 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px; background: rgba(2, 132, 199, 0.15); color: #0284c7;">
                        <i class="fa-solid fa-wallet fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Nilai Arsip Sampah</div>
                        <h3 class="fw-bold mb-0 text-primary">Rp <?= number_format($total_trash_nominal, 0, ',', '.') ?></h3>
                        <div class="text-muted small">Tidak dihitung ke dalam laporan omzet aktif</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-1"><i class="fa-solid fa-recycle text-primary me-2"></i> Arsip Transaksi Terhapus (Sampah)</h5>
                <p class="text-muted small mb-0">Transaksi di bawah disembunyikan dari kasir dan laporan, tetapi dapat dipulihkan kapan saja oleh Super Admin.</p>
            </div>
            <form action="" method="GET" class="d-flex gap-2">
                <input type="hidden" name="tab" value="trash">
                <input type="text" name="trash_search" class="form-control form-control-sm" placeholder="Cari pemesan / kode..." value="<?= e($trash_search) ?>" style="min-width: 180px; border-radius: 8px;">
                <button type="submit" class="btn btn-sm btn-outline-primary" style="border-radius: 8px;"><i class="fa-solid fa-search"></i></button>
                <?php if (!empty($trash_search)): ?>
                    <a href="<?= route_url('settings_history_log', ['tab' => 'trash']) ?>" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px;">Reset</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4" style="width: 130px;">Kode Trx</th>
                            <th>Tanggal Transaksi</th>
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
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge bg-light text-dark border font-monospace fw-bold">
                                        <?= e($kode_trx) ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?= date('d M Y, H:i', strtotime($trx['tgl_transaksi'])) ?>
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
                                        <!-- Tombol Pulihkan -->
                                        <button type="button" class="btn btn-sm btn-success px-2 py-1" title="Pulihkan Transaksi Kembali Aktif" onclick="confirmRestore(<?= (int)$trx['id_transaksi'] ?>, '<?= e($kode_trx) ?>')" style="border-radius: 8px; font-size: 0.8rem;">
                                            <i class="fa-solid fa-trash-can-arrow-up me-1"></i> Pulihkan
                                        </button>
                                        
                                        <!-- Tombol Hapus Permanen -->
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
                                    <?= !empty($trash_search) ? 'Tidak ditemukan transaksi terhapus yang cocok dengan pencarian.' : 'Tempat sampah transaksi kosong. Tidak ada data yang dihapus.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Restore -->
    <form id="restoreForm" action="<?= route_url('transaksi_restore') ?>" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
        <input type="hidden" name="id" id="restoreId" value="">
    </form>

    <!-- Hidden Form for Purge -->
    <form id="purgeForm" action="" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
        <input type="hidden" name="action" value="purge_transaction">
        <input type="hidden" name="id_transaksi" id="purgeId" value="">
    </form>

    <?php
    $extra_js = '
    <script>
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
                document.getElementById("purgeId").value = id;
                document.getElementById("purgeForm").submit();
            }
        });
    }
    </script>
    ';
    ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../../app/layouts/admin_footer.php'; ?>
