<?php
require '../../app/config.php';
check_auth([1]);

$active_menu = 'user';
$base_view = '..';

$id_user = (int)($_GET["id"] ?? $_POST['id_user'] ?? 0);

if ($id_user <= 0) {
    header("Location: " . route_url('users'));
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ? LIMIT 1");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: " . route_url('users'));
    exit();
}

$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Token keamanan sesi kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $nama = trim($_POST["nama"] ?? '');
        $username = trim($_POST["username"] ?? '');
        $new_password = $_POST["password"] ?? '';
        $email = trim($_POST["email"] ?? '');
        $no_telepon = trim($_POST["no_telepon"] ?? '');
        $level = isset($_POST["level"]) ? (int)$_POST["level"] : (int)$data['level'];

        // Jika mengubah akun sendiri, jangan ubah levelnya
        if ($id_user === (int)$_SESSION['id_user']) {
            $level = (int)$data['level'];
        }

        if (empty($nama) || empty($username) || empty($email)) {
            $error_msg = "Nama, Username, dan Email wajib diisi.";
        } elseif (mb_strlen($nama) > 50) {
            $error_msg = "Nama lengkap maksimal 50 karakter.";
        } elseif (!preg_match("/^[a-zA-Z\s\.\']+$/", $nama)) {
            $error_msg = "Nama lengkap hanya boleh berisi huruf, spasi, titik, atau tanda petik.";
        } elseif (mb_strlen($username) > 30) {
            $error_msg = "Username maksimal 30 karakter.";
        } elseif (!preg_match("/^[a-zA-Z0-9_\.]+$/", $username)) {
            $error_msg = "Username hanya boleh berisi huruf, angka, garis bawah (_), atau titik (.).";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = "Format email tidak valid.";
        } elseif (!empty($no_telepon) && !preg_match('/^0[0-9]{8,14}$/', $no_telepon)) {
            $error_msg = "Nomor telepon tidak valid. Gunakan format angka diawali angka 0 (9–15 digit angka).";
        } elseif (has_toxic_words($nama) || has_toxic_words($username)) {
            $toxicHits = array_merge(find_toxic_words($nama), find_toxic_words($username));
            $error_msg = "Gagal: Nama atau username memuat kata yang dilarang (" . e(implode(', ', array_unique($toxicHits))) . "). Harap gunakan bahasa yang sopan.";
        } else {
            // Cek apakah username/email sudah digunakan akun lain
            $chk = $conn->prepare("SELECT id_user FROM users WHERE (username = ? OR email = ?) AND id_user != ? LIMIT 1");
            $chk->bind_param("ssi", $username, $email, $id_user);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error_msg = "Username atau email tersebut sudah digunakan oleh akun lain.";
            } else {
                if (!empty($new_password)) {
                    $hashed = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt_up = $conn->prepare("UPDATE users SET nama = ?, username = ?, password = ?, email = ?, no_telepon = ?, level = ? WHERE id_user = ?");
                    $stmt_up->bind_param("sssssii", $nama, $username, $hashed, $email, $no_telepon, $level, $id_user);
                } else {
                    $stmt_up = $conn->prepare("UPDATE users SET nama = ?, username = ?, email = ?, no_telepon = ?, level = ? WHERE id_user = ?");
                    $stmt_up->bind_param("ssssii", $nama, $username, $email, $no_telepon, $level, $id_user);
                }

                if ($stmt_up->execute()) {
                    if (function_exists('log_activity')) {
                        log_activity('UPDATE', 'user', "Memperbarui profil user ID #{$id_user} ({$username})");
                    }
                    header("Location: " . route_url('users'));
                    exit();
                } else {
                    error_log("Update user error: " . $stmt_up->error);
                    $error_msg = "Gagal memperbarui pengguna. Silakan periksa kembali data Anda.";
                }
                $stmt_up->close();
            }
            $chk->close();
        }
    }
}

