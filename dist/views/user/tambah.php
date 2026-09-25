<?php
require '../../app/config.php';
check_auth([1]);

$active_menu = 'user';
$base_view = '..';

$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Token keamanan sesi kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $nama = trim($_POST["nama"] ?? '');
        $username = trim($_POST["username"] ?? '');
        $password = $_POST["password"] ?? '';
        $email = trim($_POST["email"] ?? '');
        $no_telepon = trim($_POST["no_telepon"] ?? '');
        $level = (int)($_POST["level"] ?? 0);

        if (empty($nama) || empty($username) || empty($password) || empty($email)) {
            $error_msg = "Nama, Username, Password, dan Email wajib diisi.";
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
        } elseif (has_toxic_words($nama) || has_toxic_words($username)) {
            $toxicHits = array_merge(find_toxic_words($nama), find_toxic_words($username));
            $error_msg = "Gagal: Nama atau username memuat kata yang dilarang (" . e(implode(', ', array_unique($toxicHits))) . "). Harap gunakan bahasa yang sopan.";
        } else {
            // Cek duplikasi username / email
            $chk = $conn->prepare("SELECT id_user FROM users WHERE username = ? OR email = ? LIMIT 1");
            $chk->bind_param("ss", $username, $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error_msg = "Username atau email tersebut sudah digunakan pengguna lain.";
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO users (nama, username, password, email, no_telepon, level) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssi", $nama, $username, $hashed, $email, $no_telepon, $level);
                if ($stmt->execute()) {
                    $newId = $stmt->insert_id;
                    if (function_exists('log_activity')) {
                        log_activity('TAMBAH', 'user', "Menambahkan user baru ID #{$newId}: {$username} (Role: " . get_role_name($level) . ")");
                    }
                    header("Location: " . route_url('users'));
                    exit();
                } else {
                    error_log("Insert user error: " . $stmt->error);
                    $error_msg = "Gagal menyimpan pengguna baru. Silakan periksa kembali format data yang dimasukkan.";
                }
                $stmt->close();
            }
            $chk->close();
        }
    }
}

$page_title      = 'Tambah Pengguna Baru - Pemandian Patemon';
$page_heading    = 'Tambah Pengguna Baru';
$page_subheading = 'Buat akun untuk administrator, staf kasir loket, atau pengunjung.';
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
                    <span class="fw-bold text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i> Formulir Akun Pengguna</span>
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

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="nama" class="form-label fw-semibold text-secondary small">Nama Lengkap <span class="text-danger">*</span></label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-id-card input-icon"></i>
                                    <input type="text" id="nama" name="nama" class="form-control-modern" placeholder="Nama Lengkap (maks. 50 karakter)" required maxlength="50" pattern="^[a-zA-Z\s\.\']+$" title="Nama hanya boleh berisi huruf, spasi, titik, atau tanda petik (maksimal 50 karakter)" value="<?= e($_POST['nama'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="username" class="form-label fw-semibold text-secondary small">Username <span class="text-danger">*</span></label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-user input-icon"></i>
                                    <input type="text" id="username" name="username" class="form-control-modern" placeholder="Username unik (maks. 30 karakter)" required maxlength="30" pattern="^[a-zA-Z0-9_\.]+$" title="Username hanya boleh berisi huruf, angka, underscore, atau titik (maksimal 30 karakter)" value="<?= e($_POST['username'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="email" class="form-label fw-semibold text-secondary small">Alamat Email <span class="text-danger">*</span></label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-envelope input-icon"></i>
                                    <input type="email" id="email" name="email" class="form-control-modern" placeholder="nama@email.com" required maxlength="60" value="<?= e($_POST['email'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="no_telepon" class="form-label fw-semibold text-secondary small">Nomor Telepon / WA</label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-phone input-icon"></i>
                                    <input type="tel" id="no_telepon" name="no_telepon" class="form-control-modern" placeholder="08xxxxxxxxxx" pattern="^0[0-9]{8,14}$" inputmode="numeric" maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')" value="<?= e($_POST['no_telepon'] ?? '') ?>">
                                </div>
                                <small class="text-muted" style="font-size: 0.72rem;">Format angka diawali 0 (9–15 digit).</small>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="password" class="form-label fw-semibold text-secondary small">Password <span class="text-danger">*</span></label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-lock input-icon"></i>
                                    <input type="password" id="password" name="password" class="form-control-modern" placeholder="Min. 6 karakter" required minlength="6">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="level" class="form-label fw-semibold text-secondary small">Peran (Hak Akses) <span class="text-danger">*</span></label>
                                <select id="level" name="level" class="form-select-modern">
                                    <option value="1">Super Admin (Level 1)</option>
                                    <option value="2">Admin (Level 2)</option>
                                    <option value="3">Staf Kasir Loket (Level 3)</option>
                                    <option value="0" selected>Pengunjung (Level 0)</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-brand">
                                <i class="fa-solid fa-user-check me-1"></i> Simpan Pengguna Baru
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
