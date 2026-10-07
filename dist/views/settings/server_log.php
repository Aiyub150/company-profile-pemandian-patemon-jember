<?php
/**
 * Modul Log Server & HTTP Traffic Monitor
 * Role: Khusus Super Admin (Level 1)
 * Pemandian Patemon
 */
require_once __DIR__ . '/../../app/config.php';
check_auth([1]);

$active_menu     = 'settings_server_log';
$page_title      = 'Log Server & Trafik HTTP - Pengaturan Sistem';
$page_heading    = 'Log Server & Trafik Jaringan';
$page_subheading = 'Pemantauan real-time aktivitas permintaan HTTP, respons status server, dan latency.';

$csrf_token = get_csrf_token();
$msg_success = '';
$msg_error   = '';

// Handle Clear Logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg_error = 'Token keamanan tidak valid atau telah kadaluarsa.';
    } elseif ($_POST['action'] === 'clear') {
        $conn->query("TRUNCATE TABLE server_logs");
        log_activity('server_logs', 'hapus', "Membersihkan seluruh riwayat log server.");
        $msg_success = 'Seluruh riwayat log server berhasil dibersihkan.';
    } elseif ($_POST['action'] === 'clean_orphans') {
        $clean_res = clean_orphaned_media_storage($conn);
        $freed_kb = round($clean_res['freed_bytes'] / 1024, 2);
        log_activity('server_logs', 'cleanup', "Membersihkan {$clean_res['deleted_count']} berkas media sampah fisik (membebaskan {$freed_kb} KB penyimpanan).");
        $msg_success = "Pembersihan selesai! {$clean_res['deleted_count']} berkas sampah berhasil dihapus dari server (membebaskan {$freed_kb} KB ruang penyimpanan).";
    } elseif ($_POST['action'] === 'save_email_config' || $_POST['action'] === 'save_and_test_email') {
        $mail_method = trim($_POST['mail_method'] ?? 'api');
        $api_provider = trim($_POST['email_api_provider'] ?? 'auto');
        $api_key = trim($_POST['email_api_key'] ?? '');

        $cfg_host = trim($_POST['smtp_host'] ?? '');
        $cfg_port = trim($_POST['smtp_port'] ?? '');
        $cfg_user = trim($_POST['smtp_user'] ?? '');
        $cfg_pass = str_replace(' ', '', trim($_POST['smtp_pass'] ?? ''));
        $cfg_from_email = trim($_POST['smtp_from_email'] ?? '');
        $cfg_from_name  = trim($_POST['smtp_from_name'] ?? '');

        set_setting('mail_method', $mail_method);
        set_setting('email_api_provider', $api_provider);
        if (!empty($api_key)) {
            set_setting('email_api_key', $api_key);
        }
        set_setting('smtp_host', $cfg_host);
        set_setting('smtp_port', $cfg_port);
        set_setting('smtp_user', $cfg_user);
        if (!empty($cfg_pass)) {
            set_setting('smtp_pass', $cfg_pass);
        }
        set_setting('smtp_from_email', $cfg_from_email);
        set_setting('smtp_from_name', $cfg_from_name);

        log_activity('server_logs', 'update_config', "Memperbarui konfigurasi metode pengiriman email sistem.");

        if ($_POST['action'] === 'save_and_test_email') {
            $test_target = trim($_POST['test_target_email'] ?? '');
            if (empty($test_target) || !filter_var($test_target, FILTER_VALIDATE_EMAIL)) {
                $msg_error = 'Konfigurasi disimpan, namun alamat email tujuan uji coba tidak valid.';
            } else {
                $t_subj = 'Tes Diagnostik Pengiriman Email - Pemandian Patemon';
                $t_body = '<div style="font-family:sans-serif;padding:24px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;">
                    <h3 style="color:#0284c7;margin-top:0;">Tes Pengiriman Berhasil!</h3>
                    <p>Email ini dikirim dari server VPS Cloudflare Tunnel sistem Wisata Pemandian Patemon pada ' . date('d M Y H:i:s') . ' WIB.</p>
                    <p style="color:#64748b;font-size:13px;">Jika Anda menerima email ini di kotak masuk, berarti konfigurasi pengiriman email sistem Anda telah bekerja 100%.</p>
                </div>';
                $test_res = send_email($test_target, 'Pengguna Uji', $t_subj, $t_body);
                if ($test_res['success']) {
                    $msg_success = "Konfigurasi berhasil disimpan & Uji kirim BERHASIL: " . $test_res['message'];
                } else {
                    $msg_error = "Konfigurasi berhasil disimpan, namun uji kirim GAGAL: " . $test_res['message'];
                }
            }
        } else {
            $msg_success = 'Konfigurasi Email berhasil disimpan ke pengaturan sistem database!';
        }
    } elseif ($_POST['action'] === 'test_email_dispatch') {
        $test_target = trim($_POST['test_target_email'] ?? '');
        $inline_pass = str_replace(' ', '', trim($_POST['smtp_pass'] ?? ''));
        if (!empty($inline_pass)) {
            set_setting('smtp_pass', $inline_pass);
        }
        $cur_pass = getenv('SMTP_PASS') ?: get_setting('smtp_pass', '');

        if (empty($cur_pass)) {
            $msg_error = 'Uji pengiriman GAGAL: Google App Password (16 huruf) belum disimpan di database. Masukkan Google App Password di kolom di bawah, lalu klik "Simpan & Uji Coba Kirim".';
        } elseif (empty($test_target) || !filter_var($test_target, FILTER_VALIDATE_EMAIL)) {
            $msg_error = 'Alamat email tujuan uji coba tidak valid.';
        } else {
            $t_subj = 'Tes Diagnostik Pengiriman Email - Pemandian Patemon';
            $t_body = '<div style="font-family:sans-serif;padding:24px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;">
                <h3 style="color:#0284c7;margin-top:0;">Tes Pengiriman Berhasil!</h3>
                <p>Email ini dikirim dari server pengujian sistem Wisata Pemandian Patemon pada ' . date('d M Y H:i:s') . ' WIB.</p>
                <p style="color:#64748b;font-size:13px;">Jika Anda menerima email ini di kotak masuk, berarti konfigurasi SMTP & pengiriman email sistem Anda telah bekerja 100%.</p>
            </div>';
            $test_res = send_email($test_target, 'Pengguna Uji', $t_subj, $t_body);
            if ($test_res['success']) {
                $msg_success = "Uji pengiriman BERHASIL: " . $test_res['message'];
            } else {
                $msg_error = "Uji pengiriman GAGAL: " . $test_res['message'];
            }
        }
    }
}

