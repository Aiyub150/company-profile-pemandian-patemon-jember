<?php
require '../../app/config.php';
check_auth([1, 2]);

$id_transaksi = (int)($_GET["id"] ?? 0);
$csrf = $_GET["csrf"] ?? '';

if ($id_transaksi > 0 && validate_csrf($csrf)) {
    $stmt = $conn->prepare("DELETE FROM transaksi WHERE id_transaksi = ?");
    $stmt->bind_param("i", $id_transaksi);
    $stmt->execute();
    $stmt->close();
}

header("Location: " . route_url('kasir'));
exit();
?>
