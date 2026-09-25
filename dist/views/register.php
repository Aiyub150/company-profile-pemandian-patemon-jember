<?php
require_once __DIR__ . '/../app/config.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validasi CSRF
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan sesi Anda kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $nama             = trim($_POST['nama'] ?? '');
        $username         = trim($_POST['username'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $no_telepon       = trim($_POST['no_telepon'] ?? '');
        $password         = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validasi dasar
        if (empty($nama) || empty($username) || empty($email) || empty($password)) {
            $error = "Semua field yang bertanda bintang (*) wajib diisi.";
        } elseif (mb_strlen($nama) > 50) {
            $error = "Nama lengkap maksimal 50 karakter.";
        } elseif (!preg_match("/^[a-zA-Z\s\.\']+$/", $nama)) {
            $error = "Nama lengkap hanya boleh berisi huruf, spasi, titik, atau tanda petik.";
        } elseif (mb_strlen($username) > 30) {
            $error = "Username maksimal 30 karakter.";
        } elseif (!preg_match("/^[a-zA-Z0-9_\.]+$/", $username)) {
            $error = "Username hanya boleh berisi huruf, angka, garis bawah (_), atau titik (.).";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format alamat email tidak valid.";
        } elseif (!empty($no_telepon) && !preg_match('/^0[0-9]{8,14}$/', $no_telepon)) {
            $error = "Nomor telepon / WhatsApp tidak valid. Gunakan format angka diawali angka 0 (9–15 digit).";
        } elseif (strlen($password) < 6) {
            $error = "Password minimal terdiri dari 6 karakter.";
        } elseif (has_toxic_words($nama) || has_toxic_words($username)) {
            $toxicHits = array_merge(find_toxic_words($nama), find_toxic_words($username));
            $error = "Pendaftaran ditolak: Nama atau username memuat kata yang dilarang (" . e(implode(', ', array_unique($toxicHits))) . "). Harap gunakan bahasa yang sopan.";
            if (function_exists('log_activity')) {
                log_activity('TOXIC_BLOCKED', 'register', "Pendaftaran ditolak karena kata terlarang: " . implode(', ', array_unique($toxicHits)));
            }
        } else {
            // Pengecekan apakah username atau email sudah digunakan
            $check_stmt = $conn->prepare("SELECT id_user FROM users WHERE username = ? OR email = ? LIMIT 1");
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $error = "Username atau email ini sudah pernah terdaftar. Silakan gunakan yang lain.";
            } else {
                // Enkripsi password menggunakan BCRYPT
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $level_user = 0; // Default Pengguna Biasa

                $stmt = $conn->prepare("INSERT INTO users (nama, username, password, email, no_telepon, level) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssi", $nama, $username, $hashed_password, $email, $no_telepon, $level_user);

                if ($stmt->execute()) {
                    $newId = $stmt->insert_id;
                    if (function_exists('log_activity')) {
                        log_activity('REGISTER', 'user', "Pendaftaran akun publik baru: {$username} ({$nama})", $newId);
                    }
                    header("Location: " . route_url('login', ['registered' => 1]));
                    exit();
                } else {
                    error_log("Database Registration Error: " . $stmt->error);
                    $error = "Terjadi kendala pada sistem saat mendaftar. Silakan coba beberapa saat lagi.";
                }
                $stmt->close();
            }
            $check_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Pemandian Patemon</title>
    <link rel="icon" type="image/x-icon" href="<?= public_url('img/icon.png') ?>" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= public_url('css/modern-theme.css') ?>">
    <script src="<?= public_url('js/patemon-i18n.js') ?>"></script>
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
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #0369a1 50%, #0284c7 100%);
            padding: 1.5rem;
        }

        .auth-container {
            width: 100%;
            max-width: 1050px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            display: flex;
            overflow: hidden;
            min-height: 650px;
        }

        .auth-banner {
            flex: 1;
            background: linear-gradient(rgba(15, 23, 42, 0.65), rgba(2, 132, 199, 0.75)), url('../../public/img/background.png');
            background-size: cover;
            background-position: center;
            padding: 3.5rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #ffffff;
        }

        .auth-banner-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(10px);
            padding: 0.4rem 1rem;
            border-radius: 9999px;
            font-size: 0.825rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            border: 1px solid rgba(255, 255, 255, 0.25);
            width: fit-content;
        }

        .auth-banner h2 {
            font-size: 2.25rem;
            font-weight: 800;
            line-height: 1.2;
            margin: 1.5rem 0 1rem;
        }

        .auth-banner p {
            color: #e2e8f0;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .auth-form-section {
            flex: 1.2;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
            overflow-y: auto;
        }

        .auth-header {
            margin-bottom: 1.75rem;
        }

        .auth-header img {
            height: 44px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            margin-bottom: 1rem;
        }

        .auth-header h1 {
            font-size: 1.65rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 0.4rem;
        }

        .auth-header p {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0;
        }

        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group-item {
            margin-bottom: 1.15rem;
        }

        .form-label-modern {
            display: block;
            font-size: 0.825rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.4rem;
        }

        .alert-custom {
            padding: 0.85rem 1.15rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .auth-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
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

        @media (max-width: 860px) {
            .auth-container {
                flex-direction: column;
                max-width: 520px;
            }
            .auth-banner {
                display: none;
            }
            .auth-form-section {
                padding: 2rem 1.5rem;
            }
            .form-row-2 {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
<body>
    <script>
        if (localStorage.getItem('patemon_theme') === 'dark') {
            document.body.classList.add('theme-dark');
        }
    </script>

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

<div class="auth-container">
    <!-- Left Banner -->
    <div class="auth-banner">
        <div>
            <div class="auth-banner-badge">
                <i class="fa-solid fa-water"></i> Pemandian Patemon
            </div>
            <h2>Bergabung Bersama Kami</h2>
            <p>Daftarkan akun Anda sekarang untuk memesan tiket wisata secara online, menikmati kemudahan reservasi, dan riwayat pesanan.</p>
        </div>
        
        <div style="font-size: 0.85rem; color: #94a3b8;">
            &copy; <?= date('Y') ?> Wisata Pemandian Patemon. All rights reserved.
        </div>
    </div>

    <!-- Right Form -->
    <div class="auth-form-section">
        <div class="auth-header">
            <a href="<?= route_url('home') ?>" class="d-inline-block">
                <img src="<?= public_url('img/logo_pemandian_transparant.png') ?>" alt="Logo Pemandian Patemon" class="logo-light">
                <img src="<?= public_url('img/logo_pemandian_white.svg') ?>" alt="Logo Pemandian Patemon" class="logo-dark">
            </a>
            <h1 data-i18n="register_title">Buat Akun Baru</h1>
            <p data-i18n="register_desc">Lengkapi formulir di bawah ini untuk mendaftar akun.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-custom">
                <i class="fa-solid fa-circle-exclamation fs-5"></i>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="form-row-2">
                <div class="form-group-item">
                    <label class="form-label-modern" for="nama"><span data-i18n="fullname_label">Nama Lengkap</span> <span style="color: #ef4444;">*</span></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-id-card input-icon"></i>
                        <input 
                            type="text" 
                            class="form-control-modern" 
                            id="nama" 
                            name="nama" 
                            placeholder="Nama Lengkap (maks. 50 karakter)" 
                            data-i18n-placeholder="fullname_placeholder"
                            required 
                            maxlength="50"
                            pattern="^[a-zA-Z\s\.\']+$"
                            title="Nama hanya boleh berisi huruf, spasi, titik, atau tanda petik (maksimal 50 karakter)"
                            value="<?= e($_POST['nama'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-group-item">
                    <label class="form-label-modern" for="username"><span data-i18n="username_label">Username</span> <span style="color: #ef4444;">*</span></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input 
                            type="text" 
                            class="form-control-modern" 
                            id="username" 
                            name="username" 
                            placeholder="Username (maks. 30 karakter)" 
                            data-i18n-placeholder="username_placeholder"
                            required 
                            maxlength="30"
                            pattern="^[a-zA-Z0-9_\.]+$"
                            title="Username hanya boleh berisi huruf, angka, underscore, atau titik (maksimal 30 karakter)"
                            value="<?= e($_POST['username'] ?? '') ?>"
                        >
                    </div>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group-item">
                    <label class="form-label-modern" for="email"><span data-i18n="email_label">Alamat Email</span> <span style="color: #ef4444;">*</span></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-envelope input-icon"></i>
                        <input 
                            type="email" 
                            class="form-control-modern" 
                            id="email" 
                            name="email" 
                            placeholder="nama@email.com" 
                            data-i18n-placeholder="email_placeholder"
                            required 
                            maxlength="60"
                            value="<?= e($_POST['email'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-group-item">
                    <label class="form-label-modern" for="no_telepon" data-i18n="phone_label">Nomor WhatsApp / HP</label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-phone input-icon"></i>
                        <input 
                            type="tel" 
                            class="form-control-modern" 
                            id="no_telepon" 
                            name="no_telepon" 
                            placeholder="08xxxxxxxxxx" 
                            data-i18n-placeholder="phone_placeholder"
                            pattern="^0[0-9]{8,14}$"
                            inputmode="numeric"
                            maxlength="15"
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                            title="Format nomor Indonesia diawali angka 0 (9–15 digit angka saja)"
                            value="<?= e($_POST['no_telepon'] ?? '') ?>"
                        >
                    </div>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group-item">
                    <label class="form-label-modern" for="password"><span data-i18n="password_label">Password</span> <span style="color: #ef4444;">*</span></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            class="form-control-modern" 
                            id="password" 
                            name="password" 
                            placeholder="Min. 6 karakter" 
                            data-i18n-placeholder="password_placeholder"
                            required 
                            minlength="6"
                        >
                    </div>
                </div>

                <div class="form-group-item">
                    <label class="form-label-modern" for="password_confirm"><span data-i18n="confirm_password_label">Konfirmasi Password</span> <span style="color: #ef4444;">*</span></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-shield-halved input-icon"></i>
                        <input 
                            type="password" 
                            class="form-control-modern" 
                            id="password_confirm" 
                            name="password_confirm" 
                            placeholder="Ulangi password" 
                            data-i18n-placeholder="confirm_password_placeholder"
                            required 
                            minlength="6"
                        >
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-brand" style="width: 100%; padding: 0.85rem; font-size: 1rem; margin-top: 0.5rem;">
                <i class="fa-solid fa-user-plus"></i> <span data-i18n="btn_register">Buat Akun Baru</span>
            </button>
        </form>

        <div class="auth-footer">
            <span data-i18n="have_account">Sudah memiliki akun?</span> <a href="<?= route_url('login') ?>" data-i18n="login_here">Masuk di sini</a>
            <div style="margin-top: 0.5rem;">
                <a href="<?= route_url('home') ?>" style="color: #64748b; font-size: 0.85rem;">
                    <i class="fa-solid fa-arrow-left me-1"></i> <span data-i18n="back_to_home">Kembali ke Beranda</span>
                </a>
            </div>
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