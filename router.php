<?php
/**
 * Router script for PHP Built-in Web Server
 * Handles static assets, clean routing, and blocks sensitive file access.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$fullPath = __DIR__ . $uri;

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

// 2. Redirect root path to public landing page
if ($uri === '/' || $uri === '' || $uri === '/index.html') {
    header("Location: /dist/views/index.php");
    exit;
}

// 3. If file exists on disk, let built-in server handle it (CSS, JS, images, PHP scripts)
if (file_exists($fullPath) && !is_dir($fullPath)) {
    return false;
}

// 4. If directory is accessed directly and has index.php
if (is_dir($fullPath) && file_exists(rtrim($fullPath, '/') . '/index.php')) {
    header("Location: " . rtrim($uri, '/') . "/index.php");
    exit;
}

// 5. Fallback 404
http_response_code(404);
echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body style='font-family:sans-serif;text-align:center;padding:50px;'><h1>404 Not Found</h1><p>Halaman yang Anda tuju tidak ditemukan.</p><a href='/dist/views/index.php'>Kembali ke Beranda</a></body></html>";
exit;
