<?php
/**
 * Modul Pemulihan Kata Sandi Aman (MF-01: Secure Password Recovery)
 * Dilengkapi Token CSPRNG Hash SHA-256, Expiry 15 Menit, Single-Use, Rate Limiting,
 * serta Simulasi Server Email (Mailpit / Mailbox) Interaktif.
 */
require_once __DIR__ . '/../app/config.php';

$msg = '';
$msg_type = '';
$step = 1; // 1 = Minta Tautan, 2 = Form Kata Sandi Baru, 3 = Sukses Penuh
$simulated_mail = null;
$token_user = null;

$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$fifteen_mins_ago = time() - (15 * 60);

// Bersihkan rekam login/recovery attempts lama (> 24 jam)
$conn->query("DELETE FROM login_attempts WHERE attempt_time < " . (time() - 86400));

// Periksa apakah ada token yang diberikan via GET atau POST
$input_token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));

if (!empty($input_token)) {
    $token_hash = hash('sha256', $input_token);
    $stmtChk = $conn->prepare("
        SELECT pr.id, pr.id_user, pr.expires_at, pr.used_at, u.username, u.nama, u.email 
        FROM password_resets pr 
        JOIN users u ON pr.id_user = u.id_user 
        WHERE pr.token_hash = ? 
        LIMIT 1
    ");
    $stmtChk->bind_param("s", $token_hash);
    $stmtChk->execute();
    $token_record = $stmtChk->get_result()->fetch_assoc();
    $stmtChk->close();

    if (!$token_record) {
        $step = 1;
        $msg = "Tautan atau token pemulihan kata sandi tidak valid. Silakan ajukan permohonan baru.";
        $msg_type = 'danger';
    } elseif ($token_record['used_at'] !== null) {
        $step = 1;
        $msg = "Tautan pemulihan ini sudah pernah digunakan sebelumnya (Single-Use Token). Demi keamanan akun Anda, silakan minta tautan baru.";
        $msg_type = 'danger';
    } elseif (strtotime($token_record['expires_at']) < time()) {
        $step = 1;
        $msg = "Tautan pemulihan telah kedaluwarsa (masa berlaku 15 menit telah habis). Silakan ajukan permohonan baru.";
        $msg_type = 'danger';
    } else {
        // Token valid! Tampilkan Step 2 (Form Password Baru)
        $step = 2;
        $token_user = $token_record;
    }
}

// Proses Form POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $msg = "Token keamanan sesi Anda telah kedaluwarsa. Silakan muat ulang halaman.";
        $msg_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'request_reset') {
            // 1. Cek Rate Limiting (Maksimal 5 percobaan per 15 menit per IP)
            $stmtLimit = $conn->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE ip_address = ? AND username = 'pw_recovery' AND attempt_time > ?");
            $stmtLimit->bind_param("si", $ip, $fifteen_mins_ago);
            $stmtLimit->execute();
            $recent_attempts = (int)$stmtLimit->get_result()->fetch_assoc()['cnt'];
            $stmtLimit->close();

            if ($recent_attempts >= 5) {
                $msg = "Terlalu banyak permintaan pemulihan kata sandi dari perangkat ini. Demi keamanan akun, silakan tunggu 15 menit sebelum mencoba kembali.";
                $msg_type = 'danger';
            } else {
                // Catat attempt
                $now = time();
                $stmtAtt = $conn->prepare("INSERT INTO login_attempts (ip_address, username, attempt_time) VALUES (?, 'pw_recovery', ?)");
                $stmtAtt->bind_param("si", $ip, $now);
                $stmtAtt->execute();
                $stmtAtt->close();

                $identity = trim($_POST['identity'] ?? '');
                if (empty($identity)) {
                    $msg = "Silakan masukkan username atau alamat email akun Anda.";
                    $msg_type = 'danger';
                } else {
                    // Cari user berdasarkan username ATAU email (Feedback-9 Poin 7)
                    $stmtUser = $conn->prepare("SELECT id_user, nama, username, email, is_active, deleted_at FROM users WHERE (username = ? OR email = ?) LIMIT 1");
                    $stmtUser->bind_param("ss", $identity, $identity);
                    $stmtUser->execute();
                    $user = $stmtUser->get_result()->fetch_assoc();
                    $stmtUser->close();

                    if ($user && ($user['deleted_at'] !== null || (isset($user['is_active']) && (int)$user['is_active'] === 0))) {
                        $msg = "Akun Anda telah dinonaktifkan.";
                        $msg_type = 'danger';
                        $swal_deactivated = true;
                    } elseif ($user && !empty($user['email'])) {
                        // Buat token CSPRNG aman (32 bytes = 64 karakter hex)
                        $raw_token   = bin2hex(random_bytes(32));
                        $token_hash  = hash('sha256', $raw_token);
                        $expires_at  = date('Y-m-d H:i:s', time() + (15 * 60)); // 15 menit

                        // Nonaktifkan token lama yang belum terpakai untuk user ini
                        $stmtOld = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id_user = ? AND used_at IS NULL");
                        $stmtOld->bind_param("i", $user['id_user']);
                        $stmtOld->execute();
                        $stmtOld->close();

                        // Simpan token baru ke database
                        $stmtIns = $conn->prepare("INSERT INTO password_resets (id_user, token_hash, expires_at) VALUES (?, ?, ?)");
                        $stmtIns->bind_param("iss", $user['id_user'], $token_hash, $expires_at);
                        $stmtIns->execute();
                        $stmtIns->close();

                        // Gunakan Absolute URL ke Port Web Aplikasi Utama (bukan origin Webmail)
                        $reset_link = route_url('forgot_password', ['token' => $raw_token], true);
                        $subject = "🔒 [Pemandian Patemon] Permintaan Reset Kata Sandi (@{$user['username']})";
                        $htmlBody = '
                        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;">
                            <div style="text-align: center; margin-bottom: 24px;">
                                <h2 style="color: #0284c7; margin: 0; font-size: 22px;">Wisata Pemandian Patemon</h2>
                                <p style="color: #64748b; font-size: 13px; margin: 4px 0 0 0;">Layanan Keamanan Akun & Sistem Kasir</p>
                            </div>
                            <p style="font-size: 15px; color: #1e293b;">Halo, <strong>' . htmlspecialchars($user['nama'] ?: $user['username']) . '</strong>!</p>
                            <p style="font-size: 14px; color: #475569; line-height: 1.6;">
                                Kami menerima permohonan untuk menyetel ulang kata sandi akun Anda (<strong>@' . htmlspecialchars($user['username']) . '</strong>). Tautan ini bersifat rahasia, sekali pakai, dan kedaluwarsa dalam waktu <strong>15 menit</strong>.
                            </p>
                            <div style="text-align: center; margin: 28px 0;">
                                <a href="' . $reset_link . '" style="background: linear-gradient(135deg, #0284c7, #0369a1); color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block; font-size: 14px; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);">Atur Ulang Kata Sandi Saya</a>
                            </div>
                            <p style="font-size: 13px; color: #64748b; line-height: 1.5;">
                                Apabila tombol di atas tidak dapat diklik, buka tautan langsung berikut di browser Anda:<br>
                                <a href="' . $reset_link . '" style="color: #0284c7; word-break: break-all;">' . $reset_link . '</a>
                            </p>
                            <p style="font-size: 12px; color: #94a3b8; margin-top: 24px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                                Jika Anda tidak merasa mengajukan permintaan ini, Anda dapat mengabaikan email ini. Akun Anda tetap aman terlindungi.
                            </p>
                        </div>';

                        $plainBody = "Halo " . ($user['nama'] ?: $user['username']) . ",\n\n"
                                   . "Permintaan reset kata sandi untuk akun @" . $user['username'] . ".\n"
                                   . "Buka tautan ini dalam 15 menit:\n" . $reset_link . "\n\n"
                                   . "Jika Anda tidak meminta ini, abaikan email ini.";

                        // Kirim email via SMTP ke Mailpit port 8001 (Feedback-9 Poin 5)
                        $toName = $user['nama'] ?: $user['username'];
                        $smtpResult = send_smtp_email($user['email'], $toName, $subject, $htmlBody, $plainBody);

                        if ($smtpResult['success']) {
                            if (function_exists('log_activity')) {
                                log_activity('PW_RESET_REQ', 'auth', "Email reset kata sandi terkirim ke {$user['email']} via Mailpit", $user['id_user']);
                            }
                            $_SESSION['flash_success'] = 'Tautan pemulihan kata sandi berhasil dikirim ke email Anda! Silakan periksa inbox (Mailpit) dan gunakan tautan tersebut untuk mengatur ulang kata sandi.';
                            header('Location: ' . route_url('login'));
                            exit;
                        } else {
                            $msg = 'Email pemulihan kata sandi gagal terkirim (' . ($smtpResult['error'] ?? 'koneksi SMTP terputus') . '). Pastikan server Mailpit port 8001 aktif.';
                            $msg_type = 'danger';
                        }
                    } else {
                        // Anti-User Enumeration: Berikan respon netral agar penyerang tidak dapat membedakan akun terdaftar atau tidak
                        $_SESSION['flash_success'] = 'Jika username atau alamat email Anda terdaftar di sistem, tautan pemulihan kata sandi telah dikirimkan ke kotak masuk email Anda.';
                        header('Location: ' . route_url('login'));
                        exit;
                    }
                }
            }
        } elseif ($action === 'submit_new_password') {
            $token_val = trim($_POST['token'] ?? '');
            $new_pass  = $_POST['new_password'] ?? '';
            $conf_pass = $_POST['confirm_password'] ?? '';

            if (empty($token_val)) {
                $step = 1;
                $msg = "Token pemulihan tidak ditemukan.";
                $msg_type = 'danger';
            } else {
                $token_hash = hash('sha256', $token_val);
                $stmtVer = $conn->prepare("
                    SELECT pr.id, pr.id_user, pr.expires_at, pr.used_at, u.username, u.nama 
                    FROM password_resets pr 
                    JOIN users u ON pr.id_user = u.id_user 
                    WHERE pr.token_hash = ? 
                    LIMIT 1
                ");
                $stmtVer->bind_param("s", $token_hash);
                $stmtVer->execute();
                $token_record = $stmtVer->get_result()->fetch_assoc();
                $stmtVer->close();

                if (!$token_record || $token_record['used_at'] !== null || strtotime($token_record['expires_at']) < time()) {
                    $step = 1;
                    $msg = "Sesi tautan pemulihan ini tidak valid, telah kedaluwarsa, atau sudah pernah digunakan.";
                    $msg_type = 'danger';
                } elseif (empty($new_pass) || empty($conf_pass)) {
                    $step = 2;
                    $token_user = $token_record;
                    $msg = "Semua kolom kata sandi baru wajib diisi.";
                    $msg_type = 'danger';
                } elseif (strlen($new_pass) < 6) {
                    $step = 2;
                    $token_user = $token_record;
                    $msg = "Kata sandi baru minimal harus 6 karakter demi keamanan akun.";
                    $msg_type = 'danger';
                } elseif ($new_pass !== $conf_pass) {
                    $step = 2;
                    $token_user = $token_record;
                    $msg = "Konfirmasi kata sandi baru tidak cocok. Silakan ketik ulang.";
                    $msg_type = 'danger';
                } else {
                    // Update password pengguna dengan BCRYPT hash yang kuat
                    $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
                    $stmtUp = $conn->prepare("UPDATE users SET password = ? WHERE id_user = ?");
                    $stmtUp->bind_param("si", $new_hash, $token_record['id_user']);
                    $stmtUp->execute();
                    $stmtUp->close();

                    // Tandai token sebagai telah digunakan (Single-Use Guarantee)
                    $stmtUsed = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
                    $stmtUsed->bind_param("i", $token_record['id']);
                    $stmtUsed->execute();
                    $stmtUsed->close();

                    // Bersihkan seluruh attempt recovery dari IP ini
                    $stmtClr = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND username = 'pw_recovery'");
                    $stmtClr->bind_param("s", $ip);
                    $stmtClr->execute();
                    $stmtClr->close();

                    if (function_exists('log_activity')) {
                        log_activity('PW_RESET_OK', 'auth', "Kata sandi berhasil diperbarui via token pemulihan untuk akun {$token_record['username']}", $token_record['id_user']);
                    }

                    $step = 3;
                    $msg = "Kata sandi Anda berhasil diperbarui! Silakan masuk menggunakan kata sandi baru Anda.";
                    $msg_type = 'success';
                }
            }
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
            padding: 2rem 1.25rem;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1e293b;
        }

        .auth-container-wide {
            width: 100%;
            max-width: 620px;
            margin: auto;
        }

        .auth-card-single {
            width: 100%;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            padding: 2.75rem 2.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-header img {
            height: 54px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            margin-bottom: 1.25rem;
            transition: transform 0.25s ease;
        }
        .auth-header img:hover {
            transform: scale(1.04);
        }

        .auth-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 0.5rem;
            letter-spacing: -0.02em;
        }

        .auth-header p {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.55;
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

        .input-icon-group {
            position: relative;
        }

        .input-icon-group .input-icon {
            position: absolute;
            left: 1.15rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.05rem;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .input-icon-group .toggle-pw-btn {
            position: absolute;
            right: 1.15rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 0;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }
        .input-icon-group .toggle-pw-btn:hover {
            color: #0284c7;
        }

        .input-icon-group .form-control-modern {
            width: 100%;
            height: 52px;
            padding-left: 3rem !important;
            padding-right: 2.75rem !important;
            font-size: 0.95rem;
            border-radius: 14px;
            border: 1.5px solid #e2e8f0;
            background: #ffffff;
            color: #0f172a;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }
        .input-icon-group .form-control-modern:focus {
            border-color: #0284c7;
            outline: none;
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.15);
        }

        .alert-custom-success {
            padding: 1rem 1.25rem;
            border-radius: 14px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            margin-bottom: 1.5rem;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            line-height: 1.5;
        }

        .alert-custom-danger {
            padding: 1rem 1.25rem;
            border-radius: 14px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            margin-bottom: 1.5rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            line-height: 1.5;
        }

        .step-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 1rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        /* Mailpit / Mailbox Simulator UI (Feedback-7 Poin 5) */
        .mailpit-card {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 18px;
            margin-bottom: 1.75rem;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
            transition: all 0.3s ease;
        }

        .mailpit-header {
            background: #0f172a;
            color: #f8fafc;
            padding: 0.85rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
        }

        .mailpit-header .mailpit-brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .mailpit-header .mailpit-status {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.775rem;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.15);
        }

        .mailpit-body {
            padding: 1.25rem;
        }

        .mailpit-meta {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.85rem 1.15rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }

        .mailpit-meta-row {
            display: flex;
            padding: 0.25rem 0;
            gap: 0.75rem;
            line-height: 1.4;
        }

        .mailpit-meta-label {
            width: 75px;
            flex-shrink: 0;
            color: #64748b;
            font-weight: 600;
        }

        .mailpit-meta-val {
            color: #0f172a;
            font-weight: 500;
            word-break: break-all;
        }

        .mailpit-content-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.5rem;
            text-align: left;
        }

        .mailpit-content-box h5 {
            color: #0369a1;
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0 0 0.75rem;
        }

        .mailpit-btn-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            width: 100%;
            padding: 0.95rem 1.25rem;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: 12px;
            text-decoration: none !important;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
            margin-top: 1.25rem;
            transition: all 0.2s ease;
        }
        .mailpit-btn-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(2, 132, 199, 0.4);
            filter: brightness(1.05);
        }

        .mailpit-empty {
            text-align: center;
            padding: 2rem 1.5rem;
            color: #64748b;
        }

        .mailpit-empty-icon {
            font-size: 2.75rem;
            color: #cbd5e1;
            margin-bottom: 0.75rem;
        }

        .auth-footer {
            margin-top: 2.25rem;
            text-align: center;
            font-size: 0.925rem;
            color: #64748b;
        }

        .auth-footer a {
            color: #0284c7;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .auth-footer a:hover {
            text-decoration: underline;
            color: #0369a1;
        }

        /* Dark Mode Overrides */
        .theme-dark .auth-card-single {
            background: #1e293b;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .theme-dark .auth-header h1 {
            color: #f1f5f9;
        }
        .theme-dark .auth-header p {
            color: #94a3b8;
        }
        .theme-dark .form-label-modern {
            color: #cbd5e1;
        }
        .theme-dark .input-icon-group .form-control-modern {
            background: #0f172a;
            border-color: #334155;
            color: #f8fafc;
        }
        .theme-dark .input-icon-group .form-control-modern:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.15);
        }
        .theme-dark .mailpit-card {
            background: #0f172a;
            border-color: #334155;
        }
        .theme-dark .mailpit-meta {
            background: #1e293b;
            border-color: #334155;
        }
        .theme-dark .mailpit-meta-label {
            color: #94a3b8;
        }
        .theme-dark .mailpit-meta-val {
            color: #f1f5f9;
        }
        .theme-dark .mailpit-content-box {
            background: #1e293b;
            border-color: #334155;
            color: #cbd5e1;
        }
        .theme-dark .mailpit-content-box h5 {
            color: #38bdf8;
        }
        .theme-dark .auth-footer {
            color: #94a3b8;
        }
        .theme-dark .alert-custom-success {
            background: rgba(22, 101, 52, 0.25);
            border-color: #166534;
            color: #86efac;
        }
        .theme-dark .alert-custom-danger {
            background: rgba(153, 27, 27, 0.25);
            border-color: #991b1b;
            color: #fca5a5;
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

<div class="auth-container-wide">
    <div class="auth-card-single">
        <div class="auth-header">
            <a href="<?= route_url('home') ?>" title="Kembali ke Halaman Utama">
                <img src="<?= public_url('img/logo_pemandian_transparant.png') ?>" alt="Logo Pemandian Patemon" class="logo-light" id="authLogoLight">
                <img src="<?= public_url('img/logo_pemandian_white.svg') ?>" alt="Logo Pemandian Patemon" class="logo-dark" id="authLogoDark">
            </a>
            <h1>Pemulihan Kata Sandi</h1>
            <p>Atur ulang kata sandi akun Anda secara aman dengan verifikasi token kriptografis.</p>
        </div>

        <?php if (!empty($msg)): ?>
            <?php if ($msg_type === 'success'): ?>
                <div class="alert-custom-success">
                    <i class="fa-solid fa-circle-check fs-5 mt-0.5"></i>
                    <div><?= e($msg) ?></div>
                </div>
            <?php else: ?>
                <div class="alert-custom-danger">
                    <i class="fa-solid fa-circle-exclamation fs-5 mt-0.5"></i>
                    <div><?= e($msg) ?></div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- STEP 1: Minta Tautan Reset Kata Sandi -->
            <div class="text-center mb-4">
                <span class="step-pill bg-light text-primary border">
                    <i class="fa-solid fa-shield-halved"></i> Permintaan Tautan Reset
                </span>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="request_reset">

                <div class="form-group-item">
                    <label class="form-label-modern" for="identity">Username atau Alamat Email <span class="text-danger">*</span></label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-user-lock input-icon"></i>
                        <input 
                            type="text" 
                            class="form-control-modern" 
                            id="identity" 
                            name="identity" 
                            placeholder="Masukkan username atau nama@email.com" 
                            required 
                            autocomplete="username"
                            value="<?= e($_POST['identity'] ?? '') ?>"
                        >
                    </div>
                    <small class="text-muted d-block mt-2" style="font-size: 0.8rem; line-height: 1.4;">
                        Sistem akan menghasilkan tautan pemulihan terenkripsi dengan masa berlaku 15 menit.
                    </small>
                </div>

                <button type="submit" class="btn-brand w-100 py-3 fw-bold fs-6 shadow-sm mt-3" style="border-radius: 14px; height: 52px;">
                    <i class="fa-solid fa-paper-plane me-2"></i> Kirim Tautan Pemulihan
                </button>
            </form>

        <?php elseif ($step === 1 && $simulated_mail !== null): ?>
            <!-- Jika email simulasi sudah tampil, sediakan opsi coba akun lain -->
            <div class="text-center mt-2">
                <a href="<?= route_url('forgot_password') ?>" class="text-decoration-none text-muted small fw-semibold">
                    <i class="fa-solid fa-arrow-rotate-left me-1"></i> Masukkan Alamat Email / Akun Lain
                </a>
            </div>

        <?php elseif ($step === 2 && !empty($token_user)): ?>
            <!-- STEP 2: Buat Kata Sandi Baru dengan Token Valid -->
            <div class="text-center mb-4">
                <span class="step-pill" style="background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0;">
                    <i class="fa-solid fa-key"></i> Langkah 2: Buat Kata Sandi Baru
                </span>
                <div class="mt-2" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.65rem 1rem; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
                    <i class="fa-solid fa-circle-check text-success"></i>
                    <span>Akun Terverifikasi: <strong><?= e($token_user['nama'] ?: $token_user['username']) ?></strong> (@<?= e($token_user['username']) ?>)</span>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="submit_new_password">
                <input type="hidden" name="token" value="<?= e($input_token) ?>">

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
                        <button type="button" class="toggle-pw-btn" onclick="togglePasswordVisibility('new_password', this)" title="Lihat/Sembunyikan Sandi">
                            <i class="fa-regular fa-eye"></i>
                        </button>
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
                        <button type="button" class="toggle-pw-btn" onclick="togglePasswordVisibility('confirm_password', this)" title="Lihat/Sembunyikan Sandi">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-brand w-100 py-3 fw-bold fs-6 shadow-sm mt-3" style="border-radius: 14px; height: 52px;">
                    <i class="fa-solid fa-floppy-disk me-2"></i> Simpan & Perbarui Kata Sandi
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="<?= route_url('forgot_password') ?>" class="text-muted small text-decoration-none">
                    <i class="fa-solid fa-arrow-rotate-left me-1"></i> Batalkan & Ajukan Tautan Lain
                </a>
            </div>

        <?php elseif ($step === 3): ?>
            <!-- STEP 3: Sukses Penuh -->
            <div class="text-center py-4">
                <div style="width: 76px; height: 76px; border-radius: 50%; background: #dcfce7; color: #16a34a; font-size: 2.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto;">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h3 class="fw-bold text-dark mb-2">Kata Sandi Berhasil Diperbarui!</h3>
                <p class="text-muted small mb-4" style="line-height: 1.5;">Akun Anda kini telah aman dengan kata sandi baru. Token pemulihan telah ditandai kedaluwarsa secara permanen.</p>

                <a href="<?= route_url('login') ?>" class="btn-brand d-inline-flex align-items-center justify-content-center w-100 py-3 fw-bold fs-6 text-decoration-none shadow-sm" style="border-radius: 14px; height: 52px;">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Masuk Sekarang
                </a>
            </div>
        <?php endif; ?>

        <div class="auth-footer">
            <div>Sudah ingat kata sandi? <a href="<?= route_url('login') ?>">Masuk di sini</a></div>
            <div style="margin-top: 0.65rem;"><a href="<?= route_url('register') ?>">Daftar Akun Baru</a></div>
            <div style="margin-top: 1.25rem;">
                <a href="<?= route_url('home') ?>" style="color: #64748b; font-size: 0.875rem;">
                    <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    }

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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (!empty($swal_deactivated)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'error',
        title: 'Akun Dinonaktifkan',
        text: 'Akun Anda telah dinonaktifkan.',
        confirmButtonColor: '#0284c7',
        confirmButtonText: 'Tutup'
    });
});
</script>
<?php endif; ?>
</body>
</html>
