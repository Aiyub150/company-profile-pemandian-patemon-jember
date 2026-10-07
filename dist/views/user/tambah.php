<?php
require_once __DIR__ . '/../../app/config.php';
// Hak Akses: Khusus Super Admin (Level 1)
check_auth([1], route_url('dashboard'));

$curr_login_lvl = (int)($_SESSION['level'] ?? 0);
$active_menu = 'user';
$base_view = '..';

$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Token keamanan sesi kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $nama = trim($_POST["nama"] ?? '');
        $username = trim($_POST["username"] ?? '');
        $password = $_POST["password"] ?? '';
        $email = trim($_POST["email"] ?? '');
        $no_telepon = trim($_POST["no_telepon"] ?? '');
        $level = (int)($_POST["level"] ?? 0);
        if ($curr_login_lvl !== 1 && $level === 1) {
            $level = 2; // Paksa admin level 2 tidak bisa membuat superadmin level 1
        }

        if (empty($nama) || empty($username) || empty($password) || empty($email)) {
            $error_msg = "Nama, Username, Password, dan Email wajib diisi.";
        } elseif (mb_strlen($nama) > 50) {
            $error_msg = "Nama lengkap maksimal 50 karakter.";
        } elseif (!preg_match("/^[a-zA-Z\s\.\']+$/", $nama)) {
            $error_msg = "Nama lengkap hanya boleh berisi huruf, spasi, titik, atau tanda petik.";
        } elseif (mb_strlen($username) > 30) {
            $error_msg = "Username maksimal 30 karakter.";
        } elseif (!preg_match("/^[a-zA-Z0-9_\.]+$/", $username)) {
            $error_msg = "Username hanya boleh berisi huruf, angka, garis bawah (_), atau titik (.).";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = "Format email tidak valid.";
        } elseif (!empty($no_telepon) && !preg_match('/^0[0-9]{8,14}$/', $no_telepon)) {
        } elseif (has_toxic_words($nama) || has_toxic_words($username)) {
            $toxicHits = array_merge(find_toxic_words($nama), find_toxic_words($username));
            $error_msg = "Gagal: Nama atau username memuat kata yang dilarang (" . e(implode(', ', array_unique($toxicHits))) . "). Harap gunakan bahasa yang sopan.";
        } else {
            // Cek duplikasi username / email
            $chk = $conn->prepare("SELECT id_user FROM users WHERE username = ? OR email = ? LIMIT 1");
            $chk->bind_param("ss", $username, $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error_msg = "Username atau email tersebut sudah digunakan pengguna lain.";
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);

                // Feedback-9 Poin 6: Staf baru dimulai dengan status belum aktif dan dikirimi email aktivasi
                $is_active = 0;
                $activation_token = bin2hex(random_bytes(32));
                $activation_expires_at = date('Y-m-d H:i:s', time() + (72 * 3600)); // 3 hari

                $stmt = $conn->prepare("INSERT INTO users (nama, username, password, email, no_telepon, level, is_active, activation_token, activation_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssiiss", $nama, $username, $hashed, $email, $no_telepon, $level, $is_active, $activation_token, $activation_expires_at);
                if ($stmt->execute()) {
                    $newId = $stmt->insert_id;
                    if (function_exists('log_activity')) {
                        log_activity('TAMBAH', 'user', "Menambahkan user baru ID #{$newId}: {$username} (Role: " . get_role_name($level) . ", Status: Menunggu Aktivasi)");
                    }

                    // Kirim email aktivasi ke staf baru via SMTP Mailpit (menggunakan Absolute URL ke Port Web Aplikasi Utama)
                    $activation_url = route_url('activate', ['token' => $activation_token], true);
                    $subject = "🎉 [Pemandian Patemon] Aktivasi Akun Staf Baru (@" . $username . ")";
                    $htmlBody = '
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="utf-8">
                        <style>
                            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px; }
                            .card { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
                            .header { background: linear-gradient(135deg, #0ea5e9, #0284c7); padding: 28px; text-align: center; color: #ffffff; }
                            .body { padding: 32px 28px; line-height: 1.6; font-size: 15px; }
                            .btn { display: inline-block; background-color: #0284c7; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; margin-top: 16px; margin-bottom: 20px; }
                            .note { background-color: #f0f9ff; border-left: 4px solid #0284c7; padding: 12px 16px; font-size: 13px; color: #0369a1; border-radius: 0 6px 6px 0; margin-top: 20px; }
                            .footer { padding: 16px 28px; background-color: #f1f5f9; text-align: center; font-size: 12px; color: #64748b; }
                        </style>
                    </head>
                    <body>
                        <div class="card">
                            <div class="header">
                                <h2 style="margin: 0; font-size: 20px;">Wisata Pemandian Patemon Jember</h2>
                                <p style="margin: 6px 0 0; opacity: 0.9; font-size: 14px;">Undangan Aktivasi Akun Staf Baru</p>
                            </div>
                            <div class="body">
                                <p>Halo <strong>' . htmlspecialchars($nama ?: $username, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
                                <p>Selamat datang di tim Wisata Pemandian Patemon Tanggul, Jember. Super Administrator telah mendaftarkan akun staf Anda di sistem dengan username <code>' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</code>.</p>
                                <p>Untuk mengaktifkan akun dan membuat kata sandi Anda sendiri, silakan klik tautan aktivasi berikut:</p>
                                <div style="text-align: center;">
                                    <a href="' . $activation_url . '" class="btn">Aktivasi Akun & Buat Kata Sandi</a>
                                </div>
                                <div class="note">
                                    <strong>Perhatian:</strong><br>
                                    Tautan ini berlaku selama <strong>3 hari (72 jam)</strong> hingga <strong>' . date('d M Y H:i', strtotime($activation_expires_at)) . ' WIB</strong>. Jangan berikan tautan ini kepada orang lain demi keamanan akun Anda.
                                </div>
                                <p style="margin-top: 20px; font-size: 13px; color: #64748b;">
                                    Jika tombol di atas tidak berfungsi, salin tautan berikut ke peramban web:<br>
                                    <a href="' . $activation_url . '" style="color: #0284c7; word-break: break-all;">' . $activation_url . '</a>
                                </p>
                            </div>
                            <div class="footer">
                                &copy; ' . date('Y') . ' UPTD Pariwisata Pemandian Patemon Tanggul, Jember.
                            </div>
                        </div>
                    </body>
                    </html>
                    ';
                    $plainBody = "Halo " . ($nama ?: $username) . ",\n\n"
                        . "Selamat datang di Pemandian Patemon. Administrator telah mendaftarkan akun Anda (@{$username}).\n"
                        . "Silakan klik tautan berikut untuk mengaktifkan akun dan membuat kata sandi Anda:\n"
                        . $activation_url . "\n\n"
                        . "Tautan berlaku hingga: " . date('d M Y H:i', strtotime($activation_expires_at)) . " WIB.\n";

                    $mailRes = send_smtp_email($email, $nama ?: $username, $subject, $htmlBody, $plainBody);
                    if ($mailRes['success']) {
                        $_SESSION['flash_success'] = "Pengguna baru <strong>@" . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "</strong> berhasil ditambahkan dan email aktivasi telah dikirimkan ke <strong>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</strong>.";
                    } else {
                        $_SESSION['flash_warning'] = "Pengguna baru <strong>@" . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "</strong> berhasil ditambahkan (Belum Aktif). Super Admin dapat mengaktifkannya secara langsung melalui tombol Aktifkan Langsung di tabel pengguna.";
                    }

                    header("Location: " . route_url('users'));
                    exit();
                } else {
                    error_log("Insert user error: " . $stmt->error);
                    $error_msg = "Gagal menyimpan pengguna baru. Silakan periksa kembali format data yang dimasukkan.";
                }
                $stmt->close();
            }
            $chk->close();
        }
    }
}

$page_title      = 'Tambah Pengguna Baru - Pemandian Patemon';
$page_heading    = 'Tambah Pengguna Baru';
$page_subheading = 'Buat akun untuk administrator, staf kasir loket, atau pengunjung.';
$header_actions  = '
    <a href="' . route_url('users') . '" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar User
    </a>
';

require '../../app/layouts/admin_header.php';
?>

<div class="page-content">
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="modern-card">
                <div class="modern-card-header">
                    <span class="fw-bold text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i> Formulir Akun Pengguna</span>
                </div>
                <div class="modern-card-body">
                    <?php if (!empty($error_msg)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                            <i class="fa-solid fa-circle-exclamation fs-5"></i>
                            <div><?= e($error_msg) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="nama" class="form-label fw-semibold text-secondary small">Nama Lengkap <span class="text-danger">*</span></label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-id-card input-icon"></i>
                                    <input type="text" id="nama" name="nama" class="form-control-modern" placeholder="Nama Lengkap (maks. 50 karakter)" required maxlength="50" pattern="^[a-zA-Z\s\.\']+$" title="Nama hanya boleh berisi huruf, spasi, titik, atau tanda petik (maksimal 50 karakter)" value="<?= e($_POST['nama'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="username" class="form-label fw-semibold text-secondary small">Username <span class="text-danger">*</span></label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-user input-icon"></i>
                                    <input type="text" id="username" name="username" class="form-control-modern" placeholder="Username unik (maks. 30 karakter)" required maxlength="30" pattern="^[a-zA-Z0-9_\.]+$" title="Username hanya boleh berisi huruf, angka, underscore, atau titik (maksimal 30 karakter)" value="<?= e($_POST['username'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="email" class="form-label fw-semibold text-secondary small">Alamat Email <span class="text-danger">*</span></label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-envelope input-icon"></i>
                                    <input type="email" id="email" name="email" class="form-control-modern" placeholder="nama@email.com" required maxlength="60" value="<?= e($_POST['email'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="no_telepon" class="form-label fw-semibold text-secondary small">Nomor Telepon / WA</label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-phone input-icon"></i>
                                    <input type="tel" id="no_telepon" name="no_telepon" class="form-control-modern" placeholder="08xxxxxxxxxx" pattern="^0[0-9]{8,14}$" inputmode="numeric" maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')" value="<?= e($_POST['no_telepon'] ?? '') ?>">
                                </div>
                                <small class="text-muted" style="font-size: 0.72rem;">Format angka diawali 0 (9–15 digit).</small>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="password" class="form-label fw-semibold text-secondary small">Password <span class="text-danger">*</span></label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-lock input-icon"></i>
                                    <input type="password" id="password" name="password" class="form-control-modern" placeholder="Min. 6 karakter" required minlength="6">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="level" class="form-label fw-semibold text-secondary small">Peran (Hak Akses) <span class="text-danger">*</span></label>
                                <select id="level" name="level" class="form-select-modern">
                                    <?php if ($curr_login_lvl === 1): ?>
                                    <option value="1">Super Admin (Level 1)</option>
                                    <?php endif; ?>
                                    <option value="2">Admin (Level 2)</option>
                                    <option value="3">Staf Kasir Loket (Level 3)</option>
                                    <option value="0" selected>Pengunjung (Level 0)</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-brand">
                                <i class="fa-solid fa-user-check me-1"></i> Simpan Pengguna Baru
                            </button>
                            <a href="<?= route_url('users') ?>" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require '../../app/layouts/admin_footer.php';
?>
