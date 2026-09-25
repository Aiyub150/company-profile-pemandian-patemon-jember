<?php
/**
 * Modul Profil Pengguna & Keamanan Akun
 * Mengikuti Standar Keamanan SIM-ASET
 */
require_once __DIR__ . '/../../app/config.php';

// Wajib Login
check_auth([0, 1, 2, 3], route_url('login'));

$user_id = (int)$_SESSION['id_user'];
$active_menu = 'profile';
$page_title = 'Profil Akun & Keamanan - Pemandian Patemon';
$page_heading = 'Profil Akun & Keamanan';
$page_subheading = 'Kelola informasi pribadi, kontak, foto profil, dan kredensial kata sandi akun Anda.';

$success_msg = '';
$error_msg = '';

// Ambil data user terlebih dahulu
$stmt_curr = $conn->prepare("SELECT id_user, nama, username, email, no_telepon, level, avatar FROM users WHERE id_user = ? LIMIT 1");
$stmt_curr->bind_param("i", $user_id);
$stmt_curr->execute();
$curr_user = $stmt_curr->get_result()->fetch_assoc();
$stmt_curr->close();

if (!$curr_user) {
    header("Location: " . route_url('login'));
    exit;
}

// Handle POST Update Profil & Avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_profile'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $nama       = trim($_POST['nama'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $no_telepon = trim($_POST['no_telepon'] ?? '');

        if (empty($nama) || empty($email)) {
            $error_msg = 'Nama lengkap dan email tidak boleh kosong.';
        } elseif (mb_strlen($nama) > 50) {
            $error_msg = 'Nama lengkap maksimal 50 karakter.';
        } elseif (!preg_match("/^[a-zA-Z\s\.\']+$/", $nama)) {
            $error_msg = 'Nama lengkap hanya boleh berisi huruf, spasi, titik, atau tanda petik.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = 'Format alamat email tidak valid.';
        } elseif (!empty($no_telepon) && !preg_match('/^0[0-9]{8,14}$/', $no_telepon)) {
            $error_msg = 'Nomor telepon tidak valid. Gunakan format angka diawali angka 0 (9–15 digit angka), tanpa spasi atau karakter khusus.';
        } else {
            // Cek duplikasi email pada user lain
            $chk = $conn->prepare("SELECT id_user FROM users WHERE email = ? AND id_user != ? LIMIT 1");
            $chk->bind_param("si", $email, $user_id);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $error_msg = 'Alamat email tersebut sudah digunakan oleh akun lain.';
            } else {
                // Handle Avatar Upload jika ada file yang diunggah
                $new_avatar_name = $curr_user['avatar'];
                $avatar_uploaded = false;

                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $file_error = $_FILES['avatar']['error'];
                    if ($file_error === UPLOAD_ERR_OK) {
                        $file_tmp  = $_FILES['avatar']['tmp_name'];
                        $file_size = $_FILES['avatar']['size'];
                        $file_name = $_FILES['avatar']['name'];
                        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                        $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = finfo_file($finfo, $file_tmp);
                        finfo_close($finfo);

                        if (!in_array($file_ext, $allowed_exts) || !in_array($mime_type, $allowed_mime)) {
                            $error_msg = 'Format foto tidak didukung. Harap unggah file berekstensi JPG, JPEG, PNG, atau WEBP.';
                        } elseif ($file_size > 2 * 1024 * 1024) {
                            $error_msg = 'Ukuran foto terlalu besar. Maksimum ukuran file adalah 2 MB.';
                        } else {
                            $avatar_dir = __DIR__ . '/../../../public/img/avatars/';
                            if (!is_dir($avatar_dir)) {
                                @mkdir($avatar_dir, 0755, true);
                            }

                            $new_avatar_name = 'avatar_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                            $destination = $avatar_dir . $new_avatar_name;

                            if (move_uploaded_file($file_tmp, $destination)) {
                                // Hapus avatar lama jika ada file fisiknya
                                if (!empty($curr_user['avatar'])) {
                                    $old_file = $avatar_dir . basename($curr_user['avatar']);
                                    if (file_exists($old_file) && is_file($old_file)) {
                                        @unlink($old_file);
                                    }
                                }
                                $avatar_uploaded = true;
                            } else {
                                $error_msg = 'Gagal menyimpan file foto ke server.';
                            }
                        }
                    } else {
                        $error_msg = 'Terjadi kesalahan saat mengunggah foto profil (Kode: ' . $file_error . ').';
                    }
                }

                if (empty($error_msg)) {
                    $up = $conn->prepare("UPDATE users SET nama = ?, email = ?, no_telepon = ?, avatar = ? WHERE id_user = ?");
                    $up->bind_param("ssssi", $nama, $email, $no_telepon, $new_avatar_name, $user_id);
                    if ($up->execute()) {
                        $_SESSION['nama'] = $nama;
                        $_SESSION['avatar'] = $new_avatar_name;
                        $curr_user['nama'] = $nama;
                        $curr_user['email'] = $email;
                        $curr_user['no_telepon'] = $no_telepon;
                        $curr_user['avatar'] = $new_avatar_name;
                        $success_msg = 'Informasi profil dan foto Anda berhasil diperbarui!';
                    } else {
                        $error_msg = 'Gagal menyimpan perubahan profil ke basis data.';
                    }
                    $up->close();
                }
            }
            $chk->close();
        }
    }
}

