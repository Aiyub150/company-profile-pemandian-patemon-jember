<?php
require '../../app/config.php';
check_auth([1, 2, 3]);
$id = (int)($_GET['id'] ?? 0);
header("Location: " . route_url('transaksi_update', ['id' => $id]));
exit();
?>
