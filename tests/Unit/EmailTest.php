<?php
/**
 * Test Validasi Pengiriman Email (Smart Mailer & Gmail Dispatch)
 * Menguji helper send_email, format MIME header, dan deteksi parameter Gmail SMTP.
 */

declare(strict_types=1);

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../../dist/app/config.php';

echo "Menjalankan Test Pengiriman Email (Smart Mailer & Gmail Dispatch)...\n";
$runner = new TestRunner();

// 1. Verifikasi ketersediaan helper send_email
$runner->assert(
    function_exists('send_email'),
    'Fungsi send_email() tersedia secara global di aplikasi'
);

// 2. Verifikasi pengiriman email langsung (Direct Send ke Gmail)
$res = send_email('pengunjung@gmail.com', 'Ahmad Pengunjung', 'Aktivasi Akun Patemon', '<p>Kode aktivasi: 123456</p>');
$runner->assert(
    isset($res['success']) && $res['success'] === true,
    'send_email berhasil memproses pengiriman langsung ke alamat pengunjung@gmail.com'
);

$runner->assert(
    strpos($res['message'], 'pengunjung@gmail.com') !== false,
    'Pesan hasil pengiriman email mencantumkan alamat tujuan yang sesuai'
);

// 3. Verifikasi ketersediaan parser .env untuk kredensial Gmail SMTP
$runner->assert(
    function_exists('load_env_file'),
    'Fungsi load_env_file() tersedia untuk membaca konfigurasi SMTP Gmail'
);

exit($runner->summarize());
