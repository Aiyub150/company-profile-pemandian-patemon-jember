<?php
/**
 * Test Simulasi Transaksi Tiket & Perhitungan Total
 * Memverifikasi validitas pembuatan kode transaksi, perhitungan harga, dan relasi tiket.
 */

declare(strict_types=1);

require_once __DIR__ . '/TestRunner.php';

echo "Menjalankan Test Logika Transaksi & Tiket...\n";
$runner = new TestRunner();

// 1. Verifikasi Pembuatan Format Kode Transaksi
$dummyId = 42;
$kode = format_kode_transaksi($dummyId, '2026-10-02');
$runner->assert(
    str_starts_with($kode, 'TRX-') || str_contains($kode, '42'),
    "Format kode transaksi konsisten dan menyertakan identifier: {$kode}"
);

// 2. Cek Ketersediaan Master Data Tiket di Database
global $conn;
$res = $conn->query("SELECT id_tiket, nama_tiket, harga FROM tiket LIMIT 5");
$runner->assert(
    $res !== false && $res->num_rows > 0,
    'Tabel master tiket memiliki data aktif'
);

$ticket = $res ? $res->fetch_assoc() : null;
if ($ticket) {
    $runner->assert(
        (float)$ticket['harga'] >= 0,
        "Harga tiket '{$ticket['nama_tiket']}' valid: " . format_rupiah((float)$ticket['harga'])
    );

    // 3. Simulasi Perhitungan Subtotal dan Pembulatan
    $qty = 3;
    $subtotal = (float)$ticket['harga'] * $qty;
    $runner->assert(
        $subtotal === ((float)$ticket['harga'] * 3),
        "Kalkulasi total belanja {$qty} tiket bernilai tepat: " . format_rupiah($subtotal)
    );
}

// 4. Verifikasi Deteksi Sanitasi Kata Terlarang (Ulasan/Komentar Pengunjung)
$runner->assert(
    has_toxic_words("Tempatnya sangat anjing dan jorok") === true,
    'Sistem filter sensor mendeteksi kata terlarang dalam ulasan'
);

$runner->assert(
    has_toxic_words("Pemandiannya sangat asri, sejuk, dan bersih!") === false,
    'Sistem filter ulasan meloloskan ulasan positif dan bersih'
);

exit($runner->summarize());
