<?php
// dist/app/partials/sidebar.php
$current_page = $active_menu ?? '';
$user_level   = (int)($_SESSION['level'] ?? 0);
$user_name    = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'User';
$role_name    = ($user_level === 1) ? 'Administrator' : (($user_level === 2) ? 'Staf Kasir' : 'Pengguna');
$initial      = strtoupper(substr($user_name, 0, 1));

// Fallback helper jika file belum memanggil config.php secara global
if (!function_exists('views_url')) {
    function views_url($path = '') {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $pos = strpos($script, '/dist/');
        $root = ($pos !== false) ? substr($script, 0, $pos) : '';
        return rtrim($root, '/') . '/dist/views/' . ltrim($path, '/');
    }
}
if (!function_exists('public_url')) {
    function public_url($path = '') {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $pos = strpos($script, '/dist/');
        if ($pos === false) $pos = strpos($script, '/public/');
        $root = ($pos !== false) ? substr($script, 0, $pos) : '';
        return rtrim($root, '/') . '/public/' . ltrim($path, '/');
    }
}

$is_laporan_active = in_array($current_page, ['laporan_harian', 'laporan_bulanan', 'laporan_tahunan']);
?>
<div id="sidebar" class="active">
    <div class="sidebar-wrapper active" style="box-shadow: 4px 0 20px rgba(0,0,0,0.03); display: flex; flex-direction: column; background: #ffffff;">
        <!-- Brand Header -->
        <div class="sidebar-header position-relative" style="padding: 1.5rem 1.5rem 1rem;">
            <div class="d-flex justify-content-between align-items-center">
                <div class="logo">
                    <a href="<?= ($user_level === 1) ? views_url('dashboard/dashboard.php') : views_url('index.php') ?>" class="d-flex align-items-center gap-2 text-decoration-none">
                        <img src="<?= public_url('img/icon.png') ?>" alt="Logo" style="height: 36px; width: auto; max-width: 48px; object-fit: contain;">
                        <div>
                            <div style="font-weight: 800; font-size: 1.05rem; color: #0f172a; line-height: 1.2;">PATEMON</div>
                            <div style="font-size: 0.725rem; font-weight: 600; color: #0284c7; letter-spacing: 0.05em; text-transform: uppercase;">Wisata Pemandian</div>
                        </div>
                    </a>
                </div>
                <div class="sidebar-toggler x">
                    <a href="javascript:void(0)" class="sidebar-hide d-xl-none d-block text-secondary" style="font-size: 1.5rem;">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <div class="sidebar-menu" style="flex: 1; overflow-y: auto; padding: 0.5rem 0.75rem;">
            <ul class="menu" style="padding-left: 0; list-style: none; margin: 0;">
                
                <!-- Section: Navigasi Utama -->
                <li class="sidebar-title" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 700; padding: 0.75rem 0.75rem 0.35rem;">
                    Navigasi Utama
                </li>

                <?php if ($user_level === 1): ?>
                <li class="sidebar-item <?= ($current_page === 'dashboard') ? 'active' : '' ?>">
                    <a href="<?= views_url('dashboard/dashboard.php') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <?php endif; ?>

                <li class="sidebar-item">
                    <a href="<?= views_url('index.php') ?>" class="sidebar-link" target="_blank" style="border-radius: 10px;">
                        <i class="fa-solid fa-arrow-up-right-from-square text-primary"></i>
                        <span>Lihat Website</span>
                    </a>
                </li>

                <!-- Section: Loket & Kasir -->
                <li class="sidebar-title" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 700; padding: 1rem 0.75rem 0.35rem;">
                    Loket & Kasir
                </li>

                <?php if ($user_level === 1): ?>
                <li class="sidebar-item <?= ($current_page === 'transaksi') ? 'active' : '' ?>">
                    <a href="<?= views_url('transaksi/transaksi.php') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-cash-register"></i>
                        <span>Transaksi Kasir</span>
                    </a>
                </li>
                <li class="sidebar-item <?= ($current_page === 'tiket') ? 'active' : '' ?>">
                    <a href="<?= views_url('tiket/tiket.php') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-ticket"></i>
                        <span>Kategori Tiket</span>
                    </a>
                </li>
                <?php else: ?>
                <li class="sidebar-item <?= ($current_page === 'staf') ? 'active' : '' ?>">
                    <a href="<?= views_url('transaksi/staf.php') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-cash-register"></i>
                        <span>Kasir Loket</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Section: Analitik & Laporan (Admin Only) -->
                <?php if ($user_level === 1): ?>
                <li class="sidebar-title" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 700; padding: 1rem 0.75rem 0.35rem;">
                    Analitik & Laporan
                </li>

                <li class="sidebar-item has-sub <?= $is_laporan_active ? 'active open' : '' ?>">
                    <a href="javascript:void(0)" class="sidebar-link sidebar-dropdown-toggle" style="border-radius: 10px; display: flex; align-items: center; justify-content: space-between;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                            <span>Laporan Omzet</span>
                        </div>
                        <i class="fa-solid fa-chevron-down submenu-chevron" style="font-size: 0.75rem; transition: transform 0.25s ease;"></i>
                    </a>
                    <ul class="submenu <?= $is_laporan_active ? 'active' : '' ?>" style="list-style: none; padding-left: 1.25rem; margin-top: 0.25rem; display: <?= $is_laporan_active ? 'block' : 'none' ?>;">
                        <li class="submenu-item <?= ($current_page === 'laporan_harian') ? 'active' : '' ?>" style="margin-bottom: 0.25rem;">
                            <a href="<?= views_url('transaksi/laporan_harian.php') ?>" class="submenu-link" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.45rem 0.75rem; border-radius: 8px; font-size: 0.875rem; color: <?= ($current_page === 'laporan_harian') ? '#0284c7; font-weight: 700; background: #e0f2fe;' : '#64748b;' ?> text-decoration: none;">
                                <i class="fa-regular fa-calendar-check" style="font-size: 0.85rem;"></i>
                                <span>Laporan Harian</span>
                            </a>
                        </li>
                        <li class="submenu-item <?= ($current_page === 'laporan_bulanan') ? 'active' : '' ?>" style="margin-bottom: 0.25rem;">
                            <a href="<?= views_url('transaksi/laporan_bulanan.php') ?>" class="submenu-link" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.45rem 0.75rem; border-radius: 8px; font-size: 0.875rem; color: <?= ($current_page === 'laporan_bulanan') ? '#0284c7; font-weight: 700; background: #e0f2fe;' : '#64748b;' ?> text-decoration: none;">
                                <i class="fa-regular fa-calendar-days" style="font-size: 0.85rem;"></i>
                                <span>Laporan Bulanan</span>
                            </a>
                        </li>
                        <li class="submenu-item <?= ($current_page === 'laporan_tahunan') ? 'active' : '' ?>" style="margin-bottom: 0.25rem;">
                            <a href="<?= views_url('transaksi/laporan_tahunan.php') ?>" class="submenu-link" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.45rem 0.75rem; border-radius: 8px; font-size: 0.875rem; color: <?= ($current_page === 'laporan_tahunan') ? '#0284c7; font-weight: 700; background: #e0f2fe;' : '#64748b;' ?> text-decoration: none;">
                                <i class="fa-regular fa-calendar" style="font-size: 0.85rem;"></i>
                                <span>Laporan Tahunan</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Section: Pengaturan Sistem (Admin Only) -->
                <li class="sidebar-title" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 700; padding: 1rem 0.75rem 0.35rem;">
                    Pengaturan Sistem
                </li>

                <li class="sidebar-item <?= ($current_page === 'user') ? 'active' : '' ?>">
                    <a href="<?= views_url('user/user.php') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-users-gear"></i>
                        <span>Manajemen User</span>
                    </a>
                </li>

                <li class="sidebar-item <?= ($current_page === 'ulasan') ? 'active' : '' ?>">
                    <a href="<?= views_url('ulasan/ulasan.php') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-comments"></i>
                        <span>Kritik & Saran</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- User Profile Card & Quick Logout -->
        <div style="padding: 1rem 1.25rem; border-top: 1px solid #f1f5f9; background: #ffffff;">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #0284c7, #38bdf8); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; box-shadow: 0 4px 10px rgba(2, 132, 199, 0.2);">
                        <?= $initial ?>
                    </div>
                    <div style="overflow: hidden;">
                        <div style="font-weight: 700; font-size: 0.875rem; color: #1e293b; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; max-width: 120px;" title="<?= e($user_name) ?>">
                            <?= e($user_name) ?>
                        </div>
                        <span class="badge" style="font-size: 0.675rem; font-weight: 600; padding: 0.2rem 0.5rem; background: <?= ($user_level === 1) ? '#ede9fe; color: #6d28d9;' : '#e0f2fe; color: #0369a1;' ?>">
                            <?= $role_name ?>
                        </span>
                    </div>
                </div>
                <a href="<?= views_url('logout.php') ?>" class="btn btn-sm btn-outline-danger" title="Keluar / Logout" style="border-radius: 8px; width: 34px; height: 34px; padding: 0; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                    <i class="fa-solid fa-power-off"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Self-Contained Sidebar Script (Dropdowns & Mobile Drawer) -->
