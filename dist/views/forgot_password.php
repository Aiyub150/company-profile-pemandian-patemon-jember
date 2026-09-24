<?php
require_once __DIR__ . '/../app/config.php';

$msg = '';
$msg_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $msg = "Token keamanan sesi Anda kedaluwarsa. Silakan muat ulang halaman.";
        $msg_type = 'danger';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Cek apakah email terdaftar
            $stmt = $conn->prepare("SELECT id_user FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            
            // Pesan aman anti user-enumeration
            $msg = "Jika alamat email terdaftar di sistem kami, instruksi pemulihan akun telah dikirimkan ke email Anda. Silakan periksa kotak masuk atau hubungi pengelola loket.";
            $msg_type = 'success';
            $stmt->close();
        } else {
            $msg = "Silakan masukkan alamat email yang valid.";
            $msg_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Pemandian Patemon</title>
    <link rel="icon" type="image/x-icon" href="<?= public_url('img/icon.png') ?>" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= public_url('css/modern-theme.css') ?>">
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

        .auth-card-single {
            width: 100%;
            max-width: 480px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            padding: 3rem 2.5rem;
        }

        .auth-header {
            text-align: center;
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
            margin-bottom: 1.5rem;
        }

        .form-label-modern {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
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
            line-height: 1.4;
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

<div class="auth-card-single">
    <div class="auth-header">
        <a href="<?= route_url('home') ?>">
            <img src="<?= public_url('img/logo_pemandian_transparant.png') ?>" alt="Logo Pemandian Patemon">
        </a>
        <h1>Pemulihan Akun</h1>
        <p>Masukkan alamat email Anda yang terdaftar untuk menerima petunjuk reset password.</p>
    </div>

    <?php if (!empty($msg)): ?>
        <?php if ($msg_type === 'success'): ?>
            <div class="alert-custom-success">
                <i class="fa-solid fa-circle-check fs-5 mt-1"></i>
                <div><?= e($msg) ?></div>
            </div>
        <?php else: ?>
            <div class="alert-custom-danger">
                <i class="fa-solid fa-circle-exclamation fs-5"></i>
                <div><?= e($msg) ?></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="form-group-item">
            <label class="form-label-modern" for="email">Alamat Email Terdaftar</label>
            <div class="input-icon-group">
                <i class="fa-solid fa-envelope input-icon"></i>
                <input 
                    type="email" 
                    class="form-control-modern" 
                    id="email" 
                    name="email" 
                    placeholder="nama@email.com" 
                    required 
                    autocomplete="email"
                >
            </div>
        </div>

        <button type="submit" class="btn-brand" style="width: 100%; padding: 0.85rem; font-size: 1rem;">
            <i class="fa-solid fa-paper-plane"></i> Kirim Permintaan Reset
        </button>
    </form>

    <div class="auth-footer">
        <div>Sudah ingat password? <a href="<?= route_url('login') ?>">Masuk di sini</a></div>
        <div style="margin-top: 0.5rem;"><a href="<?= route_url('register') ?>">Daftar Akun Baru</a></div>
        <div style="margin-top: 1rem;">
            <a href="<?= route_url('home') ?>" style="color: #64748b; font-size: 0.85rem;">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</div>

</body>
</html>
