<?php
/**
 * View & Controller: Aktivasi Akun Staf Baru & Buat Kata Sandi
 * Feedback-9 Poin 6
 */

require_once __DIR__ . '/../../app/config.php';

$raw_token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error_msg = '';
$user = null;

if (empty($raw_token)) {
    $error_msg = 'Tautan aktivasi tidak valid atau parameter token tidak ditemukan.';
} else {
    // Cari user berdasarkan token yang belum kedaluwarsa
    $stmt = $conn->prepare("SELECT id_user, nama, username, email, level, is_active FROM users WHERE activation_token = ? AND activation_expires_at > NOW() AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("s", $raw_token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $error_msg = 'Tautan aktivasi tidak valid, telah digunakan, atau masa berlakunya telah kedaluwarsa (maks. 72 jam). Silakan hubungi Super Admin untuk mengirim ulang tautan aktivasi.';
    }
}

// Proses form POST pembuatan kata sandi & aktivasi akun
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf($posted_token)) {
        $error_msg = 'Sesi keamanan berakhir atau token CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 8) {
            $error_msg = 'Kata sandi minimal harus terdiri dari 8 karakter.';
        } elseif ($password !== $confirm_password) {
            $error_msg = 'Konfirmasi kata sandi tidak cocok dengan kata sandi baru.';
        } else {
            // Hash password dengan BCRYPT
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // Update user: aktifkan akun dan hapus token aktivasi
            $stmtUp = $conn->prepare("UPDATE users SET password = ?, is_active = 1, activation_token = NULL, activation_expires_at = NULL WHERE id_user = ?");
            $stmtUp->bind_param("si", $password_hash, $user['id_user']);
            
            if ($stmtUp->execute()) {
                $stmtUp->close();
                if (function_exists('log_activity')) {
                    log_activity('ACTIVATE_USER', 'users', "Akun staf @{$user['username']} berhasil diaktifkan dengan kata sandi baru oleh pengguna.", $user['id_user']);
                }
                $_SESSION['flash_success'] = "Selamat! Akun staf Anda (@" . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . ") berhasil diaktifkan dan kata sandi baru telah disimpan. Silakan masuk.";
                header('Location: ' . route_url('login'));
                exit;
            } else {
                $error_msg = 'Gagal menyimpan kata sandi. Silakan coba kembali: ' . $conn->error;
            }
        }
    }
}

$page_title = "Aktivasi Akun Staf - Wisata Pemandian Patemon";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="shortcut icon" href="<?= public_url('img/icon.png') ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= public_url('assets/css/main/app.css') ?>">
    <link rel="stylesheet" href="<?= public_url('assets/css/pages/auth.css') ?>">
    <link rel="stylesheet" href="<?= public_url('css/modern-theme.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 24px 16px;
        }
        .auth-card {
            max-width: 480px;
            width: 100%;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .auth-header {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            padding: 32px 24px;
            text-align: center;
            color: #ffffff;
        }
        .auth-body {
            padding: 32px 28px;
        }
        .input-group-text {
            background-color: #f8fafc;
            border-color: #cbd5e1;
        }
        .form-control:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 0.2rem rgba(2, 132, 199, 0.15);
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <div class="mb-2">
                <i class="fa-solid fa-user-shield fa-3x"></i>
            </div>
            <h4 class="text-white mb-1 fw-bold">Aktivasi Akun Staf</h4>
            <p class="text-white text-opacity-75 mb-0 small">Sistem Informasi Kasir Wisata Pemandian Patemon</p>
        </div>

        <div class="auth-body">
            <?php if (!empty($error_msg) && !$user): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                    <i class="fa-solid fa-triangle-exclamation fa-2x me-3 flex-shrink-0"></i>
                    <div>
                        <h6 class="alert-heading fw-bold mb-1">Aktivasi Tidak Dapat Diproses</h6>
                        <p class="mb-0 small"><?= htmlspecialchars($error_msg) ?></p>
                    </div>
                </div>
                <div class="d-grid mt-4">
                    <a href="<?= route_url('login') ?>" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Halaman Masuk
                    </a>
                </div>
            <?php else: ?>
                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show small mb-4" role="alert">
                        <i class="fa-solid fa-circle-exclamation me-1"></i> <?= htmlspecialchars($error_msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="p-3 bg-light rounded-3 mb-4 border">
                    <div class="d-flex align-items-center mb-2">
                        <div class="badge bg-primary rounded-pill px-3 py-1 me-2">Staf Baru</div>
                        <span class="small text-muted">Silakan tetapkan kata sandi Anda</span>
                    </div>
                    <div class="small">
                        <strong>Nama:</strong> <?= htmlspecialchars($user['nama'] ?: $user['username']) ?><br>
                        <strong>Username:</strong> <code><?= htmlspecialchars($user['username']) ?></code><br>
                        <strong>Email:</strong> <?= htmlspecialchars($user['email']) ?>
                    </div>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($raw_token) ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Kata Sandi Baru</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock text-muted"></i></span>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Minimal 8 karakter" required minlength="8" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePass('password', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Konfirmasi Kata Sandi Baru</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-check-double text-muted"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Ulangi kata sandi baru" required minlength="8" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePass('confirm_password', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                            <i class="fa-solid fa-check-circle me-1"></i> Simpan Sandi & Aktifkan Akun
                        </button>
                    </div>

                    <div class="text-center">
                        <a href="<?= route_url('login') ?>" class="text-decoration-none small text-muted">
                            <i class="fa-solid fa-arrow-left me-1"></i> Batal, kembali ke Login
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePass(id, btn) {
            var input = document.getElementById(id);
            var icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
