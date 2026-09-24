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
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format alamat email tidak valid.";
        } elseif (strlen($password) < 6) {
            $error = "Password minimal terdiri dari 6 karakter.";
        } elseif ($password !== $password_confirm) {
            $error = "Konfirmasi password tidak cocok dengan password yang dimasukkan.";
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
                    header("Location: login.php?registered=1");
                    exit();
                } else {
                    $error = "Terjadi kesalahan sistem saat mendaftar: " . e($stmt->error);
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
</head>
<body>

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
            <a href="index.php">
                <img src="../../public/img/logo_pemandian_transparant.png" alt="Logo Pemandian Patemon">
            </a>
            <h1>Buat Akun Baru</h1>
            <p>Lengkapi formulir di bawah ini untuk mendaftar akun.</p>
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
                    <label class="form-label-modern" for="nama">Nama Lengkap <span style="color: #ef4444;">*</span></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-id-card input-icon"></i>
                        <input 
                            type="text" 
                            class="form-control-modern" 
                            id="nama" 
                            name="nama" 
                            placeholder="Nama Lengkap" 
                            required 
                            value="<?= e($_POST['nama'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-group-item">
                    <label class="form-label-modern" for="username">Username <span style="color: #ef4444;">*</span></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-user input-icon"></i>
                        <input 
                            type="text" 
                            class="form-control-modern" 
                            id="username" 
                            name="username" 
                            placeholder="Username" 
                            required 
                            value="<?= e($_POST['username'] ?? '') ?>"
                        >
                    </div>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group-item">
                    <label class="form-label-modern" for="email">Alamat Email <span style="color: #ef4444;">*</span></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-envelope input-icon"></i>
                        <input 
                            type="email" 
                            class="form-control-modern" 
                            id="email" 
                            name="email" 
                            placeholder="nama@email.com" 
                            required 
                            value="<?= e($_POST['email'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="form-group-item">
                    <label class="form-label-modern" for="no_telepon">Nomor WhatsApp / HP</label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-phone input-icon"></i>
                        <input 
                            type="tel" 
                            class="form-control-modern" 
                            id="no_telepon" 
                            name="no_telepon" 
                            placeholder="08xxxxxxxxxx" 
                            value="<?= e($_POST['no_telepon'] ?? '') ?>"
                        >
                    </div>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group-item">
                    <label class="form-label-modern" for="password">Password <span style="color: #ef4444;">*</span></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            class="form-control-modern" 
                            id="password" 
                            name="password" 
                            placeholder="Min. 6 karakter" 
                            required 
                            minlength="6"
                        >
                    </div>
                </div>

                <div class="form-group-item">
                    <label class="form-label-modern" for="password_confirm">Konfirmasi Password <span style="color: #ef4444;">*</span></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-shield-halved input-icon"></i>
                        <input 
                            type="password" 
                            class="form-control-modern" 
                            id="password_confirm" 
                            name="password_confirm" 
                            placeholder="Ulangi password" 
                            required 
                            minlength="6"
                        >
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-brand" style="width: 100%; padding: 0.85rem; font-size: 1rem; margin-top: 0.5rem;">
                <i class="fa-solid fa-user-plus"></i> Buat Akun Baru
            </button>
        </form>

        <div class="auth-footer">
            Sudah memiliki akun? <a href="login.php">Masuk di sini</a>
            <div style="margin-top: 0.5rem;">
                <a href="index.php" style="color: #64748b; font-size: 0.85rem;">
                    <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>