<?php
/**
 * Modul Kritik & Saran Pengunjung
 * Sesuai Feedback-2 Poin 4 & Feedback-3 Poin 9
 */
require '../../app/config.php';
check_auth([1, 2]);

$active_menu     = 'ulasan';
$page_title      = 'Kritik & Saran Pengunjung - Pemandian Patemon';
$page_heading    = 'Kritik & Saran Pengunjung';
$page_subheading = 'Feedback, ulasan, dan testimoni masuk dari wisatawan Pemandian Patemon.';

// Tandai semua ulasan belum dibaca sebagai sudah dibaca (bersihkan notif badge)
$col_check = $conn->query("SHOW COLUMNS FROM ulasan LIKE 'is_read'");
if ($col_check && $col_check->num_rows > 0) {
    @$conn->query("UPDATE ulasan SET is_read = 1 WHERE is_read = 0");
}


// Filter Parameter
$search_q     = trim($_GET['q'] ?? '');
$filter_date  = trim($_GET['date'] ?? '');
$filter_month = (int)($_GET['month'] ?? 0);
$filter_year  = (int)($_GET['year'] ?? 0);

$where = ["1=1"];
$params = [];
$types = "";

if (!empty($search_q)) {
    $where[] = "(username LIKE ? OR email LIKE ? OR no_telepon LIKE ? OR ulasan LIKE ?)";
    $like = "%" . $search_q . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

if (!empty($filter_date)) {
    $where[] = "DATE(tgl_ulasan) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if ($filter_month > 0 && $filter_month <= 12) {
    $where[] = "MONTH(tgl_ulasan) = ?";
    $params[] = $filter_month;
    $types .= "i";
}

if ($filter_year > 0) {
    $where[] = "YEAR(tgl_ulasan) = ?";
    $params[] = $filter_year;
    $types .= "i";
}

$sql = "SELECT * FROM ulasan WHERE " . implode(" AND ", $where) . " ORDER BY tgl_ulasan DESC, id_ulasan DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Ambil list tahun yang ada di database untuk opsi dropdown
$res_years = $conn->query("SELECT DISTINCT YEAR(tgl_ulasan) as yr FROM ulasan WHERE tgl_ulasan IS NOT NULL ORDER BY yr DESC");
$available_years = [];
if ($res_years) {
    while ($y = $res_years->fetch_assoc()) {
        if (!empty($y['yr'])) $available_years[] = (int)$y['yr'];
    }
}
if (empty($available_years)) {
    $available_years = [(int)date('Y')];
}

$daftar_bulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$total_feedback = $result ? $result->num_rows : 0;

require '../../app/layouts/admin_header.php';
?>

<div class="page-content">
    <!-- Filter Card & Search Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" action="" class="row g-3 align-items-end">
                <!-- Search Multi-Kolom -->
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted mb-1">
                        <i class="fa-solid fa-magnifying-glass me-1 text-primary"></i> Cari Data Ulasan
                    </label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-magnifying-glass input-icon"></i>
                        <input type="text" name="q" class="form-control-modern" placeholder="Ketik nama, kontak, atau kata kunci pesan..." value="<?= e($search_q) ?>">
                    </div>
                </div>

                <!-- Filter Tanggal Spesifik -->
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">
                        <i class="fa-regular fa-calendar-day me-1 text-primary"></i> Hari / Tanggal
                    </label>
                    <input type="date" name="date" class="form-control-modern" value="<?= e($filter_date) ?>">
                </div>

                <!-- Filter Bulan -->
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">
                        <i class="fa-regular fa-calendar-days me-1 text-primary"></i> Bulan
                    </label>
                    <select name="month" class="form-select-modern">
                        <option value="">Semua Bulan</option>
                        <?php foreach ($daftar_bulan as $num => $nama): ?>
                            <option value="<?= $num ?>" <?= ($filter_month === $num) ? 'selected' : '' ?>><?= $nama ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Tahun -->
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">
                        <i class="fa-solid fa-calendar-check me-1 text-primary"></i> Tahun
                    </label>
                    <select name="year" class="form-select-modern">
                        <option value="">Semua Tahun</option>
                        <?php foreach ($available_years as $yr): ?>
                            <option value="<?= $yr ?>" <?= ($filter_year === $yr) ? 'selected' : '' ?>><?= $yr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tombol Filter & Reset -->
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-brand flex-grow-1" style="height: 42px;">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                    <?php if (!empty($search_q) || !empty($filter_date) || $filter_month > 0 || $filter_year > 0): ?>
                        <a href="<?= route_url('ulasan') ?>" class="btn btn-outline-secondary" title="Reset Semua Filter" style="height: 42px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Daftar Feedback -->
    <div class="modern-card">
        <div class="modern-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold fs-6" style="color: #0f172a;">
                    <i class="fa-solid fa-comments text-primary me-2"></i> Daftar Kritik & Saran Masuk
                </span>
                <span class="badge bg-soft-primary text-primary px-2.5 py-1 fw-bold rounded-pill">
                    <?= $total_feedback ?> Data Ditemukan
                </span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="input-icon-group" style="width: 240px;">
                    <i class="fa-solid fa-magnifying-glass input-icon" style="font-size: 0.85rem; left: 0.85rem;"></i>
                    <input type="text" id="liveTableSearch" class="form-control-modern form-control-sm" placeholder="Pencarian cepat tabel..." style="padding-left: 2.3rem;">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table-modern" id="tableUlasan">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th>Pengirim</th>
                        <th>Kontak (Email / Telp)</th>
                        <th class="text-center" style="width: 170px;">Isi Pesan</th>
                        <th>Tanggal Masuk</th>
                        <th class="text-center" style="width: 90px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php 
                    $no = 1;
                    while ($row = $result->fetch_assoc()): 
                        $raw_ulasan = $row["ulasan"];
                        $msg_length = mb_strlen($raw_ulasan);
                        $formatted_date = date('d M Y', strtotime($row["tgl_ulasan"]));
                    ?>
                        <tr>
                            <td class="text-center text-muted fw-semibold"><?= $no++ ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width: 38px; height: 38px; border-radius: 12px; background: linear-gradient(135deg, #e0f2fe, #bae6fd); color: #0284c7; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0;">
                                        <?= strtoupper(substr($row['username'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.925rem;"><?= e($row["username"]) ?></div>
                                        <small class="text-muted" style="font-size: 0.75rem;">ID: #FB-<?= str_pad($row['id_ulasan'], 4, '0', STR_PAD_LEFT) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="color: #334155; font-size: 0.875rem;">
                                    <i class="fa-regular fa-envelope text-muted me-1"></i> <?= e($row["email"] ?: '-') ?>
                                </div>
                                <?php if (!empty($row["no_telepon"])): ?>
                                    <div class="small text-muted mt-0.5">
                                        <i class="fa-solid fa-phone text-muted me-1"></i> <?= e($row["no_telepon"]) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <!-- Kolom Pesan Diganti Tombol Modal (Feedback-2 Poin 4) -->
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-soft-primary px-3 py-1.5 rounded-pill fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" 
                                        onclick="showDetailModal('<?= e(addslashes($row['username'])) ?>', '<?= e(addslashes($row['email'] ?: '-')) ?>', '<?= e(addslashes($row['no_telepon'] ?: '-')) ?>', '<?= $formatted_date ?>', <?= htmlspecialchars(json_encode($raw_ulasan), ENT_QUOTES, 'UTF-8') ?>)">
                                    <i class="fa-solid fa-envelope-open-text"></i>
                                    <span>Lihat Pesan</span>
                                </button>
                                <div class="text-muted mt-1" style="font-size: 0.72rem;">
                                    <?= $msg_length ?> Karakter
                                </div>
                            </td>
                            <td>
                                <div class="text-dark fw-semibold small"><?= $formatted_date ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;"><?= date('l', strtotime($row["tgl_ulasan"])) ?></div>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-soft-danger btn-action-icon" title="Hapus Ulasan" onclick="confirmDelete(<?= (int)$row['id_ulasan'] ?>)">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="fa-regular fa-comment-dots fs-1 mb-2 d-block text-secondary"></i>
                            <h6 class="fw-bold text-dark mb-1">Tidak Ada Kritik & Saran</h6>
                            <p class="small text-muted mb-0">Belum ada kritik dan saran yang cocok dengan filter pencarian ini.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Detail Ulasan (Feedback-2 Poin 4) -->
<div class="modal fade" id="modalDetailUlasan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 540px;">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4" style="background: #f8fafc;">
                <h5 class="modal-title fw-bold text-dark fs-6 d-flex align-items-center gap-2 mb-0">
                    <i class="fa-solid fa-comment-dots text-primary"></i> Isi Kritik & Saran Pengunjung
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                    <div id="modalAvatar" style="width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #0284c7, #38bdf8); color: #fff; font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);">
                        U
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="fw-bold fs-6 text-dark text-truncate" id="modalUsername">-</div>
                        <div class="small text-muted" id="modalMeta">-</div>
                    </div>
                </div>

                <div class="mb-2 d-flex justify-content-between align-items-center">
                    <label class="form-label text-muted small fw-semibold mb-0">Teks Pesan / Masukan Lengkap:</label>
                    <span class="badge bg-light text-secondary border small" id="modalCharBadge">-</span>
                </div>
                <div class="p-3 rounded-3" style="background: #f1f5f9; border-left: 4px solid #0284c7; font-size: 0.95rem; color: #0f172a; line-height: 1.65; white-space: pre-wrap; word-break: break-word; max-height: 320px; overflow-y: auto;" id="modalText">
                </div>
            </div>
            <div class="modal-footer border-top py-2.5 px-4 bg-light d-flex justify-content-between align-items-center">
                <span class="small text-muted"><i class="fa-solid fa-shield-halved text-success me-1"></i> Data Terverifikasi</span>
                <button type="button" class="btn btn-secondary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '
<script>
setupTableSearch("liveTableSearch", "tableUlasan");

function showDetailModal(username, email, phone, date, fullText) {
    document.getElementById("modalUsername").textContent = username;
    document.getElementById("modalAvatar").textContent = username.charAt(0).toUpperCase();
    
    let metaHtml = `<i class="fa-regular fa-envelope me-1"></i> ${email}`;
    if (phone && phone !== "-") {
        metaHtml += ` &bull; <i class="fa-solid fa-phone me-1"></i> ${phone}`;
    }
    metaHtml += ` &bull; <i class="fa-regular fa-calendar me-1"></i> ${date}`;
    document.getElementById("modalMeta").innerHTML = metaHtml;
    
    document.getElementById("modalText").textContent = fullText;
    document.getElementById("modalCharBadge").textContent = fullText.length + " Karakter";

    const modal = new bootstrap.Modal(document.getElementById("modalDetailUlasan"));
    modal.show();
}

function confirmDelete(id) {
    Swal.fire({
        title: "Hapus Kritik & Saran?",
        text: "Tindakan ini permanen dan tidak dapat dibatalkan.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Hapus",
        cancelButtonText: "Batal"
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "' . route_url('ulasan_delete') . '?id=" + id + "&csrf=' . csrf_token() . '";
        }
    });
}
</script>
';

include '../../app/layouts/admin_footer.php';
?>
