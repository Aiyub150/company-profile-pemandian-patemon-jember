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
    // SEC-07: Log internal error tanpa membocorkan kredensial atau host ke frontend
    error_log("Database connection failed: " . mysqli_connect_error());
    http_response_code(500);
    die("<div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; padding: 35px 25px; background: #ffffff; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 16px; max-width: 520px; margin: 60px auto; box-shadow: 0 10px 25px rgba(0,0,0,0.06); text-align: center;'>
        <div style='font-size: 42px; margin-bottom: 12px;'>⚠️</div>
        <h3 style='margin: 0 0 10px; color: #0f172a; font-size: 1.25rem;'>Layanan Database Belum Tersedia</h3>
        <p style='color: #64748b; font-size: 14px; line-height: 1.6; margin: 0 0 20px;'>Sistem mengalami kendala saat menghubungkan ke database server. Pastikan layanan database telah aktif atau hubungi administrator sistem.</p>
        <a href='javascript:location.reload()' style='display:inline-block; padding: 10px 22px; background: #0284c7; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 13px;'>Muat Ulang Halaman</a>
    </div>");
}

mysqli_set_charset($conn, "utf8mb4");

// Start session secara aman jika belum aktif (SEC-06)
if (session_status() === PHP_SESSION_NONE) {
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

if (!function_exists('payment_url')) {
    function payment_url($path = '') {
        return base_url('dist/app/payment/' . ltrim($path, '/'));
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

            // Gallery
            'gallery'                => 'admin/gallery',
            'admin_gallery'          => 'admin/gallery',

            // Settings & Super Admin Modules (Feedback-5)
            'settings_toxic'         => 'admin/settings/toxic',
            'settings_server_log'    => 'admin/settings/server-log',
            'settings_history_log'   => 'admin/settings/history-log',
            'transaksi_restore'      => 'admin/transaksi/restore',

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

/**
 * Helper Audit Trail / Activity Logging (Feedback-5 Poin 4 & 7)
 */
if (!function_exists('log_activity')) {
    function log_activity($action, $module, $description, $id_user = null) {
        global $conn;
        if (!$conn) return false;
        
        $userId = $id_user ?: ($_SESSION['id_user'] ?? null);
        $username = $_SESSION['username'] ?? ($userId ? 'User #' . $userId : 'Guest/System');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
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
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
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
?>