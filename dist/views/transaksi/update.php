<?php
require '../../app/config.php';
check_auth([1, 2, 3]);

$active_menu = in_array((int)$_SESSION['level'], [2, 3], true) ? 'kasir' : 'transaksi';
$base_view = '..';

$id_transaksi = (int)($_GET["id"] ?? $_POST['id_transaksi'] ?? 0);

if ($id_transaksi <= 0) {
    header("Location: " . (in_array((int)$_SESSION['level'], [2, 3], true) ? route_url('kasir') : route_url('transaksi')));
    exit();
}

// Ambil tarif resmi
$harga_tiket = [];
$res_t = $conn->query("SELECT nama_tiket, harga FROM tiket");
while ($r = $res_t->fetch_assoc()) {
    $harga_tiket[$r['nama_tiket']] = (int)$r['harga'];
}
$harga_dewasa = $harga_tiket['Dewasa'] ?? 10000;
$harga_anak   = $harga_tiket['Anak-Anak'] ?? 5000;

$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Token keamanan sesi kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $metode_pembayaran = trim($_POST['metode_pembayaran'] ?? 'Tunai');
        $status            = trim($_POST['status'] ?? 'done');
        $qty_dewasa        = max(0, (int)($_POST['quantity1'] ?? 0));
        $qty_anak          = max(0, (int)($_POST['quantity2'] ?? 0));

        // Hitung total di server
        $subtotal_dewasa = $qty_dewasa * $harga_dewasa;
        $subtotal_anak   = $qty_anak * $harga_anak;
        $total_harga     = $subtotal_dewasa + $subtotal_anak;

        $conn->begin_transaction();
        try {
            // Update master transaksi
            $stmt = $conn->prepare("UPDATE transaksi SET total_harga = ?, metode_pembayaran = ?, status = ? WHERE id_transaksi = ?");
            $stmt->bind_param("issi", $total_harga, $metode_pembayaran, $status, $id_transaksi);
            $stmt->execute();
            $stmt->close();

            // Hapus detail lama dan masukkan yang baru
            $del_d = $conn->prepare("DELETE FROM detail_transaksi WHERE id_transaksi = ?");
            $del_d->bind_param("i", $id_transaksi);
            $del_d->execute();
            $del_d->close();

            $ins_d = $conn->prepare("INSERT INTO detail_transaksi (id_transaksi, jenis_tiket, quantity, sub_total) VALUES (?, ?, ?, ?)");
            if ($qty_dewasa > 0) {
                $t_dew = 'Dewasa';
                $ins_d->bind_param("isii", $id_transaksi, $t_dew, $qty_dewasa, $subtotal_dewasa);
                $ins_d->execute();
            }
            if ($qty_anak > 0) {
                $t_ank = 'Anak-Anak';
                $ins_d->bind_param("isii", $id_transaksi, $t_ank, $qty_anak, $subtotal_anak);
                $ins_d->execute();
            }
            $ins_d->close();

            $conn->commit();
            $redirect = in_array((int)$_SESSION['level'], [2, 3], true) ? route_url('kasir') : route_url('transaksi');
            header("Location: " . $redirect);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Gagal memperbarui transaksi: " . e($e->getMessage());
        }
    }
}

// Ambil data transaksi saat ini
$stmt = $conn->prepare("SELECT transaksi.*, users.nama FROM transaksi INNER JOIN users ON transaksi.id_user = users.id_user WHERE id_transaksi = ? LIMIT 1");
$stmt->bind_param("i", $id_transaksi);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: " . (in_array((int)$_SESSION['level'], [2, 3], true) ? route_url('kasir') : route_url('transaksi')));
    exit();
}

