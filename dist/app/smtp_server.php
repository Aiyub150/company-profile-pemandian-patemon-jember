<?php
/**
 * Patemon Standalone Built-in Mock SMTP Server (Fallback Mailpit)
 * 
 * Port: 8001 (SMTP)
 * Protokol: RFC 5321 (EHLO/HELO, MAIL FROM, RCPT TO, DATA, QUIT)
 * Digunakan secara otomatis saat serve.php berjalan jika mailpit.exe eksternal tidak aktif.
 */

set_time_limit(0);
date_default_timezone_set('Asia/Jakarta');

$host = '0.0.0.0';
$port = 8001;

$storageDir = __DIR__ . '/mail_inbox';
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0755, true);
}
$messagesFile = $storageDir . '/messages.json';

$socket = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
if (!$socket) {
    fwrite(STDERR, "[SMTP Server] Gagal bind ke {$host}:{$port} - {$errstr} ({$errno})\n");
    exit(1);
}

echo "[SMTP Server] Berjalan di {$host}:{$port}...\n";

while ($conn = @stream_socket_accept($socket, -1)) {
    stream_set_timeout($conn, 10);
    fwrite($conn, "220 mailpit.local Patemon Mock ESMTP Server Ready\r\n");

    $mailData = [
        'id'         => uniqid('mail_', true),
        'timestamp'  => date('Y-m-d H:i:s'),
        'from'       => '',
        'to'         => [],
        'data'       => '',
        'subject'    => '',
    ];

    $inDataMode = false;
    $rawBody = '';

    while (!feof($conn)) {
        $line = fgets($conn, 4096);
        if ($line === false) break;

        if ($inDataMode) {
            if (rtrim($line, "\r\n") === '.') {
                $inDataMode = false;
                $mailData['data'] = $rawBody;

                // Ekstrak Subject sederhana
                if (preg_match('/^Subject:\s*(.+)$/mi', $rawBody, $subMatches)) {
                    $mailData['subject'] = trim($subMatches[1]);
                }

                // Simpan ke storage messages.json
                $existing = [];
                if (file_exists($messagesFile)) {
                    $jsonContent = @file_get_contents($messagesFile);
                    if ($jsonContent) {
                        $existing = json_decode($jsonContent, true) ?: [];
                    }
                }
                array_unshift($existing, $mailData);
                if (count($existing) > 100) {
                    $existing = array_slice($existing, 0, 100);
                }
                @file_put_contents($messagesFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                fwrite($conn, "250 2.0.0 Ok: queued as " . $mailData['id'] . "\r\n");
            } else {
                // Tangani dot-stuffing
                if (str_starts_with($line, '..')) {
                    $line = substr($line, 1);
                }
                $rawBody .= $line;
            }
            continue;
        }

        $trimmed = trim($line);
        $upper = strtoupper($trimmed);

        if (str_starts_with($upper, 'EHLO') || str_starts_with($upper, 'HELO')) {
            fwrite($conn, "250-mailpit.local\r\n250-PIPELINING\r\n250-SIZE 10485760\r\n250 8BITMIME\r\n");
        } elseif (str_starts_with($upper, 'MAIL FROM:')) {
            $mailData['from'] = trim(substr($trimmed, 10), '<> ');
            fwrite($conn, "250 2.1.0 Sender Ok\r\n");
        } elseif (str_starts_with($upper, 'RCPT TO:')) {
            $rcpt = trim(substr($trimmed, 8), '<> ');
            $mailData['to'][] = $rcpt;
            fwrite($conn, "250 2.1.5 Recipient Ok\r\n");
        } elseif ($upper === 'DATA') {
            $inDataMode = true;
            $rawBody = '';
            fwrite($conn, "354 Start mail input; end with <CRLF>.<CRLF>\r\n");
        } elseif ($upper === 'RSET') {
            $mailData['from'] = '';
            $mailData['to'] = [];
            $rawBody = '';
            $inDataMode = false;
            fwrite($conn, "250 2.0.0 Reset state Ok\r\n");
        } elseif ($upper === 'NOOP') {
            fwrite($conn, "250 2.0.0 Ok\r\n");
        } elseif ($upper === 'QUIT') {
            fwrite($conn, "221 2.0.0 Bye\r\n");
            fclose($conn);
            break;
        } else {
            fwrite($conn, "500 5.5.1 Unrecognized command\r\n");
        }
    }

    if (is_resource($conn)) {
        @fclose($conn);
    }
}

if (is_resource($socket)) {
    @fclose($socket);
}
