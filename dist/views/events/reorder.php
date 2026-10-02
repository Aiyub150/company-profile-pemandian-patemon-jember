<?php
/**
 * Controller Reorder Urutan Prioritas Event (Feedback-9 Poin 3)
 * Menukar dan menyesuaikan urutan event secara otomatis tanpa duplikasi urutan.
 */
require_once __DIR__ . '/../../app/config.php';

// Hak akses: Super Admin (1) & Admin (2)
check_auth([1, 2]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . route_url('events'));
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!validate_csrf($csrf_token)) {
    $_SESSION['flash_error'] = 'Token keamanan tidak valid atau telah kedaluwarsa.';
    header('Location: ' . route_url('events'));
    exit;
}

$id_event  = (int)($_POST['id_event'] ?? 0);
$direction = strtolower(trim($_POST['direction'] ?? ''));

if ($id_event <= 0 || !in_array($direction, ['up', 'down'], true)) {
    $_SESSION['flash_error'] = 'Parameter perubahan urutan tidak valid.';
    header('Location: ' . route_url('events'));
    exit;
}

// Ambil semua event aktif (non-deleted) berurutan
$res = $conn->query("SELECT id_event, judul, urutan FROM events WHERE deleted_at IS NULL ORDER BY urutan ASC, created_at DESC");
$eventsList = [];
$targetIndex = -1;

$i = 0;
while ($row = $res->fetch_assoc()) {
    $eventsList[] = $row;
    if ((int)$row['id_event'] === $id_event) {
        $targetIndex = $i;
    }
    $i++;
}

if ($targetIndex === -1) {
    $_SESSION['flash_error'] = 'Event tidak ditemukan atau telah dihapus.';
    header('Location: ' . route_url('events'));
    exit;
}

$total = count($eventsList);
$swapIndex = -1;

if ($direction === 'up') {
    if ($targetIndex === 0) {
        $_SESSION['flash_info'] = 'Event sudah berada di urutan teratas.';
        header('Location: ' . route_url('events'));
        exit;
    }
    $swapIndex = $targetIndex - 1;
} elseif ($direction === 'down') {
    if ($targetIndex >= $total - 1) {
        $_SESSION['flash_info'] = 'Event sudah berada di urutan terbawah.';
        header('Location: ' . route_url('events'));
        exit;
    }
    $swapIndex = $targetIndex + 1;
}

// Lakukan penukaran urutan
$currentEvent = $eventsList[$targetIndex];
$partnerEvent = $eventsList[$swapIndex];

$conn->begin_transaction();
try {
    // Normalisasi sementara seluruh baris agar urutan unik bertahap
    $seq = 1;
    $tempStmt = $conn->prepare("UPDATE events SET urutan = ? WHERE id_event = ?");
    for ($k = 0; $k < $total; $k++) {
        $item = $eventsList[$k];
        $newSeq = $seq;
        if ($k === $targetIndex) {
            $newSeq = $swapIndex + 1;
        } elseif ($k === $swapIndex) {
            $newSeq = $targetIndex + 1;
        }
        $tempStmt->bind_param("ii", $newSeq, $item['id_event']);
        $tempStmt->execute();
        $seq++;
    }
    $tempStmt->close();

    $conn->commit();

    if (function_exists('log_activity')) {
        $dirLabel = ($direction === 'up') ? 'dinaikkan' : 'diturunkan';
        log_activity('EVENT_REORDER', 'events', "Urutan event '{$currentEvent['judul']}' berhasil {$dirLabel}.", $_SESSION['id_user'] ?? null);
    }

    $_SESSION['flash_success'] = 'Urutan event "' . htmlspecialchars($currentEvent['judul']) . '" berhasil diperbarui!';
} catch (\Exception $e) {
    $conn->rollback();
    $_SESSION['flash_error'] = 'Gagal memperbarui urutan event: ' . $e->getMessage();
}

header('Location: ' . route_url('events'));
exit;
