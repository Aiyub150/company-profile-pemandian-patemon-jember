<?php
require '../../app/config.php';

// Hak Akses Khusus: Hanya Administrator (Level 1)
check_auth([1]);

$active_menu = 'user';
$base_view = '..';

$sql = "SELECT id_user, nama, username, email, no_telepon, level FROM users ORDER BY level ASC, id_user ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Pemandian Patemon</title>

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
                    <span class="badge badge-modern-primary">Admin Control</span>
                </div>
            </header>

            <div class="page-heading mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="fw-bold mb-1" style="font-size: 1.75rem; color: #0f172a;">Manajemen Pengguna</h2>
                        <p class="text-muted mb-0">Kelola akun administrator, staf kasir loket, dan pengunjung terdaftar.</p>
                    </div>
                    <div>
                        <a href="tambah.php" class="btn btn-brand">
                            <i class="fa-solid fa-user-plus me-1"></i> Tambah Pengguna Baru
                        </a>
                    </div>
                </div>
            </div>

            <div class="page-content">
                <div class="modern-card">
                    <div class="modern-card-header">
                        <span class="fw-bold fs-6" style="color: #0f172a;"><i class="fa-solid fa-users-gear text-primary me-2"></i> Daftar Akun Pengguna</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th>Pengguna</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>No Telepon</th>
                                    <th>Peran (Role)</th>
                                    <th class="text-center" style="width: 140px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #0284c7, #38bdf8); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                                                    <?= strtoupper(substr($row['nama'] ?: $row['username'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold" style="color: #0f172a;"><?= e($row["nama"] ?: '-') ?></div>
                                                    <small class="text-muted">ID: #<?= (int)$row["id_user"] ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><code style="background: #f0f9ff; color: #0284c7; padding: 0.25rem 0.6rem; border-radius: 6px; font-weight: 600;"><?= e($row["username"]) ?></code></td>
                                        <td style="color: #334155;"><?= e($row["email"]) ?></td>
                                        <td style="color: #334155;"><?= e($row["no_telepon"] ?: '-') ?></td>
                                        <td>
                                            <?php 
                                             $lvl = (int)$row["level"];
                                             if ($lvl === 1) {
                                                 echo '<span class="badge-modern badge-modern-purple"><i class="fa-solid fa-shield-halved"></i> Administrator</span>';
                                             } elseif ($lvl === 2) {
                                                 echo '<span class="badge-modern badge-modern-primary"><i class="fa-solid fa-cash-register"></i> Staf Kasir</span>';
                                             } else {
                                                 echo '<span class="badge-modern badge-modern-warning"><i class="fa-solid fa-user"></i> Pengguna</span>';
                                             }
                                             ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                <a class="btn btn-sm btn-soft-primary btn-action-icon" title="Ubah Akun" href="update.php?id=<?= $row["id_user"] ?>">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <?php if ((int)$row["id_user"] !== (int)$_SESSION['id_user']): ?>
                                                    <button type="button" class="btn btn-sm btn-soft-danger btn-action-icon" title="Hapus User" onclick="confirmDelete(<?= (int)$row['id_user'] ?>, '<?= e($row['username']) ?>')">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-soft-secondary btn-action-icon" disabled title="Akun Anda yang sedang aktif">
                                                        <i class="fa-solid fa-lock"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>

                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        Tidak ada akun pengguna ditemukan.
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
    function confirmDelete(id, username) {
        Swal.fire({
            title: 'Hapus Akun ' + username + '?',
            text: 'Akun yang dihapus tidak akan dapat login kembali ke sistem!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus Akun',
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
