<?php
/**
 * Modul Kelola Event & Pop-up Notifikasi Beranda
 * Sesuai Feedback-7 Poin 6 - Khusus Admin & Super Admin (Level 1 & 2)
 */
require_once __DIR__ . '/../../app/config.php';
check_auth([1, 2]);

$active_menu     = 'events';
$page_title      = 'Kelola Event Notifikasi - Pemandian Patemon';
$page_heading    = 'Kelola Event & Pop-up Notifikasi';
$page_subheading = 'Atur flyer dan promosi acara yang tampil otomatis di pop-up beranda pengunjung (Rasio 9:16, maks. 5 event aktif).';

$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Hitung jumlah event aktif saat ini
$resActiveCount = $conn->query("SELECT COUNT(*) as total FROM events WHERE is_active = 1 AND deleted_at IS NULL");
$active_events_count = (int)($resActiveCount->fetch_assoc()['total'] ?? 0);

// Ambil semua event yang belum dihapus
$events = [];
$resEvents = $conn->query("SELECT * FROM events WHERE deleted_at IS NULL ORDER BY is_active DESC, urutan ASC, created_at DESC");
if ($resEvents) {
    while ($row = $resEvents->fetch_assoc()) {
        $events[] = $row;
    }
}

require_once __DIR__ . '/../../app/partials/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-3">
    <!-- Header Page Actions -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill bg-primary px-3 py-2">
                    <i class="fa-solid fa-bullhorn me-1"></i> Pop-up Notifikasi
                </span>
                <span class="badge rounded-pill <?= ($active_events_count >= 5) ? 'bg-warning text-dark' : 'bg-success' ?> px-3 py-2">
                    <i class="fa-solid fa-circle-check me-1"></i> <?= $active_events_count ?> / 5 Event Aktif
                </span>
            </div>
            <p class="text-muted small mb-0 mt-2">
                Event aktif akan otomatis dimunculkan sebagai pop-up interaktif pada kunjungan pertama di halaman depan website.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= route_url('home') ?>" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Pratinjau di Beranda
            </a>
            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahEvent">
                <i class="fa-solid fa-plus me-1"></i> Tambah Event Baru
            </button>
        </div>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center gap-3 mb-4" role="alert">
            <div class="rounded-circle bg-success text-white p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                <i class="fa-solid fa-check fs-6"></i>
            </div>
            <div>
                <strong class="d-block">Berhasil!</strong>
                <span class="small"><?= e($success_msg) ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center gap-3 mb-4" role="alert">
            <div class="rounded-circle bg-danger text-white p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                <i class="fa-solid fa-exclamation fs-6"></i>
            </div>
            <div>
                <strong class="d-block">Terjadi Kesalahan!</strong>
                <span class="small"><?= e($error_msg) ?></span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Banner Rekomendasi Format 9:16 -->
    <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: linear-gradient(135deg, rgba(2, 132, 199, 0.08) 0%, rgba(3, 105, 161, 0.04) 100%); border-left: 5px solid #0284c7 !important;">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                    <i class="fa-solid fa-crop-simple fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-primary">Rekomendasi Rasio & Format Gambar</h6>
                    <p class="small text-muted mb-0" style="line-height: 1.55;">
                        Untuk hasil tampilan pop-up yang paling optimal menyerupai banner digital modern, disarankan menggunakan gambar berformat <strong>JPG, PNG, atau WebP</strong> dengan <strong>rasio potret 9:16</strong> (contoh: <code>1080 x 1920 px</code> atau <code>720 x 1280 px</code>). 
                        Jika rasio gambar berbeda, kontainer tetap terkunci pada proporsi 9:16 dan gambar ditampilkan utuh tanpa distorsi (<em>contain fit</em>).
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Grid Daftar Event -->
    <?php if (empty($events)): ?>
        <div class="card border-0 rounded-4 shadow-sm text-center py-5">
            <div class="card-body">
                <div class="mb-3 text-muted">
                    <i class="fa-solid fa-bullhorn fa-4x opacity-25"></i>
                </div>
                <h5 class="fw-bold text-dark">Belum Ada Event Notifikasi</h5>
                <p class="text-muted small mb-4">Mulai tambahkan flyer promosi atau info agenda Pemandian Patemon untuk ditampilkan kepada pengunjung.</p>
                <button type="button" class="btn btn-primary rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#modalTambahEvent">
                    <i class="fa-solid fa-plus me-1"></i> Tambah Event Pertama
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($events as $evt): ?>
                <?php
                $imgSrc = public_url('img/events/' . $evt['gambar']);
                $isActive = (int)$evt['is_active'] === 1;
                ?>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <div class="card h-100 border-0 rounded-4 shadow-sm overflow-hidden d-flex flex-column" style="transition: transform 0.2s, box-shadow 0.2s;">
                        <!-- Container 9:16 Preview Box -->
                        <div class="position-relative w-100" style="aspect-ratio: 9 / 16; background: #0f172a; overflow: hidden;">
                            <!-- Blurred Backdrop for Non 9:16 Aspect Ratios -->
                            <div style="position: absolute; inset: 0; background-image: url('<?= e($imgSrc) ?>'); background-size: cover; background-position: center; filter: blur(12px) opacity(0.45); transform: scale(1.15);"></div>
                            <!-- Main Image -->
                            <img src="<?= e($imgSrc) ?>" alt="<?= e($evt['judul']) ?>" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; z-index: 1;">
                            
                            <!-- Badges Overlay -->
                            <div class="position-absolute top-0 start-0 m-3 d-flex flex-column gap-1" style="z-index: 2;">
                                <?php if ($isActive): ?>
                                    <span class="badge bg-success rounded-pill px-2.5 py-1.5 shadow-sm">
                                        <i class="fa-solid fa-eye me-1"></i> Aktif di Beranda
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary rounded-pill px-2.5 py-1.5 shadow-sm">
                                        <i class="fa-solid fa-eye-slash me-1"></i> Nonaktif
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="position-absolute top-0 end-0 m-3" style="z-index: 2;">
                                <span class="badge bg-dark bg-opacity-75 rounded-pill px-2.5 py-1.5 shadow-sm">
                                    Urutan: #<?= (int)$evt['urutan'] ?>
                                </span>
                            </div>
                        </div>

                        <!-- Card Body & Controls -->
                        <div class="card-body p-3 d-flex flex-column justify-content-between flex-grow-1">
                            <div>
                                <h6 class="fw-bold text-dark text-truncate mb-1" title="<?= e($evt['judul']) ?>">
                                    <?= e($evt['judul']) ?>
                                </h6>
                                <p class="text-muted small mb-3">
                                    <i class="fa-regular fa-clock me-1"></i> <?= date('d M Y, H:i', strtotime($evt['created_at'])) ?>
                                </p>
                            </div>

                            <div class="pt-2 border-top d-flex align-items-center justify-content-between gap-2">
                                <!-- Toggle Active Status Form -->
                                <form method="POST" action="<?= route_url('events_toggle') ?>" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id_event" value="<?= (int)$evt['id_event'] ?>">
                                    <?php if ($isActive): ?>
                                        <button type="submit" class="btn btn-outline-warning btn-sm rounded-pill px-3" title="Nonaktifkan dari pop-up">
                                            <i class="fa-solid fa-pause me-1"></i> Nonaktifkan
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-outline-success btn-sm rounded-pill px-3" <?= ($active_events_count >= 5) ? 'disabled title="Batas maksimal 5 event aktif tercapai"' : 'title="Aktifkan di pop-up beranda"' ?>>
                                            <i class="fa-solid fa-play me-1"></i> Aktifkan
                                        </button>
                                    <?php endif; ?>
                                </form>

                                <!-- Delete Event Form with Confirmation -->
                                <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-2.5" onclick="confirmDeleteEvent(<?= (int)$evt['id_event'] ?>, '<?= e(addslashes($evt['judul'])) ?>')" title="Hapus Event">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah Event -->
