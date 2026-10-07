<?php
require '../../app/config.php';

// Hak Akses: Khusus Super Admin (Level 1)
check_auth([1], route_url('dashboard'));

$curr_login_lvl  = (int)($_SESSION['level'] ?? 0);
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
        SELECT id_user, nama, username, email, no_telepon, level, avatar, is_active, activation_token 
        FROM users 
        WHERE deleted_at IS NULL
          AND (nama LIKE ? 
           OR username LIKE ? 
           OR email LIKE ? 
           OR no_telepon LIKE ?)
        ORDER BY level ASC, id_user ASC
    ");
    $stmt->bind_param("ssss", $search_like, $search_like, $search_like, $search_like);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $sql = "SELECT id_user, nama, username, email, no_telepon, level, avatar, is_active, activation_token FROM users WHERE deleted_at IS NULL ORDER BY level ASC, id_user ASC";
    $result = $conn->query($sql);
}

require '../../app/layouts/admin_header.php';

$err = $_GET['err'] ?? '';
$msg = $_GET['msg'] ?? '';

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
$flash_warning = $_SESSION['flash_warning'] ?? '';
$flash_info    = $_SESSION['flash_info'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning'], $_SESSION['flash_info']);
?>

<div class="page-content">
    <?php if (!empty($flash_success)): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="fa-solid fa-circle-check fs-5 text-success"></i>
            <div><?= $flash_success ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($flash_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation fs-5 text-danger"></i>
            <div><?= $flash_error ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($flash_warning)): ?>
        <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation fs-5 text-warning"></i>
            <div><?= $flash_warning ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($flash_info)): ?>
        <div class="alert alert-info alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="fa-solid fa-circle-info fs-5 text-info"></i>
            <div><?= $flash_info ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
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
                        <th>Status</th>
                        <th class="text-center" style="width: 170px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php 
                    $no = 1;
                    while ($row = $result->fetch_assoc()): 
                        $lvl = (int)$row["level"];
                        $is_active = isset($row['is_active']) ? (int)$row['is_active'] : 1;
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
                            <td>
                                <?php if ($is_active === 1): ?>
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        <span class="badge-modern badge-modern-success"><i class="fa-solid fa-circle-check"></i> Aktif</span>
                                        <?php if ($curr_login_lvl === 1 && $row["id_user"] != $_SESSION['id_user']): ?>
                                            <button type="button" class="btn btn-outline-warning btn-sm rounded-pill px-2 py-0" style="font-size: 0.72rem; line-height: 1.6;" title="Nonaktifkan Akun Pengguna" onclick="confirmToggleStatus(<?= (int)$row['id_user'] ?>, '<?= e(addslashes($row['username'])) ?>', 0)">
                                                <i class="fa-solid fa-ban me-1"></i> Nonaktifkan
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        <span class="badge-modern badge-modern-warning"><i class="fa-solid fa-clock"></i> Belum Aktif</span>
                                        <?php if ($curr_login_lvl === 1): ?>
                                            <button type="button" class="btn btn-success btn-sm rounded-pill px-2 py-0 text-white shadow-sm" style="font-size: 0.72rem; line-height: 1.6;" title="Aktifkan Akun Secara Langsung (Bypass Email / NAT VPS)" onclick="confirmToggleStatus(<?= (int)$row['id_user'] ?>, '<?= e(addslashes($row['username'])) ?>', 1)">
                                                <i class="fa-solid fa-bolt me-1"></i> Aktifkan Langsung
                                            </button>
                                            <?php if (!empty($row['email'])): ?>
                                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-2 py-0" style="font-size: 0.72rem; line-height: 1.6;" title="Kirim ulang tautan aktivasi akun ke email pengguna" onclick="confirmResendActivation(<?= (int)$row['id_user'] ?>, '<?= e(addslashes($row['email'])) ?>')">
                                                    <i class="fa-solid fa-paper-plane me-1"></i> Kirim Ulang
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <?php 
                                    $can_manage = ($curr_login_lvl === 1) || ($curr_login_lvl === 2 && $lvl > 1);
                                    if ($can_manage): 
                                    ?>
                                        <a class="btn btn-sm btn-soft-primary btn-action-icon" title="Edit Akun" href="<?= route_url('users_update', ['id' => $row['id_user']]) ?>">
                                            <i class="fa-solid fa-user-pen"></i>
                                        </a>

                                        <?php if ($row["id_user"] != $_SESSION['id_user']): ?>
                                            <button type="button" class="btn btn-sm btn-soft-danger btn-action-icon" title="Hapus Pengguna (Nonaktifkan)" onclick="confirmDelete(<?= (int)$row['id_user'] ?>, '<?= e(addslashes($row['username'])) ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-light btn-action-icon" title="Akun Anda Saat Ini" disabled>
                                                <i class="fa-solid fa-lock text-muted"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-light btn-action-icon" title="Hak Akses Super Admin Terproteksi" disabled>
                                            <i class="fa-solid fa-shield-halved text-purple"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
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
function confirmToggleStatus(id, username, targetStatus) {
    const isActivating = (targetStatus === 1);
    const titleText = isActivating ? "Aktifkan Akun Langsung?" : "Nonaktifkan Akun Pengguna?";
    const descText = isActivating 
        ? "Akun @" + username + " akan langsung diaktifkan tanpa menunggu aktivasi email. Pengguna dapat langsung login dengan kata sandi yang telah ditentukan."
        : "Akun @" + username + " akan dinonaktifkan sementara dan tidak dapat login ke sistem.";
    const btnColor = isActivating ? "#10b981" : "#f59e0b";
    const btnText = isActivating ? "Ya, Aktifkan Langsung!" : "Ya, Nonaktifkan!";

    Swal.fire({
        title: titleText,
        text: descText,
        icon: isActivating ? "question" : "warning",
        showCancelButton: true,
        confirmButtonColor: btnColor,
        cancelButtonColor: "#64748b",
        confirmButtonText: btnText,
        cancelButtonText: "Batal"
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "' . route_url('users_toggle') . '";
            const idInput = document.createElement("input");
            idInput.type = "hidden";
            idInput.name = "id_user";
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

function confirmResendActivation(id, email) {
    Swal.fire({
        title: "Kirim Ulang Aktivasi?",
        text: "Tautan aktivasi baru akan dikirimkan ke alamat email " + email + ".",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#0284c7",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Kirim Email",
        cancelButtonText: "Batal"
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "' . route_url('users_resend_activation') . '";
            const idInput = document.createElement("input");
            idInput.type = "hidden";
            idInput.name = "id_user";
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

function confirmDelete(id, username) {
    Swal.fire({
        title: "Hapus Pengguna @" + username + "?",
        text: "Akun ini akan dinonaktifkan dan dipindahkan ke Tempat Sampah. Anda dapat memulihkannya kapan saja melalui menu Tempat Sampah & Log.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Hapus & Nonaktifkan",
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

document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const err = urlParams.get("err");
    const msg = urlParams.get("msg");

    if (err === "has_transactions") {
        Swal.fire({
            icon: "warning",
            title: "Penghapusan Dibatalkan",
            text: "Pengguna ini memiliki riwayat transaksi penjualan/pemesanan tiket. Menghapus akun ini dilarang guna menjaga integritas rekonsiliasi laporan kas dan retribusi daerah.",
            confirmButtonColor: "#0284c7",
            confirmButtonText: "Mengerti"
        });
    } else if (err === "self_delete") {
        Swal.fire({
            icon: "error",
            title: "Aksi Ditolak",
            text: "Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan dalam sesi ini.",
            confirmButtonColor: "#ef4444",
            confirmButtonText: "Tutup"
        });
    } else if (msg === "deleted" || msg === "soft_deleted") {
        Swal.fire({
            icon: "success",
            title: "Pengguna Dihapus",
            text: "Pengguna berhasil dihapus, dinonaktifkan, dan dipindahkan ke Tempat Sampah.",
            confirmButtonColor: "#0284c7",
            confirmButtonText: "Selesai"
        });
    }
});
</script>
';
require '../../app/layouts/admin_footer.php';
?>
