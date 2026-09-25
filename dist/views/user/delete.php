<?php
require '../../app/config.php';

// Hak Akses Khusus: Hanya Administrator (Level 1)
check_auth([1]);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
          || isset($_POST['ajax']);

// SEC-03: Tolak HTTP GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    } else {
        die("Method Not Allowed: Operasi ini membutuhkan HTTP POST.");
    }
    exit();
}

$id_user = (int)($_POST["id"] ?? 0);
$csrf = $_POST["csrf_token"] ?? '';

if ($id_user <= 0 || !validate_csrf($csrf)) {
    http_response_code(403);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Token keamanan sesi kedaluwarsa.']);
    } else {
        die("403 Forbidden: Token CSRF tidak valid.");
    }
    exit();
}

// Larang menghapus akun yang sedang aktif login
if ($id_user === (int)$_SESSION['id_user']) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.']);
    } else {
        header("Location: " . route_url('users', ['err' => 'self_delete']));
    }
    exit();
}

// Proteksi Integritas Keuangan: Cegah penghapusan user yang memiliki data transaksi/pemesanan tiket aktif
$stmt_check = $conn->prepare("SELECT COUNT(*) FROM transaksi WHERE id_user = ?");
$stmt_check->bind_param("i", $id_user);
$stmt_check->execute();
$stmt_check->bind_result($tx_count);
$stmt_check->fetch();
$stmt_check->close();

if ($tx_count > 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Pengguna tidak dapat dihapus karena memiliki riwayat data transaksi keuangan.']);
    } else {
        header("Location: " . route_url('users', ['err' => 'has_transactions']));
    }
    exit();
}

// Ambil info nama/username untuk log audit
$stmtInfo = $conn->prepare("SELECT username, nama, level FROM users WHERE id_user = ?");
$stmtInfo->bind_param("i", $id_user);
$stmtInfo->execute();
$uInfo = $stmtInfo->get_result()->fetch_assoc();
$stmtInfo->close();

$stmt = $conn->prepare("DELETE FROM users WHERE id_user = ?");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$stmt->close();

if (function_exists('log_activity')) {
    $uDesc = $uInfo ? "Username: {$uInfo['username']} ({$uInfo['nama']}, Level: {$uInfo['level']})" : "ID: {$id_user}";
    log_activity('DELETE', 'user', "Menghapus user {$uDesc}");
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Pengguna berhasil dihapus permanen.']);
    exit();
}

header("Location: " . route_url('users', ['msg' => 'deleted']));
exit();
