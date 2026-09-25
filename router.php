<?php
/**
 * Router script for PHP Built-in Web Server
 * Handles static assets, clean routing, blocks sensitive file access, and logs server activity.
 */

$startTime = microtime(true);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$fullPath = __DIR__ . $uri;

// Security Headers (SEC-08)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Daftarkan server logging pada saat request selesai
register_shutdown_function(function() use ($startTime, $uri) {
    $statusCode = http_response_code() ?: 200;
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    // Abaikan aset statis agar log fokus pada aktivitas rute aplikasi
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    if (in_array(strtolower($ext), ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'woff', 'woff2', 'ttf', 'ico'])) {
        return;
    }
    try {
        @include_once __DIR__ . '/dist/app/config.php';
        if (function_exists('record_server_log')) {
            record_server_log($_SERVER['REQUEST_METHOD'] ?? 'GET', $uri, $statusCode, $duration);
        }
    } catch (\Throwable $e) {}
});

// 1. Block access to sensitive files
$blockedPatterns = [
    '/\.env/i',
    '/\.git/i',
    '/\.sql$/i',
    '/composer\.(json|lock)$/i',
    '/\.md$/i',
    '/database\//i'
];

foreach ($blockedPatterns as $pattern) {
    if (preg_match($pattern, $uri)) {
        http_response_code(403);
        echo "403 Forbidden: Akses ke file ini dibatasi demi keamanan.";
        exit;
    }
}

// 1.5 Handle Payment Proof Uploads (SEC-01 & SEC-04 Hardened)
if (str_starts_with($uri, '/app/payment/') || str_starts_with($uri, '/dist/app/payment/')) {
    // SEC-01: Gunakan basename() dan verifikasi realpath() untuk memblokir Path Traversal
    $filename = basename($uri);
    $allowedDir = realpath(__DIR__ . '/dist/app/payment');
    $paymentFile = $allowedDir . DIRECTORY_SEPARATOR . $filename;
    $realPaymentFile = realpath($paymentFile);

    if ($allowedDir && $realPaymentFile && file_exists($realPaymentFile) && is_file($realPaymentFile) && str_starts_with($realPaymentFile, $allowedDir)) {
        // Cek ekstensi file yang diizinkan (gambar/pdf)
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            http_response_code(403);
            echo "403 Forbidden: Format berkas tidak diizinkan.";
            exit;
        }

        // SEC-04: Autentikasi sesi & otorisasi hak akses bukti pembayaran
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['id_user'])) {
            http_response_code(403);
            echo "403 Forbidden: Anda harus login untuk mengakses berkas bukti pembayaran.";
            exit;
        }

        $userLevel = (int)($_SESSION['level'] ?? 0);
        $userId = (int)($_SESSION['id_user'] ?? 0);
        
        // Admin (1, 2) dan Staf (3) diizinkan melihat semua bukti pembayaran
        // Pengunjung (0) hanya diizinkan melihat file miliknya sendiri
        if ($userLevel === 0) {
            require_once __DIR__ . '/dist/app/config.php';
            $stmt = $conn->prepare("SELECT id_transaksi FROM transaksi WHERE bukti_pembayaran = ? AND id_user = ? LIMIT 1");
            $stmt->bind_param("si", $filename, $userId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                http_response_code(403);
                echo "403 Forbidden: Anda tidak memiliki hak akses terhadap bukti pembayaran ini.";
                exit;
            }
        }

        $mime = mime_content_type($realPaymentFile) ?: 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, no-cache, must-revalidate');
        readfile($realPaymentFile);
        exit;
    } else {
        http_response_code(404);
        echo "404 Not Found: Berkas bukti pembayaran tidak ditemukan.";
        exit;
    }
}

