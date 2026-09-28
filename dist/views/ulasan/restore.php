<?php
require '../../app/config.php';
// Hanya Super Admin (Level 1) yang dapat memulihkan testimoni/ulasan yang dihapus
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

$id_ulasan = (int)($_POST["id"] ?? 0);
$csrf = $_POST["csrf_token"] ?? '';

if ($id_ulasan <= 0 || !validate_csrf($csrf)) {
    http_response_code(403);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
    } else {
        die("403 Forbidden: Token CSRF tidak valid.");
    }
    exit();
}

$stmtInfo = $conn->prepare("SELECT username FROM ulasan WHERE id_ulasan = ?");
$stmtInfo->bind_param("i", $id_ulasan);
$stmtInfo->execute();
$uInfo = $stmtInfo->get_result()->fetch_assoc();
$stmtInfo->close();

$stmt = $conn->prepare("UPDATE ulasan SET deleted_at = NULL WHERE id_ulasan = ?");
$stmt->bind_param("i", $id_ulasan);
$stmt->execute();
$stmt->close();

if (function_exists('log_activity')) {
    $author = $uInfo['username'] ?? "ID #{$id_ulasan}";
    log_activity('RESTORE', 'ulasan', "Memulihkan ulasan dari {$author} dari tempat sampah kembali aktif");
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Ulasan berhasil dipulihkan.']);
    exit();
}

header("Location: " . route_url('settings_history_log', ['tab' => 'trash_ulasan', 'restored' => 1]));
exit();
