<?php
/**
 * Konfigurasi Database dan Helper Keamanan Terpusat
 * Aplikasi Kasir dan Portofolio Pemandian Patemon
 */

// Muat file .env secara otomatis jika ada
if (!function_exists('load_env_file')) {
    function load_env_file($path) {
        if (!file_exists($path) || !is_readable($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                // Lepas tanda kutip jika ada
                if (preg_match('/^(["\'])(.*)\1$/', $val, $matches)) {
                    $val = $matches[2];
                }
                if (getenv($key) === false) {
                    putenv("{$key}={$val}");
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                }
            }
        }
    }
}
load_env_file(__DIR__ . '/../../.env');
load_env_file(__DIR__ . '/.env');

// Inisialisasi konfigurasi dari environment variables atau fallback default
$host = getenv('DB_HOST') ?: '127.0.0.1';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$database = getenv('DB_NAME') ?: 'pemandian';

// Membuat koneksi ke database dengan penanganan error aman
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    // SEC-07: Log internal error tanpa membocorkan kredensial atau host ke frontend
    error_log("Database connection failed: " . mysqli_connect_error());
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        die("<div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; padding: 35px 25px; background: #ffffff; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 16px; max-width: 520px; margin: 60px auto; box-shadow: 0 10px 25px rgba(0,0,0,0.06); text-align: center;'>
            <div style='font-size: 42px; margin-bottom: 12px;'>⚠️</div>
            <h3 style='margin: 0 0 10px; color: #0f172a; font-size: 1.25rem;'>Layanan Database Belum Tersedia</h3>
            <p style='color: #64748b; font-size: 14px; line-height: 1.6; margin: 0 0 20px;'>Sistem mengalami kendala saat menghubungkan ke database server. Pastikan layanan database telah aktif atau hubungi administrator sistem.</p>
            <a href='javascript:location.reload()' style='display:inline-block; padding: 10px 22px; background: #0284c7; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 13px;'>Muat Ulang Halaman</a>
        </div>");
    }
} else {
    mysqli_set_charset($conn, "utf8mb4");
}

// Start session secara aman jika belum aktif (SEC-06 & MF-04 Hardened)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    $isSecure = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $isSecure, true);
    }
    session_start();
}

// Security headers dasar
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * Helper untuk sanitasi output HTML (Mencegah XSS)
 */
