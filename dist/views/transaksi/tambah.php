<?php
require '../../app/config.php';
check_auth([1, 2, 3]);

$active_menu = in_array((int)$_SESSION['level'], [2, 3], true) ? 'kasir' : 'transaksi';
$base_view = '..';

// Ambil tarif tiket dari DB
$harga_tiket = [];
$res_t = $conn->query("SELECT nama_tiket, harga FROM tiket");
while ($r = $res_t->fetch_assoc()) {
    $harga_tiket[$r['nama_tiket']] = (int)$r['harga'];
}
$harga_dewasa = $harga_tiket['Dewasa'] ?? 10000;
$harga_anak   = $harga_tiket['Anak-Anak'] ?? 5000;

// Ambil daftar pengguna untuk dropdown
$users_list = $conn->query("SELECT id_user, nama, username FROM users ORDER BY nama ASC");

$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Token keamanan sesi kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $id_user_transaksi = (int)($_POST['id_user'] ?? $_SESSION['id_user']);
        $nama_pemesan_custom = trim($_POST['nama_pemesan_custom'] ?? '');
        $qty_dewasa = max(0, (int)($_POST['quantity1'] ?? 0));
        $qty_anak   = max(0, (int)($_POST['quantity2'] ?? 0));
        $metode_pembayaran = trim($_POST['metode_pembayaran'] ?? 'Tunai');
        $status = trim($_POST['status'] ?? 'done');

        if ($qty_dewasa === 0 && $qty_anak === 0) {
            $error_msg = "Jumlah tiket minimal 1 lembar (Dewasa atau Anak-Anak).";
        } else {
            $subtotal_dewasa = $qty_dewasa * $harga_dewasa;
            $subtotal_anak   = $qty_anak * $harga_anak;
            $total_harga     = $subtotal_dewasa + $subtotal_anak;
            $tgl_pemesanan   = date('Y-m-d');
            $nama_gambar     = null;

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO transaksi (id_user, tgl_pemesanan, total_harga, metode_pembayaran, bukti_pembayaran, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isisss", $id_user_transaksi, $tgl_pemesanan, $total_harga, $metode_pembayaran, $nama_gambar, $status);
                $stmt->execute();
                $new_id = $conn->insert_id;
                $stmt->close();

                $stmt_d = $conn->prepare("INSERT INTO detail_transaksi (id_transaksi, jenis_tiket, quantity, sub_total) VALUES (?, ?, ?, ?)");
                if ($qty_dewasa > 0) {
                    $t_dew = 'Dewasa';
                    $stmt_d->bind_param("isii", $new_id, $t_dew, $qty_dewasa, $subtotal_dewasa);
                    $stmt_d->execute();
                }
                if ($qty_anak > 0) {
                    $t_ank = 'Anak-Anak';
                    $stmt_d->bind_param("isii", $new_id, $t_ank, $qty_anak, $subtotal_anak);
                    $stmt_d->execute();
                }
                $stmt_d->close();

                $conn->commit();
                
                // Langsung arahkan ke halaman nota cetak atau daftar transaksi
                header("Location: " . route_url('nota', ['id' => $new_id]));
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = "Gagal memproses transaksi: " . e($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Loket (POS) - Pemandian Patemon</title>

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
    <style>
        .pos-ticket-card {
            border: 2px solid var(--border-color, #e2e8f0);
            border-radius: 16px;
            padding: 1.25rem;
            transition: all 0.2s ease;
            background: var(--bg-card, #ffffff);
            color: var(--text-main, #0f172a);
        }
        .pos-ticket-card.active {
            border-color: #0284c7;
            background: rgba(2, 132, 199, 0.08);
        }
        .summary-card {
            background: var(--bg-card, #ffffff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 20px;
            padding: 1.75rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06);
            position: sticky;
            top: 2rem;
            color: var(--text-main, #0f172a);
        }
        .payment-method-selector label {
            border: 1.5px solid var(--border-color, #e2e8f0);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.15s ease;
            color: var(--text-main, #334155);
            background: var(--bg-card, #fff);
        }
        .payment-method-selector input[type="radio"]:checked + label {
            border-color: #0284c7;
            background: rgba(2, 132, 199, 0.15);
            color: #38bdf8;
        }
    </style>
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
                <h2 class="fw-bold text-dark mb-1" style="font-size: 1.75rem;">Kasir Loket Penjualan Tiket (POS)</h2>
                <p class="text-muted mb-0">Pilih jenis tiket dan kuantitas, tentukan metode pembayaran, dan cetak nota struk.</p>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                    <i class="fa-solid fa-circle-exclamation fs-5"></i>
                    <div><?= e($error_msg) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="posForm">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" id="hargaDewasa" value="<?= $harga_dewasa ?>">
                <input type="hidden" id="hargaAnak" value="<?= $harga_anak ?>">

                <div class="row g-4">
                    <!-- Left Column: Tiket & Customer Selection -->
                    <div class="col-12 col-lg-7 col-xl-8">
                        <!-- Customer Info Card -->
                        <div class="modern-card mb-4">
                            <div class="modern-card-header">
                                <span class="fw-bold text-dark">
                                    <i class="fa-solid fa-user me-2 text-primary"></i> Data Pelanggan / Pengunjung
                                </span>
                            </div>
                            <div class="modern-card-body">
                                <div class="form-group mb-0">
                                    <label class="form-label fw-semibold text-secondary" style="font-size: 0.875rem;">Pilih Akun / Pengunjung Loket:</label>
                                    <select class="form-select-modern" name="id_user" id="id_user">
                                        <?php if ($users_list && $users_list->num_rows > 0): ?>
                                            <?php while ($u = $users_list->fetch_assoc()): ?>
                                                <option value="<?= (int)$u['id_user'] ?>" <?= ($u['id_user'] == $_SESSION['id_user']) ? 'selected' : '' ?>>
                                                    <?= e($u['nama']) ?> (<?= e($u['username']) ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                    <small class="text-muted mt-1 d-block">Pilih akun pengunjung atau gunakan akun kasir yang sedang bertugas.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Ticket Selection Card -->
                        <div class="modern-card mb-4">
                            <div class="modern-card-header">
                                <span class="fw-bold text-dark">
                                    <i class="fa-solid fa-ticket me-2 text-primary"></i> Pilih Kategori & Jumlah Tiket
                                </span>
                            </div>
                            <div class="modern-card-body">
                                <div class="row g-3">
                                    <!-- Tiket Dewasa -->
                                    <div class="col-12 col-md-6">
                                        <div class="pos-ticket-card" id="cardDewasa">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="fw-bold fs-5 text-dark">Dewasa</div>
                                                <div class="metric-icon-box blue" style="width: 40px; height: 40px; font-size: 1.1rem;">
                                                    <i class="fa-solid fa-user"></i>
                                                </div>
                                            </div>
                                            <div class="text-muted small mb-3">Tiket masuk kolam untuk usia dewasa & remaja</div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="fw-extrabold text-primary fs-5"><?= format_rupiah($harga_dewasa) ?></div>
                                                <div class="qty-stepper">
                                                    <button type="button" onclick="changeQty('quantity1', -1)"><i class="fa-solid fa-minus"></i></button>
                                                    <input type="number" id="quantity1" name="quantity1" value="0" min="0" readonly>
                                                    <button type="button" onclick="changeQty('quantity1', 1)"><i class="fa-solid fa-plus"></i></button>
                                                </div>
                                            </div>
                                            <div class="text-end text-muted small mt-2">
                                                Subtotal: <strong id="subtotalDewasaTxt" class="text-dark">Rp 0</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tiket Anak -->
                                    <div class="col-12 col-md-6">
                                        <div class="pos-ticket-card" id="cardAnak">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="fw-bold fs-5 text-dark">Anak-Anak</div>
                                                <div class="metric-icon-box amber" style="width: 40px; height: 40px; font-size: 1.1rem;">
                                                    <i class="fa-solid fa-child-reaching"></i>
                                                </div>
                                            </div>
                                            <div class="text-muted small mb-3">Tiket masuk kolam untuk balita & anak-anak</div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="fw-extrabold text-amber fs-5" style="color: #d97706;"><?= format_rupiah($harga_anak) ?></div>
                                                <div class="qty-stepper">
                                                    <button type="button" onclick="changeQty('quantity2', -1)"><i class="fa-solid fa-minus"></i></button>
                                                    <input type="number" id="quantity2" name="quantity2" value="0" min="0" readonly>
                                                    <button type="button" onclick="changeQty('quantity2', 1)"><i class="fa-solid fa-plus"></i></button>
                                                </div>
                                            </div>
                                            <div class="text-end text-muted small mt-2">
                                                Subtotal: <strong id="subtotalAnakTxt" class="text-dark">Rp 0</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method Card -->
                        <div class="modern-card">
                            <div class="modern-card-header">
                                <span class="fw-bold text-dark">
                                    <i class="fa-solid fa-credit-card me-2 text-primary"></i> Metode Pembayaran
                                </span>
                            </div>
                            <div class="modern-card-body">
                                <div class="payment-method-selector d-flex flex-wrap gap-2 mb-3">
                                    <div>
                                        <input type="radio" class="d-none" name="metode_pembayaran" id="pay_cash" value="Tunai" checked>
                                        <label for="pay_cash"><i class="fa-solid fa-money-bill-wave text-success"></i> Tunai (Cash)</label>
                                    </div>
                                    <div>
                                        <input type="radio" class="d-none" name="metode_pembayaran" id="pay_qris" value="QRIS">
                                        <label for="pay_qris"><i class="fa-solid fa-qrcode text-primary"></i> QRIS / E-Wallet</label>
                                    </div>
                                    <div>
                                        <input type="radio" class="d-none" name="metode_pembayaran" id="pay_transfer" value="Transfer Bank">
                                        <label for="pay_transfer"><i class="fa-solid fa-building-columns text-info"></i> Transfer Bank</label>
                                    </div>
                                </div>

                                <div class="form-group mb-0">
                                    <label class="form-label fw-semibold text-secondary" style="font-size: 0.875rem;">Status Transaksi:</label>
                                    <select class="form-select-modern" name="status">
                                        <option value="done" selected>Sudah Dibayar (Lunas)</option>
                                        <option value="pending">Menunggu Pembayaran (Pending)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Live Order Summary -->
                    <div class="col-12 col-lg-5 col-xl-4">
                        <div class="summary-card">
                            <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                                <div class="fw-bold fs-5 text-dark">Ringkasan Tagihan</div>
                                <span class="badge badge-modern-primary"><i class="fa-solid fa-cart-shopping"></i> POS</span>
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
                                <div class="text-muted small fw-bold text-uppercase mb-1">Total Tagihan Loket:</div>
                                <div class="fw-extrabold text-primary" id="totalHargaTxt" style="font-size: 1.85rem; line-height: 1;">Rp 0</div>
                            </div>

                            <!-- Kalkulator Kembalian Uang Tunai -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-secondary small">Nominal Uang Diterima (Rp):</label>
                                <div class="input-icon-group">
                                    <i class="fa-solid fa-money-bill input-icon"></i>
                                    <input 
                                        type="number" 
                                        class="form-control-modern" 
                                        id="uangBayar" 
                                        placeholder="Contoh: 50000" 
                                        min="0"
                                        oninput="hitungKembalian()"
                                    >
                                </div>
                            </div>

                            <div class="pos-kembalian-box d-flex justify-content-between align-items-center p-3 rounded-3 mb-4">
                                <span class="text-success fw-bold small">Uang Kembalian:</span>
                                <strong class="fs-5 text-success" id="uangKembalianTxt">Rp 0</strong>
                            </div>

                            <button type="submit" class="btn-brand w-100 py-3 fs-5" id="btnSubmit">
                                <i class="fa-solid fa-print me-1"></i> Proses & Cetak Nota
                            </button>

                            <div class="text-center mt-3">
                                <a href="<?= ($_SESSION['level'] == 2) ? route_url('kasir') : route_url('transaksi') ?>" class="text-secondary small text-decoration-none">
                                    <i class="fa-solid fa-xmark me-1"></i> Batalkan Transaksi
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

        // Highlight active cards
        document.getElementById('cardDewasa').classList.toggle('active', qDewasa > 0);
        document.getElementById('cardAnak').classList.toggle('active', qAnak > 0);

        hitungKembalian();
    }

    function hitungKembalian() {
        const qDewasa = parseInt(document.getElementById('quantity1').value) || 0;
        const qAnak   = parseInt(document.getElementById('quantity2').value) || 0;
        const total   = (qDewasa * hargaDewasa) + (qAnak * hargaAnak);

        const bayar   = parseInt(document.getElementById('uangBayar').value) || 0;
        const kembalian = Math.max(0, bayar - total);

        document.getElementById('uangKembalianTxt').innerText = formatRupiah(kembalian);
    }

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

    document.addEventListener('DOMContentLoaded', () => {
        const currentTheme = localStorage.getItem('patemon_theme') || 'light';
        applyPatemonTheme(currentTheme);
    });
    </script>
</body>
</html>
