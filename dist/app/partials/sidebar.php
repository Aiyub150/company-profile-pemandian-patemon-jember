<?php
// dist/app/partials/sidebar.php
$current_page = $active_menu ?? '';
$user_level   = (int)($_SESSION['level'] ?? 0);
$user_name    = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'User';
$role_name    = get_role_name($user_level);
$initial      = strtoupper(substr($user_name, 0, 1));
$is_admin_or_super = ($user_level === 1 || $user_level === 2);
$is_staff          = ($user_level === 3);

// Ambil avatar jika ada
$sidebar_avatar = '';
if (!empty($_SESSION['id_user'])) {
    $uid = (int)$_SESSION['id_user'];
    $stmt_av = $conn->prepare("SELECT avatar FROM users WHERE id_user = ? LIMIT 1");
    if ($stmt_av) {
        $stmt_av->bind_param("i", $uid);
        $stmt_av->execute();
        $av_res = $stmt_av->get_result()->fetch_assoc();
        $stmt_av->close();
        if (!empty($av_res['avatar']) && file_exists(__DIR__ . '/../../../public/img/avatars/' . $av_res['avatar'])) {
            $sidebar_avatar = public_url('img/avatars/' . $av_res['avatar']);
        }
    }
}

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
?>
<div id="sidebar">
    <script>
        // Set sidebar active only on desktop screen (width >= 1200px) on initial render (Feedback-5 Poin 6)
        if (window.innerWidth >= 1200) {
            document.getElementById('sidebar').classList.add('active');
        } else {
            document.getElementById('sidebar').classList.remove('active');
        }
    </script>
    <div class="sidebar-wrapper" style="box-shadow: 4px 0 20px rgba(0,0,0,0.03); display: flex; flex-direction: column;">
        <!-- Brand Header -->
        <div class="sidebar-header position-relative" style="padding: 1.5rem 1.5rem 1rem;">
            <div class="d-flex justify-content-between align-items-center">
                <div class="logo">
                    <a href="<?= in_array($user_level, [1, 2, 3], true) ? route_url('dashboard') : route_url('home') ?>" class="d-flex align-items-center gap-2 text-decoration-none">
                        <img src="<?= public_url('img/icon.png') ?>" alt="Logo" style="height: 36px; width: auto; max-width: 48px; object-fit: contain;">
                        <div>
                            <div class="sidebar-brand-name" style="font-weight: 800; font-size: 1.05rem; line-height: 1.2;">PATEMON</div>
                            <div style="font-size: 0.725rem; font-weight: 600; color: #0284c7; letter-spacing: 0.05em; text-transform: uppercase;">Wisata Pemandian</div>
                        </div>
                    </a>
                </div>
                <div class="sidebar-toggler x">
                    <a href="javascript:void(0)" class="sidebar-hide d-xl-none d-block text-secondary" style="font-size: 1.5rem;" onclick="if(typeof togglePatemonSidebar==='function'){togglePatemonSidebar(false);}else{document.getElementById('sidebar')?.classList.remove('active');document.body.style.overflow='';}">
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

                <?php if (in_array($user_level, [1, 2, 3], true)): ?>
                <li class="sidebar-item <?= ($current_page === 'dashboard') ? 'active' : '' ?>">
                    <a href="<?= route_url('dashboard') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <?php endif; ?>

                <li class="sidebar-item">
                    <a href="<?= route_url('home') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-globe text-primary"></i>
                        <span>Lihat Website</span>
                    </a>
                </li>

                <!-- Section: Loket & Kasir -->
                <li class="sidebar-title" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 700; padding: 1rem 0.75rem 0.35rem;">
                    Loket & Kasir
                </li>

                <?php if ($is_admin_or_super): ?>
                <li class="sidebar-item <?= ($current_page === 'transaksi') ? 'active' : '' ?>">
                    <a href="<?= route_url('transaksi') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-cash-register"></i>
                        <span>Transaksi Kasir</span>
                    </a>
                </li>
                <li class="sidebar-item <?= ($current_page === 'tiket') ? 'active' : '' ?>">
                    <a href="<?= route_url('tiket_kategori') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-ticket"></i>
                        <span>Kategori Tiket</span>
                    </a>
                </li>
                <?php else: ?>
                <!-- Khusus Staf: Hanya 1 menu sidebar Kasir Loket (Feedback-2 Poin 2) -->
                <li class="sidebar-item <?= ($current_page === 'staf' || $current_page === 'kasir') ? 'active' : '' ?>">
                    <a href="<?= route_url('kasir') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-cash-register"></i>
                        <span>Kasir Loket</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Section: Analitik & Laporan -->
                <li class="sidebar-title" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 700; padding: 1rem 0.75rem 0.35rem;">
                    Analitik & Laporan
                </li>

                <!-- Satu Menu Laporan Terpadu (Feedback-2 Poin 3) -->
                <li class="sidebar-item <?= ($current_page === 'laporan' || in_array($current_page, ['laporan_harian', 'laporan_bulanan', 'laporan_tahunan', 'laporan_preview'])) ? 'active' : '' ?>">
                    <a href="<?= route_url('laporan') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span><?= ($user_level === 3) ? 'Laporan Saya' : 'Laporan Omzet' ?></span>
                    </a>
                </li>

                <?php if ($is_admin_or_super): ?>
                <!-- Kritik & Saran Dipindahkan ke Analitik & Laporan (Feedback-2 Poin 4) -->
                <li class="sidebar-item <?= ($current_page === 'ulasan') ? 'active' : '' ?>">
                    <a href="<?= route_url('ulasan') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-comments"></i>
                        <span>Kritik & Saran</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Section: Pengaturan Sistem -->
                <li class="sidebar-title" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 700; padding: 1rem 0.75rem 0.35rem;">
                    Pengaturan & Standar
                </li>

                <?php if ($is_admin_or_super): ?>
                <!-- Administrator Settings Dropdown (Feedback-5 Poin 4) -->
                <?php 
                $is_admin_settings_active = in_array($current_page, ['user', 'gallery', 'settings_toxic', 'settings_server_log', 'settings_history_log'], true);
                ?>
                <li class="sidebar-item has-sub <?= $is_admin_settings_active ? 'active open' : '' ?>">
                    <a href="javascript:void(0)" class="sidebar-link sidebar-dropdown-toggle" style="border-radius: 10px; display: flex; align-items: center; justify-content: space-between;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-sliders"></i>
                            <span>Administrator Settings</span>
                        </div>
                        <i class="fa-solid fa-chevron-down submenu-chevron" style="font-size: 0.725rem; transition: transform 0.25s ease;"></i>
                    </a>
                    <ul class="submenu" style="list-style: none; padding-left: 1.5rem; margin-top: 0.35rem; display: <?= $is_admin_settings_active ? 'block' : 'none' ?>;">
                        <?php if ($is_admin_or_super): ?>
                        <li class="submenu-item <?= ($current_page === 'user') ? 'active' : '' ?>" style="margin-bottom: 0.25rem;">
                            <a href="<?= route_url('users') ?>" class="submenu-link" style="font-size: 0.85rem; padding: 0.4rem 0.5rem; display: flex; align-items: center; gap: 0.5rem; border-radius: 8px;">
                                <i class="fa-solid fa-users-gear" style="font-size: 0.8rem;"></i>
                                <span>Manajemen User</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <li class="submenu-item <?= ($current_page === 'gallery') ? 'active' : '' ?>" style="margin-bottom: 0.25rem;">
                            <a href="<?= route_url('gallery') ?>" class="submenu-link" style="font-size: 0.85rem; padding: 0.4rem 0.5rem; display: flex; align-items: center; gap: 0.5rem; border-radius: 8px;">
                                <i class="fa-solid fa-images" style="font-size: 0.8rem;"></i>
                                <span>Kelola Galeri</span>
                            </a>
                        </li>

                        <?php if ($is_admin_or_super): ?>
                        <li class="submenu-item <?= ($current_page === 'settings_toxic') ? 'active' : '' ?>" style="margin-bottom: 0.25rem;">
                            <a href="<?= route_url('settings_toxic') ?>" class="submenu-link" style="font-size: 0.85rem; padding: 0.4rem 0.5rem; display: flex; align-items: center; gap: 0.5rem; border-radius: 8px;">
                                <i class="fa-solid fa-shield-halved" style="font-size: 0.8rem; color: #ef4444;"></i>
                                <span>Filter Kata Kasar</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($user_level === 1): ?>
                        <li class="submenu-item <?= ($current_page === 'settings_server_log') ? 'active' : '' ?>" style="margin-bottom: 0.25rem;">
                            <a href="<?= route_url('settings_server_log') ?>" class="submenu-link" style="font-size: 0.85rem; padding: 0.4rem 0.5rem; display: flex; align-items: center; gap: 0.5rem; border-radius: 8px;">
                                <i class="fa-solid fa-server" style="font-size: 0.8rem; color: #0284c7;"></i>
                                <span>Log Server</span>
                            </a>
                        </li>
                        <li class="submenu-item <?= ($current_page === 'settings_history_log') ? 'active' : '' ?>" style="margin-bottom: 0.25rem;">
                            <a href="<?= route_url('settings_history_log') ?>" class="submenu-link" style="font-size: 0.85rem; padding: 0.4rem 0.5rem; display: flex; align-items: center; gap: 0.5rem; border-radius: 8px;">
                                <i class="fa-solid fa-clock-rotate-left" style="font-size: 0.8rem; color: #8b5cf6;"></i>
                                <span>Log History & Sampah</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <li class="sidebar-item <?= ($current_page === 'profile') ? 'active' : '' ?>">
                    <a href="<?= route_url('profile') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-id-card"></i>
                        <span>Profil Saya</span>
                    </a>
                </li>

                <li class="sidebar-item <?= ($current_page === 'guide') ? 'active' : '' ?>">
                    <a href="<?= route_url('guide') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-book-bookmark"></i>
                        <span>Buku Panduan</span>
                    </a>
                </li>

                <li class="sidebar-item <?= ($current_page === 'version') ? 'active' : '' ?>">
                    <a href="<?= route_url('version') ?>" class="sidebar-link" style="border-radius: 10px;">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Informasi Versi</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- User Profile Card & Quick Logout -->
        <div class="sidebar-footer-card" style="padding: 1rem 1.25rem;">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($sidebar_avatar)): ?>
                        <img src="<?= e($sidebar_avatar) ?>" alt="Avatar" style="width: 38px; height: 38px; border-radius: 10px; object-fit: cover; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <div style="width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #0284c7, #38bdf8); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; box-shadow: 0 4px 10px rgba(2, 132, 199, 0.2);">
                            <?= $initial ?>
                        </div>
                    <?php endif; ?>
                    <div style="overflow: hidden;">
                        <div class="sidebar-user-name" style="font-weight: 700; font-size: 0.875rem; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; max-width: 120px;" title="<?= e($user_name) ?>">
                            <?= e($user_name) ?>
                        </div>
                        <span class="sidebar-role-badge role-lvl-<?= $user_level ?>">
                            <?= e($role_name) ?>
                        </span>
                    </div>
                </div>
                <a href="<?= route_url('logout') ?>" class="btn btn-sm btn-outline-danger" title="Keluar / Logout" style="border-radius: 8px; width: 34px; height: 34px; padding: 0; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
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
        if (window.__sidebarInitialized) return;
        window.__sidebarInitialized = true;

        // 1. Dropdown Accordion Toggle
        const dropdownToggles = document.querySelectorAll('.sidebar-dropdown-toggle');
        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
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
        }

        function closeSidebar() {
            if (!sidebar) return;
            sidebar.classList.remove('active');
        }

        burgerBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        });

        hideBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeSidebar();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();
</script>
