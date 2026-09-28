<?php
/**
 * Toggle Status Aktif Event
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
$stmt = $conn->prepare("SELECT id_event, judul, is_active FROM events WHERE id_event = ? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param("i", $id_event);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    $_SESSION['flash_error'] = 'Event tidak ditemukan atau telah dihapus.';
    header('Location: ' . route_url('events'));
    exit;
}

$new_status = ($event['is_active'] == 1) ? 0 : 1;

// Jika ingin mengaktifkan, pastikan total event aktif < 5
if ($new_status === 1) {
    $resActive = $conn->query("SELECT COUNT(*) as total FROM events WHERE is_active = 1 AND deleted_at IS NULL");
    $current_active = (int)($resActive->fetch_assoc()['total'] ?? 0);
    if ($current_active >= 5) {
        $_SESSION['flash_error'] = 'Maksimal 5 event aktif yang dapat dimunculkan di pop-up notifikasi beranda. Nonaktifkan salah satu event terlebih dahulu.';
        header('Location: ' . route_url('events'));
        exit;
    }
}

// Perbarui status
$stmtUp = $conn->prepare("UPDATE events SET is_active = ? WHERE id_event = ?");
$stmtUp->bind_param("ii", $new_status, $id_event);
if ($stmtUp->execute()) {
    $statusText = ($new_status === 1) ? 'diaktifkan' : 'dinonaktifkan';
    $_SESSION['flash_success'] = "Event '{$event['judul']}' berhasil {$statusText}.";
    if (function_exists('log_activity')) {
        log_activity('EVENT_TOGGLE', 'events', "Mengubah status event '{$event['judul']}' (#{$id_event}) menjadi {$statusText}", $_SESSION['user_id'] ?? null);
    }
} else {
    $_SESSION['flash_error'] = 'Gagal memperbarui status event: ' . $conn->error;
}
$stmtUp->close();

header('Location: ' . route_url('events'));
exit;
