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
    <link rel="stylesheet" href="<?= public_url('css/modern-theme.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
    <div id="app">
        <?php include __DIR__ . '/../partials/sidebar.php'; ?>

        <div id="main">
            <!-- Universal Topbar -->
            <header class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 border-bottom">
                <a href="#" class="burger-btn d-block d-xl-none text-dark p-2">
                    <i class="fa-solid fa-bars fs-3"></i>
                </a>

                <div class="d-flex align-items-center gap-2 ms-auto">
                    <div class="d-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-white border shadow-sm text-secondary" style="font-size: 0.85rem; font-weight: 500;">
                        <i class="fa-regular fa-calendar-days text-primary"></i>
                        <span id="topbar-date"><?= format_tanggal_indonesia(date('Y-m-d'), true) ?></span>
                        <span class="text-muted opacity-50">&bull;</span>
                        <i class="fa-regular fa-clock text-primary"></i>
                        <span id="topbar-clock" class="font-monospace fw-bold text-dark"><?= date('H:i:s') ?> WIB</span>
                    </div>
                </div>
            </header>

            <?php if (!empty($page_heading)): ?>
            <div class="page-heading mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold mb-1" style="font-size: 1.75rem; color: #0f172a;"><?= e($page_heading) ?></h2>
                        <?php if (!empty($page_subheading)): ?>
                            <p class="text-muted mb-0"><?= e($page_subheading) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (isset($header_actions)) echo $header_actions; ?>
                </div>
            </div>
            <?php endif; ?>
