<?php
require '../../app/config.php';
check_auth([1, 2]);
header("Location: tambah.php");
exit();
?>
