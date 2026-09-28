<?php
/**
 * Hapus Event (Soft Delete)
 * Sesuai Feedback-7 Poin 6
 */
require_once __DIR__ . '/../../app/config.php';
check_auth([1, 2]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . route_url('events'));
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    $_SESSION['flash_error'] = 'Token sesi tidak valid.';
    header('Location: ' . route_url('events'));
    exit;
}

$id_event = (int)($_POST['id_event'] ?? 0);
if ($id_event <= 0) {
    $_SESSION['flash_error'] = 'ID Event tidak valid.';
    header('Location: ' . route_url('events'));
    exit;
}

// Cek data event
$stmt = $conn->prepare("SELECT id_event, judul FROM events WHERE id_event = ? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param("i", $id_event);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    $_SESSION['flash_error'] = 'Event tidak ditemukan atau sudah dihapus.';
    header('Location: ' . route_url('events'));
    exit;
}

// Lakukan Soft Delete
$stmtDel = $conn->prepare("UPDATE events SET deleted_at = NOW(), is_active = 0 WHERE id_event = ?");
$stmtDel->bind_param("i", $id_event);
if ($stmtDel->execute()) {
    $_SESSION['flash_success'] = "Event '{$event['judul']}' berhasil dihapus (soft-delete).";
    if (function_exists('log_activity')) {
        log_activity('EVENT_DELETE', 'events', "Menghapus event '{$event['judul']}' (#{$id_event})", $_SESSION['user_id'] ?? null);
    }
} else {
    $_SESSION['flash_error'] = 'Gagal menghapus event: ' . $conn->error;
}
$stmtDel->close();

header('Location: ' . route_url('events'));
exit;
