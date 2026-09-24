<?php
require '../../app/config.php';
check_auth([1, 2]);
$id = (int)($_GET['id'] ?? 0);
header("Location: update.php?id=" . $id);
exit();
?>
