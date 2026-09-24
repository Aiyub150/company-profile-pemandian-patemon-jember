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
 * Helper proteksi hak akses (RBAC)
 * Level 1 = Admin, Level 2 = Staff, Level 0 = Pengguna
 */
if (!function_exists('check_auth')) {
    function check_auth($allowed_levels = [1, 2], $redirect_path = '../index.php') {
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

/**
 * Helper Resolusi URL Terpusat
 * Menghitung root path dinamis baik di CLI (serve.php) maupun Apache subfolder (XAMPP)
 */
if (!function_exists('base_url')) {
    function base_url($path = '') {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $pos = strpos($script, '/dist/');
        if ($pos === false) {
            $pos = strpos($script, '/public/');
        }
        $root = ($pos !== false) ? substr($script, 0, $pos) : '';
        $clean_path = ltrim($path, '/');
        return rtrim($root, '/') . ($clean_path !== '' ? '/' . $clean_path : '');
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
?>