// Metrics
$stat_total = 0;
$stat_2xx   = 0;
$stat_4xx   = 0;
$stat_5xx   = 0;
$stat_avg_ms = 0;

$q_stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 ELSE 0 END) as cnt_2xx,
        SUM(CASE WHEN status_code >= 400 AND status_code < 500 THEN 1 ELSE 0 END) as cnt_4xx,
        SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) as cnt_5xx,
        AVG(response_time_ms) as avg_ms
    FROM server_logs
");
if ($q_stats) {
    $row_stats = $q_stats->fetch_assoc();
    $stat_total = (int)($row_stats['total'] ?? 0);
    $stat_2xx   = (int)($row_stats['cnt_2xx'] ?? 0);
    $stat_4xx   = (int)($row_stats['cnt_4xx'] ?? 0);
    $stat_5xx   = (int)($row_stats['cnt_5xx'] ?? 0);
    $stat_avg_ms = round((float)($row_stats['avg_ms'] ?? 0), 2);
}

// Filtering
$filter_method = trim($_GET['method'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 40;
$offset = ($page - 1) * $limit;

$where_clauses = [];
$params = [];
$types = '';

if (!empty($filter_method)) {
    $where_clauses[] = "method = ?";
    $params[] = strtoupper($filter_method);
    $types .= 's';
}

if (!empty($filter_status)) {
    if ($filter_status === '2xx') {
        $where_clauses[] = "status_code >= 200 AND status_code < 300";
    } elseif ($filter_status === '3xx') {
        $where_clauses[] = "status_code >= 300 AND status_code < 400";
    } elseif ($filter_status === '4xx') {
        $where_clauses[] = "status_code >= 400 AND status_code < 500";
    } elseif ($filter_status === '5xx') {
        $where_clauses[] = "status_code >= 500";
    } elseif (is_numeric($filter_status)) {
        $where_clauses[] = "status_code = ?";
        $params[] = (int)$filter_status;
        $types .= 'i';
    }
}

if (!empty($filter_search)) {
    $where_clauses[] = "(path LIKE ? OR ip_address LIKE ?)";
    $like = '%' . $filter_search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count Query
$count_query = "SELECT COUNT(*) as total FROM server_logs $where_sql";
if (!empty($params)) {
    $stmt_c = $conn->prepare($count_query);
    $stmt_c->bind_param($types, ...$params);
    $stmt_c->execute();
    $total_filtered = (int)($stmt_c->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt_c->close();
} else {
    $c_res = $conn->query($count_query);
    $total_filtered = (int)($c_res->fetch_assoc()['total'] ?? 0);
}

$total_pages = ceil($total_filtered / $limit);

// Data Query
$data_query = "SELECT id, ip_address, method, path, status_code, response_time_ms, user_agent, created_at FROM server_logs $where_sql ORDER BY id DESC LIMIT ? OFFSET ?";
$data_types = $types . 'ii';
$data_params = array_merge($params, [$limit, $offset]);

$stmt_d = $conn->prepare($data_query);
$stmt_d->bind_param($data_types, ...$data_params);
$stmt_d->execute();
$logs_result = $stmt_d->get_result();

$mailpit_web_url = getenv('MAILPIT_WEB_URL') ?: 'http://localhost:8025';
$smtp_host_cfg = getenv('SMTP_HOST') ?: get_setting('smtp_host', '127.0.0.1');
$smtp_port_cfg = (int)(getenv('SMTP_PORT') ?: get_setting('smtp_port', 8001));
$is_remote_env = !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8000']);

$header_actions = '
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-warning" style="border-radius: 10px;" onclick="confirmCleanOrphans()">
            <i class="fa-solid fa-trash-can me-1"></i> Bersihkan Berkas Sampah
        </button>
        <a href="' . route_url('settings_server_log') . '" class="btn btn-outline-primary" style="border-radius: 10px;" title="Refresh Log">
            <i class="fa-solid fa-arrows-rotate me-1"></i> Refresh
        </a>
        <button type="button" class="btn btn-outline-danger" style="border-radius: 10px;" onclick="confirmClearLogs()">
            <i class="fa-solid fa-broom me-1"></i> Bersihkan Log
        </button>
    </div>
';

require_once __DIR__ . '/../../app/layouts/admin_header.php';
?>

<!-- Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Total Request</span>
                    <span class="badge bg-primary-subtle text-primary rounded-pill p-2"><i class="fa-solid fa-server"></i></span>
                </div>
                <h3 class="fw-bold mb-1 text-primary"><?= number_format($stat_total) ?></h3>
                <div class="text-muted small">Permintaan terekam</div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Status 2xx (Sukses)</span>
                    <span class="badge bg-success-subtle text-success rounded-pill p-2"><i class="fa-solid fa-check"></i></span>
                </div>
                <h3 class="fw-bold mb-1 text-success"><?= number_format($stat_2xx) ?></h3>
                <div class="text-muted small"><?= $stat_total > 0 ? round(($stat_2xx / $stat_total) * 100, 1) : 0 ?>% dari total trafik</div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Status 4xx (Client Error)</span>
                    <span class="badge bg-warning-subtle text-warning rounded-pill p-2"><i class="fa-solid fa-triangle-exclamation"></i></span>
                </div>
                <h3 class="fw-bold mb-1 text-warning"><?= number_format($stat_4xx) ?></h3>
                <div class="text-muted small">Termasuk percobaan ilegal & 404</div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Rata-rata Respon</span>
                    <span class="badge bg-info-subtle text-info rounded-pill p-2"><i class="fa-solid fa-stopwatch"></i></span>
                </div>
                <h3 class="fw-bold mb-1 text-info"><?= $stat_avg_ms ?> <span class="fs-6 fw-normal text-muted">ms</span></h3>
                <div class="text-muted small">Kecepatan server rata-rata</div>
            </div>
        </div>
    </div>
</div>

<!-- Email Service Status Banner -->
<div class="alert alert-primary border-0 shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4" style="border-radius: 12px; background: rgba(14, 165, 233, 0.08); border-left: 4px solid #0284c7 !important;">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle p-2 bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
            <i class="fa-solid fa-paper-plane fs-5"></i>
        </div>
        <div>
            <div class="fw-bold text-dark d-flex align-items-center gap-2">
                <span>Layanan Email: Smart Mailer Terpadu (Gmail / SMTP / Direct)</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.72rem; font-weight: 500;">Direct Delivery Aktif</span>
                <span class="badge bg-info-subtle text-info border px-2 py-1" style="font-size: 0.72rem; font-weight: 500;">Mendukung Gmail SMTP</span>
            </div>
            <div class="small text-muted">
                Email aktivasi staf &amp; pemulihan kata sandi dikirim langsung ke alamat email tujuan (Gmail / domain institusi).
            </div>
        </div>
    </div>
</div>

<?php if (!empty($msg_success)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert" style="border-radius: 12px;">
        <i class="fa-solid fa-circle-check me-2"></i> <?= e($msg_success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($msg_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert" style="border-radius: 12px;">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= e($msg_error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php
$cur_method   = get_setting('mail_method', 'api');
$cur_api_key  = getenv('EMAIL_API_KEY') ?: (getenv('BREVO_API_KEY') ?: (getenv('RESEND_API_KEY') ?: get_setting('email_api_key', '')));
$cur_provider = get_setting('email_api_provider', 'auto');
$has_api_key  = !empty($cur_api_key);

$cur_pass     = getenv('SMTP_PASS') ?: get_setting('smtp_pass', '');
$has_pass     = !empty($cur_pass);
?>

<style>
.nat-callout-card {
    background: #e0f2fe !important;
    border: 1px solid #7dd3fc !important;
    border-radius: 12px;
    color: #0c4a6e !important;
}
.nat-callout-card .nat-callout-title {
    color: #0369a1 !important;
}
.nat-callout-card .nat-callout-text {
    color: #075985 !important;
    line-height: 1.6;
}
body.theme-dark .nat-callout-card {
    background: rgba(14, 165, 233, 0.14) !important;
    border-color: rgba(56, 189, 248, 0.35) !important;
    color: #e0f2fe !important;
}
body.theme-dark .nat-callout-card .nat-callout-title {
    color: #38bdf8 !important;
}
body.theme-dark .nat-callout-card .nat-callout-text {
    color: #bae6fd !important;
}
</style>

<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="fw-bold mb-1"><i class="fa-solid fa-envelope-circle-check text-primary me-2"></i>Konfigurasi &amp; Diagnostik Pengiriman Email Sistem</h5>
            <p class="text-muted small mb-0">Dukungan HTTPS REST API (bebas blokir port pada VPS NAT) serta opsi SMTP Relay klasik.</p>
        </div>
        <div>
            <?php if ($has_api_key): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2" style="font-size: 0.8rem;"><i class="fa-solid fa-cloud-bolt me-1"></i> HTTPS API Aktif (Anti-Blokir NAT)</span>
            <?php elseif ($has_pass): ?>
                <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2" style="font-size: 0.8rem;"><i class="fa-solid fa-key me-1"></i> Password SMTP Tersimpan</span>
            <?php else: ?>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2" style="font-size: 0.8rem;"><i class="fa-solid fa-triangle-exclamation me-1"></i> Kredensial Email Belum Dikonfigurasi</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body px-4 pb-4">
        <!-- Callout Rekomendasi VPS NAT (Kontras Tinggi Mode Terang & Gelap) -->
        <div class="d-flex align-items-start gap-3 p-3 mb-4 nat-callout-card">
            <i class="fa-solid fa-circle-info fs-5 mt-1 text-primary flex-shrink-0"></i>
            <div class="small nat-callout-text">
                <strong class="nat-callout-title">Catatan Khusus VPS NAT:</strong> Pada VPS NAT, seluruh port SMTP (25, 465, 587) diblokir permanen oleh router hosting untuk mencegah spam IP bersama. 
                Gunakan <strong>Metode HTTPS API (Port 443)</strong> menggunakan <strong>Brevo</strong> (gratis 300 email/hari) atau <strong>Resend</strong> (gratis 3.000 email/bln). Lalu lintas HTTPS dijamin 100% tembus tanpa pernah terkena <em>connection timed out</em>.
            </div>
        </div>

        <form action="" method="POST" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Pilihan Metode Pengiriman -->
            <div class="col-12 col-md-6">
                <label class="form-label small fw-bold">Jalur Pengiriman (Delivery Method)</label>
                <select name="mail_method" id="mailMethodSelect" class="form-select form-select-sm fw-semibold">
                    <option value="api" <?= $cur_method === 'api' ? 'selected' : '' ?>>🌐 HTTPS REST API (Port 443 - Bebas Blokir VPS NAT)</option>
                    <option value="smtp" <?= $cur_method === 'smtp' ? 'selected' : '' ?>>🔌 SMTP Socket (Port 465/587 - Gmail Relay)</option>
                </select>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label small fw-bold">Provider API</label>
                <select name="email_api_provider" class="form-select form-select-sm">
                    <option value="auto" <?= $cur_provider === 'auto' ? 'selected' : '' ?>>⚡ Deteksi Otomatis (Brevo / Resend)</option>
                    <option value="brevo" <?= $cur_provider === 'brevo' ? 'selected' : '' ?>>Brevo (Sendinblue) - Bebas Kirim ke Email Manapun</option>
                    <option value="resend" <?= $cur_provider === 'resend' ? 'selected' : '' ?>>Resend - Pengiriman Cepat Modern</option>
                </select>
            </div>

            <!-- API Key Section -->
            <div class="col-12">
                <label class="form-label small fw-bold">API Key (Brevo / Resend)</label>
                <input type="text" name="email_api_key" class="form-control form-control-sm font-monospace" placeholder="<?= $has_api_key ? '•••••••••••••••• (Ketik baru jika ingin mengganti API Key)' : 'Contoh: xkeysib-xxxxxxxxxx... atau re_xxxxxxxxxx...' ?>" autocomplete="off">
                <div class="form-text text-muted d-flex flex-wrap gap-3 mt-1" style="font-size: 0.75rem;">
                    <span><i class="fa-solid fa-arrow-up-right-from-square text-primary me-1"></i>Daftar Brevo Gratis: <a href="https://www.brevo.com" target="_blank" class="fw-semibold text-decoration-none">brevo.com</a> (Menu SMTP &amp; API &rarr; Generate Key)</span>
                    <span><i class="fa-solid fa-arrow-up-right-from-square text-primary me-1"></i>Daftar Resend Gratis: <a href="https://resend.com" target="_blank" class="fw-semibold text-decoration-none">resend.com</a> (Menu API Keys)</span>
                </div>
            </div>

            <!-- Nama & Akun Pengirim -->
            <div class="col-12 col-md-6">
                <label class="form-label small fw-bold">Alamat Email Pengirim</label>
                <input type="email" name="smtp_from_email" class="form-control form-control-sm" value="<?= e(get_setting('smtp_from_email', get_setting('smtp_user', 'aiyubheriyanto150@gmail.com'))) ?>" placeholder="email@gmail.com">
                <div class="form-text text-muted" style="font-size: 0.72rem;">Email yang Anda gunakan saat mendaftar di Brevo / Resend.</div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-bold">Nama Pengirim Resmi</label>
                <input type="text" name="smtp_from_name" class="form-control form-control-sm" value="<?= e(get_setting('smtp_from_name', 'Wisata Pemandian Patemon')) ?>" placeholder="Wisata Pemandian Patemon">
            </div>

            <!-- Bagian Cadangan SMTP Klasik (Dapat dibuka jika diperlukan) -->
            <div class="col-12">
                <div class="p-3 rounded-3 border" style="background: rgba(148, 163, 184, 0.06);">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-body-secondary"><i class="fa-solid fa-sliders me-1"></i> Opsi Cadangan SMTP Socket (Jika Menggunakan Port SMTP)</span>
                    </div>
                    <div class="row g-2">
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-body-secondary mb-1">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control form-control-sm" value="<?= e(get_setting('smtp_host', 'smtp.gmail.com')) ?>" placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-body-secondary mb-1">Port &amp; Enkripsi</label>
                            <select name="smtp_port" class="form-select form-select-sm">
                                <option value="465" <?= (string)get_setting('smtp_port', '465') === '465' ? 'selected' : '' ?>>465 (SSL)</option>
                                <option value="587" <?= (string)get_setting('smtp_port', '465') === '587' ? 'selected' : '' ?>>587 (TLS)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-body-secondary mb-1">Akun Gmail SMTP</label>
                            <input type="email" name="smtp_user" class="form-control form-control-sm" value="<?= e(get_setting('smtp_user', 'aiyubheriyanto150@gmail.com')) ?>" placeholder="email@gmail.com">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-body-secondary mb-1">Google App Password</label>
                            <input type="text" name="smtp_pass" class="form-control form-control-sm" placeholder="<?= $has_pass ? '••••••••••••••••' : 'App Password 16 Huruf' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email Tujuan Uji Coba -->
            <div class="col-12">
                <label class="form-label small fw-bold">Email Tujuan Uji Coba Diagnostik</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-at text-muted"></i></span>
                    <input type="email" name="test_target_email" class="form-control form-control-sm" placeholder="aiyubheriyanto005@gmail.com" value="aiyubheriyanto005@gmail.com">
                </div>
                <div class="form-text text-muted" style="font-size: 0.72rem;">Email yang akan menerima surat uji coba saat tombol kirim ditekan.</div>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 justify-content-end mt-4">
                <button type="submit" name="action" value="save_email_config" class="btn btn-outline-primary btn-sm px-3">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Saja ke DB
                </button>
                <button type="submit" name="action" value="save_and_test_email" class="btn btn-success btn-sm px-4 fw-semibold shadow-sm">
                    <i class="fa-solid fa-paper-plane me-1"></i> Simpan &amp; Uji Coba Kirim Langsung
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Filter & Log Table -->
<div class="card border-0 shadow-sm" style="border-radius: 16px;">
    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-3">
        <form action="" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Cari path URI atau IP..." value="<?= e($filter_search) ?>">
                </div>
            </div>
            
            <div class="col-6 col-md-2">
                <select name="method" class="form-select">
                    <option value="">Semua Method</option>
                    <option value="GET" <?= $filter_method === 'GET' ? 'selected' : '' ?>>GET</option>
                    <option value="POST" <?= $filter_method === 'POST' ? 'selected' : '' ?>>POST</option>
                    <option value="HEAD" <?= $filter_method === 'HEAD' ? 'selected' : '' ?>>HEAD</option>
                </select>
            </div>
            
            <div class="col-6 col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status Kode</option>
                    <option value="2xx" <?= $filter_status === '2xx' ? 'selected' : '' ?>>2xx (Berhasil)</option>
                    <option value="3xx" <?= $filter_status === '3xx' ? 'selected' : '' ?>>3xx (Redirect)</option>
                    <option value="4xx" <?= $filter_status === '4xx' ? 'selected' : '' ?>>4xx (Client Error / 403 / 404)</option>
                    <option value="5xx" <?= $filter_status === '5xx' ? 'selected' : '' ?>>5xx (Server Error)</option>
                    <option value="200" <?= $filter_status === '200' ? 'selected' : '' ?>>200 OK</option>
                    <option value="302" <?= $filter_status === '302' ? 'selected' : '' ?>>302 Found</option>
                    <option value="403" <?= $filter_status === '403' ? 'selected' : '' ?>>403 Forbidden</option>
                    <option value="404" <?= $filter_status === '404' ? 'selected' : '' ?>>404 Not Found</option>
                </select>
            </div>
            
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1" style="border-radius: 10px;">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <?php if (!empty($filter_method) || !empty($filter_status) || !empty($filter_search)): ?>
                    <a href="<?= route_url('settings_server_log') ?>" class="btn btn-outline-secondary" style="border-radius: 10px;">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4" style="width: 85px;">Method</th>
                        <th style="width: 90px;">Status</th>
                        <th>Path URI Permintaan</th>
                        <th style="width: 140px;">Alamat IP</th>
                        <th style="width: 100px;">Latency</th>
                        <th style="width: 180px;">Waktu Request</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs_result && $logs_result->num_rows > 0): ?>
                        <?php while ($log = $logs_result->fetch_assoc()): 
                            $method = strtoupper($log['method']);
                            $method_badge = 'bg-secondary';
                            if ($method === 'GET') $method_badge = 'bg-primary-subtle text-primary border border-primary-subtle';
                            elseif ($method === 'POST') $method_badge = 'bg-success-subtle text-success border border-success-subtle';
                            elseif ($method === 'DELETE') $method_badge = 'bg-danger-subtle text-danger border border-danger-subtle';
                            
                            $code = (int)$log['status_code'];
                            $code_badge = 'bg-secondary';
                            if ($code >= 200 && $code < 300) $code_badge = 'badge-modern badge-modern-success';
                            elseif ($code >= 300 && $code < 400) $code_badge = 'badge-modern badge-modern-info';
                            elseif ($code >= 400 && $code < 500) $code_badge = 'badge-modern badge-modern-warning';
                            elseif ($code >= 500) $code_badge = 'badge-modern badge-modern-danger';
                        ?>
                        <tr>
                            <td class="ps-4">
                                <span class="badge <?= $method_badge ?> font-monospace fw-bold px-2 py-1" style="font-size: 0.75rem; border-radius: 6px;">
                                    <?= e($method) ?>
                                </span>
                            </td>
                            <td>
                                <span class="<?= $code_badge ?>" style="font-size: 0.775rem;">
                                    <?= $code ?>
                                </span>
                            </td>
                            <td>
                                <div class="font-monospace text-truncate text-break" style="max-width: 480px;" title="<?= e($log['path']) ?>">
                                    <?= e($log['path']) ?>
                                </div>
                                <?php if (!empty($log['user_agent'])): ?>
                                    <div class="text-muted small text-truncate" style="max-width: 480px; font-size: 0.75rem;" title="<?= e($log['user_agent']) ?>">
                                        <?= e(substr($log['user_agent'], 0, 70)) . (strlen($log['user_agent']) > 70 ? '...' : '') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="font-monospace small text-muted">
                                    <i class="fa-solid fa-network-wired me-1 opacity-50"></i> <?= e($log['ip_address'] === '::1' ? '127.0.0.1' : $log['ip_address']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.75rem;">
                                    <?= round((float)$log['response_time_ms'], 1) ?> ms
                                </span>
                            </td>
                            <td class="text-muted small">
                                <?= date('d M Y H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-network-wired fs-2 text-secondary mb-2 d-block"></i>
                                Tidak ada catatan log server yang sesuai dengan filter.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <div class="card-footer bg-transparent border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="text-muted small">Menampilkan <?= min($total_filtered, $offset + 1) ?> - <?= min($total_filtered, $offset + $limit) ?> dari <?= $total_filtered ?> log</span>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php 
                $start_p = max(1, $page - 3);
                $end_p   = min($total_pages, $page + 3);
                $query_params = $_GET;
                ?>
                <?php if ($start_p > 1): ?>
                    <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($query_params, ['page' => 1])) ?>">1</a></li>
                    <?php if ($start_p > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_p; $i <= $end_p; $i++): ?>
                    <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($query_params, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($end_p < $total_pages): ?>
                    <?php if ($end_p < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($query_params, ['page' => $total_pages])) ?>"><?= $total_pages ?></a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Clear Log Form -->
<form id="clearLogForm" action="" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
    <input type="hidden" name="action" value="clear">
</form>

<!-- Clean Orphan Files Form -->
<form id="cleanOrphanForm" action="" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
    <input type="hidden" name="action" value="clean_orphans">
</form>

<?php
$extra_js = '
<script>
function confirmCleanOrphans() {
    Swal.fire({
        title: "Bersihkan Berkas Sampah?",
        text: "Sistem akan memindai folder server dan menghapus berkas flyer/bukti bayar yang datanya sudah tidak ada di database.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#f59e0b",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Bersihkan!",
        cancelButtonText: "Batal",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById("cleanOrphanForm").submit();
        }
    });
}

function confirmClearLogs() {
    Swal.fire({
        title: "Bersihkan Seluruh Log Server?",
        text: "Semua riwayat trafik HTTP yang tersimpan di database akan dihapus permanen.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Bersihkan!",
        cancelButtonText: "Batal",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById("clearLogForm").submit();
        }
    });
}
</script>
';

require_once __DIR__ . '/../../app/layouts/admin_footer.php';
?>