<script>
(function() {
    function initSidebar() {
        // 1. Dropdown Accordion Toggle
        const dropdownToggles = document.querySelectorAll('.sidebar-dropdown-toggle');
        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const parent = this.closest('.sidebar-item.has-sub');
                if (!parent) return;
                
                const submenu = parent.querySelector('.submenu');
                const chevron = parent.querySelector('.submenu-chevron');
                
                if (submenu) {
                    const isShown = (submenu.style.display === 'block');
                    if (isShown) {
                        submenu.style.display = 'none';
                        parent.classList.remove('open');
                        if (chevron) chevron.style.transform = 'rotate(0deg)';
                    } else {
                        submenu.style.display = 'block';
                        parent.classList.add('open');
                        if (chevron) chevron.style.transform = 'rotate(180deg)';
                    }
                }
            });
        });

        // Set initial chevron rotation for open menus
        document.querySelectorAll('.sidebar-item.has-sub.open .submenu-chevron').forEach(ch => {
            ch.style.transform = 'rotate(180deg)';
        });

        // 2. Mobile Burger Menu & Drawer
        const sidebar = document.getElementById('sidebar');
        const burgerBtns = document.querySelectorAll('.burger-btn');
        const hideBtns = document.querySelectorAll('.sidebar-hide');

        function toggleSidebar() {
            if (!sidebar) return;
            sidebar.classList.toggle('active');
            let backdrop = document.querySelector('.sidebar-backdrop');
            if (sidebar.classList.contains('active') && window.innerWidth < 1200) {
                if (!backdrop) {
                    backdrop = document.createElement('div');
                    backdrop.className = 'sidebar-backdrop';
                    backdrop.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.45);backdrop-filter:blur(2px);z-index:998;';
                    backdrop.addEventListener('click', closeSidebar);
                    document.body.appendChild(backdrop);
                }
            } else {
                if (backdrop) backdrop.remove();
            }
        }

        function closeSidebar() {
            if (!sidebar) return;
            sidebar.classList.remove('active');
            const backdrop = document.querySelector('.sidebar-backdrop');
            if (backdrop) backdrop.remove();
        }

        burgerBtns.forEach(btn => btn.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebar();
        }));

        hideBtns.forEach(btn => btn.addEventListener('click', function(e) {
            e.preventDefault();
            closeSidebar();
        }));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();
</script>