// 2. Clean Routing Map
$cleanRoutes = [
    '/'                      => 'dist/views/index.php',
    '/home'                  => 'dist/views/index.php',
    '/beranda'               => 'dist/views/index.php',
    '/login'                 => 'dist/views/login.php',
    '/register'              => 'dist/views/register.php',
    '/logout'                => 'dist/views/logout.php',
    '/forgot-password'       => 'dist/views/forgot_password.php',
    '/forgot_password'       => 'dist/views/forgot_password.php',
    '/tiket'                 => 'dist/views/tiket/pesan.php',
    '/tiket/pesan'           => 'dist/views/tiket/pesan.php',
    '/pesan'                 => 'dist/views/tiket/pesan.php',
    '/tiket/nota'            => 'dist/views/tiket/nota.php',
    '/nota'                  => 'dist/views/tiket/nota.php',

    '/dashboard'             => 'dist/views/dashboard/dashboard.php',
    '/admin'                 => 'dist/views/dashboard/dashboard.php',
    '/admin/dashboard'       => 'dist/views/dashboard/dashboard.php',
    '/kasir'                 => 'dist/views/transaksi/staf.php',
    '/kasir/loket'           => 'dist/views/transaksi/staf.php',
    '/kasir/pos'             => 'dist/views/transaksi/tambah.php',

    '/admin/transaksi'       => 'dist/views/transaksi/transaksi.php',
    '/admin/transaksi/tambah'=> 'dist/views/transaksi/tambah.php',
    '/admin/transaksi/update'=> 'dist/views/transaksi/update.php',
    '/admin/transaksi/delete'=> 'dist/views/transaksi/delete.php',
    '/admin/transaksi/detail'=> 'dist/views/detail_transaksi/detail_transaksi.php',
    '/admin/transaksi/detail/update' => 'dist/views/detail_transaksi/update.php',
    '/admin/transaksi/detail/delete' => 'dist/views/detail_transaksi/delete.php',

    '/admin/tiket'           => 'dist/views/tiket/tiket.php',
    '/kategori-tiket'        => 'dist/views/tiket/tiket.php',
    '/admin/tiket/tambah'    => 'dist/views/tiket/tambah.php',
    '/admin/tiket/update'    => 'dist/views/tiket/update.php',
    '/admin/tiket/delete'    => 'dist/views/tiket/delete.php',

    '/admin/laporan'         => 'dist/views/transaksi/laporan.php',
    '/laporan'               => 'dist/views/transaksi/laporan.php',
    '/admin/laporan/harian'  => 'dist/views/transaksi/laporan_harian.php',
    '/admin/laporan/bulanan' => 'dist/views/transaksi/laporan_bulanan.php',
    '/admin/laporan/tahunan' => 'dist/views/transaksi/laporan_tahunan.php',
    '/admin/laporan/preview' => 'dist/views/transaksi/laporan_preview.php',

    '/admin/ulasan'          => 'dist/views/ulasan/ulasan.php',
    '/ulasan'                => 'dist/views/ulasan/ulasan.php',
    '/admin/ulasan/delete'   => 'dist/views/ulasan/delete.php',

    '/admin/users'           => 'dist/views/user/user.php',
    '/users'                 => 'dist/views/user/user.php',
    '/admin/users/tambah'    => 'dist/views/user/tambah.php',
    '/admin/users/update'    => 'dist/views/user/update.php',
    '/admin/users/delete'    => 'dist/views/user/delete.php',

    '/admin/gallery'         => 'dist/views/gallery/gallery.php',
    '/gallery'               => 'dist/views/gallery/gallery.php',

    '/admin/settings/toxic'      => 'dist/views/settings/toxic_filter.php',
    '/admin/settings/server-log' => 'dist/views/settings/server_log.php',
    '/admin/settings/history-log'=> 'dist/views/settings/log_history.php',
    '/admin/transaksi/restore'   => 'dist/views/transaksi/restore.php',

    '/profile'               => 'dist/views/profile/profile.php',
    '/guide'                 => 'dist/views/settings/guide.php',
    '/guide/preview-pdf'     => 'dist/views/settings/guide_preview_pdf.php',
    '/settings/version'      => 'dist/views/settings/version.php',
    '/version'               => 'dist/views/settings/version.php',

    // Fallback .php direct access
    '/index.php'             => 'dist/views/index.php',
    '/login.php'             => 'dist/views/login.php',
    '/register.php'          => 'dist/views/register.php',
    '/logout.php'            => 'dist/views/logout.php',
    '/forgot_password.php'   => 'dist/views/forgot_password.php',
    '/tiket/pesan.php'       => 'dist/views/tiket/pesan.php',
    '/tiket/nota.php'        => 'dist/views/tiket/nota.php',
    '/dashboard/dashboard.php' => 'dist/views/dashboard/dashboard.php',
    '/transaksi/transaksi.php' => 'dist/views/transaksi/transaksi.php',
    '/transaksi/staf.php'    => 'dist/views/transaksi/staf.php',
    '/transaksi/tambah.php'  => 'dist/views/transaksi/tambah.php',
    '/transaksi/update.php'  => 'dist/views/transaksi/update.php',
    '/tiket/tiket.php'       => 'dist/views/tiket/tiket.php',
    '/tiket/tambah.php'      => 'dist/views/tiket/tambah.php',
    '/tiket/update.php'      => 'dist/views/tiket/update.php',
    '/user/user.php'         => 'dist/views/user/user.php',
    '/user/tambah.php'       => 'dist/views/user/tambah.php',
    '/user/update.php'       => 'dist/views/user/update.php',
    '/ulasan/ulasan.php'     => 'dist/views/ulasan/ulasan.php',
    '/profile/profile.php'   => 'dist/views/profile/profile.php',
    '/settings/guide.php'    => 'dist/views/settings/guide.php',
    '/settings/version.php'  => 'dist/views/settings/version.php',
];

$normalizedUri = rtrim($uri, '/');
if ($normalizedUri === '') {
    $normalizedUri = '/';
}

if (isset($cleanRoutes[$normalizedUri])) {
    $targetFile = __DIR__ . '/' . $cleanRoutes[$normalizedUri];
    if (file_exists($targetFile)) {
        chdir(dirname($targetFile));
        require $targetFile;
        exit;
    }
}

// 3. Static assets: If physical file exists on disk, let built-in server handle it (CSS, JS, images, fonts)
if (file_exists($fullPath) && !is_dir($fullPath)) {
    return false;
}

// 4. Backward compatibility: if direct file in /dist/views/... is accessed directly
if (file_exists($fullPath) && is_file($fullPath) && pathinfo($fullPath, PATHINFO_EXTENSION) === 'php') {
    chdir(dirname($fullPath));
    require $fullPath;
    exit;
}

// 5. Fallback 404
http_response_code(404);
echo "<!DOCTYPE html><html><head><title>404 Not Found - Pemandian Patemon</title><link rel='stylesheet' href='/public/css/modern-theme.css'></head><body style='font-family:sans-serif;text-align:center;padding:80px 20px;background:#f8fafc;'><div style='max-width:500px;margin:auto;background:#fff;padding:40px;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,0.05);'><h1 style='color:#0f172a;font-size:3rem;margin-bottom:10px;'>404</h1><h3 style='color:#334155;margin-bottom:15px;'>Halaman Tidak Ditemukan</h3><p style='color:#64748b;margin-bottom:25px;'>Alamat URL yang Anda tuju tidak tersedia atau telah dipindahkan.</p><a href='/' style='display:inline-block;padding:10px 24px;background:#0284c7;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;'>Kembali ke Beranda</a></div></body></html>";
exit;
