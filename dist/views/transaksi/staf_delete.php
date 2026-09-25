<?php
require '../../app/config.php';
check_auth([1, 2, 3]);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
          || isset($_POST['ajax']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Operasi ini membutuhkan HTTP POST.']);
    } else {
        die("Method Not Allowed: Operasi penghapusan data harus melalui metode HTTP POST.");
    }
    exit();
}

$id_transaksi = (int)($_POST["id"] ?? 0);
$csrf = $_POST["csrf_token"] ?? '';

if ($id_transaksi <= 0 || !validate_csrf($csrf)) {
    http_response_code(403);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Token keamanan sesi kedaluwarsa.']);
    } else {
        die("403 Forbidden: Token CSRF tidak valid.");
    }
    exit();
}

// Soft delete
$stmt = $conn->prepare("UPDATE transaksi SET deleted_at = NOW() WHERE id_transaksi = ?");
$stmt->bind_param("i", $id_transaksi);
$stmt->execute();
$stmt->close();

if (function_exists('log_activity')) {
    log_activity('SOFT_DELETE', 'transaksi', "Staf kasir memindahkan transaksi ID #{$id_transaksi} ke tempat sampah");
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Transaksi berhasil dihapus (Soft Delete).']);
    exit();
}

header("Location: " . route_url('kasir'));
exit();
