<?php
/**
 * Controller: Aktifkan / Nonaktifkan Pengguna (Toggle Status)
 * Feedback-9 Poin 7
 */

require_once __DIR__ . '/../../app/config.php';

// Hanya Super Admin (level 1) yang diizinkan menonaktifkan akun
if (!isset($_SESSION['id_user'])) {
    header('Location: ' . route_url('login'));
    exit;
}

$level = (int)($_SESSION['level'] ?? 0);
if ($level !== 1) {
    $_SESSION['flash_error'] = 'Akses ditolak. Hanya Super Admin yang berhak mengubah status akun.';
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

// Tidak boleh menonaktifkan akun sendiri
if ($id_user === (int)$_SESSION['id_user']) {
    $_SESSION['flash_error'] = 'Anda tidak dapat menonaktifkan akun Super Admin Anda sendiri yang sedang aktif.';
    header('Location: ' . route_url('users'));
    exit;
}

// Ambil data user
$stmt = $conn->prepare("SELECT id_user, nama, username, is_active FROM users WHERE id_user = ? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['flash_error'] = 'Pengguna tidak ditemukan.';
    header('Location: ' . route_url('users'));
    exit;
}

$current_active = (int)($user['is_active'] ?? 1);
$new_status = ($current_active === 1) ? 0 : 1;

$stmtUp = $conn->prepare("UPDATE users SET is_active = ? WHERE id_user = ?");
$stmtUp->bind_param("ii", $new_status, $id_user);

if ($stmtUp->execute()) {
    $stmtUp->close();
    if ($new_status === 0) {
        if (function_exists('log_activity')) {
            log_activity('USER_DEACTIVATED', 'users', "Akun @{$user['username']} dinonaktifkan oleh Super Admin.", $id_user);
        }
        $_SESSION['flash_success'] = "Akun pengguna <strong>@" . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . "</strong> berhasil dinonaktifkan. Pengguna tidak dapat login ke sistem.";
    } else {
        if (function_exists('log_activity')) {
            log_activity('USER_ACTIVATED', 'users', "Akun @{$user['username']} diaktifkan kembali oleh Super Admin.", $id_user);
        }
        $_SESSION['flash_success'] = "Akun pengguna <strong>@" . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . "</strong> berhasil diaktifkan kembali.";
    }
} else {
    $_SESSION['flash_error'] = "Gagal memperbarui status akun: " . $conn->error;
}

header('Location: ' . route_url('users'));
exit;
