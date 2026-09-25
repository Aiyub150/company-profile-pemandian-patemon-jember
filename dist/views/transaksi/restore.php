<?php
require '../../app/config.php';
// Hanya Super Admin (Level 1) yang dapat memulihkan transaksi yang dihapus
check_auth([1]);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
          || isset($_POST['ajax']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    } else {
        die("Method Not Allowed: Operasi pemulihan harus melalui metode HTTP POST.");
    }
    exit();
}

$id_transaksi = (int)($_POST["id"] ?? 0);
$csrf = $_POST["csrf_token"] ?? '';

if ($id_transaksi <= 0 || !validate_csrf($csrf)) {
    http_response_code(403);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
    } else {
        die("403 Forbidden: Token CSRF tidak valid.");
    }
    exit();
}

$stmt = $conn->prepare("UPDATE transaksi SET deleted_at = NULL WHERE id_transaksi = ?");
$stmt->bind_param("i", $id_transaksi);
$stmt->execute();
$stmt->close();

if (function_exists('log_activity')) {
    log_activity('RESTORE', 'transaksi', "Memulihkan transaksi ID #{$id_transaksi} dari tempat sampah kembali ke data aktif");
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Transaksi berhasil dipulihkan.']);
    exit();
}

header("Location: " . route_url('settings_history_log', ['tab' => 'trash', 'restored' => 1]));
exit();
