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
        } elseif (has_toxic_words($nama)) {
            $toxicHits = find_toxic_words($nama);
            $error_msg = 'Nama lengkap memuat kata yang dilarang (' . e(implode(', ', array_unique($toxicHits))) . '). Harap gunakan bahasa yang sopan.';
        } else {
            // Cek duplikasi email pada user lain
            $chk = $conn->prepare("SELECT id_user FROM users WHERE email = ? AND id_user != ? LIMIT 1");
            $chk->bind_param("si", $email, $user_id);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $error_msg = 'Alamat email tersebut sudah digunakan oleh akun lain.';
            } else {
                // Handle Avatar Upload: Utamakan foto hasil crop (base64) atau fallback ke $_FILES
                $new_avatar_name = $curr_user['avatar'];
                $avatar_uploaded = false;
                $avatar_dir = __DIR__ . '/../../../public/img/avatars/';
                $cropped_data = trim($_POST['avatar_cropped_data'] ?? '');

                if (!empty($cropped_data) && preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/s', $cropped_data, $crop_matches)) {
                    $crop_mime_type = $crop_matches[1];
                    $crop_ext = ($crop_mime_type === 'jpeg') ? 'jpg' : $crop_mime_type;
                    $decoded_raw = base64_decode($crop_matches[2], true);

                    if ($decoded_raw === false || strlen($decoded_raw) === 0) {
                        $error_msg = 'Data foto profil hasil crop tidak valid.';
                    } elseif (strlen($decoded_raw) > 2097152) {
                        $error_msg = 'Ukuran foto profil hasil crop melebihi batas 2 MB.';
                    } else {
                        // Periksa pola script berbahaya
                        $dangerous_patterns = [
                            '/<\?php/i',
                            '/<\?=/i',
                            '/<\x00?\?\x00?p\x00?h\x00?p/i',
                            '/<script\b/i',
                            '/\b(eval|passthru|shell_exec|exec|system|base64_decode|assert)\s*\(/i'
                        ];
                        $is_safe = true;
                        foreach ($dangerous_patterns as $pattern) {
                            if (preg_match($pattern, $decoded_raw)) {
                                $is_safe = false;
                                break;
                            }
                        }

                        if (!$is_safe) {
                            $error_msg = 'Berkas foto profil ditolak: terdeteksi pola yang tidak aman.';
                        } else {
                            if (!is_dir($avatar_dir)) {
                                @mkdir($avatar_dir, 0755, true);
                            }
                            $rand_avatar_name = 'avatar_' . bin2hex(random_bytes(16)) . '.' . $crop_ext;
                            $dest_file = $avatar_dir . $rand_avatar_name;

                            $saved = false;
                            if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
                                $gd_img = @imagecreatefromstring($decoded_raw);
                                if ($gd_img) {
                                    if ($crop_ext === 'png') {
                                        imagealphablending($gd_img, false);
                                        imagesavealpha($gd_img, true);
                                        $saved = @imagepng($gd_img, $dest_file, 8);
                                    } elseif ($crop_ext === 'webp' && function_exists('imagewebp')) {
                                        $saved = @imagewebp($gd_img, $dest_file, 90);
                                    } else {
                                        $saved = @imagejpeg($gd_img, $dest_file, 90);
                                    }
                                    @imagedestroy($gd_img);
                                }
                            }

                            if (!$saved) {
                                $saved = (@file_put_contents($dest_file, $decoded_raw) !== false);
                            }

                            if ($saved) {
                                $new_avatar_name = $rand_avatar_name;
                                if (!empty($curr_user['avatar'])) {
                                    $old_file = $avatar_dir . basename($curr_user['avatar']);
                                    if (file_exists($old_file) && is_file($old_file)) {
                                        @unlink($old_file);
                                    }
                                }
                                $avatar_uploaded = true;
                            } else {
                                $error_msg = 'Gagal menyimpan foto profil hasil pemotongan.';
                            }
                        }
                    }
                } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload_res = secure_upload_image($_FILES['avatar'], $avatar_dir, ['jpg', 'jpeg', 'png', 'webp'], 2097152);
                    if ($upload_res['success']) {
                        $new_avatar_name = $upload_res['filename'];
                        // Hapus avatar lama jika ada file fisiknya
                        if (!empty($curr_user['avatar'])) {
                            $old_file = $avatar_dir . basename($curr_user['avatar']);
                            if (file_exists($old_file) && is_file($old_file)) {
                                @unlink($old_file);
                            }
                        }
                        $avatar_uploaded = true;
                    } else {
                        $error_msg = 'Gagal mengunggah foto profil: ' . $upload_res['error'];
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
                        if (function_exists('log_activity')) {
                            log_activity('UPDATE', 'profile', "Memperbarui profil pengguna ID #{$user_id} ({$curr_user['username']})", $user_id);
                        }
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
                    if (function_exists('log_activity')) {
                        log_activity('UPDATE', 'profile', "Memperbarui kata sandi akun ID #{$user_id} ({$curr_user['username']})", $user_id);
                    }
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

<link rel="stylesheet" href="<?= public_url('assets/extensions/cropperjs/cropper.min.css') ?>">
<style>
/* Styling Lensa Pemotong Foto & Stage Wallpaper */
.crop-modal-stage-wrapper {
    background: #090d16;
    border-radius: 14px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 380px;
    max-height: 520px;
    position: relative;
    overflow: hidden;
}
.crop-wallpaper-stage {
    position: relative;
    display: inline-block;
    max-width: 100%;
    max-height: 460px;
    line-height: 0;
    overflow: hidden;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.55);
    user-select: none;
    -webkit-user-select: none;
}
.crop-wallpaper-stage img.crop-wallpaper-img {
    display: block;
    max-width: 100%;
    max-height: 460px;
    width: auto;
    height: auto;
    object-fit: contain;
    user-select: none;
    -webkit-user-drag: none;
    pointer-events: none;
}
/* Lingkaran Lensa Transparan (Spotlight Lens) */
.crop-lens {
    position: absolute;
    border-radius: 50%;
    border: 3px solid #38bdf8;
    box-shadow: 0 0 0 9999px rgba(9, 13, 22, 0.65), 0 0 16px rgba(56, 189, 248, 0.5);
    cursor: move;
    touch-action: none;
    user-select: none;
    z-index: 10;
    box-sizing: border-box;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.crop-lens:active, .crop-lens.is-dragging {
    border-color: #60a5fa;
    box-shadow: 0 0 0 9999px rgba(9, 13, 22, 0.72), 0 0 22px rgba(96, 165, 250, 0.65);
}
/* Titik bidik tengah / crosshair subtle */
.crop-lens::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 12px;
    height: 12px;
    transform: translate(-50%, -50%);
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    border: 1.5px solid rgba(255, 255, 255, 0.8);
    pointer-events: none;
}
/* Handle Pengubah Ukuran Lensa (Resize Handle 1:1) */
.crop-lens-handle {
    position: absolute;
    right: 3px;
    bottom: 3px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #0284c7;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
    cursor: nwse-resize;
    z-index: 12;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 10px;
    touch-action: none;
}
/* Styling modern avatar upload form section */
.avatar-form-card {
    background: #f8fafc;
    border: 1.5px dashed #cbd5e1;
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.25s ease;
}
.avatar-form-card:hover {
    border-color: #0284c7;
    background: #f0f9ff;
}
.avatar-preview-display {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 3.5px solid #0284c7;
    box-shadow: 0 10px 25px rgba(2, 132, 199, 0.25);
    overflow: hidden;
    flex-shrink: 0;
    position: relative;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<div class="row g-4">
    <!-- Kolom Kiri: Ringkasan Akun -->
    <div class="col-12 col-lg-4">
        <div class="modern-card text-center p-4">
            <div class="position-relative d-inline-block mx-auto mb-3">
                <?php if (!empty($avatar_url)): ?>
                    <img id="sidebarAvatarImg" src="<?= e($avatar_url) ?>" alt="Foto Profil" class="rounded-circle shadow" style="width: 104px; height: 104px; object-fit: cover; border: 3.5px solid #ffffff; box-shadow: 0 10px 25px rgba(2, 132, 199, 0.25);">
                <?php else: ?>
                    <div id="sidebarAvatarInitial" style="width: 104px; height: 104px; border-radius: 50%; background: linear-gradient(135deg, #0284c7, #38bdf8); color: #fff; font-weight: 800; font-size: 2.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 25px rgba(2, 132, 199, 0.35);">
                        <?= $user_initial ?>
                    </div>
                    <img id="sidebarAvatarImg" src="" alt="Foto Profil" class="rounded-circle shadow" style="width: 104px; height: 104px; object-fit: cover; border: 3.5px solid #ffffff; box-shadow: 0 10px 25px rgba(2, 132, 199, 0.25); display: none;">
                <?php endif; ?>
            </div>
            
            <h4 class="fw-bold mb-1 text-dark"><?= e($curr_user['nama'] ?: $curr_user['username']) ?></h4>
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
                <span class="fw-bold text-dark"><i class="fa-solid fa-user-pen text-primary me-2"></i> Perbarui Data Pribadi & Foto Profil</span>
            </div>
            <div class="p-4">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action_update_profile" value="1">
                    <input type="hidden" name="avatar_cropped_data" id="avatarCroppedData" value="">

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
                        
                        <!-- Upload & Ganti Foto Profil (Dimensi Lebar & Standar Industri Modern) -->
                        <div class="col-12 mt-3 pt-3 border-top">
                            <label class="form-label fw-bold text-dark d-flex align-items-center justify-content-between mb-2">
                                <span class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-camera text-primary"></i>
                                    <span>Foto Profil & Identitas Akun</span>
                                </span>
                                <span id="avatarStatusBadge" class="badge <?= !empty($avatar_url) ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary' ?> small">
                                    <i class="fa-solid <?= !empty($avatar_url) ? 'fa-check' : 'fa-circle-info' ?> me-1"></i>
                                    <?= !empty($avatar_url) ? 'Foto Aktif Tersimpan' : 'Belum Ada Foto Khusus' ?>
                                </span>
                            </label>

                            <div class="avatar-form-card">
                                <div class="d-flex flex-column flex-md-row align-items-center gap-4">
                                    <!-- Pratinjau Avatar Besar (120x120) -->
                                    <div class="avatar-preview-display" id="avatarPreviewContainer">
                                        <?php if (!empty($avatar_url)): ?>
                                            <img id="avatarPreviewImg" src="<?= e($avatar_url) ?>" data-initial-src="<?= e($avatar_url) ?>" alt="Preview Foto Profil" style="width: 100%; height: 100%; object-fit: cover;">
                                            <i id="avatarPreviewIcon" class="fa-solid fa-user text-muted fs-1" style="display: none;"></i>
                                        <?php else: ?>
                                            <i id="avatarPreviewIcon" class="fa-solid fa-user text-muted fs-1"></i>
                                            <img id="avatarPreviewImg" src="" data-initial-src="" alt="Preview Foto Profil" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                        <?php endif; ?>
                                    </div>

                                    <!-- Deskripsi & Kontrol Unggah -->
                                    <div class="flex-grow-1 text-center text-md-start">
                                        <h6 class="fw-bold text-dark mb-1">Sesuaikan Foto Profil Anda</h6>
                                        <p class="text-muted small mb-3">
                                            Gunakan foto wajah yang jelas. Format didukung: <strong>JPG, JPEG, PNG, WEBP</strong> (Maksimal <strong>2 MB</strong>). Sistem menyediakan dialog pemotong (*crop*) rasio 1:1 otomatis berstandar modern.
                                        </p>
                                        <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start align-items-center">
                                            <label for="avatarInput" class="btn btn-sm btn-primary px-3 py-2 cursor-pointer shadow-sm d-inline-flex align-items-center gap-2 mb-0">
                                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                                <span>Pilih & Unggah Foto Baru</span>
                                            </label>
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-2" id="btnReopenCrop" style="display: none;">
                                                <i class="fa-solid fa-crop-simple me-1"></i> Sesuaikan Ulang
                                            </button>
                                            <input type="file" name="avatar" id="avatarInput" class="form-control-modern d-none" accept=".jpg,.jpeg,.png,.webp">
                                        </div>
                                    </div>
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

        <!-- Kartu 2: Keamanan Sandi Akun -->
        <div class="modern-card">
            <div class="modern-card-header">
                <span class="fw-bold text-dark"><i class="fa-solid fa-key text-warning me-2"></i> Perbarui Kata Sandi</span>
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

<!-- Modal Potong (Crop) Foto Profil (Single-Image Wallpaper & Transparent Lens Viewfinder 1:1) -->
<div class="modal fade" id="modalCropAvatar" tabindex="-1" aria-labelledby="modalCropAvatarLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg" style="max-width: 860px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <h5 class="modal-title fw-bold text-white fs-6 d-flex align-items-center gap-2 mb-0" id="modalCropAvatarLabel">
                    <i class="fa-solid fa-crop-simple"></i> Sesuaikan Foto Profil (1:1)
                </h5>
                <button type="button" class="btn-close btn-close-white" aria-label="Close" id="btnCancelCropX"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- Area Wallpaper Backdrop & Lensa Transparan 1:1 (Single Image, No Duplication) -->
                <div class="crop-modal-stage-wrapper shadow-sm mb-3" id="cropStageWrapper">
                    <div class="crop-wallpaper-stage" id="cropWallpaperStage">
                        <img id="cropWallpaperImg" class="crop-wallpaper-img" src="" alt="Pratinjau Foto Profil">
                        <div id="cropLens" class="crop-lens">
                            <div class="crop-lens-handle" id="cropLensHandle" title="Tarik untuk memperbesar/memperkecil lingkaran crop">
                                <i class="fa-solid fa-up-right-and-down-left-from-center"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kontrol Ukuran Lingkaran & Pratinjau Avatar Bulat -->
                <div class="card border-0 shadow-sm p-3 bg-white rounded-3 mb-2">
                    <div class="row align-items-center g-3">
                        <div class="col-12 col-md-7">
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                <span class="small fw-bold text-dark"><i class="fa-solid fa-circle-dot text-primary me-1"></i> Ukuran Lingkaran:</span>
                                <button type="button" class="btn btn-sm btn-light border px-2.5 py-1" id="btnLensSizeSm">Kecil</button>
                                <button type="button" class="btn btn-sm btn-light border px-2.5 py-1" id="btnLensSizeMd">Sedang</button>
                                <button type="button" class="btn btn-sm btn-light border px-2.5 py-1" id="btnLensSizeLg">Maksimal</button>
                                <button type="button" class="btn btn-sm btn-light border px-2.5 py-1 text-danger" id="btnResetLens">
                                    <i class="fa-solid fa-arrows-to-dot me-1"></i> Pusatkan
                                </button>
                            </div>
                            <div class="text-muted small">
                                <i class="fa-solid fa-hand-pointer text-primary me-1"></i> Geser lingkaran untuk menentukan bagian foto profil.
                            </div>
                        </div>

                        <!-- Live Pratinjau Bulat Real-Time -->
                        <div class="col-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-end gap-3 border-start-md">
                            <div class="text-end d-none d-sm-block">
                                <div class="small fw-semibold text-dark">Hasil Avatar</div>
                                <div class="text-muted" style="font-size: 0.72rem;">Pratinjau bulat asli</div>
                            </div>
                            <canvas id="liveCropPreviewCanvas" width="96" height="96" class="rounded-circle border border-2 border-primary shadow-sm" style="width: 72px; height: 72px; background: #e2e8f0;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-between">
                <button type="button" class="btn btn-light border px-4" id="btnCancelCrop">
                    <i class="fa-solid fa-xmark me-1"></i> Batal
                </button>
                <button type="button" class="btn btn-primary px-4 fw-bold shadow-sm" id="btnApplyCrop">
                    <i class="fa-solid fa-check me-1"></i> Selesai & Terapkan Foto
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '
<script src="' . public_url('assets/extensions/cropperjs/cropper.min.js') . '"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Konfigurasi pemotong rasio aspek 1:1 (aspectRatio: 1)
    const cropConfig = { aspectRatio: 1, minSize: 60, exportSize: 512 };

    const avatarInput = document.getElementById("avatarInput");
    const avatarCroppedData = document.getElementById("avatarCroppedData");
    const avatarPreviewImg = document.getElementById("avatarPreviewImg");
    const avatarPreviewIcon = document.getElementById("avatarPreviewIcon");
    const modalEl = document.getElementById("modalCropAvatar");
    const stageWrapper = document.getElementById("cropStageWrapper");
    const stageEl = document.getElementById("cropWallpaperStage");
    const cropImg = document.getElementById("cropWallpaperImg");
    const cropLens = document.getElementById("cropLens");
    const lensHandle = document.getElementById("cropLensHandle");
    const liveCanvas = document.getElementById("liveCropPreviewCanvas");
    const liveCtx = liveCanvas ? liveCanvas.getContext("2d") : null;

    const originalAvatarSrc = avatarPreviewImg ? (avatarPreviewImg.getAttribute("data-initial-src") || avatarPreviewImg.src) : "";
    const hadOriginalAvatar = ' . (!empty($avatar_url) ? 'true' : 'false') . ';

    let rawImageDataUrl = "";
    let rawImageObj = new Image();
    let lensX = 0;
    let lensY = 0;
    let lensSize = 150;
    let isDragging = false;
    let isResizing = false;
    let dragStartX = 0;
    let dragStartY = 0;
    let initialLensX = 0;
    let initialLensY = 0;
    let initialLensSize = 150;

    function applyLensPosition() {
        if (!cropLens || !cropImg) return;
        const stageW = cropImg.offsetWidth;
        const stageH = cropImg.offsetHeight;
        if (stageW <= 0 || stageH <= 0) return;

        lensSize = Math.max(cropConfig.minSize, Math.min(Math.min(stageW, stageH), lensSize));
        lensX = Math.max(0, Math.min(stageW - lensSize, lensX));
        lensY = Math.max(0, Math.min(stageH - lensSize, lensY));

        cropLens.style.width = lensSize + "px";
        cropLens.style.height = lensSize + "px";
        cropLens.style.left = lensX + "px";
        cropLens.style.top = lensY + "px";
    }

    function updateLivePreview() {
        if (!liveCtx || !rawImageObj.complete || rawImageObj.naturalWidth === 0 || !cropImg) return;
        const stageW = cropImg.offsetWidth;
        const stageH = cropImg.offsetHeight;
        if (stageW <= 0 || stageH <= 0) return;

        const scaleX = rawImageObj.naturalWidth / stageW;
        const scaleY = rawImageObj.naturalHeight / stageH;

        const sx = Math.max(0, Math.min(rawImageObj.naturalWidth, lensX * scaleX));
        const sy = Math.max(0, Math.min(rawImageObj.naturalHeight, lensY * scaleY));
        const sSize = Math.min(lensSize * scaleX, Math.min(rawImageObj.naturalWidth - sx, rawImageObj.naturalHeight - sy));

        liveCtx.clearRect(0, 0, liveCanvas.width, liveCanvas.height);
        liveCtx.save();
        liveCtx.beginPath();
        liveCtx.arc(liveCanvas.width / 2, liveCanvas.height / 2, liveCanvas.width / 2, 0, Math.PI * 2);
        liveCtx.clip();
        try {
            liveCtx.drawImage(rawImageObj, sx, sy, sSize, sSize, 0, 0, liveCanvas.width, liveCanvas.height);
        } catch(e) {}
        liveCtx.restore();
    }

    function initLensOnStage() {
        if (!cropImg || !rawImageObj.complete || rawImageObj.naturalWidth === 0) return;

        requestAnimationFrame(function() {
            const stageW = cropImg.offsetWidth;
            const stageH = cropImg.offsetHeight;
            if (stageW <= 0 || stageH <= 0) {
                setTimeout(initLensOnStage, 60);
                return;
            }

            // Atur ukuran awal lingkaran sekitar 75% dari sisi terpendek gambar
            lensSize = Math.round(Math.min(stageW, stageH) * 0.75);
            lensX = Math.round((stageW - lensSize) / 2);
            lensY = Math.round((stageH - lensSize) / 2);

            applyLensPosition();
            updateLivePreview();
        });
    }

    // Pasang listener drag pada lensa transparan (pointer events)
    if (cropLens) {
        cropLens.addEventListener("pointerdown", function(e) {
            if (e.target === lensHandle || lensHandle.contains(e.target)) return;
            isDragging = true;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            initialLensX = lensX;
            initialLensY = lensY;
            cropLens.classList.add("is-dragging");
            try { cropLens.setPointerCapture(e.pointerId); } catch(err) {}
            e.preventDefault();
        });

        cropLens.addEventListener("pointermove", function(e) {
            if (!isDragging || !cropImg) return;
            const stageW = cropImg.offsetWidth;
            const stageH = cropImg.offsetHeight;
            const dx = e.clientX - dragStartX;
            const dy = e.clientY - dragStartY;

            lensX = Math.max(0, Math.min(stageW - lensSize, initialLensX + dx));
            lensY = Math.max(0, Math.min(stageH - lensSize, initialLensY + dy));

            applyLensPosition();
            updateLivePreview();
        });

        const stopDrag = function(e) {
            if (isDragging) {
                isDragging = false;
                cropLens.classList.remove("is-dragging");
                try { cropLens.releasePointerCapture(e.pointerId); } catch(err) {}
            }
        };
        cropLens.addEventListener("pointerup", stopDrag);
        cropLens.addEventListener("pointercancel", stopDrag);
    }

    // Pasang listener resize pada handle sudut lensa
    if (lensHandle) {
        lensHandle.addEventListener("pointerdown", function(e) {
            e.stopPropagation();
            isResizing = true;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            initialLensSize = lensSize;
            try { lensHandle.setPointerCapture(e.pointerId); } catch(err) {}
            e.preventDefault();
        });

        lensHandle.addEventListener("pointermove", function(e) {
            if (!isResizing || !cropImg) return;
            const stageW = cropImg.offsetWidth;
            const stageH = cropImg.offsetHeight;
            const delta = Math.max(e.clientX - dragStartX, e.clientY - dragStartY);
            const maxPossible = Math.min(stageW - lensX, stageH - lensY);

            lensSize = Math.max(cropConfig.minSize, Math.min(maxPossible, initialLensSize + delta));
            applyLensPosition();
            updateLivePreview();
        });

        const stopResize = function(e) {
            if (isResizing) {
                isResizing = false;
                try { lensHandle.releasePointerCapture(e.pointerId); } catch(err) {}
            }
        };
        lensHandle.addEventListener("pointerup", stopResize);
        lensHandle.addEventListener("pointercancel", stopResize);
    }

    // Tombol Preset Ukuran Lingkaran
    document.getElementById("btnLensSizeSm")?.addEventListener("click", function() {
        if (!cropImg) return;
        const minDim = Math.min(cropImg.offsetWidth, cropImg.offsetHeight);
        lensSize = Math.max(cropConfig.minSize, Math.round(minDim * 0.45));
        applyLensPosition();
        updateLivePreview();
    });

    document.getElementById("btnLensSizeMd")?.addEventListener("click", function() {
        if (!cropImg) return;
        const minDim = Math.min(cropImg.offsetWidth, cropImg.offsetHeight);
        lensSize = Math.max(cropConfig.minSize, Math.round(minDim * 0.70));
        applyLensPosition();
        updateLivePreview();
    });

    document.getElementById("btnLensSizeLg")?.addEventListener("click", function() {
        if (!cropImg) return;
        const minDim = Math.min(cropImg.offsetWidth, cropImg.offsetHeight);
        lensSize = minDim;
        lensX = Math.round((cropImg.offsetWidth - lensSize) / 2);
        lensY = Math.round((cropImg.offsetHeight - lensSize) / 2);
        applyLensPosition();
        updateLivePreview();
    });

    document.getElementById("btnResetLens")?.addEventListener("click", function() {
        if (!cropImg) return;
        lensX = Math.round((cropImg.offsetWidth - lensSize) / 2);
        lensY = Math.round((cropImg.offsetHeight - lensSize) / 2);
        applyLensPosition();
        updateLivePreview();
    });

    // Kontrol Buka dan Tutup Modal
    modalEl?.addEventListener("shown.bs.modal", function() {
        initLensOnStage();
    });

    function openCropModal() {
        if (!modalEl) return;
        if (window.bootstrap && window.bootstrap.Modal) {
            try {
                const inst = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                inst.show();
                setTimeout(initLensOnStage, 100);
                return;
            } catch(err) {
                console.warn("Bootstrap modal fallback:", err);
            }
        }
        // Fallback Vanilla JS Modal
        modalEl.style.display = "block";
        modalEl.classList.add("show");
        document.body.classList.add("modal-open");
        let backdrop = document.getElementById("modalCropBackdrop");
        if (!backdrop) {
            backdrop = document.createElement("div");
            backdrop.id = "modalCropBackdrop";
            backdrop.className = "modal-backdrop fade show";
            document.body.appendChild(backdrop);
        }
        setTimeout(initLensOnStage, 120);
    }

    function closeCropModal() {
        if (!modalEl) return;
        if (window.bootstrap && window.bootstrap.Modal) {
            try {
                const inst = window.bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();
            } catch(e) {}
        }
        modalEl.style.display = "none";
        modalEl.classList.remove("show");
        document.body.classList.remove("modal-open");
        const backdrop = document.getElementById("modalCropBackdrop");
        if (backdrop) backdrop.remove();
    }

    function cancelCrop() {
        closeCropModal();
        if (!avatarCroppedData.value) {
            if (hadOriginalAvatar && originalAvatarSrc) {
                if (avatarPreviewImg) {
                    avatarPreviewImg.src = originalAvatarSrc;
                    avatarPreviewImg.style.display = "block";
                }
                if (avatarPreviewIcon) avatarPreviewIcon.style.display = "none";
            } else {
                if (avatarPreviewImg) {
                    avatarPreviewImg.src = "";
                    avatarPreviewImg.style.display = "none";
                }
                if (avatarPreviewIcon) avatarPreviewIcon.style.display = "block";
            }
            if (avatarInput) avatarInput.value = "";
            const avatarStatusBadge = document.getElementById("avatarStatusBadge");
            if (avatarStatusBadge) {
                avatarStatusBadge.className = hadOriginalAvatar ? "badge bg-soft-success text-success small" : "badge bg-soft-secondary text-secondary small";
                avatarStatusBadge.innerHTML = hadOriginalAvatar ? \'<i class="fa-solid fa-check me-1"></i> Foto Aktif Tersimpan\' : \'<i class="fa-solid fa-circle-info me-1"></i> Belum Ada Foto Khusus\';
            }
        }
    }

    // Tangkap pemilihan berkas foto profil
    avatarInput?.addEventListener("change", function(e) {
        const file = e.target.files && e.target.files[0];
        if (!file) return;

        // Validasi ukuran berkas maksimal 2 MB
        if (file.size > 2 * 1024 * 1024) {
            if (typeof Swal !== "undefined") {
                Swal.fire({
                    icon: "warning",
                    title: "Peringatan Ukuran File",
                    text: "gambar yang anda upload melebihi ukuran 2mb silahkan upload gambar lain"
                });
            } else {
                alert("gambar yang anda upload melebihi ukuran 2mb silahkan upload gambar lain");
            }
            avatarInput.value = "";
            return;
        }

        if (!file.type.startsWith("image/")) {
            return;
        }

        const reader = new FileReader();
        reader.onload = function(evt) {
            rawImageDataUrl = evt.target.result;
            rawImageObj = new Image();
            rawImageObj.onload = function() {
                if (cropImg) cropImg.src = rawImageDataUrl;
                openCropModal();
            };
            rawImageObj.src = rawImageDataUrl;
        };
        reader.readAsDataURL(file);
    });

    document.getElementById("btnReopenCrop")?.addEventListener("click", function() {
        if (rawImageDataUrl) {
            if (cropImg) cropImg.src = rawImageDataUrl;
            openCropModal();
        } else if (avatarPreviewImg && avatarPreviewImg.src) {
            rawImageDataUrl = avatarPreviewImg.src;
            rawImageObj = new Image();
            rawImageObj.onload = function() {
                if (cropImg) cropImg.src = rawImageDataUrl;
                openCropModal();
            };
            rawImageObj.src = rawImageDataUrl;
        }
    });

    document.getElementById("btnCancelCrop")?.addEventListener("click", cancelCrop);
    document.getElementById("btnCancelCropX")?.addEventListener("click", cancelCrop);

    // Terapkan Crop Foto ke Format 1:1 Resolusi Tinggi
    document.getElementById("btnApplyCrop")?.addEventListener("click", function() {
        if (!rawImageObj.complete || rawImageObj.naturalWidth === 0 || !cropImg) {
            closeCropModal();
            return;
        }

        const stageW = cropImg.offsetWidth;
        const stageH = cropImg.offsetHeight;
        if (stageW <= 0 || stageH <= 0) {
            closeCropModal();
            return;
        }

        const scaleX = rawImageObj.naturalWidth / stageW;
        const scaleY = rawImageObj.naturalHeight / stageH;

        const sx = Math.max(0, Math.min(rawImageObj.naturalWidth, lensX * scaleX));
        const sy = Math.max(0, Math.min(rawImageObj.naturalHeight, lensY * scaleY));
        const sSize = Math.min(lensSize * scaleX, Math.min(rawImageObj.naturalWidth - sx, rawImageObj.naturalHeight - sy));

        const exportCanvas = document.createElement("canvas");
        exportCanvas.width = cropConfig.exportSize;
        exportCanvas.height = cropConfig.exportSize;
        const exportCtx = exportCanvas.getContext("2d");
        exportCtx.imageSmoothingEnabled = true;
        exportCtx.imageSmoothingQuality = "high";

        exportCtx.drawImage(rawImageObj, sx, sy, sSize, sSize, 0, 0, cropConfig.exportSize, cropConfig.exportSize);
        const croppedBase64 = exportCanvas.toDataURL("image/jpeg", 0.92);

        avatarCroppedData.value = croppedBase64;

        if (avatarPreviewImg) {
            avatarPreviewImg.src = croppedBase64;
            avatarPreviewImg.style.display = "block";
        }
        if (avatarPreviewIcon) {
            avatarPreviewIcon.style.display = "none";
        }
        const sidebarAvatarImg = document.getElementById("sidebarAvatarImg");
        const sidebarAvatarInitial = document.getElementById("sidebarAvatarInitial");
        if (sidebarAvatarImg) {
            sidebarAvatarImg.src = croppedBase64;
            sidebarAvatarImg.style.display = "block";
        }
        if (sidebarAvatarInitial) {
            sidebarAvatarInitial.style.display = "none";
        }

        const avatarStatusBadge = document.getElementById("avatarStatusBadge");
        if (avatarStatusBadge) {
            avatarStatusBadge.className = "badge bg-soft-warning text-warning small";
            avatarStatusBadge.innerHTML = \'<i class="fa-solid fa-clock-rotate-left me-1"></i> Pratinjau Baru (Belum Disimpan)\';
        }

        const btnReopenCrop = document.getElementById("btnReopenCrop");
        if (btnReopenCrop) {
            btnReopenCrop.style.display = "inline-flex";
        }

        // Fallback DataTransfer untuk sinkronisasi File object
        if (window.DataTransfer) {
            exportCanvas.toBlob(function(blob) {
                if (blob) {
                    try {
                        const dt = new DataTransfer();
                        const f = new File([blob], "avatar_cropped.jpg", { type: "image/jpeg" });
                        dt.items.add(f);
                        avatarInput.files = dt.files;
                    } catch(err) {}
                }
            }, "image/jpeg", 0.92);
        }

        closeCropModal();

        if (typeof Swal !== "undefined") {
            Swal.fire({
                icon: "success",
                title: "Foto Berhasil Dipotong (1:1)",
                text: "Pratinjau foto profil telah diperbarui. Silakan tekan tombol \"Simpan Perubahan Profil\" untuk menyimpan ke database.",
                timer: 3500,
                showConfirmButton: false,
                toast: true,
                position: "top-end"
            });
        }
    });
});
</script>
';

include __DIR__ . '/../../app/layouts/admin_footer.php';
?>
