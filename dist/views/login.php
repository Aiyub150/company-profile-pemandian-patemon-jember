<?php
require_once __DIR__ . '/../app/config.php';

// Jika sudah login, redirect sesuai peran
if (isset($_SESSION['id_user'])) {
    if ((int)$_SESSION['level'] === 1) {
        header("Location: " . route_url('dashboard'));
    } elseif ((int)$_SESSION['level'] === 2) {
        header("Location: " . route_url('kasir'));
    } else {
        header("Location: " . route_url('home'));
    }
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validasi CSRF Token
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan sesi Anda kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = "Username dan password wajib diisi.";
        } else {
            // Gunakan Prepared Statement
            $stmt = $conn->prepare("SELECT id_user, nama, username, password, level FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                // Verifikasi password hash BCRYPT (dengan fallback migrasi otomatis)
                $is_password_valid = password_verify($password, $user['password']);
                if (!$is_password_valid && $password === $user['password']) {
                    $is_password_valid = true;
                    $new_hash = password_hash($password, PASSWORD_BCRYPT);
                    $up_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id_user = ?");
                    $up_stmt->bind_param("si", $new_hash, $user['id_user']);
                    $up_stmt->execute();
                    $up_stmt->close();
                }

                if ($is_password_valid) {
                    session_regenerate_id(true);

                    $_SESSION['id_user']  = (int)$user['id_user'];
                    $_SESSION['nama']     = $user['nama'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['level']    = (int)$user['level'];

                    if ((int)$_SESSION['level'] === 1) {
                        header("Location: " . route_url('dashboard'));
                    } elseif ((int)$_SESSION['level'] === 2) {
                        header("Location: " . route_url('kasir'));
                    } else {
                        header("Location: " . route_url('home'));
                    }
                    exit();
                } else {
                    $error = "Username atau password yang Anda masukkan salah.";
                }
            } else {
                $error = "Username atau password yang Anda masukkan salah.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pemandian Patemon</title>
    <link rel="icon" type="image/x-icon" href="../../public/img/icon.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/modern-theme.css">
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
            min-height: 600px;
        }

        .auth-banner {
            flex: 1.1;
            background: linear-gradient(rgba(15, 23, 42, 0.65), rgba(2, 132, 199, 0.75)), url('../../public/img/background.png');
            background-size: cover;
            background-position: center;
            padding: 3.5rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #ffffff;
            position: relative;
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

        .feature-pills {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .feature-pill-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.95rem;
            font-weight: 500;
            color: #f1f5f9;
        }

        .feature-pill-item i {
            color: #38bdf8;
            font-size: 1.1rem;
        }

        .auth-form-section {
            flex: 1;
            padding: 3.5rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }

        .auth-header {
            margin-bottom: 2rem;
        }

        .auth-header img {
            height: 48px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            margin-bottom: 1.25rem;
        }

        .auth-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 0.5rem;
        }

        .auth-header p {
            color: #64748b;
            font-size: 0.925rem;
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

        .alert-custom {
            padding: 0.85rem 1.15rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
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
            transition: color 0.15s ease;
        }

        .auth-footer a:hover {
            color: #0369a1;
            text-decoration: underline;
        }

        @media (max-width: 860px) {
            .auth-container {
                flex-direction: column;
                max-width: 480px;
            }
            .auth-banner {
                display: none;
            }
            .auth-form-section {
                padding: 2.5rem 2rem;
            }
        }
    </style>
</head>
<body>

<div class="auth-container">
    <!-- Left Visual Showcase -->
    <div class="auth-banner">
        <div>
            <div class="auth-banner-badge">
                <i class="fa-solid fa-water"></i> Pemandian Patemon
            </div>
            <h2>Destinasi Wisata Pemandian Alami</h2>
            <p>Sistem manajemen loket kasir terintegrasi, pemesanan tiket online instan, serta pelaporan pendapatan yang transparan.</p>
            
            <div class="feature-pills">
                <div class="feature-pill-item">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Sistem POS Loket Kasir Cepat & Otomatis</span>
                </div>
                <div class="feature-pill-item">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Cetak Nota & Barcode Struk Transaksi</span>
                </div>
                <div class="feature-pill-item">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Laporan Analitik Penjualan Real-time</span>
                </div>
            </div>
        </div>
        
        <div style="font-size: 0.85rem; color: #94a3b8;">
            &copy; <?= date('Y') ?> Wisata Pemandian Patemon. All rights reserved.
        </div>
    </div>

    <!-- Right Login Form -->
    <div class="auth-form-section">
        <div class="auth-header">
            <a href="<?= route_url('home') ?>">
                <img src="../../public/img/logo_pemandian_transparant.png" alt="Logo Pemandian Patemon">
            </a>
            <h1>Selamat Datang</h1>
            <p>Masukkan akun Anda untuk masuk ke sistem loket kasir.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-custom">
                <i class="fa-solid fa-circle-exclamation fs-5"></i>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= route_url('login') ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="form-group-item">
                <label class="form-label-modern" for="username">Username / Email</label>
                <div class="input-icon-group">
                    <i class="fa-solid fa-user input-icon"></i>
                    <input 
                        type="text" 
                        class="form-control-modern" 
                        id="username" 
                        name="username" 
                        placeholder="Masukkan username atau email" 
                        required 
                        autocomplete="username"
                        value="<?= e($_POST['username'] ?? '') ?>"
                    >
                </div>
            </div>

            <div class="form-group-item">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <label class="form-label-modern" for="password" style="margin-bottom: 0;">Password</label>
                    <a href="<?= route_url('forgot_password') ?>" style="font-size: 0.825rem; color: #0284c7; text-decoration: none; font-weight: 600;">Lupa Password?</a>
                </div>
                <div class="input-icon-group">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input 
                        type="password" 
                        class="form-control-modern" 
                        id="password" 
                        name="password" 
                        placeholder="Masukkan password Anda" 
                        required 
                        autocomplete="current-password"
                    >
                    <button type="button" class="toggle-password" id="btnTogglePassword" aria-label="Lihat Password">
                        <i class="fa-regular fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-brand" style="width: 100%; padding: 0.85rem; font-size: 1rem; margin-top: 0.5rem;">
                <i class="fa-solid fa-right-to-bracket"></i> Masuk Sekarang
            </button>
        </form>

        <div class="auth-footer">
            Belum punya akun? <a href="<?= route_url('register') ?>">Daftar Akun Baru</a>
            <div style="margin-top: 0.75rem;">
                <a href="<?= route_url('home') ?>" style="color: #64748b; font-weight: 500; font-size: 0.85rem;">
                    <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    const btnTogglePassword = document.getElementById('btnTogglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    btnTogglePassword.addEventListener('click', () => {
        const isPassword = passwordInput.getAttribute('type') === 'password';
        passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
        eyeIcon.classList.toggle('fa-eye', !isPassword);
        eyeIcon.classList.toggle('fa-eye-slash', isPassword);
    });
</script>
</body>
</html>
