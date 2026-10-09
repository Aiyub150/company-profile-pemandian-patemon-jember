<?php
/**
 * CLI Development Server Runner
 *
 * Usage:
 *   php serve.php
 *   php serve.php --port=8080
 *   php serve.php --host=0.0.0.0 --port=8080
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Akses ditolak: Skrip ini hanya dapat dijalankan melalui antarmuka baris perintah (CLI).");
}

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

// 4. Inisialisasi & Verifikasi Port 8001 (SMTP Mailpit / Mock Mailer)
if (!function_exists('is_port_listening')) {
    function is_port_listening($host, $port, $timeout = 0.4) {
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($fp) {
            fclose($fp);
            return true;
        }
        return false;
    }
}

$mailpitStartedByServe = false;
$mailStatus = "Tidak aktif";
$mailWebUrl = "";
$mailSmtpPort = 8001;
$mailWebPort = 8025;

if (is_port_listening('127.0.0.1', $mailSmtpPort)) {
    $mailStatus = "Aktif di 127.0.0.1:{$mailSmtpPort} (Sudah berjalan di sistem)";
    if (is_port_listening('127.0.0.1', $mailWebPort)) {
        $mailWebUrl = "http://localhost:{$mailWebPort}/";
    }
} else {
    // Cari binary mailpit di tools/mailpit atau sistem
    $mailpitBin = null;
    $possiblePaths = [
        __DIR__ . '/tools/mailpit/mailpit.exe',
        __DIR__ . '/tools/mailpit/mailpit',
    ];
    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_file($path)) {
            $mailpitBin = realpath($path);
            break;
        }
    }

    if ($mailpitBin) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $winBin = str_replace('/', '\\', $mailpitBin);
            $cmd = 'powershell -NoProfile -WindowStyle Hidden -Command "Start-Process -FilePath \'' . $winBin . '\' -ArgumentList \'--smtp 0.0.0.0:' . $mailSmtpPort . ' --listen 0.0.0.0:' . $mailWebPort . '\' -WindowStyle Hidden"';
            @shell_exec($cmd);
        } else {
            exec('"' . $mailpitBin . '" --smtp 0.0.0.0:' . $mailSmtpPort . ' --listen 0.0.0.0:' . $mailWebPort . ' > /dev/null 2>&1 &');
        }
        $mailpitStartedByServe = true;
        for ($i = 0; $i < 15; $i++) {
            usleep(150000);
            if (is_port_listening('127.0.0.1', $mailSmtpPort)) break;
        }
        if (is_port_listening('127.0.0.1', $mailSmtpPort)) {
            $mailStatus = "Mailpit Aktif di 127.0.0.1:{$mailSmtpPort}";
            $mailWebUrl = "http://localhost:{$mailWebPort}/";
        }
    } else {
        // Fallback: jalankan built-in PHP SMTP mock server
        $smtpScript = __DIR__ . '/dist/app/smtp_server.php';
        if (file_exists($smtpScript)) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $winScript = str_replace('/', '\\', $smtpScript);
                $cmd = 'powershell -NoProfile -WindowStyle Hidden -Command "Start-Process -FilePath \'php\' -ArgumentList \'\'' . $winScript . '\'\' -WindowStyle Hidden"';
                @shell_exec($cmd);
            } else {
                exec('php "' . $smtpScript . '" > /dev/null 2>&1 &');
            }
            for ($i = 0; $i < 10; $i++) {
                usleep(150000);
                if (is_port_listening('127.0.0.1', $mailSmtpPort)) break;
            }
            if (is_port_listening('127.0.0.1', $mailSmtpPort)) {
                $mailStatus = "Patemon Standalone SMTP Aktif di 127.0.0.1:{$mailSmtpPort}";
            }
        }
    }
}

// Cleanup Mailpit saat serve.php dihentikan
register_shutdown_function(function() use ($mailpitStartedByServe) {
    if ($mailpitStartedByServe) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @shell_exec('cmd.exe /c "taskkill /F /IM mailpit.exe 2>nul"');
        } else {
            @shell_exec('pkill -f mailpit 2>/dev/null');
        }
    }
});

// 5. Detect LAN IP Addresses
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

// 6. Display Banner
echo "\n";
echo "====================================================================\n";
echo "    COMPANY PROFILE & APLIKASI KASIR PEMANDIAN PATEMON              \n";
echo "====================================================================\n";
echo " [Database]    : " . $dbStatus . "\n";
echo " [Mailpit SMTP]: Port {$mailSmtpPort} -> " . $mailStatus . "\n";
if (!empty($mailWebUrl)) {
    echo " [Mailpit Web] : Port {$mailWebPort} -> {$mailWebUrl} (Web UI Inbox Email)\n";
    if ($host === '0.0.0.0' && !empty($lanIps)) {
        foreach ($lanIps as $lanIp) {
            echo "                 http://{$lanIp}:{$mailWebPort}/ (Akses Web UI dari HP/Laptop)\n";
        }
    }
}
echo "--------------------------------------------------------------------\n";
echo " [Web Server]  : Port {$port} (PHP Built-in Server Berjalan!)\n";
echo " - Local URL   : http://localhost:{$port}/\n";
if ($host === '0.0.0.0' && !empty($lanIps)) {
    foreach ($lanIps as $lanIp) {
        echo " - Network URL : http://{$lanIp}:{$port}/ (Bisa diakses dari HP/Laptop lain)\n";
    }
} elseif ($host !== '127.0.0.1' && $host !== '0.0.0.0') {
    echo " - Network URL : http://{$host}:{$port}/\n";
}
echo " [Keamanan]    : Kredensial akun dilindungi (SEC-02). Gunakan akun terdaftar untuk masuk.\n";
echo " Tekan Ctrl + C di terminal ini untuk mematikan server.\n";
echo "====================================================================\n\n";

// 7. Launch Server
$command = sprintf(
    'php -S %s:%d router.php',
    escapeshellarg($host),
    $port
);

passthru($command);