<div class="modal fade" id="modalTambahEvent" tabindex="-1" aria-labelledby="modalTambahEventLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold" id="modalTambahEventLabel">
                        <i class="fa-solid fa-bullhorn text-primary me-2"></i> Tambah Event Notifikasi Baru
                    </h5>
                    <p class="text-muted small mb-0">Unggah poster flyer untuk pop-up promosi / agenda di beranda.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= route_url('events_tambah') ?>" enctype="multipart/form-data" id="formTambahEvent">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <!-- Input Kolom Kiri -->
                        <div class="col-md-7">
                            <div class="mb-3">
                                <label for="judul" class="form-label fw-semibold small">Judul / Nama Acara <span class="text-danger">*</span></label>
                                <input type="text" class="form-control rounded-3" id="judul" name="judul" placeholder="Contoh: Gebyar Libur Lebaran Pemandian Patemon" required maxlength="150">
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label for="urutan" class="form-label fw-semibold small">Urutan Prioritas</label>
                                    <input type="number" class="form-control rounded-3" id="urutan" name="urutan" value="1" min="1" max="99" required>
                                    <div class="form-text small">Nomor urutan kemunculan di slider.</div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold small">Status Awal</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= ($active_events_count < 5) ? 'checked' : '' ?> <?= ($active_events_count >= 5) ? 'disabled' : '' ?>>
                                        <label class="form-check-label small" for="is_active">
                                            <?= ($active_events_count >= 5) ? '<span class="text-warning fw-semibold">Penuh (5 Aktif)</span>' : 'Langsung Aktifkan' ?>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="gambar" class="form-label fw-semibold small">Pilih Berkas Flyer <span class="text-danger">*</span></label>
                                <input type="file" class="form-control rounded-3" id="gambar" name="gambar" accept="image/jpeg,image/png,image/webp" required onchange="previewEventImage(this)">
                                <div class="form-text small">Format JPG, PNG, atau WebP (Maks. 2 MB). Disarankan rasio 9:16 (1080x1920 px). Dilindungi validasi anti-webshell dan pembersihan metadata otomatis.</div>
                            </div>
                        </div>

                        <!-- Pratinjau Flyer Kolom Kanan (9:16 Ratio Box) -->
                        <div class="col-md-5 d-flex flex-column align-items-center">
                            <span class="fw-semibold small text-muted mb-2">Pratinjau Pop-up (Rasio 9:16)</span>
                            <div class="position-relative rounded-4 overflow-hidden border shadow-sm" style="width: 180px; aspect-ratio: 9 / 16; background: #0f172a;">
                                <div id="previewBackdrop" style="position: absolute; inset: 0; background-size: cover; background-position: center; filter: blur(8px) opacity(0.5); transform: scale(1.15);"></div>
                                <img id="previewEventImg" src="" alt="Pratinjau Flyer" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; z-index: 1; display: none;">
                                <div id="previewPlaceholder" class="position-absolute inset-0 d-flex flex-column align-items-center justify-content-center text-center p-3 text-white-50" style="z-index: 1; width: 100%; height: 100%;">
                                    <i class="fa-solid fa-image fa-2x mb-2 opacity-50"></i>
                                    <span style="font-size: 0.75rem;">Pilih berkas gambar untuk melihat pratinjau</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm" id="btnSubmitEvent">
                        <i class="fa-solid fa-cloud-arrow-up me-1"></i> Simpan & Unggah Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Form for Delete Action -->
