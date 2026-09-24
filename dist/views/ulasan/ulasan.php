<?php
require '../../app/config.php';
check_auth([1]);

$active_menu = 'ulasan';
$base_view = '..';

$sql = "SELECT * FROM ulasan ORDER BY tgl_ulasan DESC, id_ulasan DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kritik & Saran - Pemandian Patemon</title>

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
                    <span class="badge badge-modern-primary">Feedback Pengunjung</span>
                </div>
            </header>

            <div class="page-heading mb-4">
                <h2 class="fw-bold mb-1" style="font-size: 1.75rem; color: #0f172a;">Kritik & Saran Pengunjung</h2>
                <p class="text-muted mb-0">Feedback dan testimoni dari para wisatawan Pemandian Patemon.</p>
            </div>

            <div class="page-content">
                <div class="modern-card">
                    <div class="modern-card-header">
                        <span class="fw-bold fs-6" style="color: #0f172a;"><i class="fa-solid fa-comments text-primary me-2"></i> Daftar Feedback Masuk</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th>Pengirim</th>
                                    <th>Kontak (Email / Telp)</th>
                                    <th>Isi Pesan / Saran</th>
                                    <th>Tanggal</th>
                                    <th class="text-center" style="width: 80px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 36px; height: 36px; border-radius: 10px; background: #e0f2fe; color: #0284c7; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                                                    <?= strtoupper(substr($row['username'], 0, 1)) ?>
                                                </div>
                                                <div class="fw-bold" style="color: #0f172a;"><?= e($row["username"]) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="color: #334155;"><i class="fa-regular fa-envelope text-muted me-1"></i> <?= e($row["email"] ?: '-') ?></div>
                                            <?php if (!empty($row["no_telepon"])): ?>
                                                <div class="small text-muted"><i class="fa-solid fa-phone text-muted me-1"></i> <?= e($row["no_telepon"]) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 380px;">
                                            <div class="p-2.5 rounded-3 shadow-none" style="background: #f8fafc; border-left: 3.5px solid #0284c7; font-size: 0.885rem; color: #1e293b; line-height: 1.5;">
                                                <?= nl2br(e($row["ulasan"])) ?>
                                            </div>
                                        </td>
                                        <td class="text-muted small"><?= date('d M Y', strtotime($row["tgl_ulasan"])) ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-soft-danger btn-action-icon" title="Hapus Ulasan" onclick="confirmDelete(<?= (int)$row['id_ulasan'] ?>)">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>

                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        <i class="fa-regular fa-comment-dots fs-2 mb-2 d-block text-secondary"></i>
                                        Belum ada kritik dan saran yang masuk.
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
    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Ulasan #' + id + '?',
            text: 'Ulasan ini akan dihapus secara permanen dari database!',
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
