<?php
require_once __DIR__ . '/../app/config.php';

// Hapus semua variabel sesi
$_SESSION = [];
if (session_id() !== '') {
    @session_unset();
    @session_destroy();
}

// Arahkan kembali ke halaman beranda
header("Location: " . route_url('home'));
exit();
?>