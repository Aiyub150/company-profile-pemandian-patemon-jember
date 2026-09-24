<?php
require '../../app/config.php';

// Hak Akses Khusus: Hanya Administrator (Level 1)
check_auth([1]);

$id_user = (int)($_GET["id"] ?? 0);
$csrf = $_GET["csrf"] ?? '';

if ($id_user > 0 && validate_csrf($csrf)) {
    // Larang menghapus akun yang sedang aktif login
    if ($id_user === (int)$_SESSION['id_user']) {
        header("Location: user.php?err=self_delete");
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id_user = ?");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    $stmt->close();
}

header("Location: user.php");
exit();
?>
