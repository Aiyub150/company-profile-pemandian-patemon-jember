<?php
/**
 * Modul Kelola Kalender Hari Libur & Penutupan Wisata
 * Khusus Super Admin (Level 1) - Feedback-9 Poin 4
 */
require_once __DIR__ . '/../../app/config.php';

// Hak akses: Super Admin (Level 1) & Admin (Level 2)
check_auth([1, 2]);

$active_menu     = 'settings_calendar';
$page_title      = 'Kelola Kalender Libur - Wisata Pemandian Patemon';
$page_heading    = 'Kelola Kalender Hari Libur & Penutupan Wisata';
$page_subheading = 'Atur jadwal libur khusus pengelola, cuti bersama, dan penutupan operasional pemandian.';

$csrf_token = csrf_token();
$msg_success = $_SESSION['flash_success'] ?? '';
$msg_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle Penambahan Jadwal Libur / Tutup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $posted_token = $_POST['csrf_token'] ?? '';

    if (!validate_csrf($posted_token)) {
        $_SESSION['flash_error'] = 'Token keamanan tidak valid atau telah kedaluwarsa.';
        header('Location: ' . route_url('settings_calendar'));
        exit;
    }

    if ($action === 'add') {
        $tanggal    = trim($_POST['tanggal'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        $tipe       = trim($_POST['tipe'] ?? 'libur');

        if (empty($tanggal) || empty($keterangan)) {
            $_SESSION['flash_error'] = 'Tanggal dan keterangan libur/penutupan wajib diisi.';
            header('Location: ' . route_url('settings_calendar'));
            exit;
        }

        if (!in_array($tipe, ['libur', 'tutup_pemeliharaan', 'cuti'], true)) {
            $tipe = 'libur';
        }

        // Cek apakah tanggal tersebut sudah pernah ditambahkan
        $chkStmt = $conn->prepare("SELECT id FROM calendar_holidays WHERE tanggal = ? LIMIT 1");
        $chkStmt->bind_param("s", $tanggal);
        $chkStmt->execute();
        if ($chkStmt->get_result()->num_rows > 0) {
            $_SESSION['flash_error'] = 'Tanggal ' . format_tanggal_indonesia($tanggal) . ' sudah terdaftar dalam jadwal libur/penutupan.';
            $chkStmt->close();
            header('Location: ' . route_url('settings_calendar'));
            exit;
        }
        $chkStmt->close();

        $createdBy = (int)($_SESSION['id_user'] ?? 1);
        $insStmt = $conn->prepare("INSERT INTO calendar_holidays (tanggal, keterangan, tipe, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
        $insStmt->bind_param("sssi", $tanggal, $keterangan, $tipe, $createdBy);

        if ($insStmt->execute()) {
            if (function_exists('log_activity')) {
                log_activity('CALENDAR_ADD', 'calendar_holidays', "Menambahkan jadwal libur/penutupan pada {$tanggal}: {$keterangan} ({$tipe})", $createdBy);
            }
            $_SESSION['flash_success'] = 'Jadwal libur/penutupan berhasil disimpan dan langsung terintegrasi ke kalender dashboard serta beranda!';
        } else {
            $_SESSION['flash_error'] = 'Gagal menyimpan ke basis data: ' . $conn->error;
        }
        $insStmt->close();
        header('Location: ' . route_url('settings_calendar'));
        exit;

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID jadwal tidak valid.';
            header('Location: ' . route_url('settings_calendar'));
            exit;
        }

        $delStmt = $conn->prepare("DELETE FROM calendar_holidays WHERE id = ?");
        $delStmt->bind_param("i", $id);
        if ($delStmt->execute()) {
            if (function_exists('log_activity')) {
                log_activity('CALENDAR_DELETE', 'calendar_holidays', "Menghapus jadwal libur/penutupan ID #{$id}", $_SESSION['id_user'] ?? null);
            }
            $_SESSION['flash_success'] = 'Jadwal libur/penutupan berhasil dihapus.';
        } else {
            $_SESSION['flash_error'] = 'Gagal menghapus jadwal: ' . $conn->error;
        }
        $delStmt->close();
        header('Location: ' . route_url('settings_calendar'));
        exit;
    }
}

// Ambil daftar libur kustom
$customList = [];
$resHolidays = $conn->query("
    SELECT ch.id, ch.tanggal, ch.keterangan, ch.tipe, ch.created_at, u.nama as pembuat
    FROM calendar_holidays ch
    LEFT JOIN users u ON ch.created_by = u.id_user
    ORDER BY ch.tanggal DESC
");
if ($resHolidays) {
    while ($row = $resHolidays->fetch_assoc()) {
        $customList[] = $row;
    }
}

require_once __DIR__ . '/../../app/layouts/admin_header.php';
?>

<div class="row g-4 mb-4">
    <!-- Stat Card 1 -->
    <div class="col-12 col-md-4">
        <div class="card h-100 border-0 shadow-sm" style="border-radius: 16px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(5, 150, 105, 0.02)); border-left: 4px solid #10b981 !important;">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px; background: rgba(16, 185, 129, 0.15); color: #059669;">
                    <i class="fa-solid fa-calendar-check fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Jadwal Khusus</div>
                    <h3 class="fw-bold mb-0 text-success"><?= count($customList) ?></h3>
                    <div class="text-muted small">Tercatat di kalender wisata</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Card 2: Hari Ini -->
    <?php
    $todayClosure = get_active_closure_today();
    ?>
    <div class="col-12 col-md-8">
        <div class="card h-100 border-0 shadow-sm" style="border-radius: 16px; border-left: 4px solid <?= $todayClosure ? '#ef4444' : '#0284c7' ?> !important;">
            <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Status Operasional Wisata Hari Ini (<?= format_tanggal_indonesia(date('Y-m-d')) ?>)</div>
                    <?php if ($todayClosure): ?>
                        <h5 class="fw-bold text-danger mb-1">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> TUTUP / LIBUR: <?= e($todayClosure['keterangan']) ?>
                        </h5>
                        <p class="text-muted small mb-0">Banner peringatan penutupan otomatis aktif di beranda pengunjung.</p>
                    <?php else: ?>
                        <h5 class="fw-bold text-primary mb-1">
                            <i class="fa-solid fa-circle-check text-success me-1"></i> BUKA NORMAL SESUAI JADWAL
                        </h5>
                        <p class="text-muted small mb-0">Tidak ada jadwal penutupan khusus yang terdaftar untuk hari ini.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <a href="<?= route_url('dashboard') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="fa-solid fa-table-cells-large me-1"></i> Cek Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($msg_success): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i> <?= e($msg_success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($msg_error): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= e($msg_error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form Tambah Hari Libur / Tutup -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-calendar-plus text-primary me-2"></i> Tambah Jadwal Libur/Tutup
                </h5>
                <p class="text-muted small">Entri tanggal libur khusus atau penutupan operasional pemandian.</p>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= route_url('settings_calendar') ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label for="tanggal" class="form-label fw-semibold small">Tanggal Libur / Penutupan</label>
                        <input type="date" class="form-control rounded-3" id="tanggal" name="tanggal" required value="<?= date('Y-m-d') ?>">
                        <div class="form-text small">Pilih tanggal spesifik pelaksanaan.</div>
                    </div>

                    <div class="mb-3">
                        <label for="tipe" class="form-label fw-semibold small">Kategori Jadwal</label>
                        <select class="form-select rounded-3" id="tipe" name="tipe" required>
                            <option value="libur">Libur Khusus Pengelola</option>
                            <option value="tutup_pemeliharaan">Tutup Pemeliharaan / Renovasi</option>
                            <option value="cuti">Cuti Bersama Wisata</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="keterangan" class="form-label fw-semibold small">Keterangan / Alasan</label>
                        <textarea class="form-control rounded-3" id="keterangan" name="keterangan" rows="3" placeholder="Contoh: Pembersihan rutin kolam renang utama..." required maxlength="255"></textarea>
                        <div class="form-text small">Keterangan akan tampil di kalender dashboard dan banner beranda.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-3 fw-bold shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Jadwal Kalender
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Hari Libur / Tutup Khusus -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fa-solid fa-list-check text-success me-2"></i> Daftar Jadwal Khusus Pengelola
                    </h5>
                    <p class="text-muted small mb-0">Jadwal yang dikelola langsung terintegrasi secara dinamis.</p>
                </div>
            </div>
            <div class="card-body p-4">
                <?php if (empty($customList)): ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-calendar-xmark text-muted opacity-50 display-4 mb-3"></i>
                        <h6 class="fw-bold text-dark">Belum Ada Jadwal Libur Khusus</h6>
                        <p class="text-muted small">Tambahkan jadwal libur atau renovasi fasilitas melalui formulir di samping.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Tanggal</th>
                                    <th>Keterangan</th>
                                    <th>Kategori</th>
                                    <th>Dibuat Oleh</th>
                                    <th class="text-end pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customList as $item): 
                                    $isPast = (strtotime($item['tanggal']) < strtotime('today'));
                                    $isToday = ($item['tanggal'] === date('Y-m-d'));
                                ?>
                                    <tr class="<?= $isToday ? 'table-warning' : ($isPast ? 'text-muted' : '') ?>">
                                        <td class="ps-3 fw-bold">
                                            <?= format_tanggal_indonesia($item['tanggal']) ?>
                                            <?php if ($isToday): ?>
                                                <span class="badge bg-danger rounded-pill ms-1">Hari Ini</span>
                                            <?php elseif ($isPast): ?>
                                                <span class="badge bg-secondary rounded-pill ms-1" style="font-size: 0.65rem;">Lewat</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark"><?= e($item['keterangan']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($item['tipe'] === 'tutup_pemeliharaan'): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-2.5 py-1">
                                                    <i class="fa-solid fa-screwdriver-wrench me-1"></i> Tutup Pemeliharaan
                                                </span>
                                            <?php elseif ($item['tipe'] === 'cuti'): ?>
                                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 rounded-pill px-2.5 py-1">
                                                    <i class="fa-solid fa-umbrella-beach me-1"></i> Cuti Bersama
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2.5 py-1">
                                                    <i class="fa-solid fa-ban me-1"></i> Libur Pengelola
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?= e($item['pembuat'] ?? 'Super Admin') ?><br>
                                            <span style="font-size: 0.72rem;"><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <form method="POST" action="<?= route_url('settings_calendar') ?>" class="d-inline form-delete-calendar">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                                <button type="button" class="btn btn-outline-danger btn-sm rounded-circle p-0 d-inline-flex align-items-center justify-content-center btn-del-cal" style="width: 32px; height: 32px;" title="Hapus Jadwal" data-tanggal="<?= e(format_tanggal_indonesia($item['tanggal'])) ?>" data-ket="<?= e($item['keterangan']) ?>">
                                                    <i class="fa-solid fa-trash-can" style="font-size: 0.8rem;"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-del-cal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var form = this.closest('form');
            var tgl = this.getAttribute('data-tanggal');
            var ket = this.getAttribute('data-ket');

            Swal.fire({
                title: 'Hapus Jadwal Libur?',
                html: 'Apakah Anda yakin ingin menghapus jadwal <strong>' + ket + '</strong> pada tanggal <strong>' + tgl + '</strong> dari kalender wisata?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hapus Jadwal',
                cancelButtonText: 'Batal'
            }).then(function(result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>

<?php
require_once __DIR__ . '/../../app/layouts/admin_footer.php';
?>
