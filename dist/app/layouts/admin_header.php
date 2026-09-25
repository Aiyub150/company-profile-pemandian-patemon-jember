<?php
/**
 * Master Admin & Staff Layout - Header
 * Pemandian Patemon
 */
require_once __DIR__ . '/../config.php';

$page_title      = $page_title ?? 'Dashboard Admin - Pemandian Patemon';
$active_menu     = $active_menu ?? '';
$page_heading    = $page_heading ?? '';
$page_subheading = $page_subheading ?? '';
$user_name       = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Petugas';
$user_level      = (int)($_SESSION['level'] ?? 0);
$role_badge      = ($user_level === 1) ? 'Administrator' : 'Staf Kasir';

// Hitung ulasan belum dibaca (untuk level 1 & 2 saja)
$unread_ulasan_count = 0;
if ($user_level === 1 || $user_level === 2) {
    // Cek apakah kolom is_read ada (agar backward-compatible dengan DB lama)
    $col_check = $conn->query("SHOW COLUMNS FROM ulasan LIKE 'is_read'");
    if ($col_check && $col_check->num_rows > 0) {
        $res_unread = $conn->query("SELECT COUNT(*) as cnt FROM ulasan WHERE is_read = 0");
        if ($res_unread) {
            $unread_ulasan_count = (int)($res_unread->fetch_assoc()['cnt'] ?? 0);
        }
    } else {
        // Kolom belum ada – tambahkan secara otomatis agar notif langsung aktif
        @$conn->query("ALTER TABLE ulasan ADD COLUMN is_read tinyint(1) NOT NULL DEFAULT 0 AFTER tgl_ulasan");
        // Semua data lama dianggap belum dibaca
        $res_unread = $conn->query("SELECT COUNT(*) as cnt FROM ulasan");
        if ($res_unread) {
            $unread_ulasan_count = (int)($res_unread->fetch_assoc()['cnt'] ?? 0);
        }
    }
}
$notif_label = $unread_ulasan_count > 99 ? '99+' : (string)$unread_ulasan_count;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>

    <link rel="icon" type="image/x-icon" href="<?= public_url('img/icon.png') ?>" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= public_url('assets/css/main/app.css') ?>">
    <link rel="stylesheet" href="<?= public_url('assets/css/main/app-dark.css') ?>">
    <link rel="stylesheet" href="<?= public_url('css/modern-theme.css') ?>?v=<?= file_exists(__DIR__ . '/../../../public/css/modern-theme.css') ? filemtime(__DIR__ . '/../../../public/css/modern-theme.css') : time() ?>">
    <script>
        // Inisialisasi tema sebelum DOM selesai dimuat untuk menghindari flickering (FOUC)
        (function() {
            var theme = localStorage.getItem('patemon_theme') || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.add('theme-dark');
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            } else {
                document.documentElement.classList.remove('theme-dark');
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
    <script>
        // Pastikan class theme-dark juga disinkronkan ke elemen body
        if (localStorage.getItem('patemon_theme') === 'dark') {
            document.body.classList.add('theme-dark');
        }
    </script>
    <div id="app">
        <?php include __DIR__ . '/../partials/sidebar.php'; ?>

        <div id="main">
            <!-- Universal Topbar -->
            <header class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 border-bottom">
                <a href="#" class="burger-btn d-block d-xl-none p-2">
                    <i class="fa-solid fa-bars fs-3"></i>
                </a>

                <div class="d-flex align-items-center gap-2 ms-auto">
                    <!-- Tombol Switcher Mode Gelap / Terang -->
                    <button type="button" id="themeToggleBtn" class="btn-theme-switcher" onclick="togglePatemonTheme()" title="Beralih Mode Gelap / Terang">
                        <span class="theme-icon-moon"><i class="fa-solid fa-moon"></i></span>
                        <span class="theme-icon-sun"><i class="fa-solid fa-sun"></i></span>
                        <span class="d-none d-sm-inline ms-1" id="themeLabelText">Tema</span>
                    </button>

                    <?php if (($user_level === 1 || $user_level === 2)): ?>
                    <!-- Notifikasi Kritik & Saran -->
                    <a href="<?= route_url('ulasan') ?>" class="topbar-notif-btn position-relative text-decoration-none" title="Kritik & Saran Masuk<?= $unread_ulasan_count > 0 ? ' (' . $notif_label . ' belum dibaca)' : '' ?>" style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 10px; transition: all 0.2s;">
                        <i class="fa-regular fa-bell fs-6 <?= $unread_ulasan_count > 0 ? 'fa-shake' : '' ?>"></i>
                        <?php if ($unread_ulasan_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill" style="background: #ef4444; font-size: 0.65rem; font-weight: 700; padding: 0.2em 0.45em; min-width: 18px; line-height: 1.2; border: 2px solid #fff; z-index: 10;">
                            <?= e($notif_label) ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>

                    <div class="topbar-time-pill d-flex align-items-center gap-2 px-3 py-1.5 rounded-pill border shadow-sm" style="font-size: 0.85rem; font-weight: 500;">
                        <i class="fa-regular fa-calendar-days text-primary"></i>
                        <span id="topbar-date"><?= format_tanggal_indonesia(date('Y-m-d'), true) ?></span>
                        <span class="text-muted opacity-50">&bull;</span>
                        <i class="fa-regular fa-clock text-primary"></i>
                        <span id="topbar-clock" class="font-monospace fw-bold"><?= date('H:i:s') ?> WIB</span>
                    </div>
                </div>
            </header>

            <?php if (!empty($page_heading)): ?>
            <div class="page-heading mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold mb-1" style="font-size: 1.75rem;"><?= e($page_heading) ?></h2>
                        <?php if (!empty($page_subheading)): ?>
                            <p class="text-muted mb-0"><?= e($page_subheading) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (isset($header_actions)) echo $header_actions; ?>
                </div>
            </div>
            <?php endif; ?>
