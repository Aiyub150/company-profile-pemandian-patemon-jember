<?php
require '../../app/config.php';

// Hak akses: Super Admin (1), Admin (2), dan Staf Kasir (3)
check_auth([1, 2, 3]);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
          || isset($_POST['ajax']);

// SEC-03: Hanya izinkan metode HTTP POST untuk mencegah eksekusi via tautan GET
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
        echo json_encode(['success' => false, 'message' => 'Token keamanan sesi kedaluwarsa atau parameter tidak valid.']);
    } else {
        die("403 Forbidden: Token CSRF tidak valid.");
    }
    exit();
}

// Ambil info transaksi sebelum dihapus untuk catatan audit trail
$stmtInfo = $conn->prepare("SELECT total_harga, metode_pembayaran FROM transaksi WHERE id_transaksi = ?");
$stmtInfo->bind_param("i", $id_transaksi);
$stmtInfo->execute();
$info = $stmtInfo->get_result()->fetch_assoc();
$stmtInfo->close();

// Poin 7: Soft Delete transaksi (mengisi deleted_at = NOW())
$stmt = $conn->prepare("UPDATE transaksi SET deleted_at = NOW() WHERE id_transaksi = ?");
$stmt->bind_param("i", $id_transaksi);
$stmt->execute();
$stmt->close();

if (function_exists('log_activity')) {
    $detail = $info ? "Nominal: " . format_rupiah($info['total_harga']) . ", Metode: {$info['metode_pembayaran']}" : "";
    log_activity('SOFT_DELETE', 'transaksi', "Memindahkan transaksi ID #{$id_transaksi} ke tempat sampah/audit log. {$detail}");
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Transaksi berhasil dipindahkan ke tempat sampah (Soft Delete).'
    ]);
    exit();
}

$redirect = in_array((int)$_SESSION['level'], [2, 3], true) ? route_url('kasir') : route_url('transaksi');
header("Location: " . $redirect);
exit();
