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
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = "Format email tidak valid.";
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
                    header("Location: user.php");
                    exit();
                } else {
                    $error_msg = "Gagal menyimpan pengguna baru: " . e($stmt->error);
                }
                $stmt->close();
            }
            $chk->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pengguna - Pemandian Patemon</title>

    <link rel="icon" type="image/x-icon" href="../../../public/img/icon.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/assets/css/main/app.css">
    <link rel="stylesheet" href="../../../public/css/modern-theme.css">
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
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <a href="user.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar User
                    </a>
                </div>
            </header>

            <div class="page-heading mb-4">
                <h2 class="fw-bold text-dark mb-1" style="font-size: 1.75rem;">Tambah Pengguna Baru</h2>
                <p class="text-muted mb-0">Buat akun untuk administrator, staf kasir loket, atau pengunjung.</p>
            </div>

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
                                                <input type="text" id="nama" name="nama" class="form-control-modern" placeholder="Nama Lengkap" required value="<?= e($_POST['nama'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label for="username" class="form-label fw-semibold text-secondary small">Username <span class="text-danger">*</span></label>
                                            <div class="input-icon-group">
                                                <i class="fa-solid fa-user input-icon"></i>
                                                <input type="text" id="username" name="username" class="form-control-modern" placeholder="Username unik" required value="<?= e($_POST['username'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label for="email" class="form-label fw-semibold text-secondary small">Alamat Email <span class="text-danger">*</span></label>
                                            <div class="input-icon-group">
                                                <i class="fa-solid fa-envelope input-icon"></i>
                                                <input type="email" id="email" name="email" class="form-control-modern" placeholder="nama@email.com" required value="<?= e($_POST['email'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label for="no_telepon" class="form-label fw-semibold text-secondary small">Nomor Telepon / WA</label>
                                            <div class="input-icon-group">
                                                <i class="fa-solid fa-phone input-icon"></i>
                                                <input type="tel" id="no_telepon" name="no_telepon" class="form-control-modern" placeholder="08xxxxxxxxxx" value="<?= e($_POST['no_telepon'] ?? '') ?>">
                                            </div>
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
                                                <option value="0" selected>Pengguna Biasa (Pelanggan)</option>
                                                <option value="2">Staf Kasir Loket (Level 2)</option>
                                                <option value="1">Administrator Penuh (Level 1)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2 mt-4">
                                        <button type="submit" class="btn btn-brand">
                                            <i class="fa-solid fa-user-check me-1"></i> Simpan Pengguna Baru
                                        </button>
                                        <a href="user.php" class="btn btn-outline-secondary">Batal</a>
                                    </div>
                                </form>
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

    <script src="../../../public/assets/js/bootstrap.js"></script>
</body>
</html>
