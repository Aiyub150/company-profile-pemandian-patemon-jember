<?php
require '../../app/config.php';

// Hak Akses Khusus: Hanya Administrator (Level 1)
check_auth([1]);

$active_menu     = 'user';
$page_title      = 'Manajemen Pengguna - Pemandian Patemon';
$page_heading    = 'Manajemen Pengguna';
$page_subheading = 'Kelola akun administrator, staf kasir loket, dan pengunjung terdaftar.';

$header_actions = '
    <a href="' . route_url('users_tambah') . '" class="btn btn-brand">
        <i class="fa-solid fa-user-plus me-1"></i> Tambah Pengguna Baru
    </a>
';

$search = trim($_GET['search'] ?? '');

if (!empty($search)) {
    $search_like = "%" . $search . "%";
    $stmt = $conn->prepare("
        SELECT id_user, nama, username, email, no_telepon, level, avatar 
        FROM users 
        WHERE nama LIKE ? 
           OR username LIKE ? 
           OR email LIKE ? 
           OR no_telepon LIKE ?
        ORDER BY level ASC, id_user ASC
    ");
    $stmt->bind_param("ssss", $search_like, $search_like, $search_like, $search_like);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $sql = "SELECT id_user, nama, username, email, no_telepon, level, avatar FROM users ORDER BY level ASC, id_user ASC";
    $result = $conn->query($sql);
}

require '../../app/layouts/admin_header.php';

$err = $_GET['err'] ?? '';
$msg = $_GET['msg'] ?? '';
?>

<div class="page-content">
    <?php if ($err === 'has_transactions'): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
            <i class="fa-solid fa-triangle-exclamation fs-5 text-warning"></i>
            <div>
                <strong>Penghapusan Dibatalkan:</strong> Pengguna ini memiliki riwayat transaksi penjualan/pemesanan tiket. Menghapus akun ini dilarang guna menjaga integritas rekonsiliasi laporan kas dan retribusi daerah.
            </div>
        </div>
    <?php elseif ($err === 'self_delete'): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
            <i class="fa-solid fa-circle-exclamation fs-5 text-danger"></i>
            <div>
                <strong>Aksi Ditolak:</strong> Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan dalam sesi ini.
            </div>
        </div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
            <i class="fa-solid fa-circle-check fs-5 text-success"></i>
            <div>
                Pengguna berhasil dihapus dari sistem.
            </div>
        </div>
    <?php endif; ?>

    <div class="modern-card">
        <div class="modern-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-bold fs-6 text-dark">
                <i class="fa-solid fa-users-gear text-primary me-2"></i> Daftar Akun Pengguna
            </span>
            <div class="d-flex align-items-center gap-2">
                <form method="GET" action="" class="input-icon-group" style="width: 280px;">
                    <i class="fa-solid fa-search input-icon"></i>
                    <input type="text" name="search" class="form-control-modern form-control-sm" placeholder="Cari nama, user, email..." value="<?= e($search) ?>">
                </form>
                <?php if (!empty($search)): ?>
                    <a href="<?= route_url('users') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table-modern" id="tableUsers">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th>Kode & Pengguna</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>No Telepon</th>
                        <th>Peran (Role)</th>
                        <th class="text-center" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php 
                    $no = 1;
                    while ($row = $result->fetch_assoc()): 
                        $lvl = (int)$row["level"];
                        $user_avatar = '';
                        if (!empty($row['avatar']) && file_exists(__DIR__ . '/../../../public/img/avatars/' . $row['avatar'])) {
                            $user_avatar = public_url('img/avatars/' . $row['avatar']);
                        }
                    ?>
                        <tr>
                            <td class="text-center text-muted fw-semibold"><?= $no++ ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($user_avatar)): ?>
                                        <img src="<?= e($user_avatar) ?>" alt="Avatar" style="width: 38px; height: 38px; border-radius: 10px; object-fit: cover; border: 1.5px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.06);">
                                    <?php else: ?>
                                        <div style="width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #0284c7, #38bdf8); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; box-shadow: 0 2px 6px rgba(2, 132, 199, 0.2);">
                                            <?= strtoupper(substr($row['nama'] ?: $row['username'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold text-dark"><?= e($row["nama"] ?: '-') ?></div>
                                        <small class="text-muted font-monospace">#USR-<?= sprintf('%03d', (int)$row["id_user"]) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><code><?= e($row["username"]) ?></code></td>
                            <td><?= e($row["email"] ?: '-') ?></td>
                            <td><?= e($row["no_telepon"] ?: '-') ?></td>
                            <td>
                                <?php 
                                if ($lvl === 1) {
                                    echo '<span class="badge-modern badge-modern-purple"><i class="fa-solid fa-shield-halved"></i> Super Admin</span>';
                                } elseif ($lvl === 2) {
                                    echo '<span class="badge-modern badge-modern-primary"><i class="fa-solid fa-user-tie"></i> Admin</span>';
                                } elseif ($lvl === 3) {
                                    echo '<span class="badge-modern badge-modern-success"><i class="fa-solid fa-cash-register"></i> Staf Kasir</span>';
                                } else {
                                    echo '<span class="badge-modern badge-modern-secondary"><i class="fa-solid fa-user"></i> Pengunjung</span>';
                                }
                                ?>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a class="btn btn-sm btn-soft-primary btn-action-icon" title="Edit Akun" href="<?= route_url('users_update', ['id' => $row['id_user']]) ?>">
                                        <i class="fa-solid fa-user-pen"></i>
                                    </a>
                                    <?php if ($row["id_user"] != $_SESSION['id_user']): ?>
                                        <button type="button" class="btn btn-sm btn-soft-danger btn-action-icon" title="Hapus Pengguna" onclick="confirmDelete(<?= (int)$row['id_user'] ?>, '<?= e(addslashes($row['username'])) ?>')">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-light btn-action-icon" title="Akun Anda Saat Ini" disabled>
                                            <i class="fa-solid fa-lock text-muted"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="fa-solid fa-user-xmark fs-2 mb-2 d-block text-secondary"></i>
                            Tidak ada data pengguna yang sesuai.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$extra_js = '
<script>
function confirmDelete(id, username) {
    Swal.fire({
        title: "Hapus Pengguna @" + username + "?",
        text: "Akun ini akan dinonaktifkan dan dihapus dari sistem secara permanen.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Hapus",
        cancelButtonText: "Batal"
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "' . route_url('users_delete') . '";
            const idInput = document.createElement("input");
            idInput.type = "hidden";
            idInput.name = "id";
            idInput.value = id;
            const csrfInput = document.createElement("input");
            csrfInput.type = "hidden";
            csrfInput.name = "csrf_token";
            csrfInput.value = "' . csrf_token() . '";
            form.appendChild(idInput);
            form.appendChild(csrfInput);
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>
';
require '../../app/layouts/admin_footer.php';
?>
