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

// Ambil seluruh tarif resmi tiket dari database
$all_tickets = [];
$res_t = $conn->query("SELECT id_tiket, nama_tiket, harga, ikon FROM tiket ORDER BY id_tiket ASC");
if ($res_t) {
    while ($r = $res_t->fetch_assoc()) {
        $id = (int)$r['id_tiket'];
        $all_tickets[$id] = [
            'id_tiket'   => $id,
            'nama_tiket' => $r['nama_tiket'],
            'harga'      => (int)$r['harga'],
            'ikon'       => !empty($r['ikon']) ? $r['ikon'] : get_ticket_icon($r['nama_tiket'])
        ];
    }
}

$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Token keamanan sesi kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $metode_pembayaran   = trim($_POST['metode_pembayaran'] ?? 'Tunai');
        $status              = trim($_POST['status'] ?? 'done');
        $nama_pemesan_custom = trim($_POST['nama_pemesan'] ?? '');

        // Cek filter toxic words pada nama pemesan jika diubah
        if (!empty($nama_pemesan_custom) && has_toxic_words($nama_pemesan_custom)) {
            $toxicHits = find_toxic_words($nama_pemesan_custom);
            $error_msg = "Nama pemesan memuat kata terlarang (" . e(implode(', ', array_unique($toxicHits))) . "). Harap gunakan bahasa yang sopan.";
        } else {
            $order_items = [];
            $total_qty   = 0;
            $total_harga = 0;

            if (isset($_POST['tickets']) && is_array($_POST['tickets'])) {
                foreach ($_POST['tickets'] as $id_tkt => $qty) {
                    $id_tkt = (int)$id_tkt;
                    $qty    = max(0, (int)$qty);
                    if ($qty > 0 && isset($all_tickets[$id_tkt])) {
                        $tInfo    = $all_tickets[$id_tkt];
                        $subtotal = $qty * $tInfo['harga'];
                        $order_items[] = [
                            'id_tiket'   => $id_tkt,
                            'nama_tiket' => $tInfo['nama_tiket'],
                            'qty'        => $qty,
                            'harga'      => $tInfo['harga'],
                            'subtotal'   => $subtotal
                        ];
                        $total_qty   += $qty;
                        $total_harga += $subtotal;
                    }
                }
            } else {
                // Fallback legacy inputs
                $qty_dewasa = max(0, (int)($_POST['quantity1'] ?? 0));
                $qty_anak   = max(0, (int)($_POST['quantity2'] ?? 0));
                foreach ($all_tickets as $t) {
                    if (strcasecmp($t['nama_tiket'], 'dewasa') === 0 && $qty_dewasa > 0) {
                        $sub = $qty_dewasa * $t['harga'];
                        $order_items[] = ['id_tiket' => $t['id_tiket'], 'nama_tiket' => $t['nama_tiket'], 'qty' => $qty_dewasa, 'harga' => $t['harga'], 'subtotal' => $sub];
                        $total_qty   += $qty_dewasa;
                        $total_harga += $sub;
                    } elseif (strcasecmp($t['nama_tiket'], 'anak-anak') === 0 && $qty_anak > 0) {
                        $sub = $qty_anak * $t['harga'];
                        $order_items[] = ['id_tiket' => $t['id_tiket'], 'nama_tiket' => $t['nama_tiket'], 'qty' => $qty_anak, 'harga' => $t['harga'], 'subtotal' => $sub];
                        $total_qty   += $qty_anak;
                        $total_harga += $sub;
                    }
                }
            }

            if ($total_qty === 0) {
                $error_msg = "Kuantitas tiket minimal 1 lembar. Transaksi tidak boleh memiliki 0 tiket.";
            } else {
                $conn->begin_transaction();
                try {
                    // Update master transaksi
                    $stmt = $conn->prepare("UPDATE transaksi SET nama_pemesan = ?, total_harga = ?, metode_pembayaran = ?, status = ? WHERE id_transaksi = ?");
                    $nama_to_save = !empty($nama_pemesan_custom) ? $nama_pemesan_custom : null;
                    $stmt->bind_param("sissi", $nama_to_save, $total_harga, $metode_pembayaran, $status, $id_transaksi);
                    $stmt->execute();
                    $stmt->close();

                    // Hapus detail tiket lama dan masukkan yang baru
                    $del_d = $conn->prepare("DELETE FROM detail_transaksi WHERE id_transaksi = ?");
                    $del_d->bind_param("i", $id_transaksi);
                    $del_d->execute();
                    $del_d->close();

                    $ins_d = $conn->prepare("INSERT INTO detail_transaksi (id_transaksi, jenis_tiket, quantity, sub_total) VALUES (?, ?, ?, ?)");
                    foreach ($order_items as $item) {
                        $ins_d->bind_param("isii", $id_transaksi, $item['nama_tiket'], $item['qty'], $item['subtotal']);
                        $ins_d->execute();
                    }
                    $ins_d->close();

                    $conn->commit();
                    if (function_exists('log_activity')) {
                        log_activity('UPDATE', 'transaksi', "Memperbarui transaksi ID #{$id_transaksi} status '{$status}', metode '{$metode_pembayaran}', total Rp " . number_format($total_harga, 0, ',', '.'));
                    }
                    $redirect = in_array((int)$_SESSION['level'], [2, 3], true) ? route_url('kasir') : route_url('transaksi');
                    header("Location: " . $redirect);
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_msg = "Gagal memperbarui transaksi: " . e($e->getMessage());
                }
            }
        }
    }
}

