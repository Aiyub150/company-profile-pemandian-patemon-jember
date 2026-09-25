<?php
require '../../app/config.php';
check_auth([1, 2]);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
          || isset($_POST['ajax']);

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

$id_tiket = (int)($_POST["id"] ?? 0);
$csrf = $_POST["csrf_token"] ?? '';

if ($id_tiket <= 0 || !validate_csrf($csrf)) {
    http_response_code(403);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
    } else {
        die("403 Forbidden: Token CSRF tidak valid.");
    }
    exit();
}

$stmtInfo = $conn->prepare("SELECT nama_tiket, harga FROM tiket WHERE id_tiket = ?");
$stmtInfo->bind_param("i", $id_tiket);
$stmtInfo->execute();
$tInfo = $stmtInfo->get_result()->fetch_assoc();
$stmtInfo->close();

$stmt = $conn->prepare("DELETE FROM tiket WHERE id_tiket = ?");
$stmt->bind_param("i", $id_tiket);
$stmt->execute();
$stmt->close();

if (function_exists('log_activity')) {
    $tDesc = $tInfo ? "Kategori: {$tInfo['nama_tiket']} (" . format_rupiah($tInfo['harga']) . ")" : "ID: {$id_tiket}";
    log_activity('DELETE', 'tiket', "Menghapus kategori tiket {$tDesc}");
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Kategori tiket berhasil dihapus.']);
    exit();
}

header("Location: " . route_url('admin_tiket'));
exit();
