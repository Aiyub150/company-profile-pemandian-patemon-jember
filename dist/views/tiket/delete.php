<?php
require '../../app/config.php';
check_auth([1, 2]);

$id_tiket = (int)($_GET["id"] ?? 0);
$csrf = $_GET["csrf"] ?? '';

if ($id_tiket > 0 && validate_csrf($csrf)) {
    $stmt = $conn->prepare("DELETE FROM tiket WHERE id_tiket = ?");
    $stmt->bind_param("i", $id_tiket);
    $stmt->execute();
    $stmt->close();
}

header("Location: " . route_url('admin_tiket'));
exit();
?>
