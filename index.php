<?php
/**
 * Root Application Entry Point & Front Controller
 * Pemandian Patemon - Sistem Kasir & Portofolio Wisata
 */

declare(strict_types=1);

$target = __DIR__ . '/dist/views/index.php';

if (file_exists($target)) {
    require_once $target;
} else {
    http_response_code(404);
    echo "Halaman utama tidak ditemukan.";
}
