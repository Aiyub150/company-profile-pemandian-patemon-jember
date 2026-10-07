<?php
/**
 * Controller: Kirim Ulang Email Aktivasi Akun Staf
 * Feedback-9 Poin 6
 */

require_once __DIR__ . '/../../app/config.php';

// Verifikasi sesi login (hanya Super Admin / Admin)
if (!isset($_SESSION['id_user'])) {
    header('Location: ' . route_url('login'));
    exit;
}

$level = (int)($_SESSION['level'] ?? 0);
if ($level !== 1 && $level !== 2) {
    $_SESSION['flash_error'] = 'Akses ditolak. Anda tidak memiliki izin untuk tindakan ini.';
    header('Location: ' . route_url('users'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . route_url('users'));
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!validate_csrf($csrf_token)) {
    $_SESSION['flash_error'] = 'Token keamanan tidak valid atau telah kedaluwarsa.';
    header('Location: ' . route_url('users'));
    exit;
}

$id_user = (int)($_POST['id_user'] ?? 0);
if ($id_user <= 0) {
    $_SESSION['flash_error'] = 'ID pengguna tidak valid.';
    header('Location: ' . route_url('users'));
    exit;
}

// Ambil data user
$stmt = $conn->prepare("SELECT id_user, nama, username, email, is_active FROM users WHERE id_user = ? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['flash_error'] = 'Data pengguna tidak ditemukan.';
    header('Location: ' . route_url('users'));
    exit;
}

if (empty($user['email'])) {
    $_SESSION['flash_error'] = "Pengguna '{$user['username']}' tidak memiliki alamat email yang terdaftar.";
    header('Location: ' . route_url('users'));
    exit;
}

if ((int)$user['is_active'] === 1) {
    $_SESSION['flash_info'] = "Akun '{$user['username']}' sudah berstatus aktif.";
    header('Location: ' . route_url('users'));
    exit;
}

// Buat token aktivasi baru
$token = bin2hex(random_bytes(32));
$expires_at = date('Y-m-d H:i:s', time() + (72 * 3600)); // 3 hari

$stmtUp = $conn->prepare("UPDATE users SET activation_token = ?, activation_expires_at = ? WHERE id_user = ?");
$stmtUp->bind_param("ssi", $token, $expires_at, $id_user);
$stmtUp->execute();
$stmtUp->close();

// Siapkan tautan aktivasi (menggunakan Absolute URL ke Port Web Aplikasi Utama)
$activation_url = route_url('activate', ['token' => $token], true);

// Buat konten email
$subject = "🎉 [Pemandian Patemon] Aktivasi Akun Staf Baru (@" . $user['username'] . ")";
$htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px; }
        .card { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .header { background: linear-gradient(135deg, #0ea5e9, #0284c7); padding: 28px; text-align: center; color: #ffffff; }
        .body { padding: 32px 28px; line-height: 1.6; font-size: 15px; }
        .btn { display: inline-block; background-color: #0284c7; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; margin-top: 16px; margin-bottom: 20px; }
        .note { background-color: #f0f9ff; border-left: 4px solid #0284c7; padding: 12px 16px; font-size: 13px; color: #0369a1; border-radius: 0 6px 6px 0; margin-top: 20px; }
        .footer { padding: 16px 28px; background-color: #f1f5f9; text-align: center; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h2 style="margin: 0; font-size: 20px;">Wisata Pemandian Patemon Jember</h2>
            <p style="margin: 6px 0 0; opacity: 0.9; font-size: 14px;">Undangan Aktivasi Akun Staf</p>
        </div>
        <div class="body">
            <p>Halo <strong>' . htmlspecialchars($user['nama'] ?: $user['username'], ENT_QUOTES, 'UTF-8') . '</strong>,</p>
            <p>Administrator telah mendaftarkan akun staf Anda di Sistem Informasi Kasir & Manajemen Wisata Pemandian Patemon dengan username <code>' . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . '</code>.</p>
            <p>Untuk mengaktifkan akun serta membuat kata sandi Anda sendiri, silakan klik tombol di bawah ini:</p>
            <div style="text-align: center;">
                <a href="' . $activation_url . '" class="btn">Aktivasi Akun & Buat Sandi</a>
            </div>
            <div class="note">
                <strong>Catatan Keamanan:</strong><br>
                Tautan aktivasi ini berlaku selama <strong>3 hari (72 jam)</strong> hingga <strong>' . date('d M Y H:i', strtotime($expires_at)) . ' WIB</strong>. Jangan berikan tautan ini kepada siapa pun.
            </div>
            <p style="margin-top: 20px; font-size: 13px; color: #64748b;">
                Jika tombol di atas tidak dapat diklik, salin dan tempel URL berikut ke peramban web Anda:<br>
                <a href="' . $activation_url . '" style="color: #0284c7; word-break: break-all;">' . $activation_url . '</a>
            </p>
        </div>
        <div class="footer">
            &copy; ' . date('Y') . ' UPTD Pariwisata Pemandian Patemon Tanggul, Jember.
        </div>
    </div>
</body>
</html>
';

$plainBody = "Halo " . ($user['nama'] ?: $user['username']) . ",\n\n"
    . "Administrator telah mendaftarkan akun staf Anda (@{$user['username']}).\n"
    . "Silakan klik tautan berikut untuk mengaktifkan akun dan membuat kata sandi:\n"
    . $activation_url . "\n\n"
    . "Tautan ini berlaku hingga: " . date('d M Y H:i', strtotime($expires_at)) . " WIB.\n";

$mailRes = send_smtp_email($user['email'], $user['nama'] ?: $user['username'], $subject, $htmlBody, $plainBody);

if ($mailRes['success']) {
    if (function_exists('log_activity')) {
        log_activity('RESEND_ACTIVATION_EMAIL', 'users', "Email aktivasi akun @{$user['username']} berhasil dikirim ulang ke {$user['email']}.", $id_user);
    }
    $_SESSION['flash_success'] = "Email aktivasi akun berhasil dikirimkan ke <strong>" . htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') . "</strong>.";
} else {
    $_SESSION['flash_warning'] = "Token aktivasi diperbarui, namun pengiriman email mengalami kendala (" . htmlspecialchars($mailRes['message'] ?? 'server Mailpit tidak aktif', ENT_QUOTES, 'UTF-8') . "). Super Admin dapat mengaktifkan akun secara langsung melalui tombol Aktifkan Langsung.";
}

header('Location: ' . route_url('users'));
exit;