$page_title      = "Edit Pengguna #{$id_user} - Pemandian Patemon";
$page_heading    = "Edit Data Pengguna #{$id_user}";
$page_subheading = "Ubah profil pengguna, peran, atau perbarui kata sandi.";
$header_actions  = '
    <a href="' . route_url('users') . '" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar User
    </a>
';

require '../../app/layouts/admin_header.php';
?>

<div class="page-content">
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="modern-card">
                <div class="modern-card-header">
                    <span class="fw-bold text-dark"><i class="fa-solid fa-user-pen text-primary me-2"></i> Formulir Edit Pengguna</span>
                    <span class="badge badge-modern-primary">ID: #<?= $id_user ?></span>
                </div>
                <div class="modern-card-body">
                    <?php if (!empty($error_msg)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                            <i class="fa-solid fa-circle-exclamation fs-5"></i>
                            <div><?= e($error_msg) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id_user" value="<?= $id_user ?>">

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="nama" class="form-label fw-semibold text-secondary small">Nama Lengkap <span class="text-danger">*</span></label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-id-card input-icon"></i>
                                    <input type="text" id="nama" name="nama" class="form-control-modern" required maxlength="50" pattern="^[a-zA-Z\s\.\']+$" title="Nama hanya boleh berisi huruf, spasi, titik, atau tanda petik (maksimal 50 karakter)" value="<?= e($data['nama']) ?>">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="username" class="form-label fw-semibold text-secondary small">Username <span class="text-danger">*</span></label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-user input-icon"></i>
                                    <input type="text" id="username" name="username" class="form-control-modern" required maxlength="30" pattern="^[a-zA-Z0-9_\.]+$" title="Username hanya boleh berisi huruf, angka, underscore, atau titik (maksimal 30 karakter)" value="<?= e($data['username']) ?>">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="email" class="form-label fw-semibold text-secondary small">Alamat Email <span class="text-danger">*</span></label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-envelope input-icon"></i>
                                    <input type="email" id="email" name="email" class="form-control-modern" required maxlength="60" value="<?= e($data['email']) ?>">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="no_telepon" class="form-label fw-semibold text-secondary small">Nomor Telepon / WA</label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-phone input-icon"></i>
                                    <input type="tel" id="no_telepon" name="no_telepon" class="form-control-modern" placeholder="08xxxxxxxxxx" pattern="^0[0-9]{8,14}$" inputmode="numeric" maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')" value="<?= e($data['no_telepon']) ?>">
                                </div>
                                <small class="text-muted" style="font-size: 0.72rem;">Format angka diawali 0 (9–15 digit).</small>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="password" class="form-label fw-semibold text-secondary small">Password Baru <small class="text-muted">(Kosongkan jika tidak diubah)</small></label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-lock input-icon"></i>
                                    <input type="password" id="password" name="password" class="form-control-modern" placeholder="Masukkan password baru">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="level" class="form-label fw-semibold text-secondary small">Peran (Hak Akses) <span class="text-danger">*</span></label>
                                <select id="level" name="level" class="form-select-modern" <?= ($id_user === (int)$_SESSION['id_user']) ? 'disabled' : '' ?>>
                                    <option value="1" <?= ($data['level'] == 1) ? 'selected' : '' ?>>Super Admin (Level 1)</option>
                                    <option value="2" <?= ($data['level'] == 2) ? 'selected' : '' ?>>Admin (Level 2)</option>
                                    <option value="3" <?= ($data['level'] == 3) ? 'selected' : '' ?>>Staf Kasir Loket (Level 3)</option>
                                    <option value="0" <?= ($data['level'] == 0) ? 'selected' : '' ?>>Pengunjung (Level 0)</option>
                                </select>
                                <?php if ($id_user === (int)$_SESSION['id_user']): ?>
                                    <input type="hidden" name="level" value="1">
                                    <small class="text-muted d-block mt-1">Anda tidak dapat mengubah level akun sendiri.</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-brand">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan
                            </button>
                            <a href="<?= route_url('users') ?>" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require '../../app/layouts/admin_footer.php';
?>
