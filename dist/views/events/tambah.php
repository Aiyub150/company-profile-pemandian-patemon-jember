<?php
/**
 * Proses Penambahan Event Baru
 * Sesuai Feedback-7 Poin 6 & Poin 7
 */
require_once __DIR__ . '/../../app/config.php';
check_auth([1, 2]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . route_url('events'));
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    $_SESSION['flash_error'] = 'Token sesi keamanan tidak valid. Silakan muat ulang halaman.';
    header('Location: ' . route_url('events'));
    exit;
}

$judul     = mb_substr(trim($_POST['judul'] ?? ''), 0, 150);
$urutan    = (int)($_POST['urutan'] ?? 1);
$is_active = isset($_POST['is_active']) ? 1 : 0;

if (empty($judul)) {
    $_SESSION['flash_error'] = 'Judul event wajib diisi.';
    header('Location: ' . route_url('events'));
    exit;
}

// Cek batas maksimal 5 event aktif
if ($is_active === 1) {
    $resActive = $conn->query("SELECT COUNT(*) as total FROM events WHERE is_active = 1 AND deleted_at IS NULL");
    $current_active = (int)($resActive->fetch_assoc()['total'] ?? 0);
    if ($current_active >= 5) {
        $is_active = 0; // Turunkan ke nonaktif jika sudah 5 aktif
        $_SESSION['flash_error'] = 'Batas maksimal 5 event aktif telah tercapai. Event baru berhasil disimpan namun dalam status nonaktif.';
    }
}

// Proses Unggah Gambar Flyer via secure_upload_image (Feedback-7 Poin 7)
if (!isset($_FILES['gambar']) || $_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_error'] = 'Silakan pilih berkas flyer event yang valid.';
    header('Location: ' . route_url('events'));
    exit;
}

$target_dir = __DIR__ . '/../../../public/img/events/';
$upload_res = secure_upload_image($_FILES['gambar'], $target_dir, ['jpg', 'jpeg', 'png', 'webp'], 2097152); // Maks 2MB

if (!$upload_res['success']) {
    $_SESSION['flash_error'] = 'Gagal mengunggah flyer: ' . $upload_res['error'];
    header('Location: ' . route_url('events'));
    exit;
}

$safe_filename = $upload_res['filename'];

// Simpan ke Database
$stmt = $conn->prepare("INSERT INTO events (judul, gambar, is_active, urutan, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("ssii", $judul, $safe_filename, $is_active, $urutan);

if ($stmt->execute()) {
    $newEventId = $stmt->insert_id;
    if (function_exists('log_activity')) {
        log_activity('EVENT_CREATE', 'events', "Menambahkan event baru '{$judul}' (#{$newEventId})", $_SESSION['user_id'] ?? null);
    }
    if (empty($_SESSION['flash_error'])) {
        $_SESSION['flash_success'] = 'Event "' . $judul . '" berhasil ditambahkan dan siap ditampilkan!';
    }
} else {
    $_SESSION['flash_error'] = 'Terjadi kesalahan basis data saat menyimpan event: ' . $conn->error;
}
$stmt->close();

header('Location: ' . route_url('events'));
exit;
