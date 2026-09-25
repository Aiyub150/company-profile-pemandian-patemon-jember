<?php
require '../../app/config.php';
check_auth([1, 2, 3]);
header("Location: " . route_url('transaksi_tambah'));
exit();
?>
