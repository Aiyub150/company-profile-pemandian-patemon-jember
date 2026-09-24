<?php
require '../../app/config.php';
check_auth([1]);

$id_ulasan = (int)($_GET["id"] ?? 0);
$csrf = $_GET["csrf"] ?? '';

if ($id_ulasan > 0 && validate_csrf($csrf)) {
    $stmt = $conn->prepare("DELETE FROM ulasan WHERE id_ulasan = ?");
    $stmt->bind_param("i", $id_ulasan);
    $stmt->execute();
    $stmt->close();
}

header("Location: ulasan.php");
exit();
?>
