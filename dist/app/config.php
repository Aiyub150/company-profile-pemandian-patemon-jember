<?php
/**
 * Konfigurasi Database dan Helper Keamanan Terpusat
 * Aplikasi Kasir dan Portofolio Pemandian Patemon
 */

// Inisialisasi konfigurasi dari environment variables atau fallback default
$host = getenv('DB_HOST') ?: '127.0.0.1';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$database = getenv('DB_NAME') ?: 'pemandian';

// Membuat koneksi ke database dengan penanganan error aman
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    // Jika koneksi gagal, jangan tampilkan kredensial ke publik
    error_log("Database connection failed: " . mysqli_connect_error());
    die("<div style='font-family: sans-serif; padding: 20px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 6px; max-width: 600px; margin: 50px auto;'>
        <h3>Layanan Database Belum Tersedia</h3>
        <p>Gagal terhubung ke database <strong>{$database}</strong> pada host <strong>{$host}</strong>.</p>
        <p>Pastikan layanan MySQL/MariaDB (XAMPP) sudah berjalan.</p>
    </div>");
}

mysqli_set_charset($conn, "utf8mb4");

// Start session secara aman jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    // Pengerasan cookie session
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
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
 * Helper CSRF Token
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
        $token = $token ?: ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
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
            if ($root === '/' || $root === '\\') $root = '';
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

/**
 * Helper Clean Route Name Resolver
 */
if (!function_exists('route_url')) {
    function route_url($name = '', $params = []) {
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
        $url = base_url($path);
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

        // Ambil data dari API Kemendesa (seperti pada SIM-ASET)
        $url = "https://api.kemendesa.link/libur-nasional/api/holidays/{$year}.json";
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 3, // fast timeout agar tidak menghambat loading jika offline
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

        // Fallback jika offline
        return [
            'data' => [
                ['date' => $year . '-01-01', 'name' => 'Tahun Baru Masehi', 'is_cuti_bersama' => false],
                ['date' => $year . '-05-01', 'name' => 'Hari Buruh Internasional', 'is_cuti_bersama' => false],
                ['date' => $year . '-08-17', 'name' => 'Hari Kemerdekaan RI', 'is_cuti_bersama' => false],
                ['date' => $year . '-12-25', 'name' => 'Hari Raya Natal', 'is_cuti_bersama' => false],
            ]
        ];
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
                    'date'    => $d,
                    'title'   => $item['name'] ?? ($item['holiday_name'] ?? 'Hari Libur'),
                    'is_cuti' => !empty($item['is_cuti_bersama']) || !empty($item['is_cuti'])
                ];
            }
        }
        return $result;
    }
}
?>