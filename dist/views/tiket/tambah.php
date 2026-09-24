<?php
require '../../app/config.php';
check_auth([1]);

$active_menu = 'tiket';
$base_view = '..';

$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Token keamanan sesi kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $nama_tiket = trim($_POST["nama_tiket"] ?? ''); 
        $harga = max(0, (int)($_POST["harga"] ?? 0));

        if (empty($nama_tiket) || $harga <= 0) {
            $error_msg = "Nama tiket dan tarif harga harus diisi dengan benar.";
        } else {
            $stmt = $conn->prepare("INSERT INTO tiket (nama_tiket, harga) VALUES (?, ?)");
            $stmt->bind_param("si", $nama_tiket, $harga);

            if ($stmt->execute()) {
                header("Location: tiket.php");
                exit();
            } else {
                $error_msg = "Gagal menambahkan tiket: " . e($stmt->error);
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kategori Tiket - Pemandian Patemon</title>

    <link rel="icon" type="image/x-icon" href="../../../public/img/icon.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/assets/css/main/app.css">
    <link rel="stylesheet" href="../../../public/css/modern-theme.css">
</head>

<body>
    <div id="app">
        <?php include '../../app/partials/sidebar.php'; ?>

        <div id="main">
            <!-- Header Topbar -->
            <header class="mb-4 d-flex justify-content-between align-items-center">
                <a href="#" class="burger-btn d-block d-xl-none text-dark">
                    <i class="fa-solid fa-bars fs-3"></i>
                </a>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <a href="tiket.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar Tiket
                    </a>
                </div>
            </header>

            <div class="page-heading mb-4">
                <h2 class="fw-bold text-dark mb-1" style="font-size: 1.75rem;">Tambah Kategori Tiket</h2>
                <p class="text-muted mb-0">Tambahkan jenis tiket baru untuk loket pemandian.</p>
            </div>

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
                                            <input type="text" id="nama_tiket" name="nama_tiket" class="form-control-modern" placeholder="Contoh: Dewasa, Anak-Anak, Rombongan" required value="<?= e($_POST['nama_tiket'] ?? '') ?>">
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="harga" class="form-label fw-semibold text-secondary small">Tarif Tiket (Rupiah) <span class="text-danger">*</span></label>
                                        <div class="input-icon-group">
                                            <i class="fa-solid fa-money-bill input-icon"></i>
                                            <input type="number" id="harga" name="harga" class="form-control-modern" min="500" step="500" placeholder="10000" required value="<?= (int)($_POST['harga'] ?? 10000) ?>">
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-brand">
                                            <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Kategori
                                        </button>
                                        <a href="tiket.php" class="btn btn-outline-secondary">Batal</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <?php include '../../app/partials/footer.php'; ?>
            </div>
        </div>
    </div>

    <script src="../../../public/assets/js/bootstrap.js"></script>
</body>
</html>