// Handle POST Update Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_password'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $curr_pass = $_POST['current_password'] ?? '';
        $new_pass  = $_POST['new_password'] ?? '';
        $conf_pass = $_POST['confirm_password'] ?? '';

        if (empty($curr_pass) || empty($new_pass) || empty($conf_pass)) {
            $error_msg = 'Semua kolom kata sandi wajib diisi.';
        } elseif (strlen($new_pass) < 6) {
            $error_msg = 'Kata sandi baru minimal harus 6 karakter.';
        } elseif ($new_pass !== $conf_pass) {
            $error_msg = 'Konfirmasi kata sandi baru tidak cocok.';
        } else {
            // Verifikasi password saat ini
            $stmt = $conn->prepare("SELECT password FROM users WHERE id_user = ? LIMIT 1");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$res || !password_verify($curr_pass, $res['password'])) {
                $error_msg = 'Kata sandi saat ini salah. Pastikan Anda mengingat kata sandi lama.';
            } else {
                $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $up_pass = $conn->prepare("UPDATE users SET password = ? WHERE id_user = ?");
                $up_pass->bind_param("si", $new_hash, $user_id);
                if ($up_pass->execute()) {
                    $success_msg = 'Kata sandi Anda berhasil diperbarui dengan aman!';
                } else {
                    $error_msg = 'Gagal memperbarui kata sandi.';
                }
                $up_pass->close();
            }
        }
    }
}

$user_level = (int)$curr_user['level'];
$role_title = get_role_name($user_level);
$user_initial = strtoupper(substr($curr_user['nama'] ?: $curr_user['username'], 0, 1));

// Avatar URL
$avatar_url = '';
if (!empty($curr_user['avatar'])) {
    $avatar_file_path = __DIR__ . '/../../../public/img/avatars/' . $curr_user['avatar'];
    if (file_exists($avatar_file_path)) {
        $avatar_url = public_url('img/avatars/' . $curr_user['avatar']);
    }
}

include __DIR__ . '/../../app/layouts/admin_header.php';
?>