// Ambil detail tiket saat ini
$qty_dewasa_current = 0;
$stmt_d = $conn->prepare("SELECT quantity FROM detail_transaksi WHERE id_transaksi = ? AND jenis_tiket = 'Dewasa' LIMIT 1");
$stmt_d->bind_param("i", $id_transaksi);
$stmt_d->execute();
$res_d = $stmt_d->get_result()->fetch_assoc();
if ($res_d) $qty_dewasa_current = (int)$res_d['quantity'];
$stmt_d->close();

$qty_anak_current = 0;
$stmt_a = $conn->prepare("SELECT quantity FROM detail_transaksi WHERE id_transaksi = ? AND jenis_tiket = 'Anak-Anak' LIMIT 1");
$stmt_a->bind_param("i", $id_transaksi);
$stmt_a->execute();
$res_a = $stmt_a->get_result()->fetch_assoc();
if ($res_a) $qty_anak_current = (int)$res_a['quantity'];
$stmt_a->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transaksi #<?= $id_transaksi ?> - Pemandian Patemon</title>

    <link rel="icon" type="image/x-icon" href="<?= public_url('img/icon.png') ?>" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= public_url('assets/css/main/app.css') ?>">
    <link rel="stylesheet" href="<?= public_url('css/modern-theme.css') ?>?v=<?= file_exists(__DIR__ . '/../../../public/css/modern-theme.css') ? filemtime(__DIR__ . '/../../../public/css/modern-theme.css') : time() ?>">
    <script>
        (function() {
            var theme = localStorage.getItem('patemon_theme') || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.add('theme-dark');
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            } else {
                document.documentElement.classList.remove('theme-dark');
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>
</head>

<body>
    <script>
        if (localStorage.getItem('patemon_theme') === 'dark') {
            document.body.classList.add('theme-dark');
        }
    </script>
    <div id="app">
        <?php include '../../app/partials/sidebar.php'; ?>

        <div id="main">
            <!-- Header Topbar -->
            <header class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 border-bottom">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="fa-solid fa-bars fs-3"></i>
                </a>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <!-- Tombol Switcher Tema -->
                    <button type="button" id="themeToggleBtn" class="btn-theme-switcher" onclick="togglePatemonTheme()" title="Beralih Mode Gelap / Terang">
                        <span class="theme-icon-moon"><i class="fa-solid fa-moon"></i></span>
                        <span class="theme-icon-sun"><i class="fa-solid fa-sun"></i></span>
                        <span class="d-none d-sm-inline ms-1" id="themeLabelText">Tema</span>
                    </button>
                    <a href="<?= in_array((int)$_SESSION['level'], [2, 3], true) ? route_url('kasir') : route_url('transaksi') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Riwayat
                    </a>
                </div>
            </header>

            <div class="page-heading mb-4">
                <h2 class="fw-bold text-dark mb-1" style="font-size: 1.75rem;">Edit Transaksi #<?= $id_transaksi ?></h2>
                <p class="text-muted mb-0">Ubah kuantitas tiket, status pelunasan, atau metode pembayaran.</p>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                    <i class="fa-solid fa-circle-exclamation fs-5"></i>
                    <div><?= e($error_msg) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="posForm">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id_transaksi" value="<?= $id_transaksi ?>">
                <input type="hidden" id="hargaDewasa" value="<?= $harga_dewasa ?>">
                <input type="hidden" id="hargaAnak" value="<?= $harga_anak ?>">

                <div class="row g-4">
                    <!-- Left: Ticket & Payment Controls -->
                    <div class="col-12 col-lg-7 col-xl-8">
                        <div class="modern-card mb-4">
                            <div class="modern-card-header">
                                <span class="fw-bold text-dark"><i class="fa-solid fa-circle-info text-primary me-2"></i> Info Pemesan</span>
                                <span class="badge badge-modern-primary">ID: #<?= $id_transaksi ?></span>
                            </div>
                            <div class="modern-card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small fw-semibold">Nama Pemesan:</label>
                                        <div class="fw-bold fs-6 text-dark"><?= e($data['nama']) ?></div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="text-muted small fw-semibold">Tanggal Pemesanan:</label>
                                        <div class="fw-bold fs-6 text-dark"><?= date('d F Y', strtotime($data['tgl_pemesanan'])) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ticket Qty Steppers -->
                        <div class="modern-card mb-4">
                            <div class="modern-card-header">
                                <span class="fw-bold text-dark"><i class="fa-solid fa-ticket text-primary me-2"></i> Kuantitas Tiket</span>
                            </div>
                            <div class="modern-card-body">
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <div class="ticket-pos-card active">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="fw-bold fs-5 text-dark">Dewasa</div>
                                                <span class="badge badge-modern-primary"><?= format_rupiah($harga_dewasa) ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <div class="qty-stepper">
                                                    <button type="button" onclick="changeQty('quantity1', -1)"><i class="fa-solid fa-minus"></i></button>
                                                    <input type="number" id="quantity1" name="quantity1" value="<?= $qty_dewasa_current ?>" min="0" readonly>
                                                    <button type="button" onclick="changeQty('quantity1', 1)"><i class="fa-solid fa-plus"></i></button>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted d-block">Subtotal:</small>
                                                    <strong id="subtotalDewasaTxt" class="text-primary fs-6">Rp 0</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <div class="ticket-pos-card active">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="fw-bold fs-5 text-dark">Anak-Anak</div>
                                                <span class="badge badge-modern-warning"><?= format_rupiah($harga_anak) ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <div class="qty-stepper">
                                                    <button type="button" onclick="changeQty('quantity2', -1)"><i class="fa-solid fa-minus"></i></button>
                                                    <input type="number" id="quantity2" name="quantity2" value="<?= $qty_anak_current ?>" min="0" readonly>
                                                    <button type="button" onclick="changeQty('quantity2', 1)"><i class="fa-solid fa-plus"></i></button>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted d-block">Subtotal:</small>
                                                    <strong id="subtotalAnakTxt" class="text-amber fs-6" style="color: #d97706;">Rp 0</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status & Payment Selector -->
                        <div class="modern-card">
                            <div class="modern-card-header">
                                <span class="fw-bold text-dark"><i class="fa-solid fa-sliders text-primary me-2"></i> Pengaturan Status & Pembayaran</span>
                            </div>
                            <div class="modern-card-body">
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold text-secondary small">Metode Pembayaran:</label>
                                        <select class="form-select-modern" name="metode_pembayaran">
                                            <option value="Tunai" <?= ($data['metode_pembayaran'] === 'Tunai' || strtolower($data['metode_pembayaran']) === 'bayar di loket') ? 'selected' : '' ?>>Tunai (Loket)</option>
                                            <option value="QRIS" <?= (strtoupper($data['metode_pembayaran']) === 'QRIS') ? 'selected' : '' ?>>QRIS / E-Wallet</option>
                                            <option value="Transfer Bank" <?= (stripos($data['metode_pembayaran'], 'transfer') !== false) ? 'selected' : '' ?>>Transfer Bank</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold text-secondary small">Status Transaksi:</label>
                                        <select class="form-select-modern" name="status">
                                            <option value="done" <?= ($data['status'] === 'done') ? 'selected' : '' ?>>Selesai (Sudah Dibayar)</option>
                                            <option value="notyet" <?= ($data['status'] !== 'done') ? 'selected' : '' ?>>Pending (Belum Dibayar)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Summary Card -->
                    <div class="col-12 col-lg-5 col-xl-4">
                        <div class="summary-card">
                            <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                                <div class="fw-bold fs-5 text-dark">Ringkasan Total Baru</div>
                                <span class="badge badge-modern-primary">Update</span>
                            </div>

                            <div class="d-flex justify-content-between mb-2 text-muted" style="font-size: 0.9rem;">
                                <span>Tiket Dewasa (<span id="sumQtyDewasa">0</span>x)</span>
                                <strong id="sumSubDewasa" class="text-dark">Rp 0</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-3 text-muted" style="font-size: 0.9rem;">
                                <span>Tiket Anak (<span id="sumQtyAnak">0</span>x)</span>
                                <strong id="sumSubAnak" class="text-dark">Rp 0</strong>
                            </div>

                            <div class="pos-total-box p-3 rounded-3 mb-4">
                                <div class="text-muted small fw-bold text-uppercase mb-1">Total Tagihan:</div>
                                <div class="fw-extrabold text-primary" id="totalHargaTxt" style="font-size: 1.85rem; line-height: 1;">Rp 0</div>
                            </div>

                            <button type="submit" class="btn-brand w-100 py-3 fs-5 mb-2">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan
                            </button>

                            <div class="text-center mt-3">
                                <a href="<?= ($_SESSION['level'] == 2) ? route_url('kasir') : route_url('transaksi') ?>" class="text-secondary small text-decoration-none">
                                    <i class="fa-solid fa-xmark me-1"></i> Batalkan
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="mt-5">
                <?php include '../../app/partials/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?= public_url('assets/js/bootstrap.js') ?>"></script>
    <script>
    const hargaDewasa = parseInt(document.getElementById('hargaDewasa').value) || 10000;
    const hargaAnak   = parseInt(document.getElementById('hargaAnak').value) || 5000;

    function formatRupiah(num) {
        return 'Rp ' + num.toLocaleString('id-ID');
    }

    function changeQty(id, delta) {
        const input = document.getElementById(id);
        let val = parseInt(input.value) || 0;
        val = Math.max(0, val + delta);
        input.value = val;
        recalculate();
    }

    function recalculate() {
        const qDewasa = parseInt(document.getElementById('quantity1').value) || 0;
        const qAnak   = parseInt(document.getElementById('quantity2').value) || 0;

        const subDewasa = qDewasa * hargaDewasa;
        const subAnak   = qAnak * hargaAnak;
        const total     = subDewasa + subAnak;

        document.getElementById('subtotalDewasaTxt').innerText = formatRupiah(subDewasa);
        document.getElementById('subtotalAnakTxt').innerText   = formatRupiah(subAnak);

        document.getElementById('sumQtyDewasa').innerText = qDewasa;
        document.getElementById('sumSubDewasa').innerText = formatRupiah(subDewasa);

        document.getElementById('sumQtyAnak').innerText = qAnak;
        document.getElementById('sumSubAnak').innerText = formatRupiah(subAnak);

        document.getElementById('totalHargaTxt').innerText = formatRupiah(total);
    }

    document.addEventListener("DOMContentLoaded", function() {
        recalculate();
        const currentTheme = localStorage.getItem('patemon_theme') || 'light';
        applyPatemonTheme(currentTheme);
    });

    // Theme Switcher Controller
    function togglePatemonTheme() {
        const isDark = document.body.classList.contains('theme-dark') || document.documentElement.classList.contains('theme-dark');
        const newTheme = isDark ? 'light' : 'dark';
        applyPatemonTheme(newTheme);
        localStorage.setItem('patemon_theme', newTheme);
    }

    function applyPatemonTheme(theme) {
        const btn = document.getElementById('themeToggleBtn');
        const label = document.getElementById('themeLabelText');
        if (theme === 'dark') {
            document.body.classList.add('theme-dark');
            document.documentElement.classList.add('theme-dark');
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            if (btn) {
                btn.classList.add('active-dark');
                btn.setAttribute('title', 'Beralih ke Mode Terang');
            }
            if (label) label.textContent = 'Gelap';
        } else {
            document.body.classList.remove('theme-dark');
            document.documentElement.classList.remove('theme-dark');
            document.documentElement.setAttribute('data-bs-theme', 'light');
            if (btn) {
                btn.classList.remove('active-dark');
                btn.setAttribute('title', 'Beralih ke Mode Gelap');
            }
            if (label) label.textContent = 'Terang';
        }
    }
    </script>
</body>
</html>
