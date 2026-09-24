<?php
require '../../app/config.php';

// Hak akses: Admin (1) dan Staff (2)
check_auth([1, 2]);

$id_transaksi = (int)($_GET["id"] ?? 0);
$csrf = $_GET["csrf"] ?? '';

if ($id_transaksi > 0 && validate_csrf($csrf)) {
    // Karena tabel detail_transaksi memiliki ON DELETE CASCADE,
    // menghapus dari transaksi otomatis membersihkan detail_transaksi dengan aman.
    $stmt = $conn->prepare("DELETE FROM transaksi WHERE id_transaksi = ?");
    $stmt->bind_param("i", $id_transaksi);
    $stmt->execute();
    $stmt->close();
}

$redirect = ($_SESSION['level'] == 2) ? route_url('kasir') : route_url('transaksi');
header("Location: " . $redirect);
exit();
?>