<div class="row g-4">
    <!-- Kolom Kiri: Ringkasan Akun -->
    <div class="col-12 col-lg-4">
        <div class="modern-card text-center p-4">
            <div class="position-relative d-inline-block mx-auto mb-3">
                <?php if (!empty($avatar_url)): ?>
                    <img src="<?= e($avatar_url) ?>" alt="Foto Profil" class="rounded-circle shadow" style="width: 104px; height: 104px; object-fit: cover; border: 3.5px solid #ffffff; box-shadow: 0 10px 25px rgba(2, 132, 199, 0.25);">
                <?php else: ?>
                    <div style="width: 104px; height: 104px; border-radius: 50%; background: linear-gradient(135deg, #0284c7, #38bdf8); color: #fff; font-weight: 800; font-size: 2.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 25px rgba(2, 132, 199, 0.35);">
                        <?= $user_initial ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <h4 class="fw-bold mb-1" style="color: #0f172a;"><?= e($curr_user['nama'] ?: $curr_user['username']) ?></h4>
            <div class="mb-3">
                <?php
                $badge_class = 'badge-modern-primary';
                $role_icon = 'fa-user-check';
                if ($user_level === 1) { $badge_class = 'badge-modern-purple'; $role_icon = 'fa-shield-halved'; }
                elseif ($user_level === 2) { $badge_class = 'badge-modern-primary'; $role_icon = 'fa-user-tie'; }
                elseif ($user_level === 3) { $badge_class = 'badge-modern-success'; $role_icon = 'fa-cash-register'; }
                ?>
                <span class="badge <?= $badge_class ?> px-3 py-1">
                    <i class="fa-solid <?= $role_icon ?> me-1"></i>
                    <?= $role_title ?>
                </span>
            </div>

            <div class="border-top pt-3 text-start">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted small">Username</span>
                    <strong class="text-dark small">@<?= e($curr_user['username']) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted small">ID Pengguna</span>
                    <span class="badge bg-light text-secondary">USR-<?= str_pad($curr_user['id_user'], 3, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted small">Hak Akses</span>
                    <span class="text-dark small fw-semibold">Level <?= $user_level ?> (<?= e($role_title) ?>)</span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted small">Status Keamanan</span>
                    <span class="text-success small fw-bold"><i class="fa-solid fa-circle-check"></i> Aktif & Terlindungi</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Form Edit Profil & Ganti Password -->
    <div class="col-12 col-lg-8">
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 mb-4 shadow-sm">
                <i class="fa-solid fa-circle-check fs-5"></i>
                <div><?= e($success_msg) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 shadow-sm">
                <i class="fa-solid fa-triangle-exclamation fs-5"></i>
                <div><?= e($error_msg) ?></div>
            </div>
        <?php endif; ?>

        <!-- Kartu 1: Edit Informasi Profil & Foto -->
        <div class="modern-card mb-4">
            <div class="modern-card-header">
                <span class="fw-bold" style="color: #0f172a;"><i class="fa-solid fa-user-pen text-primary me-2"></i> Perbarui Data Pribadi & Foto Profil</span>
            </div>
            <div class="p-4">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action_update_profile" value="1">

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control-modern" value="<?= e($curr_user['nama']) ?>" required maxlength="50" pattern="^[a-zA-Z\s\.\']+$" title="Nama hanya boleh berisi huruf, spasi, titik, atau tanda petik (maksimal 50 karakter)">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">Alamat Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control-modern" value="<?= e($curr_user['email']) ?>" required maxlength="60">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">Nomor Telepon / WhatsApp</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-phone"></i></span>
                                <input type="tel" name="no_telepon" class="form-control-modern" value="<?= e($curr_user['no_telepon']) ?>" placeholder="08xxxxxxxxxx" pattern="^0[0-9]{8,14}$" inputmode="numeric" maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')" title="Gunakan format nomor HP diawali 0 (9-15 digit angka saja)">
                            </div>
                            <small class="text-muted" style="font-size: 0.75rem;">Format nomor Indonesia (diawali 0, hanya angka, 9–15 digit).</small>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-muted">Username (Permanen)</label>
                            <input type="text" class="form-control-modern bg-light text-muted" value="<?= e($curr_user['username']) ?>" readonly disabled>
                            <small class="text-muted" style="font-size: 0.75rem;">Username tidak dapat diubah demi konsistensi audit log.</small>
                        </div>
                        
                        <!-- Upload Avatar Profil -->
                        <div class="col-12 mt-3 pt-3 border-top">
                            <label class="form-label fw-semibold text-dark d-flex align-items-center justify-content-between">
                                <span><i class="fa-solid fa-camera text-primary me-1"></i> Foto Profil (Avatar)</span>
                                <?php if (!empty($avatar_url)): ?>
                                    <span class="badge bg-soft-success text-success small"><i class="fa-solid fa-check"></i> Foto Tersimpan</span>
                                <?php endif; ?>
                            </label>
                            <div class="d-flex align-items-center gap-3">
                                <div id="avatarPreviewContainer" style="width: 60px; height: 60px; border-radius: 50%; overflow: hidden; background: #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 2px dashed #94a3b8;">
                                    <?php if (!empty($avatar_url)): ?>
                                        <img id="avatarPreviewImg" src="<?= e($avatar_url) ?>" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i id="avatarPreviewIcon" class="fa-solid fa-user text-muted fs-4"></i>
                                        <img id="avatarPreviewImg" src="" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" name="avatar" id="avatarInput" class="form-control-modern" accept=".jpg,.jpeg,.png,.webp">
                                    <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Format yang didukung: JPG, PNG, WEBP. Ukuran file maksimal 2 MB.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-brand px-4">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan Profil
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        document.getElementById('avatarInput')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    const img = document.getElementById('avatarPreviewImg');
                    const icon = document.getElementById('avatarPreviewIcon');
                    if (img) {
                        img.src = evt.target.result;
                        img.style.display = 'block';
                    }
                    if (icon) icon.style.display = 'none';
                }
                reader.readAsDataURL(file);
            }
        });
        </script>

        <!-- Kartu 2: Keamanan Sandi Akun -->
        <div class="modern-card">
            <div class="modern-card-header">
                <span class="fw-bold" style="color: #0f172a;"><i class="fa-solid fa-key text-warning me-2"></i> Perbarui Kata Sandi</span>
            </div>
            <div class="p-4">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action_update_password" value="1">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark">Kata Sandi Saat Ini</label>
                            <input type="password" name="current_password" class="form-control-modern" required placeholder="Masukkan kata sandi lama Anda">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">Kata Sandi Baru</label>
                            <input type="password" name="new_password" class="form-control-modern" minlength="6" required placeholder="Minimal 6 karakter">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">Ulangi Kata Sandi Baru</label>
                            <input type="password" name="confirm_password" class="form-control-modern" minlength="6" required placeholder="Ketik ulang kata sandi baru">
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-soft-warning px-4">
                            <i class="fa-solid fa-shield-halved me-1"></i> Perbarui Kata Sandi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../app/layouts/admin_footer.php';
?>
