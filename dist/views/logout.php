<?php
/**
 * Modul Logout Aman (MF-10: Secure Logout Semantics)
 * Mendukung POST dengan verifikasi CSRF, pembersihan variabel sesi,
 * pencatatan audit trail, serta invalidasi cookie peramban.
 */
require_once __DIR__ . '/../app/config.php';

$user_id = $_SESSION['id_user'] ?? null;
$username = $_SESSION['username'] ?? 'User';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifikasi CSRF pada request POST
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        // Jika token tidak valid saat POST, hentikan untuk mencegah login/logout CSRF
        http_response_code(403);
        die("403 Forbidden: Token keamanan sesi tidak valid saat memproses logout.");
    }
}

// Catat aktivitas logout jika user sedang login
if ($user_id && function_exists('log_activity')) {
    log_activity('LOGOUT', 'auth', "Pengguna {$username} keluar dari sistem.", $user_id);
}

// 1. Kosongkan array sesi
$_SESSION = [];

// 2. Hapus cookie sesi dari peramban
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Hancurkan sesi server
if (session_id() !== '') {
    @session_unset();
    @session_destroy();
}

// Arahkan kembali ke halaman login atau beranda dengan pesan sukses
header("Location: " . route_url('login') . "?msg=logged_out");
exit();