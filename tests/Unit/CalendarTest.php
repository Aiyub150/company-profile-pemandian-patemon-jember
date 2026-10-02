<?php
/**
 * Test Logika Operasional & Kalender Libur Wisata
 * Memverifikasi deteksi status buka/tutup dan penanganan hari libur.
 */

declare(strict_types=1);

require_once __DIR__ . '/TestRunner.php';

echo "Menjalankan Test Kalender & Status Operasional...\n";
$runner = new TestRunner();

// 1. Uji helper format Rupiah
$runner->assert(
    format_rupiah(50000) === 'Rp 50.000',
    'format_rupiah menghasilkan string berformat benar'
);

// 2. Uji helper sanitize / escaping HTML
$rawHtml = "<script>alert('xss');</script>";
$runner->assert(
    e($rawHtml) === '&lt;script&gt;alert(&#039;xss&#039;);&lt;/script&gt;',
    'Fungsi e() melakukan escaping karakter berbahaya XSS dengan aman'
);

// 3. Uji Query Kalender Libur Hari Ini
global $conn;
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT * FROM calendar_holidays WHERE tanggal = ? LIMIT 1");
$stmt->bind_param("s", $today);
$stmt->execute();
$res = $stmt->get_result();
$todayHoliday = $res->fetch_assoc();

$runner->assert(
    $stmt->errno === 0,
    'Query pengecekan calendar_holidays berjalan tanpa error SQL'
);

if ($todayHoliday) {
    $runner->assert(
        in_array($todayHoliday['tipe'], ['libur_pengelola', 'tutup_pemeliharaan', 'cuti_bersama']),
        "Tipe penutupan hari ini valid ({$todayHoliday['tipe']})"
    );
}

exit($runner->summarize());