// Ambil data transaksi saat ini
$stmt = $conn->prepare("SELECT transaksi.*, users.nama as user_nama, users.username FROM transaksi INNER JOIN users ON transaksi.id_user = users.id_user WHERE id_transaksi = ? LIMIT 1");
$stmt->bind_param("i", $id_transaksi);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: " . (in_array((int)$_SESSION['level'], [2, 3], true) ? route_url('kasir') : route_url('transaksi')));
    exit();
}

// Ambil detail tiket transaksi saat ini dari database
$current_ticket_qtys = [];
$stmt_dt = $conn->prepare("SELECT jenis_tiket, quantity, sub_total FROM detail_transaksi WHERE id_transaksi = ?");
$stmt_dt->bind_param("i", $id_transaksi);
$stmt_dt->execute();
$res_dt = $stmt_dt->get_result();
while ($row = $res_dt->fetch_assoc()) {
    $current_ticket_qtys[$row['jenis_tiket']] = (int)$row['quantity'];
}
$stmt_dt->close();

$kode_transaksi = format_kode_transaksi($data['id_transaksi'], $data['tgl_pemesanan']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transaksi #<?= e($data['id_transaksi']) ?> - Pemandian Patemon</title>

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
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }
        .pos-ticket-card.active {
            border-color: #0284c7;
            background: rgba(2, 132, 199, 0.08);
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.15);
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
                    <button type="button" id="themeToggleBtn" class="btn-theme-switcher" onclick="togglePatemonTheme()" title="Beralih Mode Gelap / Terang">
                        <span class="theme-icon-moon"><i class="fa-solid fa-moon"></i></span>
                        <span class="theme-icon-sun"><i class="fa-solid fa-sun"></i></span>
                        <span class="d-none d-sm-inline ms-1" id="themeLabelText">Tema</span>
                    </button>
                    <a href="<?= in_array((int)$_SESSION['level'], [2, 3], true) ? route_url('kasir') : route_url('transaksi') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i> Batal & Kembali
                    </a>
                </div>
            </header>

            <div class="page-heading mb-4">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h2 class="fw-bold text-dark mb-0" style="font-size: 1.75rem;">Edit Transaksi Loket</h2>
                    <span class="badge badge-modern-primary font-monospace"><?= e($kode_transaksi) ?></span>
                </div>
                <p class="text-muted mb-0">Ubah kuantitas tiket, status pembayaran, atau nama pemesan untuk ID #<?= (int)$data['id_transaksi'] ?>.</p>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                    <i class="fa-solid fa-circle-exclamation fs-5"></i>
                    <div><?= e($error_msg) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id_transaksi" value="<?= (int)$data['id_transaksi'] ?>">

                <div class="row g-4">
                    <!-- Left: Form Controls -->
                    <div class="col-12 col-lg-7 col-xl-8">
                        
                        <!-- Customer Info Card -->
                        <div class="modern-card mb-4">
                            <div class="modern-card-header">
                                <span class="fw-bold text-dark"><i class="fa-solid fa-id-card text-primary me-2"></i> Informasi Pengunjung & Pemesan</span>
                            </div>
                            <div class="modern-card-body">
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold text-secondary small">Akun Petugas / Pengguna:</label>
                                        <div class="form-control-modern bg-light text-muted">
                                            <i class="fa-solid fa-user me-1 text-primary"></i> <?= e($data['user_nama']) ?> (<?= e($data['username']) ?>)
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold text-secondary small">Nama Tamu / Pemesan (Manual):</label>
                                        <input 
                                            type="text" 
                                            class="form-control-modern" 
                                            name="nama_pemesan" 
                                            value="<?= e($data['nama_pemesan'] ?? '') ?>" 
                                            placeholder="Nama lengkap pengunjung / rombongan..."
                                            maxlength="100"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ticket Qty Steppers (Dynamic Ticket Categories) -->
                        <div class="modern-card mb-4">
                            <div class="modern-card-header d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark"><i class="fa-solid fa-ticket text-primary me-2"></i> Kuantitas Kategori Tiket</span>
                                <span class="badge badge-modern-primary"><?= count($all_tickets) ?> Kategori Tersedia</span>
                            </div>
                            <div class="modern-card-body">
                                <div class="row g-3">
                                    <?php foreach ($all_tickets as $t): 
                                        $init_qty = $current_ticket_qtys[$t['nama_tiket']] ?? 0;
                                    ?>
                                        <div class="col-12 col-sm-6">
                                            <div class="pos-ticket-card <?= ($init_qty > 0) ? 'active' : '' ?>" id="cardTicket_<?= $t['id_tiket'] ?>">
                                                <div>
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <div class="fw-bold fs-5 text-dark"><?= e($t['nama_tiket']) ?></div>
                                                        <span class="badge badge-modern-primary"><?= format_rupiah($t['harga']) ?></span>
                                                    </div>
                                                </div>
                                                <div class="mt-3">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="qty-stepper">
                                                            <button type="button" onclick="changeTicketQty(<?= $t['id_tiket'] ?>, -1)"><i class="fa-solid fa-minus"></i></button>
                                                            <input 
                                                                type="number" 
                                                                id="ticket_qty_<?= $t['id_tiket'] ?>" 
                                                                name="tickets[<?= $t['id_tiket'] ?>]" 
                                                                value="<?= $init_qty ?>" 
                                                                min="0" 
                                                                readonly
                                                            >
                                                            <button type="button" onclick="changeTicketQty(<?= $t['id_tiket'] ?>, 1)"><i class="fa-solid fa-plus"></i></button>
                                                        </div>
                                                        <div class="text-end">
                                                            <small class="text-muted d-block">Subtotal:</small>
                                                            <strong id="subtotalTicketTxt_<?= $t['id_tiket'] ?>" class="text-primary fs-6"><?= format_rupiah($init_qty * $t['harga']) ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
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
                                        <select class="form-select-modern searchable-select" name="metode_pembayaran">
                                            <option value="Tunai" <?= ($data['metode_pembayaran'] === 'Tunai' || strtolower($data['metode_pembayaran']) === 'bayar di loket') ? 'selected' : '' ?>>Tunai (Loket)</option>
                                            <option value="QRIS" <?= (strtoupper($data['metode_pembayaran']) === 'QRIS') ? 'selected' : '' ?>>QRIS / E-Wallet</option>
                                            <option value="Transfer Bank" <?= (stripos($data['metode_pembayaran'], 'transfer') !== false) ? 'selected' : '' ?>>Transfer Bank</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold text-secondary small">Status Transaksi:</label>
                                        <select class="form-select-modern searchable-select" name="status">
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

                            <div id="summaryTicketList" class="mb-3">
                                <!-- Dynamic itemized list populated by JS -->
                            </div>

                            <div class="pos-total-box p-3 rounded-3 mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small fw-bold text-uppercase">Total Tagihan:</span>
                                    <span class="badge bg-primary text-white" id="totalQtyBadge">0 Tiket</span>
                                </div>
                                <div class="fw-extrabold text-primary" id="totalHargaTxt" style="font-size: 1.85rem; line-height: 1;">Rp 0</div>
                            </div>

                            <button type="submit" class="btn-brand w-100 py-3 fs-5 mb-2">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan
                            </button>

                            <div class="text-center mt-3">
                                <a href="<?= in_array((int)$_SESSION['level'], [2, 3], true) ? route_url('kasir') : route_url('transaksi') ?>" class="text-secondary small text-decoration-none">
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
    <script src="<?= public_url('js/searchable-select.js') ?>"></script>
    <script>
    const ticketCatalog = <?= json_encode($all_tickets) ?>;

    function formatRupiah(num) {
        return 'Rp ' + (Number(num) || 0).toLocaleString('id-ID');
    }

    function changeTicketQty(id, delta) {
        const input = document.getElementById('ticket_qty_' + id);
        if (!input) return;
        let val = parseInt(input.value) || 0;
        val = Math.max(0, val + delta);
        input.value = val;
        recalculate();
    }

    function recalculate() {
        let grandTotal = 0;
        let grandQty = 0;
        const summaryList = document.getElementById('summaryTicketList');
        let summaryHTML = '';

        for (const [id, t] of Object.entries(ticketCatalog)) {
            const input = document.getElementById('ticket_qty_' + id);
            const card  = document.getElementById('cardTicket_' + id);
            const subTxt = document.getElementById('subtotalTicketTxt_' + id);
            const qty = input ? (parseInt(input.value) || 0) : 0;
            const subtotal = qty * t.harga;

            if (subTxt) subTxt.innerText = formatRupiah(subtotal);
            if (card) card.classList.toggle('active', qty > 0);

            if (qty > 0) {
                grandTotal += subtotal;
                grandQty   += qty;
                summaryHTML += `
                    <div class="d-flex justify-content-between align-items-center mb-2 text-muted" style="font-size: 0.875rem;">
                        <span>
                            <i class="fa-solid fa-ticket text-primary me-1" style="font-size: 0.75rem;"></i>
                            ${t.nama_tiket} (<strong class="text-dark">${qty}x</strong>)
                        </span>
                        <strong class="text-dark">${formatRupiah(subtotal)}</strong>
                    </div>
                `;
            }
        }

        if (grandQty === 0) {
            summaryList.innerHTML = `
                <div class="text-muted small text-center py-2">
                    Belum ada tiket yang dipilih.
                </div>
            `;
        } else {
            summaryList.innerHTML = summaryHTML;
        }

        document.getElementById('totalHargaTxt').innerText = formatRupiah(grandTotal);
        document.getElementById('totalQtyBadge').innerText = grandQty + ' Tiket';
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
        recalculate();
    });
    </script>
</body>
</html>