<form id="formDeleteEvent" method="POST" action="<?= route_url('events_delete') ?>" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id_event" id="delete_id_event" value="">
</form>

<script>
    function previewEventImage(input) {
        const file = input.files && input.files[0];
        const previewImg = document.getElementById('previewEventImg');
        const previewBackdrop = document.getElementById('previewBackdrop');
        const placeholder = document.getElementById('previewPlaceholder');

        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
                previewBackdrop.style.backgroundImage = `url('${e.target.result}')`;
                placeholder.style.display = 'none';
            };
            reader.readAsDataURL(file);
        } else {
            previewImg.src = '';
            previewImg.style.display = 'none';
            previewBackdrop.style.backgroundImage = 'none';
            placeholder.style.display = 'flex';
        }
    }

    function confirmDeleteEvent(idEvent, judul) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Hapus Event Ini?',
                html: `Apakah Anda yakin ingin menghapus event <strong>"${judul}"</strong>?<br><small class="text-muted">Data akan dipindahkan ke soft-delete dan dapat dipulihkan sewaktu-waktu.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fa-solid fa-trash me-1"></i> Ya, Hapus',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-4 shadow',
                    confirmButton: 'rounded-pill px-4',
                    cancelButton: 'rounded-pill px-4'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete_id_event').value = idEvent;
                    document.getElementById('formDeleteEvent').submit();
                }
            });
        } else {
            if (confirm(`Apakah Anda yakin ingin menghapus event "${judul}"?`)) {
                document.getElementById('delete_id_event').value = idEvent;
                document.getElementById('formDeleteEvent').submit();
            }
        }
    }
</script>

<?php require_once __DIR__ . '/../../app/partials/footer.php'; ?>
