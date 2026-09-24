<?php
/**
 * CLI Development Server Runner
 *
 * Usage:
 *   php serve.php
 *   php serve.php --port=8080
 *   php serve.php --host=0.0.0.0 --port=8080
 */

// 1. Parse CLI Arguments
$options = getopt("h:p:", ["host:", "port:"]);
$host = $options['host'] ?? $options['h'] ?? '0.0.0.0';
$port = (int)($options['port'] ?? $options['p'] ?? 8000);

if ($port <= 0 || $port > 65535) {
    echo "Error: Port tidak valid ($port). Port harus berada di rentang 1 - 65535.\n";
    exit(1);
}

// 2. Load Environment Variables if .env exists
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
        }
    }
}

$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';
$dbName = $_ENV['DB_NAME'] ?? 'pemandian';
$dbPort = (int)($_ENV['DB_PORT'] ?? 3306);

// 3. Check & Auto-Migrate Database
$dbStatus = "Menghubungkan ke MySQL...";
$dbConnected = false;

try {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($dbHost, $dbUser, $dbPass, "", $dbPort);

    if ($conn->connect_error) {
        $dbStatus = "GAGAL TERHUBUNG (" . $conn->connect_error . ")\n    Pastikan MySQL di XAMPP Control Panel sudah aktif!";
    } else {
        // Cek database
        $checkDb = $conn->query("SHOW DATABASES LIKE '$dbName'");
        if ($checkDb && $checkDb->num_rows === 0) {
            $conn->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->select_db($dbName);

            $sqlFile = __DIR__ . '/database/pemandian.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                $conn->multi_query($sqlContent);
                while ($conn->next_result()) { /* flush results */ }
                $dbStatus = "Database `$dbName` berhasil dibuat & dimigrasi otomatis!";
            } else {
                $dbStatus = "Database `$dbName` berhasil dibuat (file SQL tidak ditemukan).";
            }
        } else {
            $conn->select_db($dbName);
            // Cek apakah tabel users ada
            $checkTable = $conn->query("SHOW TABLES LIKE 'users'");
            if ($checkTable && $checkTable->num_rows === 0) {
                $sqlFile = __DIR__ . '/database/pemandian.sql';
                if (file_exists($sqlFile)) {
                    $sqlContent = file_get_contents($sqlFile);
                    $conn->multi_query($sqlContent);
                    while ($conn->next_result()) { /* flush results */ }
                    $dbStatus = "Tabel database dimigrasi otomatis dari `database/pemandian.sql`!";
                } else {
                    $dbStatus = "Tabel database belum ada. Silakan import `database/pemandian.sql`.";
                }
            } else {
                $dbStatus = "Terhubung OK ke `$dbName` di $dbHost:$dbPort";
            }
        }
        $dbConnected = true;
        $conn->close();
    }
} catch (Throwable $e) {
    $dbStatus = "Error: " . $e->getMessage();
}

// 4. Detect LAN IP Addresses
$lanIps = [];
$hostname = gethostname();
$hostIps = @gethostbynamel($hostname);
if (is_array($hostIps)) {
    foreach ($hostIps as $ip) {
        if ($ip !== '127.0.0.1' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $lanIps[] = $ip;
        }
    }
}

// 5. Display Banner
echo "\n";
echo "====================================================================\n";
echo "    SISTEM KASIR & PORTOFOLIO PEMANDIAN PATEMON                    \n";
echo "====================================================================\n";
echo " [Database] : " . $dbStatus . "\n";
echo "--------------------------------------------------------------------\n";
echo " [Server]   : PHP Built-in Server Berjalan!\n";
echo " - Local    : http://localhost:{$port}/\n";
if ($host === '0.0.0.0' && !empty($lanIps)) {
    foreach ($lanIps as $lanIp) {
        echo " - Network  : http://{$lanIp}:{$port}/ (Bisa diakses dari HP/Laptop lain)\n";
    }
} elseif ($host !== '127.0.0.1' && $host !== '0.0.0.0') {
    echo " - Network  : http://{$host}:{$port}/\n";
}
echo "--------------------------------------------------------------------\n";
echo " [Info Akun Demo]:\n";
echo " - Admin Loket : username = admin  | password = admin123\n";
echo " - Staf Kasir  : username = staff  | password = staff123\n";
echo "--------------------------------------------------------------------\n";
echo " Tekan Ctrl + C di terminal ini untuk mematikan server.\n";
echo "====================================================================\n\n";

// 6. Launch Server
$command = sprintf(
    'php -S %s:%d router.php',
    escapeshellarg($host),
    $port
);

passthru($command);
