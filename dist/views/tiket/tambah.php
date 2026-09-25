<?php
require '../../app/config.php';
check_auth([1, 2]);

$active_menu     = 'tiket';
$page_title      = 'Tambah Kategori Tiket - Pemandian Patemon';
$page_heading    = 'Tambah Kategori Tiket';
$page_subheading = 'Tambahkan jenis tiket baru beserta ikon untuk loket dan pemesanan online.';

$header_actions = '
    <a href="' . route_url('admin_tiket') . '" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar
    </a>
';

$error_msg = '';

$available_icons = [
    'fa-ticket'         => 'Tiket Umum (fa-ticket)',
    'fa-person'         => 'Dewasa / Umum (fa-person)',
    'fa-child'          => 'Anak-Anak (fa-child)',
    'fa-person-cane'    => 'Lansia / Disabilitas (fa-person-cane)',
    'fa-users'          => 'Rombongan / Grup (fa-users)',
    'fa-graduation-cap' => 'Pelajar / Mahasiswa (fa-graduation-cap)',
    'fa-star'           => 'VIP / Fasilitas Khusus (fa-star)',
    'fa-motorcycle'     => 'Parkir Motor (fa-motorcycle)',
    'fa-car'            => 'Parkir Mobil (fa-car)',
    'fa-water-ladder'   => 'Kolam Renang / Waterboom (fa-water-ladder)'
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Token keamanan sesi kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $nama_tiket = trim($_POST["nama_tiket"] ?? ''); 
        $harga      = max(0, (int)($_POST["harga"] ?? 0));
        $ikon       = trim($_POST["ikon"] ?? 'fa-ticket');

        if (empty($nama_tiket) || $harga <= 0) {
            $error_msg = "Nama tiket dan tarif harga harus diisi dengan benar.";
        } elseif (mb_strlen($nama_tiket) > 50) {
            $error_msg = "Nama tiket maksimal 50 karakter.";
        } elseif (!preg_match("/^[a-zA-Z0-9\s\(\)\-\/\.\+]+$/", $nama_tiket)) {
            $error_msg = "Nama tiket hanya boleh berisi huruf, angka, spasi, tanda kurung, atau strip (-).";
        } elseif ($harga > 5000000) {
            $error_msg = "Tarif tiket maksimal Rp 5.000.000.";
        } else {
            $stmt = $conn->prepare("INSERT INTO tiket (nama_tiket, harga, ikon) VALUES (?, ?, ?)");
            $stmt->bind_param("sis", $nama_tiket, $harga, $ikon);

            if ($stmt->execute()) {
                header("Location: " . route_url('admin_tiket'));
                exit();
            } else {
                $error_msg = "Gagal menambahkan tiket: " . e($stmt->error);
            }
            $stmt->close();
        }
    }
}

require '../../app/layouts/admin_header.php';
?>

<div class="page-content">
    <div class="row">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="modern-card">
                <div class="modern-card-header">
                    <span class="fw-bold text-dark"><i class="fa-solid fa-tag text-primary me-2"></i> Formulir Kategori Tiket</span>
                </div>
                <div class="modern-card-body">
                    <?php if (!empty($error_msg)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
                            <i class="fa-solid fa-circle-exclamation fs-5"></i>
                            <div><?= e($error_msg) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                        <div class="mb-3">
                            <label for="nama_tiket" class="form-label fw-semibold text-secondary small">Nama Kategori Tiket <span class="text-danger">*</span></label>
                            <div class="input-icon-group">
                                <i class="fa-solid fa-ticket input-icon"></i>
                                <input type="text" id="nama_tiket" name="nama_tiket" class="form-control-modern" placeholder="Contoh: Dewasa, Anak-Anak, Lansia" required maxlength="50" pattern="^[a-zA-Z0-9\s\(\)\-\/\.\+]+$" title="Nama tiket hanya boleh berisi huruf, angka, spasi, atau tanda kurung (maks. 50 karakter)" value="<?= e($_POST['nama_tiket'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="harga" class="form-label fw-semibold text-secondary small">Tarif Tiket (Rupiah) <span class="text-danger">*</span></label>
                            <div class="input-icon-group">
                                <i class="fa-solid fa-money-bill input-icon"></i>
                                <input type="number" id="harga" name="harga" class="form-control-modern" min="500" max="5000000" step="500" placeholder="10000" required value="<?= (int)($_POST['harga'] ?? 10000) ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="ikon" class="form-label fw-semibold text-secondary small">Pilih Ikon Tiket <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-primary" id="iconPreview">
                                    <i class="fa-solid fa-ticket fs-5"></i>
                                </span>
                                <select id="ikon" name="ikon" class="form-select form-control-modern" onchange="updateIconPreview(this.value)">
                                    <?php foreach ($available_icons as $ico => $label): ?>
                                        <option value="<?= $ico ?>" <?= (($_POST['ikon'] ?? 'fa-ticket') === $ico) ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small class="text-muted mt-1 d-block">Ikon ini akan otomatis tampil di loket kasir, nota, dan daftar tiket landing page.</small>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <a href="<?= route_url('admin_tiket') ?>" class="btn btn-light px-4">Batal</a>
                            <button type="submit" class="btn btn-brand px-4">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Tiket
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '
<script>
function updateIconPreview(iconClass) {
    const preview = document.getElementById("iconPreview");
    if (preview) {
        preview.innerHTML = `<i class="fa-solid ${iconClass} fs-5"></i>`;
    }
}
document.addEventListener("DOMContentLoaded", function() {
    const select = document.getElementById("ikon");
    if (select) updateIconPreview(select.value);
});
</script>
';
require '../../app/layouts/admin_footer.php';
?>
