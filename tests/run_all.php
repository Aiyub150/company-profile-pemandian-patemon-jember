<?php
/**
 * Master Test Suite Runner - Pemandian Patemon
 * Menjalankan semua unit & integration test sekaligus.
 */

declare(strict_types=1);

echo "\n" . str_repeat('=', 65) . "\n";
echo "   PEMANDIAN PATEMON - SUITE PENGUJIAN OTOMATIS (TEST RUNNER)   \n";
echo str_repeat('=', 65) . "\n\n";

$testFiles = [
    'Auth & RBAC Security'        => __DIR__ . '/Unit/AuthTest.php',
    'Calendar & Operations'       => __DIR__ . '/Unit/CalendarTest.php',
    'Ticket & Transaction Logic'  => __DIR__ . '/Unit/TransactionTest.php',
    'Email & Gmail Dispatch'      => __DIR__ . '/Unit/EmailTest.php',
];

$allPassed = true;

foreach ($testFiles as $suiteName => $filePath) {
    echo "▶ [SUITE] {$suiteName}\n";
    $cmd = 'php ' . escapeshellarg($filePath);
    passthru($cmd, $exitCode);
    echo "\n";
    if ($exitCode !== 0) {
        $allPassed = false;
    }
}

echo str_repeat('-', 65) . "\n";
if ($allPassed) {
    echo "\033[32m✔ SEMUA TEST SUITE BERHASIL DILALUI DENGAN SUKSES!\033[0m\n";
    echo "Aplikasi siap dijalankan & aman dari regresi logika utama.\n";
    exit(0);
} else {
    echo "\033[31m✘ TERDAPAT TEST SUITE YANG GAGAL!\033[0m\n";
    exit(1);
}
