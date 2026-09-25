<?php
require_once __DIR__ . '/../app/config.php';

$msg = '';
$msg_type = '';
$step = 1;

// Cek apakah ada sesi reset aktif
if (isset($_SESSION['pw_reset_user_id'])) {
    $step = 2;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $msg = "Token keamanan sesi Anda kedaluwarsa. Silakan muat ulang halaman.";
        $msg_type = 'danger';
    } else {
        $action = $_POST['action'] ?? 'verify_account';

        if ($action === 'verify_account') {
            $identity = trim($_POST['identity'] ?? '');
            $phone_input = trim($_POST['phone'] ?? '');

            if (empty($identity)) {
                $msg = "Silakan masukkan username atau alamat email akun Anda.";
                $msg_type = 'danger';
            } else {
                $stmt = $conn->prepare("SELECT id_user, nama, username, email, no_telepon FROM users WHERE username = ? OR email = ? LIMIT 1");
                $stmt->bind_param("ss", $identity, $identity);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$user) {
                    $msg = "Akun dengan username atau email tersebut tidak ditemukan dalam sistem.";
                    $msg_type = 'danger';
                } else {
                    // Validasi kecocokan nomor telepon jika akun memiliki data no_telepon
                    $db_phone = preg_replace('/[^0-9]/', '', $user['no_telepon'] ?? '');
                    $clean_phone = preg_replace('/[^0-9]/', '', $phone_input);

                    $phone_valid = true;
                    if (!empty($db_phone)) {
                        if (empty($clean_phone) || substr($db_phone, -7) !== substr($clean_phone, -7)) {
                            $phone_valid = false;
                        }
                    }

                    if (!$phone_valid) {
                        $msg = "Nomor telepon / WhatsApp tidak cocok dengan nomor terdaftar pada akun ini.";
                        $msg_type = 'danger';
                    } else {
                        // Verifikasi sukses, lanjut ke Step 2
                        $_SESSION['pw_reset_user_id'] = (int)$user['id_user'];
                        $_SESSION['pw_reset_name'] = $user['nama'] ?: $user['username'];
                        $_SESSION['pw_reset_username'] = $user['username'];
                        $step = 2;
                        $msg = "Identitas akun terverifikasi. Silakan masukkan kata sandi baru Anda.";
                        $msg_type = 'success';
                    }
                }
            }
        } elseif ($action === 'reset_password') {
            $user_id = (int)($_SESSION['pw_reset_user_id'] ?? 0);
            $new_pass = $_POST['new_password'] ?? '';
            $conf_pass = $_POST['confirm_password'] ?? '';

            if ($user_id <= 0) {
                $step = 1;
                $msg = "Sesi verifikasi telah kedaluwarsa. Silakan ulangi langkah pemulihan.";
                $msg_type = 'danger';
            } elseif (empty($new_pass) || empty($conf_pass)) {
                $step = 2;
                $msg = "Semua kolom kata sandi baru wajib diisi.";
                $msg_type = 'danger';
            } elseif (strlen($new_pass) < 6) {
                $step = 2;
                $msg = "Kata sandi baru minimal harus 6 karakter demi keamanan akun.";
                $msg_type = 'danger';
            } elseif ($new_pass !== $conf_pass) {
                $step = 2;
                $msg = "Konfirmasi kata sandi baru tidak cocok. Silakan ketik ulang.";
                $msg_type = 'danger';
            } else {
                // Update kata sandi dengan BCRYPT hash yang aman
                $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $up_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id_user = ?");
                $up_stmt->bind_param("si", $new_hash, $user_id);

                if ($up_stmt->execute()) {
                    $up_stmt->close();
                    unset($_SESSION['pw_reset_user_id'], $_SESSION['pw_reset_name'], $_SESSION['pw_reset_username']);
                    $step = 3; // Sukses penuh
                    $msg = "Kata sandi Anda berhasil diperbarui! Silakan masuk menggunakan kata sandi baru Anda.";
                    $msg_type = 'success';
                } else {
                    $up_stmt->close();
                    $step = 2;
                    $msg = "Terjadi kesalahan sistem saat memperbarui kata sandi. Silakan coba lagi.";
                    $msg_type = 'danger';
                }
            }
        } elseif ($action === 'cancel_reset') {
            unset($_SESSION['pw_reset_user_id'], $_SESSION['pw_reset_name'], $_SESSION['pw_reset_username']);
            $step = 1;
            $msg = "";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemulihan Akun & Reset Password - Pemandian Patemon</title>
    <link rel="icon" type="image/x-icon" href="<?= public_url('img/icon.png') ?>" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= public_url('css/modern-theme.css') ?>">
    <script>
        (function() {
            var theme = localStorage.getItem('patemon_theme') || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.add('theme-dark');
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            } else {
                document.documentElement.classList.remove('theme-dark');
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>
    <script src="<?= public_url('js/patemon-i18n.js') ?>"></script>
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #0369a1 50%, #0284c7 100%);
            padding: 1.5rem;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .auth-card-single {
            width: 100%;
            max-width: 500px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            padding: 3rem 2.5rem;
            transition: all 0.3s ease;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-header img {
            height: 52px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            margin-bottom: 1.25rem;
            transition: transform 0.2s ease;
        }
        .auth-header img:hover {
            transform: scale(1.03);
        }

        .auth-header h1 {
            font-size: 1.65rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 0.5rem;
        }

        .auth-header p {
            color: #64748b;
            font-size: 0.925rem;
            line-height: 1.5;
            margin: 0;
        }

        .form-group-item {
            margin-bottom: 1.35rem;
        }

        .form-label-modern {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }

        .input-icon-group {
            position: relative;
        }

        .input-icon-group .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
            pointer-events: none;
        }

        .input-icon-group .form-control-modern {
            padding-left: 2.75rem !important;
        }

        .alert-custom-success {
            padding: 0.85rem 1.15rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            line-height: 1.45;
        }

        .alert-custom-danger {
            padding: 0.85rem 1.15rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .step-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.775rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        .auth-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.9rem;
            color: #64748b;
        }

        .auth-footer a {
            color: #0284c7;
            font-weight: 600;
            text-decoration: none;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <script>
        if (localStorage.getItem('patemon_theme') === 'dark') {
            document.body.classList.add('theme-dark');
        }
    </script>

<!-- Floating Theme & Language Switchers -->
<div style="position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; align-items: center; gap: 0.5rem;">
    <button type="button" class="btn-lang-switcher" onclick="togglePatemonLanguage()" title="Beralih Bahasa / Switch Language">
        <svg class="flag-icon-svg" viewBox="0 0 640 480" width="18" height="13" style="border-radius:2px; vertical-align:middle; display:inline-block; box-shadow:0 0 1px rgba(0,0,0,0.5); margin-right:4px;"><g fill-rule="evenodd" stroke-width="1pt"><path fill="#e70011" d="M0 0h640v240H0z"/><path fill="#ffffff" d="M0 240h640v240H0z"/></g></svg><strong>ID</strong>
    </button>
    <button type="button" id="themeToggleBtn" class="btn-theme-switcher" onclick="togglePatemonTheme()" title="Beralih Mode Gelap / Terang">
        <span class="theme-icon-moon"><i class="fa-solid fa-moon"></i></span>
        <span class="theme-icon-sun"><i class="fa-solid fa-sun"></i></span>
        <span class="d-none d-sm-inline ms-1" id="themeLabelText">Tema</span>
    </button>
</div>

<div class="auth-card-single">
    <div class="auth-header">
        <a href="<?= route_url('home') ?>" title="Kembali ke Halaman Utama">
            <!-- Light Logo for Light Theme -->
            <img src="<?= public_url('img/logo_pemandian_transparant.png') ?>" alt="Logo Pemandian Patemon" class="logo-light" id="authLogoLight">
            <!-- White Logo for Dark Theme (Feedback 4 Poin 1) -->
            <img src="<?= public_url('img/logo_pemandian_white.svg') ?>" alt="Logo Pemandian Patemon" class="logo-dark" id="authLogoDark">
        </a>
        <h1>Pemulihan Akun</h1>
        <p>Atur ulang kata sandi akun Anda secara aman dan mandiri.</p>
    </div>

    <?php if (!empty($msg)): ?>
        <?php if ($msg_type === 'success'): ?>
            <div class="alert-custom-success">
                <i class="fa-solid fa-circle-check fs-5 mt-0.5"></i>
                <div><?= e($msg) ?></div>
            </div>
        <?php else: ?>
            <div class="alert-custom-danger">
                <i class="fa-solid fa-circle-exclamation fs-5"></i>
                <div><?= e($msg) ?></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <!-- STEP 1: Verifikasi Akun & Identitas -->
        <div class="text-center mb-3">
            <span class="step-pill bg-light text-primary border">
                <i class="fa-solid fa-user-shield"></i> Langkah 1 dari 2: Verifikasi Akun
            </span>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="verify_account">

            <div class="form-group-item">
                <label class="form-label-modern" for="identity">Username atau Alamat Email <span class="text-danger">*</span></label>
                <div class="input-icon-group">
                    <i class="fa-solid fa-user input-icon"></i>
                    <input 
                        type="text" 
                        class="form-control-modern" 
                        id="identity" 
                        name="identity" 
                        placeholder="Contoh: admin atau nama@email.com" 
                        required 
                        autocomplete="username"
                        value="<?= e($_POST['identity'] ?? '') ?>"
                    >
                </div>
                <small class="text-muted" style="font-size: 0.775rem;">Masukkan username atau email akun yang terdaftar.</small>
            </div>

            <div class="form-group-item">
                <label class="form-label-modern" for="phone">Nomor Telepon / WhatsApp Terdaftar <span class="text-danger">*</span></label>
                <div class="input-icon-group">
                    <i class="fa-solid fa-phone input-icon"></i>
                    <input 
                        type="tel" 
                        class="form-control-modern" 
                        id="phone" 
                        name="phone" 
                        placeholder="08xxxxxxxxxx" 
                        required 
                        autocomplete="tel"
                        value="<?= e($_POST['phone'] ?? '') ?>"
                    >
                </div>
                <small class="text-muted" style="font-size: 0.775rem;">Verifikasi kepemilikan akun melalui nomor HP yang terdaftar.</small>
            </div>

            <button type="submit" class="btn-brand w-100 py-3 fw-bold fs-6 shadow-sm mt-2">
                <i class="fa-solid fa-arrow-right me-1"></i> Lanjut ke Buat Kata Sandi Baru
            </button>
        </form>

    <?php elseif ($step === 2): ?>
        <!-- STEP 2: Buat Kata Sandi Baru (Berfungsi Nyata) -->
        <div class="text-center mb-3">
            <span class="step-pill bg-soft-success text-success border border-success">
                <i class="fa-solid fa-key"></i> Langkah 2 dari 2: Buat Kata Sandi Baru
            </span>
            <div class="text-muted small mt-1">
                Akun Terverifikasi: <strong class="text-dark"><?= e($_SESSION['pw_reset_name'] ?? 'Pengguna') ?> (@<?= e($_SESSION['pw_reset_username'] ?? '') ?>)</strong>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="reset_password">

            <div class="form-group-item">
                <label class="form-label-modern" for="new_password">Kata Sandi Baru <span class="text-danger">*</span></label>
                <div class="input-icon-group">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input 
                        type="password" 
                        class="form-control-modern" 
                        id="new_password" 
                        name="new_password" 
                        placeholder="Minimal 6 karakter" 
                        required 
                        minlength="6"
                        autocomplete="new-password"
                    >
                </div>
            </div>

            <div class="form-group-item">
                <label class="form-label-modern" for="confirm_password">Ulangi Kata Sandi Baru <span class="text-danger">*</span></label>
                <div class="input-icon-group">
                    <i class="fa-solid fa-shield-halved input-icon"></i>
                    <input 
                        type="password" 
                        class="form-control-modern" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Ketik ulang kata sandi baru" 
                        required 
                        minlength="6"
                        autocomplete="new-password"
                    >
                </div>
            </div>

            <button type="submit" class="btn-brand w-100 py-3 fw-bold fs-6 shadow-sm mt-2">
                <i class="fa-solid fa-floppy-disk me-1"></i> Simpan & Perbarui Kata Sandi
            </button>
        </form>

        <form method="POST" action="" class="mt-2 text-center">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="cancel_reset">
            <button type="submit" class="btn btn-link text-muted small text-decoration-none">
                <i class="fa-solid fa-arrow-rotate-left me-1"></i> Batal & Pilih Akun Lain
            </button>
        </form>

    <?php elseif ($step === 3): ?>
        <!-- STEP 3: Sukses Penuh -->
        <div class="text-center py-3">
            <div style="width: 72px; height: 72px; border-radius: 50%; background: #dcfce7; color: #16a34a; font-size: 2.25rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto;">
                <i class="fa-solid fa-check"></i>
            </div>
            <h3 class="fw-bold text-dark mb-2">Kata Sandi Berhasil Diperbarui!</h3>
            <p class="text-muted small mb-4">Akun Anda kini telah aman dengan kata sandi baru. Silakan masuk menggunakan kata sandi yang baru saja Anda buat.</p>

            <a href="<?= route_url('login') ?>" class="btn-brand d-block w-100 py-3 fw-bold fs-6 text-decoration-none shadow-sm">
                <i class="fa-solid fa-right-to-bracket me-1"></i> Masuk ke Akun Sekarang
            </a>
        </div>
    <?php endif; ?>

    <div class="auth-footer">
        <div>Sudah ingat kata sandi? <a href="<?= route_url('login') ?>">Masuk di sini</a></div>
        <div style="margin-top: 0.5rem;"><a href="<?= route_url('register') ?>">Daftar Akun Baru</a></div>
        <div style="margin-top: 1rem;">
            <a href="<?= route_url('home') ?>" style="color: #64748b; font-size: 0.85rem;">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</div>

<script>
    function togglePatemonTheme() {
        const isDark = document.body.classList.contains('theme-dark') || document.documentElement.classList.contains('theme-dark');
        const newTheme = isDark ? 'light' : 'dark';
        applyPatemonTheme(newTheme);
        localStorage.setItem('patemon_theme', newTheme);
    }
    function applyPatemonTheme(theme) {
        const btn = document.getElementById('themeToggleBtn');
        const label = document.getElementById('themeLabelText');
        if (theme === 'dark') {
            document.body.classList.add('theme-dark');
            document.documentElement.classList.add('theme-dark');
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            if (btn) btn.classList.add('active-dark');
            if (label) label.textContent = 'Gelap';
        } else {
            document.body.classList.remove('theme-dark');
            document.documentElement.classList.remove('theme-dark');
            document.documentElement.setAttribute('data-bs-theme', 'light');
            if (btn) btn.classList.remove('active-dark');
            if (label) label.textContent = 'Terang';
        }
    }
    document.addEventListener('DOMContentLoaded', () => {
        applyPatemonTheme(localStorage.getItem('patemon_theme') || 'light');
    });
</script>
</body>
</html>