if (!function_exists('e')) {
    function e($data) {
        return htmlspecialchars((string)($data ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Helper Mendapatkan IP Address Pengunjung Asli (Real Client IP)
 * Memprioritaskan IP publik pengunjung yang mengakses web melalui Cloudflare Tunnel / Reverse Proxy,
 * dan secara cerdas menyaring/mengabaikan IP internal VPS, loopback (127.0.0.1 / ::1), serta subnet privat.
 */
if (!function_exists('get_client_ip')) {
    function get_client_ip() {
        $candidates = [];

        // 1. Cloudflare Tunnel / Cloudflare CDN Headers
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidates[] = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        }
        if (!empty($_SERVER['CF_CONNECTING_IP'])) {
            $candidates[] = trim($_SERVER['CF_CONNECTING_IP']);
        }
        if (!empty($_SERVER['HTTP_TRUE_CLIENT_IP'])) {
            $candidates[] = trim($_SERVER['HTTP_TRUE_CLIENT_IP']);
        }

        // 2. X-Forwarded-For (Bisa berisi rangkaian IP: client_ip, proxy1, proxy2)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedIps = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($forwardedIps as $fIp) {
                $candidates[] = trim($fIp);
            }
        }

        // 3. X-Real-IP & Client-IP
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $candidates[] = trim($_SERVER['HTTP_X_REAL_IP']);
        }
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $candidates[] = trim($_SERVER['HTTP_CLIENT_IP']);
        }

        // 4. Remote Addr standar
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $candidates[] = trim($_SERVER['REMOTE_ADDR']);
        }

        // Tahap 1: Prioritaskan IP PUBLIK yang mengakses web (Bukan IP Private VPS / Localhost)
        foreach ($candidates as $ip) {
            if ($ip === '::1' || $ip === '127.0.0.1') {
                continue;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        // Tahap 2: Fallback jika di lingkungan internal murni (misal pengujian lokal intranet/LAN)
        foreach ($candidates as $ip) {
            if ($ip === '::1') {
                return '127.0.0.1';
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '127.0.0.1';
    }
}

/**
 * Helper CSRF Token (MF-09: Dibatasi hanya via POST & Header, bukan GET)
 */
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validate_csrf')) {
    function validate_csrf($token = null) {
        $token = $token ?: ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
    }
}

if (!function_exists('get_csrf_token')) {
    function get_csrf_token() {
        return csrf_token();
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token = null) {
        return validate_csrf($token);
    }
}

if (!function_exists('isValidCSRFToken')) {
    function isValidCSRFToken($token = null) {
        return validate_csrf($token);
    }
}

/**
 * Helper format rupiah
 */
if (!function_exists('format_rupiah')) {
    function format_rupiah($nominal) {
        return 'Rp ' . number_format((float)$nominal, 0, ',', '.');
    }
}

/**
 * Helper format tanggal Indonesia
 */
if (!function_exists('format_tanggal_indonesia')) {
    function format_tanggal_indonesia($dateStr, $with_day = false) {
        if (!$dateStr) return '-';
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $time = strtotime($dateStr);
        if (!$time) return e($dateStr);
        $d = date('j', $time);
        $m = (int)date('n', $time);
        $y = date('Y', $time);
        $res = "{$d} " . ($bulan[$m] ?? '') . " {$y}";
        if ($with_day) {
            $w = (int)date('w', $time);
            $res = ($hari[$w] ?? '') . ", {$res}";
        }
        return $res;
    }
}

if (!function_exists('format_bulan_indonesia')) {
    function format_bulan_indonesia($dateStr) {
        if (!$dateStr) return '-';
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $parts = explode('-', $dateStr);
        $y = $parts[0] ?? date('Y');
        $m = (int)($parts[1] ?? date('n'));
        return ($bulan[$m] ?? '') . " " . $y;
    }
}

/**
 * Helper manajemen pengaturan sistem dinamis (Key-Value Store)
 * Enterprise-grade auto-table migration and runtime caching
 */
if (!function_exists('get_setting')) {
    function get_setting($key, $default = null) {
        global $conn;
        if (!isset($GLOBALS['settings_cache'])) {
            $GLOBALS['settings_cache'] = [];
            if ($conn) {
                // Buat tabel jika belum ada secara otomatis
                $conn->query("CREATE TABLE IF NOT EXISTS `settings` (
                  `setting_key` varchar(50) NOT NULL,
                  `setting_value` text DEFAULT NULL,
                  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

                // Auto-migration tabel calendar_holidays (Feedback-9 Poin 4)
                $conn->query("CREATE TABLE IF NOT EXISTS `calendar_holidays` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `tanggal` date NOT NULL,
                  `keterangan` varchar(255) NOT NULL,
                  `tipe` enum('libur', 'tutup_pemeliharaan', 'cuti') NOT NULL DEFAULT 'libur',
                  `created_by` int(11) DEFAULT NULL,
                  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_tanggal` (`tanggal`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

                // Auto-migration kolom status aktivasi pada tabel users (Feedback-9 Poin 6)
                $chk_act = $conn->query("SHOW COLUMNS FROM `users` LIKE 'is_active'");
                if ($chk_act && $chk_act->num_rows === 0) {
                    $conn->query("ALTER TABLE `users` ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `level`");
                }
                $chk_act_tok = $conn->query("SHOW COLUMNS FROM `users` LIKE 'activation_token'");
                if ($chk_act_tok && $chk_act_tok->num_rows === 0) {
                    $conn->query("ALTER TABLE `users` ADD COLUMN `activation_token` varchar(100) DEFAULT NULL AFTER `is_active`");
                }
                $chk_act_exp = $conn->query("SHOW COLUMNS FROM `users` LIKE 'activation_expires_at'");
                if ($chk_act_exp && $chk_act_exp->num_rows === 0) {
                    $conn->query("ALTER TABLE `users` ADD COLUMN `activation_expires_at` datetime DEFAULT NULL AFTER `activation_token`");
                }

                $res = $conn->query("SELECT setting_key, setting_value FROM settings");
                if ($res) {
                    while ($r = $res->fetch_assoc()) {
                        $GLOBALS['settings_cache'][$r['setting_key']] = $r['setting_value'];
                    }
                }
            }
        }
        return $GLOBALS['settings_cache'][$key] ?? $default;
    }
}

if (!function_exists('set_setting')) {
    function set_setting($key, $value) {
        global $conn;
        if (!$conn) return false;

        $key = trim($key);
        // Pastikan cache dan tabel terinisialisasi
        get_setting('__init__', '');

        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        if ($stmt) {
            $stmt->bind_param("ss", $key, $value);
            $res = $stmt->execute();
            $stmt->close();
            if ($res) {
                $GLOBALS['settings_cache'][$key] = $value;
            }
            return $res;
        }
        return false;
    }
}

/**
 * Helper informasi peran RBAC (Standar SIM-ASET)
 * Level 1 = Super Admin, Level 2 = Admin, Level 3 = Staf Kasir, Level 0 = Pengunjung
 */
if (!function_exists('get_role_name')) {
    function get_role_name($level) {
        switch ((int)$level) {
            case 1: return 'Super Admin';
            case 2: return 'Admin';
            case 3: return 'Staf Kasir';
            case 0: return 'Pengunjung';
            default: return 'Pengguna';
        }
    }
}

/**
 * Helper proteksi hak akses (RBAC)
 * Level 1 = Super Admin, Level 2 = Admin, Level 3 = Staf Kasir, Level 0 = Pengunjung
 */
if (!function_exists('check_auth')) {
    function check_auth($allowed_levels = [1, 2, 3], $redirect_path = null) {
        if ($redirect_path === null) {
            $redirect_path = function_exists('route_url') ? route_url('login') : '../index.php';
        }
        if (!isset($_SESSION['id_user']) || !isset($_SESSION['level'])) {
            header("Location: " . $redirect_path);
            exit();
        }
        
        $user_level = (int)$_SESSION['level'];
        if (!in_array($user_level, $allowed_levels, true)) {
            // Level tidak diizinkan
            header("Location: " . $redirect_path);
            exit();
        }
    }
}

// Konfigurasi Fitur Pembayaran & Payment Gateway
if (!defined('FEATURE_PAYMENT_GATEWAY')) {
    // Set true jika mengaktifkan integrasi dynamic QRIS / payment gateway otomatis
    define('FEATURE_PAYMENT_GATEWAY', false);
}
if (!defined('BANK_NAME')) define('BANK_NAME', 'Bank Mandiri');
if (!defined('BANK_REK')) define('BANK_REK', '142-00-18293-881');
if (!defined('BANK_AN')) define('BANK_AN', 'Wisata Pemandian Patemon');
if (!defined('APP_VERSION')) define('APP_VERSION', '2.0.0-Enterprise');
if (!defined('APP_NAME')) define('APP_NAME', 'Pemandian Patemon Jember');

/**
 * Helper Resolusi URL Terpusat
 * Menghitung root path dinamis baik di CLI (serve.php) maupun Apache subfolder (XAMPP)
 */
if (!function_exists('base_url')) {
    function base_url($path = '') {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        // Jika berjalan melalui router.php / serve.php (PHP CLI Built-in Server)
        if (str_ends_with($script, 'router.php') || str_ends_with($script, 'serve.php')) {
            $root = '';
        } else {
            $pos = strpos($script, '/dist/');
            if ($pos === false) $pos = strpos($script, '/public/');
            if ($pos === false) $pos = strpos($script, '/index.php');
            $root = ($pos !== false) ? substr($script, 0, $pos) : rtrim(dirname($script), '/\\');
            if ($root === '/' || $root === '\\' || $root === '.' || empty($script)) $root = '';
        }
        $clean_path = ltrim($path, '/');
        return ($root !== '' ? rtrim($root, '/') : '') . ($clean_path !== '' ? '/' . $clean_path : '/');
    }
}

if (!function_exists('views_url')) {
    function views_url($path = '') {
        return base_url('dist/views/' . ltrim($path, '/'));
    }
}

if (!function_exists('public_url')) {
    function public_url($path = '') {
        return base_url('public/' . ltrim($path, '/'));
    }
}

if (!function_exists('payment_url')) {
    function payment_url($path = '') {
        return base_url('dist/app/payment/' . ltrim($path, '/'));
    }
}

if (!function_exists('app_url')) {
    /**
     * Menghasilkan Full Absolute URL lengkap dengan skema (http/https), host, dan port web aplikasi.
     * Sangat penting untuk tautan yang dikirim melalui email (aktivasi staf, reset sandi),
     * agar saat dibuka di Mailpit Web UI (port 8025) atau webmail lain, tautan langsung mengarah
     * ke aplikasi web utama (port 8000), bukan ke port webmail.
     *
     * @param string $path Jalur relatif
     * @return string URL absolut lengkap (contoh: http://localhost:8000/activate)
     */
    function app_url($path = '') {
        $scheme = 'http';
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_CF_VISITOR']) && str_contains($_SERVER['HTTP_CF_VISITOR'], 'https')) ||
            (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
            $scheme = 'https';
        }
        
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (empty($host)) {
            $server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
            $port = $_SERVER['SERVER_PORT'] ?? '8000';
            $host = $server_name . ($port && !in_array($port, ['80', '443']) ? ':' . $port : '');
        }

        // Jika host murni localhost/127.0.0.1 tanpa port, tambahkan default port web 8000
        if ($host === 'localhost' || $host === '127.0.0.1') {
            $port = $_SERVER['SERVER_PORT'] ?? '8000';
            if ($port && !in_array($port, ['80', '443'])) {
                $host .= ':' . $port;
            }
        }

        $base = base_url($path);
        return $scheme . '://' . $host . $base;
    }
}

/**
 * Helper Clean Route Name Resolver
 */
if (!function_exists('route_url')) {
    function route_url($name = '', $params = [], $absolute = false) {
        $routes = [
            // Home / Landing
            ''                       => '',
            'home'                   => '',
            'beranda'                => '',
            'index'                  => '',

            // Auth
            'login'                  => 'login',
            'register'               => 'register',
            'logout'                 => 'logout',
            'forgot_password'        => 'forgot-password',

            // Public Reservation & Nota
            'tiket_pesan'            => 'tiket/pesan',
            'pesan'                  => 'tiket/pesan',
            'tiket_nota'             => 'tiket/nota',
            'nota'                   => 'tiket/nota',

            // Dashboard
            'dashboard'              => 'dashboard',
            'admin'                  => 'dashboard',
            'admin_dashboard'        => 'dashboard',

            // Kasir POS
            'kasir'                  => 'kasir',
            'staf'                   => 'kasir',
            'kasir_pos'              => 'admin/transaksi/tambah',
            'staf_delete'            => 'kasir/delete',
            'kasir_delete'           => 'kasir/delete',

            // Transaksi Admin
            'transaksi'              => 'admin/transaksi',
            'admin_transaksi'        => 'admin/transaksi',
            'transaksi_tambah'       => 'admin/transaksi/tambah',
            'transaksi_update'       => 'admin/transaksi/update',
            'transaksi_delete'       => 'admin/transaksi/delete',
            'transaksi_detail'       => 'admin/transaksi/detail',

            // Tiket Kategori
            'tiket'                  => 'admin/tiket',
            'admin_tiket'            => 'admin/tiket',
            'tiket_kategori'         => 'admin/tiket',
            'tiket_tambah'           => 'admin/tiket/tambah',
            'tiket_update'           => 'admin/tiket/update',
            'tiket_delete'           => 'admin/tiket/delete',

            // Laporan
            'laporan'                => 'admin/laporan',
            'admin_laporan'          => 'admin/laporan',
            'laporan_harian'         => 'admin/laporan/harian',
            'laporan_bulanan'        => 'admin/laporan/bulanan',
            'laporan_tahunan'        => 'admin/laporan/tahunan',
            'laporan_preview'        => 'admin/laporan/preview',

            // Ulasan
            'ulasan'                 => 'admin/ulasan',
            'admin_ulasan'           => 'admin/ulasan',
            'ulasan_delete'          => 'admin/ulasan/delete',

            // Users
            'users'                  => 'admin/users',
            'admin_users'            => 'admin/users',
            'user'                   => 'admin/users',
            'users_tambah'           => 'admin/users/tambah',
            'users_update'           => 'admin/users/update',
            'users_delete'           => 'admin/users/delete',

            // Gallery
            'gallery'                => 'admin/gallery',
            'admin_gallery'          => 'admin/gallery',

            // Settings & Super Admin Modules (Feedback-5 & Feedback-9)
            'settings_toxic'         => 'admin/settings/toxic',
            'settings_server_log'    => 'admin/settings/server-log',
            'settings_history_log'   => 'admin/settings/history-log',
            'settings_calendar'      => 'admin/settings/calendar',
            'calendar'               => 'admin/settings/calendar',
            'transaksi_restore'      => 'admin/transaksi/restore',
            'users_restore'          => 'admin/users/restore',
            'ulasan_restore'         => 'admin/ulasan/restore',
            'users_toggle'           => 'admin/users/toggle',
            'users_resend_activation'=> 'admin/users/resend-activation',

            // Events (Feedback-7 & Feedback-9 Poin 3)
            'events'                 => 'admin/events',
            'event'                  => 'admin/events',
            'events_tambah'          => 'admin/events/tambah',
            'events_delete'          => 'admin/events/delete',
            'events_toggle'          => 'admin/events/toggle',
            'events_reorder'         => 'admin/events/reorder',

            // Auth & Aktivasi Akun (Feedback-9 Poin 6)
            'activate'               => 'activate',
            'user_activate'          => 'activate',

            // Profil, Panduan, Versi
            'profile'                => 'profile',
            'profil'                 => 'profile',
            'guide'                  => 'guide',
            'panduan'                => 'guide',
            'guide_preview_pdf'      => 'guide/preview-pdf',
            'version'                => 'settings/version',
            'versi'                  => 'settings/version',
        ];

        $path = $routes[$name] ?? $name;
        $url = $absolute ? app_url($path) : base_url($path);
        if (!empty($params)) {
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $separator . http_build_query($params);
        }
        return $url;
    }
}

/**
 * Helper Format Standar Kode Transaksi (Poin 11)
 * Contoh: TRX-20260924-0001
 */
if (!function_exists('format_kode_transaksi')) {
    function format_kode_transaksi($id_transaksi, $tgl_pemesanan = null) {
        $dateStr = $tgl_pemesanan ? date('Ymd', strtotime($tgl_pemesanan)) : date('Ymd');
        return 'TRX-' . $dateStr . '-' . str_pad((int)$id_transaksi, 4, '0', STR_PAD_LEFT);
    }
}

/**
 * Helper Ikon Kategori Tiket Dinamis (Poin 6)
 */
if (!function_exists('get_ticket_icon')) {
    function get_ticket_icon($nama_tiket, $custom_icon = null) {
        if (!empty($custom_icon) && $custom_icon !== 'fa-ticket') {
            return $custom_icon;
        }
        $nama = strtolower(trim((string)$nama_tiket));
        if (str_contains($nama, 'lansia') || str_contains($nama, 'orang tua') || str_contains($nama, 'elderly')) {
            return 'fa-person-cane';
        }
        if (str_contains($nama, 'dewasa') || str_contains($nama, 'adult')) {
            return 'fa-person';
        }
        if (str_contains($nama, 'anak') || str_contains($nama, 'balita') || str_contains($nama, 'kid')) {
            return 'fa-child-reaching';
        }
        if (str_contains($nama, 'pelajar') || str_contains($nama, 'mahasiswa') || str_contains($nama, 'santri')) {
            return 'fa-graduation-cap';
        }
        if (str_contains($nama, 'rombongan') || str_contains($nama, 'grup') || str_contains($nama, 'paket')) {
            return 'fa-users';
        }
        if (str_contains($nama, 'vip') || str_contains($nama, 'spesial')) {
            return 'fa-star';
        }
        return 'fa-ticket';
    }
}

if (!function_exists('get_ticket_color')) {
    function get_ticket_color($nama_tiket) {
        $nama = strtolower(trim((string)$nama_tiket));
        if (str_contains($nama, 'lansia')) return 'emerald';
        if (str_contains($nama, 'dewasa')) return 'blue';
        if (str_contains($nama, 'anak')) return 'amber';
        if (str_contains($nama, 'pelajar')) return 'purple';
        if (str_contains($nama, 'rombongan')) return 'teal';
        return 'blue';
    }
}

/**
 * Helper Integrasi API Libur Nasional Kemendesa (Poin 12 - SIM-ASET Standard)
 * Menampilkan konteks operasional lonjakan pengunjung pada dashboard
 */
if (!function_exists('get_national_holidays')) {
    function get_national_holidays($year = null) {
        $year = $year ?: date('Y');
        $cacheDir = __DIR__ . '/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $cacheFile = $cacheDir . "/holidays_{$year}.json";

        // Cache selama 24 jam (86400 detik)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            $cached = @file_get_contents($cacheFile);
            if ($cached) {
                return json_decode($cached, true) ?: [];
            }
        }

        // Ambil data dari API Kemendesa (seperti pada SIM-ASET) dengan timeout ultra-cepat
        $url = "https://api.kemendesa.link/libur-nasional/api/holidays/{$year}.json";
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 1.2, // Ultra-fast timeout agar navigasi kalender instan tanpa lag
                'ignore_errors' => true,
                'user_agent' => 'PemandianPatemon/2.0'
            ]
        ]);

        $json = @file_get_contents($url, false, $ctx);
        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                @file_put_contents($cacheFile, $json);
                return $data;
            }
        }

        // Fallback jika offline atau timeout, cache sementara agar navigasi bulan berikutnya tidak delay
        $fallback = [
            'data' => [
                ['date' => $year . '-01-01', 'name' => 'Tahun Baru Masehi', 'is_cuti_bersama' => false],
                ['date' => $year . '-05-01', 'name' => 'Hari Buruh Internasional', 'is_cuti_bersama' => false],
                ['date' => $year . '-08-17', 'name' => 'Hari Kemerdekaan RI', 'is_cuti_bersama' => false],
                ['date' => $year . '-12-25', 'name' => 'Hari Raya Natal', 'is_cuti_bersama' => false],
            ]
        ];
        @file_put_contents($cacheFile, json_encode($fallback));
        return $fallback;
    }
}

if (!function_exists('get_custom_holidays')) {
    function get_custom_holidays($year = null, $month = null) {
        global $conn;
        $holidays = [];
        if (!$conn) return $holidays;
        
        $year = (int)($year ?: date('Y'));
        $where = "YEAR(tanggal) = {$year}";
        if ($month) {
            $month = (int)$month;
            $where .= " AND MONTH(tanggal) = {$month}";
        }
        
        $res = $conn->query("SELECT id, tanggal, keterangan, tipe FROM calendar_holidays WHERE {$where} ORDER BY tanggal ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $holidays[] = [
                    'id'         => (int)$row['id'],
                    'date'       => $row['tanggal'],
                    'title'      => $row['keterangan'],
                    'tipe'       => $row['tipe'],
                    'is_custom'  => true,
                    'is_cuti'    => ($row['tipe'] === 'cuti')
                ];
            }
        }
        return $holidays;
    }
}

if (!function_exists('get_active_closure_today')) {
    function get_active_closure_today() {
        global $conn;
        if (!$conn) return null;
        $today = date('Y-m-d');
        // Hanya entri khusus yang ditetapkan Super Admin yang menyatakan penutupan:
        // tipe 'tutup_pemeliharaan' ATAU entri libur khusus pengelola
        $stmt = $conn->prepare("SELECT id, tanggal, keterangan, tipe FROM calendar_holidays WHERE tanggal = ? AND tipe IN ('tutup_pemeliharaan', 'libur') LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $res ?: null;
        }
        return null;
    }
}

if (!function_exists('is_pool_closed_today')) {
    /**
     * Memeriksa apakah kolam pemandian ditutup hari ini karena pemeliharaan / penutupan khusus oleh Super Admin.
     * Hari libur nasional atau cuti bersama umum TIDAK menutup kolam (pengunjung tetap ramai dan tiket tetap dibuka).
     */
    function is_pool_closed_today() {
        $closure = get_active_closure_today();
        return $closure !== null;
    }
}

if (!function_exists('get_month_holidays')) {
    function get_month_holidays($year = null, $month = null) {
        $year  = (int)($year ?: date('Y'));
        $month = (int)($month ?: date('n'));
        $raw   = get_national_holidays($year);
        $list  = $raw['data'] ?? (isset($raw[0]) ? $raw : []);
        $result = [];
        $prefix = sprintf('%04d-%02d-', $year, $month);

        foreach ($list as $item) {
            $d = $item['date'] ?? ($item['holiday_date'] ?? '');
            if (strpos($d, $prefix) === 0) {
                $result[] = [
                    'date'      => $d,
                    'title'     => $item['name'] ?? ($item['holiday_name'] ?? 'Hari Libur'),
                    'is_cuti'   => !empty($item['is_cuti_bersama']) || !empty($item['is_cuti']),
                    'is_custom' => false,
                    'tipe'      => 'libur'
                ];
            }
        }

        // Integrasikan libur kustom wisata dari Super Admin (Feedback-9 Poin 4)
        $customs = get_custom_holidays($year, $month);
        foreach ($customs as $c) {
            $result[] = $c;
        }

        return $result;
    }
}

/**
 * Helper Pengiriman Email Terpadu (Smart Mailer: Gmail SMTP / TLS / Direct Mail / Fallback)
 * Mendukung pengiriman langsung ke Gmail (smtp.gmail.com), Custom SMTP Server, maupun Direct Mail.
 * 
 * Pengaturan dapat dikonfigurasi melalui Environment Variables (.env) atau Pengaturan Sistem:
 * - SMTP_HOST        : Default 'smtp.gmail.com' (jika SMTP_USER berakhiran @gmail.com) atau '127.0.0.1'
 * - SMTP_PORT        : 587 (TLS/STARTTLS), 465 (SSL), atau 25
 * - SMTP_USER        : Alamat email pengirim (misal: youremail@gmail.com)
 * - SMTP_PASS        : Password email / Google App Password (16 digit)
 * - SMTP_FROM_EMAIL  : Alamat email pengirim yang ditampilkan
 * - SMTP_FROM_NAME   : Nama institusi pengirim (Wisata Pemandian Patemon)
 */
if (!function_exists('send_email')) {
    function send_email($to_email, $to_name, $subject, $html_body, $text_body = '') {
        $smtpUser = getenv('SMTP_USER') ?: (getenv('GMAIL_USER') ?: get_setting('smtp_user', ''));
        $smtpPass = getenv('SMTP_PASS') ?: (getenv('GMAIL_PASS') ?: get_setting('smtp_pass', ''));
        $smtpHost = getenv('SMTP_HOST') ?: get_setting('smtp_host', '');
        $smtpPort = (int)(getenv('SMTP_PORT') ?: get_setting('smtp_port', 0));

        // Deteksi apakah konfigurasi ditujukan untuk Gmail
        if (empty($smtpHost)) {
            if (!empty($smtpUser) && (strpos($smtpUser, '@gmail.com') !== false || strpos($to_email, '@gmail.com') !== false)) {
                $smtpHost = 'smtp.gmail.com';
                if ($smtpPort <= 0) $smtpPort = 587;
            }
        }

        // 1. Jika Kredensial SMTP (Gmail / Custom Provider) Disediakan
        if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
            $smtpRes = _send_via_smtp_auth($to_email, $to_name, $subject, $html_body, $smtpHost, $smtpPort, $smtpUser, $smtpPass);
            if ($smtpRes['success']) {
                return $smtpRes;
            }
            error_log("SMTP Auth Dispatch Failed (" . $smtpRes['message'] . "), attempting direct mail fallback...");
        }

        // 2. Fallback: Direct Mail via native PHP mail()
        $fromEmail = get_setting('smtp_from_email', (!empty($smtpUser) ? $smtpUser : 'no-reply@patemon.jemberkab.go.id'));
        $fromName  = get_setting('smtp_from_name', 'Wisata Pemandian Patemon');

        $cleanToName = preg_replace('/[^\w\s\.-]/', '', $to_name);
        $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $fromFormatted = "=?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>";
        $toFormatted = "=?UTF-8?B?" . base64_encode($cleanToName) . "?= <{$to_email}>";

        $headers = [
            "From: {$fromFormatted}",
            "Reply-To: {$fromFormatted}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "X-Mailer: Patemon-Smart-Mailer/2.0"
        ];
        $headersStr = implode("\r\n", $headers);

        $mailSent = @mail($to_email, $subject, $html_body, $headersStr);

        if (function_exists('error_log')) {
            error_log("Email Dispatch: Mengirim ke {$to_email} (Subject: {$subject}) - Direct Send: " . ($mailSent ? 'Sent' : 'Failed'));
        }

        if ($mailSent) {
            return [
                'success' => true,
                'message' => "Email berhasil dikirim ke {$to_email}."
            ];
        }

        return [
            'success' => false,
            'message' => "Pengiriman email via native mail() gagal (layanan mail server lokal VPS tidak merespon). Konfigurasikan SMTP di .env untuk pengiriman via Gmail."
        ];
    }
}

/**
 * Socket SMTP Sender dengan Dukungan TLS/SSL & AUTH LOGIN (Gmail, Outlook, Custom Relay)
 */
if (!function_exists('_send_via_smtp_auth')) {
    function _send_via_smtp_auth($to_email, $to_name, $subject, $html_body, $host, $port, $user, $pass) {
        $timeout = 10;
        $fromEmail = $user;
        $fromName  = get_setting('smtp_from_name', 'Wisata Pemandian Patemon');

        $isSSL = ($port === 465);
        $connectHost = ($isSSL ? 'ssl://' : '') . $host;
        $socket = @fsockopen($connectHost, $port, $errno, $errstr, $timeout);

        if (!$socket) {
            return ['success' => false, 'message' => "Gagal terhubung ke {$host}:{$port} ({$errstr})"];
        }

        stream_set_timeout($socket, $timeout);

        $read = function() use ($socket) {
            $data = '';
            while ($line = fgets($socket, 512)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $data;
        };

        $cmd = function($command) use ($socket, $read) {
            fputs($socket, $command . "\r\n");
            return $read();
        };

        $greet = $read();
        if (substr($greet, 0, 3) !== '220') {
            fclose($socket);
            return ['success' => false, 'message' => 'Greeting SMTP ditolak: ' . trim($greet)];
        }

        $cmd("EHLO " . (gethostname() ?: 'localhost'));

        // STARTTLS jika port 587 atau non-SSL
        if (!$isSSL && ($port === 587 || $port === 25)) {
            $tlsResp = $cmd("STARTTLS");
            if (substr($tlsResp, 0, 3) === '220') {
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$crypto) {
                    fclose($socket);
                    return ['success' => false, 'message' => 'Gagal negosiasi enkripsi STARTTLS dengan server SMTP'];
                }
                $cmd("EHLO " . (gethostname() ?: 'localhost'));
            }
        }

        // Autentikasi AUTH LOGIN
        $authResp = $cmd("AUTH LOGIN");
        if (substr($authResp, 0, 3) !== '334') {
            fclose($socket);
            return ['success' => false, 'message' => 'Server tidak mendukung AUTH LOGIN: ' . trim($authResp)];
        }

        $userResp = $cmd(base64_encode($user));
        if (substr($userResp, 0, 3) !== '334') {
            fclose($socket);
            return ['success' => false, 'message' => 'Autentikasi User SMTP ditolak: ' . trim($userResp)];
        }

        $passResp = $cmd(base64_encode($pass));
        if (substr($passResp, 0, 3) !== '235') {
            fclose($socket);
            return ['success' => false, 'message' => 'Password/App Password SMTP ditolak: ' . trim($passResp)];
        }

        $cmd("MAIL FROM:<{$fromEmail}>");
        $rcptResp = $cmd("RCPT TO:<{$to_email}>");
        if (substr($rcptResp, 0, 3) !== '250') {
            fclose($socket);
            return ['success' => false, 'message' => 'Penerima email ditolak oleh SMTP: ' . trim($rcptResp)];
        }

        $dataResp = $cmd("DATA");
        if (substr($dataResp, 0, 3) !== '354') {
            fclose($socket);
            return ['success' => false, 'message' => 'Perintah DATA ditolak: ' . trim($dataResp)];
        }

        $cleanToName = preg_replace('/[^\w\s\.-]/', '', $to_name);
        $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        $headers = [
            "Date: " . date('r'),
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
            "To: =?UTF-8?B?" . base64_encode($cleanToName) . "?= <{$to_email}>",
            "Subject: {$encodedSubject}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit"
        ];
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $html_body . "\r\n.\r\n";
        fputs($socket, $payload);
        $sendResp = $read();

        $cmd("QUIT");
        fclose($socket);

        if (substr($sendResp, 0, 3) === '250') {
            return ['success' => true, 'message' => "Email berhasil dikirim langsung via SMTP ke {$to_email}."];
        }

        return ['success' => false, 'message' => 'Pengiriman data email ditolak server: ' . trim($sendResp)];
    }
}

/**
 * Socket SMTP Sender (Opsional legacy helper)
 */
if (!function_exists('_send_via_smtp_socket')) {
    function _send_via_smtp_socket($to_email, $to_name, $subject, $html_body, $text_body = '') {
        return send_email($to_email, $to_name, $subject, $html_body, $text_body);
    }
}

/**
 * Kompatibilitas ke belakang (send_smtp_email dialihkan ke send_email)
 */
if (!function_exists('send_smtp_email')) {
    function send_smtp_email($to_email, $to_name, $subject, $html_body, $text_body = '') {
        return send_email($to_email, $to_name, $subject, $html_body, $text_body);
    }
}

/**
 * Helper Audit Trail / Activity Logging (Feedback-5 Poin 4 & 7)
 */
if (!function_exists('log_activity')) {
    function log_activity($action, $module, $description, $id_user = null) {
        global $conn;
        if (!$conn) return false;
        
        $userId = $id_user ?: ($_SESSION['id_user'] ?? null);
        $username = $_SESSION['username'] ?? ($userId ? 'User #' . $userId : 'Guest/System');
        $ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        
        $stmt = $conn->prepare("INSERT INTO activity_logs (id_user, username, action, module, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("issssss", $userId, $username, $action, $module, $description, $ip, $ua);
            return $stmt->execute();
        }
        return false;
    }
}

/**
 * Helper Filter Kata-Kata Kasar / Toxic Words (Feedback-5 Poin 3)
 */
if (!function_exists('get_toxic_words_list')) {
    function get_toxic_words_list() {
        global $conn;
        static $cachedWords = null;
        if ($cachedWords !== null) return $cachedWords;
        
        $words = [];
        if ($conn) {
            $res = $conn->query("SELECT word FROM toxic_words ORDER BY word ASC");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $w = strtolower(trim($row['word']));
                    if ($w !== '') $words[] = $w;
                }
            }
        }
        
        if (empty($words)) {
            $words = ['anjing', 'babi', 'monyet', 'bangsat', 'bajingan', 'kontol', 'memek', 'jembut', 'pantek', 'asu', 'perek', 'lonte', 'kampret', 'tai', 'tolol', 'goblok', 'idiot', 'fuck', 'shit', 'bitch'];
        }
        $cachedWords = $words;
        return $words;
    }
}

if (!function_exists('find_toxic_words')) {
    function find_toxic_words($text) {
        if (empty($text)) return [];
        $toxicWords = get_toxic_words_list();
        $textLower = strtolower((string)$text);
        // Normalisasi teks sederhana untuk mendeteksi variasi karakter
        $cleanText = preg_replace('/[^a-z0-9\s]/', '', $textLower);
        
        $found = [];
        foreach ($toxicWords as $tw) {
            if ($tw === '') continue;
            $pattern = '/\b' . preg_quote($tw, '/') . '\b/i';
            if (preg_match($pattern, $textLower) || preg_match($pattern, $cleanText) || (strlen($tw) >= 4 && str_contains($cleanText, $tw))) {
                $found[] = $tw;
            }
        }
        return array_values(array_unique($found));
    }
}

if (!function_exists('has_toxic_words')) {
    function has_toxic_words($text) {
        $found = find_toxic_words($text);
        return !empty($found);
    }
}

/**
 * Helper Server Log Traffic & Respon (Feedback-5 Poin 4)
 */
if (!function_exists('record_server_log')) {
    function record_server_log($method, $path, $status_code = 200, $response_time_ms = 0, $id_user = null) {
        global $conn;
        if (!$conn) return false;
        
        $userId = $id_user ?: ($_SESSION['id_user'] ?? null);
        $ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $cleanPath = substr((string)$path, 0, 255);
        $method = strtoupper(substr((string)$method, 0, 10));
        
        $stmt = $conn->prepare("INSERT INTO server_logs (method, path, status_code, ip_address, user_agent, response_time_ms, id_user) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssissdi", $method, $cleanPath, $status_code, $ip, $ua, $response_time_ms, $userId);
            return $stmt->execute();
        }
        return false;
    }
}

/**
 * Helper Keamanan Unggah Gambar Terpusat (Feedback-7 Poin 7: Anti-Polyglot & Steganography Hardening)
 * Memvalidasi MIME type, ekstensi whitelist, ukuran, struktur piksel, membersihkan EXIF/script tersembunyi
 * melalui re-encoding GD library murni, dan menghasilkan nama file acak CSPRNG.
 */
if (!function_exists('secure_upload_image')) {
    function secure_upload_image($file, $target_dir, $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'], $max_bytes = 2097152) {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'error' => 'Parameter berkas tidak valid.'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'error' => 'Tidak ada berkas yang diunggah.'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'error' => 'Ukuran berkas melebihi batas yang diizinkan server.'];
            default:
                return ['success' => false, 'error' => 'Terjadi kesalahan sistem saat mengunggah berkas.'];
        }

        if ($file['size'] > $max_bytes) {
            $max_mb = round($max_bytes / 1048576, 1);
            return ['success' => false, 'error' => "Ukuran berkas maksimal adalah {$max_mb} MB."];
        }

        // 1. Ekstensi Whitelist (Validasi awal format berkas)
        $orig_ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($orig_ext, $allowed_extensions, true)) {
            return ['success' => false, 'error' => 'Format ekstensi berkas tidak diizinkan. Hanya ' . implode(', ', $allowed_extensions) . ' yang diperbolehkan.'];
        }

        $tmp_name = $file['tmp_name'];
        if (!is_uploaded_file($tmp_name) && !(php_sapi_name() === 'cli' && file_exists($tmp_name))) {
            return ['success' => false, 'error' => 'Berkas tidak diunggah melalui mekanisme HTTP POST yang sah.'];
        }

        // 2. MIME Type Verification via finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = @finfo_file($finfo, $tmp_name);
        finfo_close($finfo);

        $allowed_mimes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp'
        ];

        if (!in_array($mime, $allowed_mimes, true)) {
            return ['success' => false, 'error' => 'Tipe konten berkas (MIME type) tidak sah atau bukan gambar yang valid.'];
        }

        // 3. Inspeksi Struktur Piksel Gambar (getimagesize)
        $img_info = @getimagesize($tmp_name);
        if ($img_info === false || $img_info[0] <= 0 || $img_info[1] <= 0) {
            return ['success' => false, 'error' => 'Berkas rusak atau bukan merupakan gambar valid.'];
        }

        // 4. Deteksi Script Berbahaya / Polyglot / WebShell dalam Konten Mentah
        $file_content = @file_get_contents($tmp_name, false, null, 0, 524288); // 512KB first chunk
        if ($file_content !== false) {
            $dangerous_patterns = [
                '/<\?php/i',
                '/<\?=/i',
                '/<\x00?\?\x00?p\x00?h\x00?p/i',
                '/<script\b/i',
                '/\b(eval|passthru|shell_exec|exec|system|base64_decode|assert)\s*\(/i'
            ];
            foreach ($dangerous_patterns as $pattern) {
                if (preg_match($pattern, $file_content)) {
                    return ['success' => false, 'error' => 'Berkas ditolak: Terdeteksi pola script berbahaya atau kode tersembunyi dalam berkas gambar.'];
                }
            }
        }

        // Pastikan direktori tujuan ada dan berizin tulis
        if (!is_dir($target_dir)) {
            @mkdir($target_dir, 0755, true);
        }
        $target_dir = rtrim($target_dir, '/\\') . DIRECTORY_SEPARATOR;

        // Nama file acak kriptografis (CSPRNG)
        $safe_filename = bin2hex(random_bytes(16)) . '.' . ($orig_ext === 'jpeg' ? 'jpg' : $orig_ext);
        $destination = $target_dir . $safe_filename;

        // 5. Deep Re-Encoding & EXIF Stripping via GD Library
        $sanitized = false;
        if (extension_loaded('gd')) {
            $src_img = null;
            if ($mime === 'image/jpeg') {
                $src_img = @imagecreatefromjpeg($tmp_name);
            } elseif ($mime === 'image/png') {
                $src_img = @imagecreatefrompng($tmp_name);
            } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
                $src_img = @imagecreatefromwebp($tmp_name);
            }

            if ($src_img) {
                // Pertahankan transparansi PNG / WebP
                if ($mime === 'image/png' || $mime === 'image/webp') {
                    imagealphablending($src_img, false);
                    imagesavealpha($src_img, true);
                }

                if ($mime === 'image/jpeg') {
                    $sanitized = @imagejpeg($src_img, $destination, 90);
                } elseif ($mime === 'image/png') {
                    $sanitized = @imagepng($src_img, $destination, 8);
                } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
                    $sanitized = @imagewebp($src_img, $destination, 90);
                }
                @imagedestroy($src_img);
            }
        }

        // Fallback jika GD gagal atau tidak aktif
        if (!$sanitized) {
            $moved = false;
            if (is_uploaded_file($tmp_name)) {
                $moved = @move_uploaded_file($tmp_name, $destination);
            } elseif (php_sapi_name() === 'cli' && file_exists($tmp_name)) {
                $moved = @copy($tmp_name, $destination);
            }
            if (!$moved) {
                return ['success' => false, 'error' => 'Gagal memindahkan berkas ke penyimpanan server.'];
            }
        }

        @chmod($destination, 0644);

        return [
            'success'   => true,
            'filename'  => $safe_filename,
            'filepath'  => $destination,
            'mime'      => $mime,
            'size'      => @filesize($destination)
        ];
    }
}

if (!function_exists('safe_delete_media')) {
    /**
     * Menghapus berkas media fisik secara aman dari disk server
     * Mencegah path traversal, LFI, dan melindungi berkas bawaan sistem.
     *
     * @param string $filepath Jalur absolut berkas atau nama berkas
     * @param string|null $directory Direktori dasar jika hanya nama berkas yang diberikan
     * @param array $extra_protected Berkas tambahan yang dilindungi
     * @return bool
     */
    function safe_delete_media($filepath, $directory = null, $extra_protected = []) {
        if (empty($filepath)) {
            return false;
        }

        if ($directory !== null && !file_exists($filepath)) {
            $filepath = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . ltrim($filepath, '/\\');
        }

        $filename = basename($filepath);

        // Aset inti yang mutlak dilindungi
        $protected_defaults = array_merge([
            '', '.', '..', '.gitkeep', '.htaccess', 'web.config', 'index.php', 'index.html',
            'default.png', 'default_avatar.png', 'sample_event_patemon.png',
            'logo.png', 'logo2.png', 'logo-white.png', 'favicon.ico', 'favicon.png',
            'gambar1.png', 'gambar2.png', 'gambar3.png', 'gambar4.png', 'gambar5.png',
            'gambar6.png', 'gambar7.png', 'gambar8.png', 'gambar9.png'
        ], $extra_protected);

        if (in_array(strtolower($filename), array_map('strtolower', $protected_defaults), true)) {
            return false;
        }

        $real_target = realpath($filepath);
        if (!$real_target || !is_file($real_target)) {
            return false;
        }

        // Pastikan berkas berada di dalam direktori root project
        $root_project = realpath(__DIR__ . '/../../');
        if (!$root_project || strpos($real_target, $root_project) !== 0) {
            return false;
        }

        // Whitelist direktori media yang sah (hanya berkas di dalam direktori ini yang diizinkan untuk dihapus)
        $allowed_media_dirs = [
            realpath(__DIR__ . '/../../public/img'),
            realpath(__DIR__ . '/../../dist/app/payment')
        ];

        $is_inside_allowed_media = false;
        foreach ($allowed_media_dirs as $allowed_dir) {
            if ($allowed_dir && (strpos($real_target, $allowed_dir) === 0)) {
                $is_inside_allowed_media = true;
                break;
            }
        }

        if (!$is_inside_allowed_media) {
            return false;
        }

        // Pastikan hanya berkas media yang dapat dihapus (bukan berkas skrip kode .php, .env, dll)
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf'];
        $ext = strtolower(pathinfo($real_target, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts, true)) {
            return false;
        }

        return @unlink($real_target);
    }
}

if (!function_exists('clean_orphaned_media_storage')) {
    /**
     * Memindai dan membersihkan berkas sampah (orphaned media) yang tidak lagi memiliki referensi di database
     *
     * @param mysqli $conn
     * @return array [deleted_count => int, freed_bytes => int, deleted_files => array]
     */
    function clean_orphaned_media_storage($conn) {
        $result = [
            'deleted_count' => 0,
            'freed_bytes'   => 0,
            'deleted_files' => []
        ];

        // 1. Bersihkan Bukti Pembayaran Transaksi (dist/app/payment/)
        $payment_dir = realpath(__DIR__ . '/payment');
        if ($payment_dir && is_dir($payment_dir)) {
            $valid_payments = [];
            $res = $conn->query("SELECT DISTINCT bukti_pembayaran FROM transaksi WHERE bukti_pembayaran IS NOT NULL AND bukti_pembayaran != ''");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $valid_payments[] = strtolower(basename($row['bukti_pembayaran']));
                }
            }

            $files = @scandir($payment_dir);
            if ($files) {
                foreach ($files as $f) {
                    if (in_array($f, ['.', '..', '.gitkeep', '.htaccess'], true)) continue;
                    if (!in_array(strtolower($f), $valid_payments, true)) {
                        $full_path = $payment_dir . DIRECTORY_SEPARATOR . $f;
                        $fsize = @filesize($full_path) ?: 0;
                        if (safe_delete_media($full_path)) {
                            $result['deleted_count']++;
                            $result['freed_bytes'] += $fsize;
                            $result['deleted_files'][] = 'payment/' . $f;
                        }
                    }
                }
            }
        }

        // 2. Bersihkan Flyer Events (public/img/events/)
        $events_dir = realpath(__DIR__ . '/../../public/img/events');
        if ($events_dir && is_dir($events_dir)) {
            $valid_events = [];
            $res = $conn->query("SELECT DISTINCT gambar FROM events WHERE gambar IS NOT NULL AND gambar != '' AND deleted_at IS NULL");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $valid_events[] = strtolower(basename($row['gambar']));
                }
            }

            $files = @scandir($events_dir);
            if ($files) {
                foreach ($files as $f) {
                    if (in_array($f, ['.', '..', '.gitkeep', 'sample_event_patemon.png'], true)) continue;
                    if (!in_array(strtolower($f), $valid_events, true)) {
                        $full_path = $events_dir . DIRECTORY_SEPARATOR . $f;
                        $fsize = @filesize($full_path) ?: 0;
                        if (safe_delete_media($full_path)) {
                            $result['deleted_count']++;
                            $result['freed_bytes'] += $fsize;
                            $result['deleted_files'][] = 'events/' . $f;
                        }
                    }
                }
            }
        }

        // 3. Bersihkan Avatar Pengguna Nonaktif/Terhapus (public/img/avatars/)
        $avatar_dir = realpath(__DIR__ . '/../../public/img/avatars');
        if ($avatar_dir && is_dir($avatar_dir)) {
            $valid_avatars = ['default.png'];
            $res = $conn->query("SELECT DISTINCT avatar FROM users WHERE avatar IS NOT NULL AND avatar != '' AND deleted_at IS NULL");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $valid_avatars[] = strtolower(basename($row['avatar']));
                }
            }

            $files = @scandir($avatar_dir);
            if ($files) {
                foreach ($files as $f) {
                    if (in_array($f, ['.', '..', '.gitkeep', 'default.png'], true)) continue;
                    if (!in_array(strtolower($f), $valid_avatars, true)) {
                        $full_path = $avatar_dir . DIRECTORY_SEPARATOR . $f;
                        $fsize = @filesize($full_path) ?: 0;
                        if (safe_delete_media($full_path)) {
                            $result['deleted_count']++;
                            $result['freed_bytes'] += $fsize;
                            $result['deleted_files'][] = 'avatars/' . $f;
                        }
                    }
                }
            }
        }

        return $result;
    }
}
?>