<?php
/**
 * Test Validasi Autentikasi & Keamanan Sesi
 * Menguji hashing password BCRYPT, level role pengguna, dan verifikasi akun.
 */

declare(strict_types=1);

require_once __DIR__ . '/TestRunner.php';

echo "Menjalankan Test Autentikasi & Keamanan Akun...\n";
$runner = new TestRunner();

// 1. Verifikasi Password Hashing BCRYPT
$testPass = 'admin123';
$hashed = password_hash($testPass, PASSWORD_BCRYPT);
$runner->assert(
    password_verify($testPass, $hashed),
    'password_verify berhasil memvalidasi hash BCRYPT'
);

$runner->assert(
    !password_verify('wrongpassword', $hashed),
    'password_verify menolak kata sandi yang salah'
);

// 2. Cek Akun Super Admin di Database
global $conn;
$stmt = $conn->prepare("SELECT id_user, username, password, level, is_active FROM users WHERE username = 'super_admin' LIMIT 1");
$stmt->execute();
$adminUser = $stmt->get_result()->fetch_assoc();

$runner->assert(
    $adminUser !== null,
    'Akun super_admin terdaftar di database'
);

if ($adminUser) {
    $runner->assert(
        (int)$adminUser['level'] === 1,
        'Level role super_admin bernilai 1 (Super Administrator)'
    );

    $runner->assert(
        (int)$adminUser['is_active'] === 1,
        'Status akun super_admin aktif (is_active = 1)'
    );

    $runner->assert(
        password_verify('admin123', $adminUser['password']),
        'Kredensial super_admin (admin123) valid dan cocok dengan hash database'
    );
}

// 3. Verifikasi Proteksi & Auth Helper
$runner->assert(
    function_exists('check_auth'),
    'Helper autentikasi check_auth() tersedia secara global'
);

$runner->assert(
    get_role_name(1) === 'Super Admin' && get_role_name(2) === 'Admin' && get_role_name(3) === 'Staf Kasir',
    'Fungsi get_role_name() memetakan level 1, 2, 3 dengan tepat'
);

$runner->assert(
    function_exists('csrf_token') && function_exists('validate_csrf'),
    'Helper CSRF token dan validasi CSRF tersedia secara global'
);

exit($runner->summarize());
