<?php
require '../../app/config.php';
check_auth([1]);

$active_menu = 'tiket';
$base_view = '..';

$sql = "SELECT * FROM tiket ORDER BY id_tiket ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori Tiket - Pemandian Patemon</title>

    <link rel="icon" type="image/x-icon" href="../../../public/img/icon.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/assets/css/main/app.css">
    <link rel="stylesheet" href="../../../public/css/modern-theme.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                    <span class="badge badge-modern-primary">Admin Loket</span>
                </div>
            </header>

            <div class="page-heading mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold mb-1" style="font-size: 1.75rem; color: #0f172a;">Tarif & Kategori Tiket</h2>
                        <p class="text-muted mb-0">Atur harga tiket masuk pengunjung untuk loket kasir dan pemesanan online.</p>
                    </div>
                    <div>
                        <a href="tambah.php" class="btn btn-brand">
                            <i class="fa-solid fa-plus me-1"></i> Tambah Kategori Tiket
                        </a>
                    </div>
                </div>
            </div>

            <div class="page-content">
                <div class="modern-card">
                    <div class="modern-card-header">
                        <span class="fw-bold fs-6" style="color: #0f172a;"><i class="fa-solid fa-tags text-primary me-2"></i> Daftar Kategori Tiket</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">ID</th>
                                    <th>Kategori Tiket</th>
                                    <th>Tarif Masuk</th>
                                    <th class="text-center" style="width: 140px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong class="text-primary">#<?= (int)$row["id_tiket"] ?></strong></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="metric-icon-box <?= ($row['nama_tiket'] === 'Dewasa') ? 'blue' : 'amber' ?>" style="width: 36px; height: 36px; font-size: 0.95rem;">
                                                    <i class="fa-solid <?= ($row['nama_tiket'] === 'Dewasa') ? 'fa-person' : 'fa-child' ?>"></i>
                                                </div>
                                                <span class="fw-bold" style="color: #0f172a;"><?= e($row["nama_tiket"]) ?></span>
                                            </div>
                                        </td>
                                        <td class="fw-extrabold text-primary fs-6"><?= format_rupiah($row["harga"]) ?></td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                <a class="btn btn-sm btn-soft-primary btn-action-icon" title="Ubah Tarif" href="update.php?id=<?= $row["id_tiket"] ?>">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-soft-danger btn-action-icon" title="Hapus Tiket" onclick="confirmDelete(<?= (int)$row['id_tiket'] ?>, '<?= e($row['nama_tiket']) ?>')">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-5">
                                        Belum ada kategori tiket terdaftar.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <?php include '../../app/partials/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../../public/assets/js/bootstrap.js"></script>
    <script>
    function confirmDelete(id, name) {
        Swal.fire({
            title: 'Hapus Kategori Tiket ' + name + '?',
            text: 'Pastikan tiket ini tidak lagi digunakan pada transaksi aktif!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete.php?id=' + id + '&csrf=<?= csrf_token() ?>';
            }
        });
    }
    </script>
</body>
</html>
