<?php
require '../../app/config.php';
check_auth([1, 2, 3]);

$active_menu = in_array((int)$_SESSION['level'], [2, 3], true) ? 'kasir' : 'transaksi';
$base_view = '..';

// Ambil seluruh tarif tiket dari database secara dinamis (Feedback-6 Poin 4)
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

// Ambil daftar pengguna untuk dropdown akun terdaftar
$users_list = $conn->query("SELECT id_user, nama, username, email FROM users ORDER BY nama ASC");

$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Token keamanan sesi kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $cust_type           = trim($_POST['cust_type'] ?? 'manual');
        $nama_pemesan_custom = trim($_POST['nama_pemesan_custom'] ?? '');
        $id_user_selected    = (int)($_POST['id_user'] ?? $_SESSION['id_user']);
        $metode_pembayaran   = trim($_POST['metode_pembayaran'] ?? 'Tunai');
        $status              = trim($_POST['status'] ?? 'done');

        // Tentukan id_user dan nama_pemesan yang disimpan di tabel transaksi
        if ($cust_type === 'manual') {
            // Walk-in / Pengunjung Langsung: foreign key id_user diisi kasir yang melayani
            $id_user_transaksi = (int)$_SESSION['id_user'];
            $nama_pemesan_val  = !empty($nama_pemesan_custom) ? $nama_pemesan_custom : 'Pengunjung Loket (Tamu)';
        } else {
            // Akun terdaftar dipilih
            $id_user_transaksi = ($id_user_selected > 0) ? $id_user_selected : (int)$_SESSION['id_user'];
            $nama_pemesan_val  = null; // Akan join langsung ke tabel users
        }

        // Cek filter kata terlarang (Toxic Words) pada nama manual jika diisi
        if (!empty($nama_pemesan_custom) && has_toxic_words($nama_pemesan_custom)) {
            $toxicHits = find_toxic_words($nama_pemesan_custom);
            $error_msg = "Nama pemesan memuat kata yang dilarang (" . e(implode(', ', array_unique($toxicHits))) . "). Harap gunakan bahasa yang sopan.";
        } else {
            // Ambil kuantitas tiap kategori tiket secara dinamis
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
                // Fallback backward-compatible legacy input (quantity1 & quantity2)
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
                $error_msg = "Silakan pilih minimal 1 lembar tiket untuk melanjutkan transaksi.";
            } else {
                $tgl_pemesanan = date('Y-m-d');
                $nama_gambar   = null;

                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO transaksi (id_user, nama_pemesan, tgl_pemesanan, total_harga, metode_pembayaran, bukti_pembayaran, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ississs", $id_user_transaksi, $nama_pemesan_val, $tgl_pemesanan, $total_harga, $metode_pembayaran, $nama_gambar, $status);
                    $stmt->execute();
                    $new_id = $conn->insert_id;
                    $stmt->close();

                    $stmt_d = $conn->prepare("INSERT INTO detail_transaksi (id_transaksi, jenis_tiket, quantity, sub_total) VALUES (?, ?, ?, ?)");
                    foreach ($order_items as $item) {
                        $stmt_d->bind_param("isii", $new_id, $item['nama_tiket'], $item['qty'], $item['subtotal']);
                        $stmt_d->execute();
                    }
                    $stmt_d->close();

                    $conn->commit();

                    if (function_exists('log_activity')) {
                        $p_label = !empty($nama_pemesan_val) ? "Tamu: {$nama_pemesan_val}" : "User ID #{$id_user_transaksi}";
                        log_activity('TAMBAH', 'transaksi', "Membuat transaksi loket POS ID #{$new_id} ({$p_label}) total Rp " . number_format($total_harga, 0, ',', '.') . " ({$metode_pembayaran})");
                    }

                    header("Location: " . route_url('nota', ['id' => $new_id]));
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_msg = "Gagal memproses transaksi loket: " . e($e->getMessage());
                }
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
            display: flex;
            flex-column: column;
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
            color: #0284c7;
        }
        .cust-type-pill {
            cursor: pointer;
            padding: 0.6rem 1.1rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            border: 1.5px solid var(--border-color, #cbd5e1);
            background: var(--bg-card, #ffffff);
            color: var(--text-main, #334155);
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .cust-type-pill.active {
            border-color: #0284c7;
            background: #0284c7;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
        }
        .quick-cash-chip {
            cursor: pointer;
            border: 1px solid var(--border-color, #cbd5e1);
            background: var(--bg-page, #f8fafc);
            color: var(--text-main, #334155);
            border-radius: 8px;
            padding: 0.3rem 0.6rem;
            font-size: 0.78rem;
            font-weight: 600;
            transition: all 0.15s;
        }
        .quick-cash-chip:hover {
            border-color: #0284c7;
            color: #0284c7;
            background: rgba(2, 132, 199, 0.08);
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
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Riwayat
                    </a>
                </div>
            </header>

            <div class="page-heading mb-4">
                <h2 class="fw-bold text-dark mb-1" style="font-size: 1.75rem;">Kasir Loket Penjualan Tiket (POS)</h2>
                <p class="text-muted mb-0">Pilih jenis tiket dan kuantitas, tentukan data pengunjung & metode pembayaran, lalu cetak nota struk.</p>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                    <i class="fa-solid fa-circle-exclamation fs-5"></i>
                    <div><?= e($error_msg) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="posForm">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="cust_type" id="custTypeInput" value="manual">

                <div class="row g-4">
                    <!-- Left Column: Tiket & Customer Selection -->
                    <div class="col-12 col-lg-7 col-xl-8">
                        
                        <!-- Customer Info Card (Feedback-6 Poin 4: Walk-in Manual & Registered User) -->
                        <div class="modern-card mb-4">
                            <div class="modern-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span class="fw-bold text-dark">
                                    <i class="fa-solid fa-user me-2 text-primary"></i> Data Pelanggan / Pengunjung
                                </span>
                                <span class="badge bg-light text-secondary border small">Loket Kasir</span>
                            </div>
                            <div class="modern-card-body">
                                <!-- Switcher: Pengunjung Langsung vs Akun Terdaftar -->
                                <div class="mb-3 d-flex flex-wrap gap-2">
                                    <div class="cust-type-pill active" id="pillManual" onclick="switchCustType('manual')">
                                        <i class="fa-solid fa-user-clock"></i>
                                        <span>Pengunjung Langsung (Tamu Loket)</span>
                                    </div>
                                    <div class="cust-type-pill" id="pillRegistered" onclick="switchCustType('registered')">
                                        <i class="fa-solid fa-address-book"></i>
                                        <span>Pilih Akun Terdaftar</span>
                                    </div>
                                </div>

                                <!-- Field 1: Input Manual Nama Tamu (Default) -->
                                <div id="secManualCust">
                                    <label class="form-label fw-semibold text-secondary" style="font-size: 0.875rem;">
                                        Nama Lengkap Pengunjung / Rombongan:
                                    </label>
                                    <div class="input-icon-group mb-1">
                                        <i class="fa-solid fa-id-card input-icon"></i>
                                        <input 
                                            type="text" 
                                            class="form-control-modern" 
                                            name="nama_pemesan_custom" 
                                            id="nama_pemesan_custom" 
                                            placeholder="Contoh: Bpk. H. Rahmat / Rombongan SMPN 1 Tanggul"
                                            maxlength="100"
                                            value="<?= e($_POST['nama_pemesan_custom'] ?? '') ?>"
                                        >
                                    </div>
                                    <small class="text-muted d-block">
                                        <i class="fa-solid fa-circle-info me-1 text-primary"></i>
                                        Opsional. Jika dikosongkan, nama otomatis dicatat sebagai <em>Pengunjung Loket (Tamu)</em>.
                                    </small>
                                </div>

                                <!-- Field 2: Select Option Akun Terdaftar dengan Searchable Select (Feedback-6 Poin 5) -->
                                <div id="secRegisteredCust" style="display: none;">
                                    <label class="form-label fw-semibold text-secondary" style="font-size: 0.875rem;">
                                        Cari Akun Pengguna Terdaftar:
                                    </label>
                                    <select class="form-select-modern searchable-select" name="id_user" id="id_user">
                                        <?php if ($users_list && $users_list->num_rows > 0): ?>
                                            <?php while ($u = $users_list->fetch_assoc()): ?>
                                                <option value="<?= (int)$u['id_user'] ?>" <?= ($u['id_user'] == $_SESSION['id_user']) ? 'selected' : '' ?>>
                                                    <?= e($u['nama']) ?> (<?= e($u['username']) ?><?= !empty($u['email']) ? ' - ' . e($u['email']) : '' ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                    <small class="text-muted mt-1 d-block">
                                        Pilih akun pengunjung yang telah terdaftar dalam sistem untuk sinkronisasi riwayat transaksi.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Ticket Selection Card (Feedback-6 Poin 4: Dynamic Ticket Categories) -->
                        <div class="modern-card mb-4">
                            <div class="modern-card-header d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark">
                                    <i class="fa-solid fa-ticket me-2 text-primary"></i> Pilih Kategori & Jumlah Tiket
                                </span>
                                <span class="badge badge-modern-primary"><?= count($all_tickets) ?> Kategori Tersedia</span>
                            </div>
                            <div class="modern-card-body">
                                <?php if (empty($all_tickets)): ?>
                                    <div class="alert alert-warning mb-0">
                                        Belum ada data tarif tiket di database. Silakan tambahkan kategori tiket terlebih dahulu pada menu Kategori Tiket.
                                    </div>
                                <?php else: ?>
                                    <div class="row g-3">
                                        <?php foreach ($all_tickets as $t): ?>
                                            <div class="col-12 col-sm-6 col-xl-6">
                                                <div class="pos-ticket-card" id="cardTicket_<?= $t['id_tiket'] ?>">
                                                    <div>
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <div class="fw-bold fs-5 text-dark"><?= e($t['nama_tiket']) ?></div>
                                                            <div class="metric-icon-box blue" style="width: 42px; height: 42px; font-size: 1.15rem;">
                                                                <i class="fa-solid <?= e($t['ikon']) ?>"></i>
                                                            </div>
                                                        </div>
                                                        <div class="text-muted small mb-3">
                                                            Kategori resmi pemandian &bull; Sumber alami Patemon
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="fw-extrabold text-primary fs-5">
                                                                <?= format_rupiah($t['harga']) ?>
                                                            </div>
                                                            <div class="qty-stepper">
                                                                <button type="button" onclick="changeTicketQty(<?= $t['id_tiket'] ?>, -1)">
                                                                    <i class="fa-solid fa-minus"></i>
                                                                </button>
                                                                <input 
                                                                    type="number" 
                                                                    id="ticket_qty_<?= $t['id_tiket'] ?>" 
                                                                    name="tickets[<?= $t['id_tiket'] ?>]" 
                                                                    value="0" 
                                                                    min="0" 
                                                                    readonly
                                                                >
                                                                <button type="button" onclick="changeTicketQty(<?= $t['id_tiket'] ?>, 1)">
                                                                    <i class="fa-solid fa-plus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="text-end text-muted small mt-2">
                                                            Subtotal: <strong id="subtotalTicketTxt_<?= $t['id_tiket'] ?>" class="text-dark">Rp 0</strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Payment Method Card -->
                        <div class="modern-card">
                            <div class="modern-card-header">
                                <span class="fw-bold text-dark">
                                    <i class="fa-solid fa-credit-card me-2 text-primary"></i> Metode Pembayaran & Status
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
                                    <select class="form-select-modern searchable-select" name="status">
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
                                <span class="badge badge-modern-primary"><i class="fa-solid fa-cart-shopping"></i> POS Loket</span>
                            </div>

                            <!-- Dynamic itemized list of selected tickets -->
                            <div id="summaryTicketList" class="mb-3">
                                <div class="text-muted small text-center py-3" id="emptyTicketNotice">
                                    <i class="fa-solid fa-ticket-simple fs-4 text-secondary mb-2 d-block opacity-50"></i>
                                    Belum ada tiket yang dipilih.
                                </div>
                            </div>

                            <div class="pos-total-box p-3 rounded-3 mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small fw-bold text-uppercase">Total Tagihan Loket:</span>
                                    <span class="badge bg-primary text-white" id="totalQtyBadge">0 Tiket</span>
                                </div>
                                <div class="fw-extrabold text-primary" id="totalHargaTxt" style="font-size: 1.85rem; line-height: 1;">Rp 0</div>
                            </div>

                            <!-- Kalkulator Kembalian Uang Tunai -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-semibold text-secondary small mb-0">Uang Diterima (Rp):</label>
                                    <span class="quick-cash-chip" onclick="setCashPas()">Uang Pas</span>
                                </div>
                                <div class="input-icon-group mb-2">
                                    <i class="fa-solid fa-money-bill input-icon"></i>
                                    <input 
                                        type="number" 
                                        class="form-control-modern" 
                                        id="uangBayar" 
                                        placeholder="0" 
                                        min="0"
                                        oninput="hitungKembalian()"
                                    >
                                </div>
                                <!-- Quick chips -->
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="quick-cash-chip" onclick="addCash(10000)">+10rb</span>
                                    <span class="quick-cash-chip" onclick="addCash(20000)">+20rb</span>
                                    <span class="quick-cash-chip" onclick="addCash(50000)">+50rb</span>
                                    <span class="quick-cash-chip" onclick="addCash(100000)">+100rb</span>
                                    <span class="quick-cash-chip" onclick="clearCash()">Reset</span>
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
                                <a href="<?= in_array((int)$_SESSION['level'], [2, 3], true) ? route_url('kasir') : route_url('transaksi') ?>" class="text-secondary small text-decoration-none">
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
    <script src="<?= public_url('js/searchable-select.js') ?>"></script>
    <script>
    // Catalog Tiket Dinamis dari Database
    const ticketCatalog = <?= json_encode($all_tickets) ?>;

    function formatRupiah(num) {
        return 'Rp ' + (Number(num) || 0).toLocaleString('id-ID');
    }

    // Customer Type Switcher (Manual / Walk-in vs Registered)
    function switchCustType(type) {
        const input = document.getElementById('custTypeInput');
        const pillManual = document.getElementById('pillManual');
        const pillRegistered = document.getElementById('pillRegistered');
        const secManual = document.getElementById('secManualCust');
        const secRegistered = document.getElementById('secRegisteredCust');

        input.value = type;
        if (type === 'registered') {
            pillRegistered.classList.add('active');
            pillManual.classList.remove('active');
            secRegistered.style.display = 'block';
            secManual.style.display = 'none';
        } else {
            pillManual.classList.add('active');
            pillRegistered.classList.remove('active');
            secManual.style.display = 'block';
            secRegistered.style.display = 'none';
        }
    }

    // Quantity Stepper
    function changeTicketQty(id, delta) {
        const input = document.getElementById('ticket_qty_' + id);
        if (!input) return;
        let val = parseInt(input.value) || 0;
        val = Math.max(0, val + delta);
        input.value = val;
        recalculate();
    }

    // Hitung Ulang Total & Render Live Ringkasan
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
                <div class="text-muted small text-center py-3" id="emptyTicketNotice">
                    <i class="fa-solid fa-ticket-simple fs-4 text-secondary mb-2 d-block opacity-50"></i>
                    Belum ada tiket yang dipilih.
                </div>
            `;
        } else {
            summaryList.innerHTML = summaryHTML;
        }

        document.getElementById('totalHargaTxt').innerText = formatRupiah(grandTotal);
        document.getElementById('totalQtyBadge').innerText = grandQty + ' Tiket';

        hitungKembalian();
    }

    // Kalkulator Kembalian
    function hitungKembalian() {
        let grandTotal = 0;
        for (const [id, t] of Object.entries(ticketCatalog)) {
            const input = document.getElementById('ticket_qty_' + id);
            const qty = input ? (parseInt(input.value) || 0) : 0;
            grandTotal += (qty * t.harga);
        }

        const bayar = parseInt(document.getElementById('uangBayar').value) || 0;
        const kembalian = Math.max(0, bayar - grandTotal);
        document.getElementById('uangKembalianTxt').innerText = formatRupiah(kembalian);
    }

    function addCash(amount) {
        const input = document.getElementById('uangBayar');
        const curr = parseInt(input.value) || 0;
        input.value = curr + amount;
        hitungKembalian();
    }

    function clearCash() {
        document.getElementById('uangBayar').value = '';
        hitungKembalian();
    }

    function setCashPas() {
        let grandTotal = 0;
        for (const [id, t] of Object.entries(ticketCatalog)) {
            const input = document.getElementById('ticket_qty_' + id);
            const qty = input ? (parseInt(input.value) || 0) : 0;
            grandTotal += (qty * t.harga);
        }
        document.getElementById('uangBayar').value = grandTotal;
        hitungKembalian();
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
