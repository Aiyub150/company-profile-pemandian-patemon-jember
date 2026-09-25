<?php
/**
 * Manajemen Galeri Wisata
 * Sesuai Feedback-3 Poin 8 - Hanya untuk Super Admin & Admin (level 1 & 2)
 */
require '../../app/config.php';
check_auth([1, 2]);

// Pastikan tabel gallery ada
$conn->query("CREATE TABLE IF NOT EXISTS `gallery` (
  `id_gallery` int(11) NOT NULL AUTO_INCREMENT,
  `judul` varchar(100) NOT NULL,
  `deskripsi_card` varchar(200) DEFAULT NULL,
  `deskripsi_popup` text DEFAULT NULL,
  `gambar_card` varchar(255) NOT NULL,
  `gambar_popup` varchar(255) NOT NULL,
  `urutan` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_gallery`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$active_menu     = 'gallery';
$page_title      = 'Kelola Galeri - Pemandian Patemon';
$page_heading    = 'Kelola Galeri Wisata';
$page_subheading = 'Atur gambar dan deskripsi 3 konten galeri yang tampil di halaman utama website.';

$success_msg = '';
$error_msg   = '';

// Handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $id_gallery      = (int)($_POST['id_gallery'] ?? 0);
        $judul           = mb_substr(trim($_POST['judul'] ?? ''), 0, 100);
        $deskripsi_card  = mb_substr(trim($_POST['deskripsi_card'] ?? ''), 0, 200);
        $deskripsi_popup = mb_substr(trim($_POST['deskripsi_popup'] ?? ''), 0, 2000);

        if (empty($judul)) {
            $error_msg = 'Judul galeri wajib diisi.';
        } elseif ($id_gallery < 1 || $id_gallery > 3) {
            $error_msg = 'ID galeri tidak valid.';
        } else {
            $gambar_card  = trim($_POST['gambar_card_current'] ?? '');
            $gambar_popup = trim($_POST['gambar_popup_current'] ?? '');

            // Upload gambar card
            if (isset($_FILES['gambar_card']) && $_FILES['gambar_card']['error'] === UPLOAD_ERR_OK) {
                $tmp  = $_FILES['gambar_card']['tmp_name'];
                $ext  = strtolower(pathinfo($_FILES['gambar_card']['name'], PATHINFO_EXTENSION));
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $tmp);
                finfo_close($finfo);
                $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array($mime, $allowed, true)) {
                    $error_msg = 'Format gambar card tidak didukung (jpg/png/webp/gif).';
                } elseif ($_FILES['gambar_card']['size'] > 3 * 1024 * 1024) {
                    $error_msg = 'Ukuran gambar card maksimal 3 MB.';
                } else {
                    $newname = 'gallery_' . $id_gallery . '_card_' . time() . '.' . $ext;
                    $dest    = __DIR__ . '/../../../public/img/' . $newname;
                    if (move_uploaded_file($tmp, $dest)) {
                        $gambar_card = $newname;
                    } else {
                        $error_msg = 'Gagal mengunggah gambar card. Pastikan folder public/img dapat ditulis.';
                    }
                }
            }

            // Upload gambar popup (jika tidak ada error sebelumnya)
            if (empty($error_msg) && isset($_FILES['gambar_popup']) && $_FILES['gambar_popup']['error'] === UPLOAD_ERR_OK) {
                $tmp  = $_FILES['gambar_popup']['tmp_name'];
                $ext  = strtolower(pathinfo($_FILES['gambar_popup']['name'], PATHINFO_EXTENSION));
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $tmp);
                finfo_close($finfo);
                $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array($mime, $allowed, true)) {
                    $error_msg = 'Format gambar popup tidak didukung (jpg/png/webp/gif).';
                } elseif ($_FILES['gambar_popup']['size'] > 3 * 1024 * 1024) {
                    $error_msg = 'Ukuran gambar popup maksimal 3 MB.';
                } else {
                    $newname = 'gallery_' . $id_gallery . '_popup_' . time() . '.' . $ext;
                    $dest    = __DIR__ . '/../../../public/img/' . $newname;
                    if (move_uploaded_file($tmp, $dest)) {
                        $gambar_popup = $newname;
                    } else {
                        $error_msg = 'Gagal mengunggah gambar popup.';
                    }
                }
            }

            if (empty($error_msg)) {
                $existing = $conn->query("SELECT id_gallery FROM `gallery` WHERE id_gallery = " . $id_gallery);
                if ($existing && $existing->num_rows > 0) {
                    $stmt_u = $conn->prepare("UPDATE `gallery` SET judul=?, deskripsi_card=?, deskripsi_popup=?, gambar_card=?, gambar_popup=? WHERE id_gallery=?");
                    $stmt_u->bind_param('sssssi', $judul, $deskripsi_card, $deskripsi_popup, $gambar_card, $gambar_popup, $id_gallery);
                    if ($stmt_u->execute()) {
                        $success_msg = 'Konten galeri #' . $id_gallery . ' berhasil diperbarui.';
                    } else {
                        $error_msg = 'Gagal menyimpan: ' . $stmt_u->error;
                    }
                    $stmt_u->close();
                } else {
                    $stmt_i = $conn->prepare("INSERT INTO `gallery` (id_gallery, judul, deskripsi_card, deskripsi_popup, gambar_card, gambar_popup, urutan) VALUES (?,?,?,?,?,?,?)");
                    $stmt_i->bind_param('isssssi', $id_gallery, $judul, $deskripsi_card, $deskripsi_popup, $gambar_card, $gambar_popup, $id_gallery);
                    if ($stmt_i->execute()) {
                        $success_msg = 'Konten galeri #' . $id_gallery . ' berhasil ditambahkan.';
                    } else {
                        $error_msg = 'Gagal menyimpan: ' . $stmt_i->error;
                    }
                    $stmt_i->close();
                }
            }
        }
    }
}

// Ambil data gallery
$gallery_items = [];
$res_g = $conn->query("SELECT * FROM `gallery` ORDER BY urutan ASC, id_gallery ASC");
if ($res_g) {
    while ($row = $res_g->fetch_assoc()) {
        $gallery_items[$row['id_gallery']] = $row;
    }
}

// Seed default jika kosong
if (empty($gallery_items)) {
    $defaults = [
        [1, 'Wahana Kolam & Waterpark', 'Fasilitas Rekreasi Keluarga Modern & Asri', 'Pemandian Patemon menyediakan kolam renang bertingkat serta wahana seluncuran air yang aman dan menyenangkan untuk pengunjung segala usia. Air dialirkan dari sumber mata air alami tanpa kaporit.', 'gambar5.png', 'gambar9.png'],
        [2, 'Kunjungan Mantan Bupati Jember', 'Peninjauan Pemandian Patemon (Periode 2021-2025)', 'Mantan Bupati Jember, Ir. H. Hendy Siswanto, ST. IPU., melakukan peninjauan langsung ke Pemandian Patemon untuk mengecek kelayakan fasilitas wisata.', 'gambar7.png', 'gambar8.png'],
        [3, 'Mata Air Alami Argopuro', 'Air Dingin Jernih Tanpa Bahan Kaporit', 'Limpahan mata air alami dari lereng Pegunungan Argopuro mengalir jernih dan murni tanpa kaporit, menjadikan Pemandian Patemon destinasi favorit keluarga.', 'gambar4.png', 'gambar4.png'],
    ];
    foreach ($defaults as $d) {
        $si = $conn->prepare("INSERT IGNORE INTO `gallery` (id_gallery, judul, deskripsi_card, deskripsi_popup, gambar_card, gambar_popup, urutan) VALUES (?,?,?,?,?,?,?)");
        $si->bind_param('isssssi', $d[0], $d[1], $d[2], $d[3], $d[4], $d[5], $d[0]);
        $si->execute();
        $si->close();
    }
    $res_g2 = $conn->query("SELECT * FROM `gallery` ORDER BY urutan ASC");
    if ($res_g2) {
        while ($row = $res_g2->fetch_assoc()) $gallery_items[$row['id_gallery']] = $row;
    }
}

$extra_css = '
<style>
.gallery-edit-card { border-radius: 16px; background: #fff; border: 1px solid #e2e8f0; overflow: hidden; }
.gallery-preview-img { width: 100%; height: 160px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; cursor: pointer; }
.img-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 4px; }
.upload-zone { border: 2px dashed #cbd5e1; border-radius: 10px; padding: 1rem; text-align: center; cursor: pointer; transition: all 0.2s; font-size: 0.85rem; color: #64748b; }
.upload-zone:hover { border-color: #0284c7; color: #0284c7; background: #f0f9ff; }
</style>
';

require '../../app/layouts/admin_header.php';
?>

<div class="page-content">

<?php if (!empty($success_msg)): ?>
<div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
    <i class="fa-solid fa-circle-check me-2"></i> <?= e($success_msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif (!empty($error_msg)): ?>
<div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
    <i class="fa-solid fa-circle-exclamation me-2"></i> <?= e($error_msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="modern-card mb-4">
    <div class="modern-card-header">
        <span class="fw-bold fs-6 text-dark">
            <i class="fa-solid fa-images text-primary me-2"></i> Daftar Konten Galeri (<?= count($gallery_items) ?>/3 Slot)
        </span>
        <small class="text-muted ms-2">Klik <strong>Edit</strong> untuk mengubah gambar atau deskripsi tiap slot galeri.</small>
    </div>
    <div class="modern-card-body">
        <div class="row g-4">
        <?php for ($slot = 1; $slot <= 3; $slot++):
            $g = $gallery_items[$slot] ?? null;
            $img_card  = $g ? $g['gambar_card']  : 'gambar5.png';
            $img_popup = $g ? $g['gambar_popup'] : 'gambar5.png';
            $judul_g   = $g ? $g['judul']        : '(Belum diisi)';
            $desc_card = $g ? $g['deskripsi_card']  : '';
            $desc_popup= $g ? $g['deskripsi_popup'] : '';
        ?>
        <div class="col-12 col-md-4">
            <div class="gallery-edit-card p-3 h-100 d-flex flex-column gap-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge badge-modern-primary">Slot #<?= $slot ?></span>
                    <button type="button" class="btn btn-sm btn-soft-primary" onclick="openEditModal(<?= $slot ?>)" title="Edit konten galeri #<?= $slot ?>">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                    </button>
                </div>
                <div>
                    <div class="img-label">Gambar Card (Preview)</div>
                    <img src="<?= public_url('img/' . e($img_card)) ?>" alt="Card #<?= $slot ?>" class="gallery-preview-img mb-2" onerror="this.src='<?= public_url('img/gambar5.png') ?>'">
                    <div class="img-label">Gambar Popup (Lightbox)</div>
                    <img src="<?= public_url('img/' . e($img_popup)) ?>" alt="Popup #<?= $slot ?>" class="gallery-preview-img" onerror="this.src='<?= public_url('img/gambar5.png') ?>'">
                </div>
                <div class="mt-1">
                    <div class="fw-bold text-dark mb-1" style="font-size: 0.9rem;"><?= e($judul_g) ?></div>
                    <div class="text-muted small"><?= e(mb_substr($desc_card, 0, 80)) ?><?= strlen($desc_card) > 80 ? '...' : '' ?></div>
                </div>
            </div>
        </div>
        <?php endfor; ?>
        </div>
    </div>
</div>
</div>

<!-- Edit Modals for each slot -->
<?php for ($slot = 1; $slot <= 3; $slot++):
    $g = $gallery_items[$slot] ?? null;
    $img_card  = $g ? $g['gambar_card']  : '';
    $img_popup = $g ? $g['gambar_popup'] : '';
    $judul_g   = $g ? $g['judul']        : '';
    $desc_card = $g ? $g['deskripsi_card']  : '';
    $desc_popup= $g ? $g['deskripsi_popup'] : '';
?>
<div class="modal fade" id="editModal<?= $slot ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <form method="POST" enctype="multipart/form-data" action="<?= route_url('gallery') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id_gallery" value="<?= $slot ?>">
                <input type="hidden" name="gambar_card_current" value="<?= e($img_card) ?>">
                <input type="hidden" name="gambar_popup_current" value="<?= e($img_popup) ?>">

                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h5 class="modal-title fw-bold">Edit Galeri Slot #<?= $slot ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Judul <span class="text-danger">*</span></label>
                        <input type="text" name="judul" class="form-control-modern" value="<?= e($judul_g) ?>" maxlength="100" required placeholder="Judul konten galeri...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Deskripsi Singkat (Card Preview)</label>
                        <input type="text" name="deskripsi_card" class="form-control-modern" value="<?= e($desc_card) ?>" maxlength="200" placeholder="Teks pendek yang tampil di bawah card gambar...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">Deskripsi Popup (Lightbox)</label>
                        <textarea name="deskripsi_popup" class="form-control-modern" rows="3" maxlength="2000" placeholder="Teks panjang yang tampil saat popup dibuka..."><?= e($desc_popup) ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Gambar Card/Preview</label>
                            <?php if (!empty($img_card)): ?>
                            <div class="mb-2">
                                <img src="<?= public_url('img/' . e($img_card)) ?>" alt="Current card" style="width:100%; height: 120px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0;" onerror="this.style.display='none'">
                                <div class="text-muted" style="font-size: 0.7rem; margin-top: 4px;">File saat ini: <?= e($img_card) ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="upload-zone" onclick="document.getElementById('card_file_<?= $slot ?>').click()">
                                <i class="fa-solid fa-cloud-arrow-up text-primary fs-5 mb-1"></i>
                                <div id="card_label_<?= $slot ?>">Klik untuk upload gambar card baru</div>
                                <div class="text-muted" style="font-size: 0.7rem;">JPG, PNG, WEBP (maks. 3 MB)</div>
                            </div>
                            <input type="file" id="card_file_<?= $slot ?>" name="gambar_card" class="d-none" accept="image/*" onchange="previewUpload(this, 'card_label_<?= $slot ?>')">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-dark">Gambar Popup/Lightbox</label>
                            <?php if (!empty($img_popup)): ?>
                            <div class="mb-2">
                                <img src="<?= public_url('img/' . e($img_popup)) ?>" alt="Current popup" style="width:100%; height: 120px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0;" onerror="this.style.display='none'">
                                <div class="text-muted" style="font-size: 0.7rem; margin-top: 4px;">File saat ini: <?= e($img_popup) ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="upload-zone" onclick="document.getElementById('popup_file_<?= $slot ?>').click()">
                                <i class="fa-solid fa-cloud-arrow-up text-primary fs-5 mb-1"></i>
                                <div id="popup_label_<?= $slot ?>">Klik untuk upload gambar popup baru</div>
                                <div class="text-muted" style="font-size: 0.7rem;">JPG, PNG, WEBP (maks. 3 MB)</div>
                            </div>
                            <input type="file" id="popup_file_<?= $slot ?>" name="gambar_popup" class="d-none" accept="image/*" onchange="previewUpload(this, 'popup_label_<?= $slot ?>')">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-brand">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endfor; ?>

<?php
$extra_js = '
<script>
function openEditModal(slot) {
    const modal = new bootstrap.Modal(document.getElementById("editModal" + slot));
    modal.show();
}
function previewUpload(input, labelId) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById(labelId).textContent = "Terpilih: " + file.name + " (" + (file.size / 1024).toFixed(1) + " KB)";
    }
}
</script>
';
require "../../app/layouts/admin_footer.php";
?>
