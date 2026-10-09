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

// Ambil daftar pengguna untuk dropdown akun terdaftar (termasuk nomor telepon)
$users_list = $conn->query("SELECT id_user, nama, username, email, no_telepon FROM users WHERE deleted_at IS NULL ORDER BY nama ASC");

$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Token keamanan sesi kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $cust_type           = trim($_POST['cust_type'] ?? 'manual');
        $nama_pemesan_custom = trim($_POST['nama_pemesan_custom'] ?? '');
        $no_telepon_custom   = trim($_POST['no_telepon_custom'] ?? '');
        $id_user_selected    = (int)($_POST['id_user'] ?? $_SESSION['id_user']);
        $metode_pembayaran   = trim($_POST['metode_pembayaran'] ?? 'Tunai');
        $status              = trim($_POST['status'] ?? 'done');
        $uang_bayar          = (int)($_POST['uang_bayar'] ?? 0);
        $kasir_id            = (int)($_SESSION['id_user'] ?? 0);

        // Tentukan id_user, nama_pemesan, dan no_telepon yang disimpan di tabel transaksi (Feedback-8 Poin 2)
        if ($cust_type === 'manual') {
            // Walk-in / Pengunjung Langsung: foreign key id_user diisi kasir yang melayani
            $id_user_transaksi  = (int)$_SESSION['id_user'];
            $nama_pemesan_val   = !empty($nama_pemesan_custom) ? $nama_pemesan_custom : 'Pengunjung Loket (Tamu)';
            $no_telepon_val     = !empty($no_telepon_custom) ? $no_telepon_custom : null;
        } else {
            // Akun terdaftar dipilih: nomor telepon otomatis diambil dari database tabel users
            $id_user_transaksi  = ($id_user_selected > 0) ? $id_user_selected : (int)$_SESSION['id_user'];
            $nama_pemesan_val   = null; // Akan join langsung ke tabel users
            $no_telepon_val     = null;
            $stmt_u = $conn->prepare("SELECT no_telepon FROM users WHERE id_user = ? LIMIT 1");
            $stmt_u->bind_param("i", $id_user_transaksi);
            $stmt_u->execute();
            $u_row = $stmt_u->get_result()->fetch_assoc();
            $stmt_u->close();
            if ($u_row && !empty($u_row['no_telepon'])) {
                $no_telepon_val = $u_row['no_telepon'];
            }
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
            } elseif ($metode_pembayaran === 'Tunai' && $uang_bayar < $total_harga) {
                // Feedback-7 Poin 3: Validasi uang kurang
                $kurang = $total_harga - $uang_bayar;
                $error_msg = "Uang pembayaran yang diterima (" . format_rupiah($uang_bayar) . ") kurang " . format_rupiah($kurang) . " dari total tagihan (" . format_rupiah($total_harga) . ").";
            } else {
                // Non-Tunai default bayar = total harga jika tidak diinput
                if ($metode_pembayaran !== 'Tunai' && $uang_bayar <= 0) {
                    $uang_bayar = $total_harga;
                }
                $kembalian = max(0, $uang_bayar - $total_harga);

                // Feedback-7 Poin 3 & Poin 7: Upload bukti pembayaran non-tunai
                $nama_gambar = null;
                if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload_res = secure_upload_image($_FILES['bukti_pembayaran'], __DIR__ . '/../../app/payment/');
                    if ($upload_res['success']) {
                        $nama_gambar = $upload_res['filename'];
                    } else {
                        $error_msg = "Bukti Pembayaran: " . $upload_res['error'];
                    }
                }

                if (empty($error_msg)) {
                    $tgl_pemesanan = date('Y-m-d');

                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare("INSERT INTO transaksi (id_user, kasir_id, nama_pemesan, no_telepon_pemesan, tgl_pemesanan, total_harga, uang_bayar, kembalian, metode_pembayaran, bukti_pembayaran, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iisssiiisss", $id_user_transaksi, $kasir_id, $nama_pemesan_val, $no_telepon_val, $tgl_pemesanan, $total_harga, $uang_bayar, $kembalian, $metode_pembayaran, $nama_gambar, $status);
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            flex-direction: column !important;
            justify-content: space-between;
            gap: 1.25rem;
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
        .pos-kembalian-box {
            transition: all 0.2s ease;
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
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="themeToggleBtn" onclick="togglePatemonTheme()" title="Beralih Mode Gelap/Terang" style="border-radius: 8px;">
                        <i class="fa-solid fa-moon"></i> <span id="themeLabelText">Tema</span>
                    </button>
                    <a href="<?= in_array((int)$_SESSION['level'], [2, 3], true) ? route_url('kasir') : route_url('transaksi') ?>" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Riwayat
                    </a>
                </div>
            </header>

            <div class="page-heading mb-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h3 class="fw-bold mb-1 text-dark">Kasir Loket (Point of Sale)</h3>
                        <p class="text-muted mb-0">Input penjualan tiket loket fisik secara cepat, akurat, dan cetak struk pembayaran.</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 border-0 shadow-sm" style="border-radius: 12px;">
                    <i class="fa-solid fa-circle-exclamation fs-5 flex-shrink-0"></i>
                    <div><?= e($error_msg) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="posForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="cust_type" id="custTypeInput" value="manual">
                <input type="hidden" name="uang_bayar" id="hiddenUangBayar" value="0">

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
                                <!-- Type Switcher Pills -->
                                <div class="d-flex gap-2 mb-3">
                                    <div class="cust-type-pill active" id="pillManual" onclick="switchCustType('manual')">
                                        <i class="fa-solid fa-user-plus"></i> Pengunjung Langsung (Tamu Loket)
                                    </div>
                                    <div class="cust-type-pill" id="pillRegistered" onclick="switchCustType('registered')">
                                        <i class="fa-solid fa-address-book"></i> Pilih Akun Terdaftar
                                    </div>
                                </div>

                                <!-- Field 1: Manual / Walk-in Name & Phone Input (Feedback-8 Poin 2) -->
                                <div id="secManualCust">
                                    <div class="row g-3">
                                        <div class="col-12 col-md-7">
                                            <label class="form-label fw-semibold text-secondary" style="font-size: 0.875rem;">
                                                Nama Pengunjung / Rombongan:
                                            </label>
                                            <div class="input-icon-group mb-1">
                                                <i class="fa-solid fa-signature input-icon"></i>
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
                                            <small class="text-muted d-block" style="font-size: 0.775rem;">
                                                <i class="fa-solid fa-circle-info me-1 text-primary"></i>
                                                Opsional. Jika kosong, dicatat sebagai <em>Pengunjung Loket (Tamu)</em>.
                                            </small>
                                        </div>
                                        <div class="col-12 col-md-5">
                                            <label class="form-label fw-semibold text-secondary" style="font-size: 0.875rem;">
                                                No. Telepon / WhatsApp:
                                            </label>
                                            <div class="input-icon-group mb-1">
                                                <i class="fa-solid fa-phone input-icon"></i>
                                                <input 
                                                    type="text" 
                                                    class="form-control-modern" 
                                                    name="no_telepon_custom" 
                                                    id="no_telepon_custom" 
                                                    placeholder="Contoh: 081234567890"
                                                    maxlength="20"
                                                    value="<?= e($_POST['no_telepon_custom'] ?? '') ?>"
                                                >
                                            </div>
                                            <small class="text-muted d-block" style="font-size: 0.775rem;">
                                                <i class="fa-solid fa-shield-halved me-1 text-success"></i>
                                                Kontak pengunjung untuk konfirmasi atau struk digital.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Field 2: Select Option Akun Terdaftar dengan Searchable Select -->
                                <div id="secRegisteredCust" style="display: none;">
                                    <label class="form-label fw-semibold text-secondary" style="font-size: 0.875rem;">
                                        Cari Akun Pengguna Terdaftar:
                                    </label>
                                    <select class="form-select-modern searchable-select" name="id_user" id="id_user">
                                        <?php if ($users_list && $users_list->num_rows > 0): ?>
                                            <?php while ($u = $users_list->fetch_assoc()): ?>
                                                <option value="<?= (int)$u['id_user'] ?>" <?= ($u['id_user'] == $_SESSION['id_user']) ? 'selected' : '' ?>>
                                                    <?= e($u['nama']) ?> (<?= e($u['username']) ?><?= !empty($u['no_telepon']) ? ' &bull; Telp: ' . e($u['no_telepon']) : '' ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                    <small class="text-muted mt-1 d-block" style="font-size: 0.775rem;">
                                        <i class="fa-solid fa-circle-check me-1 text-success"></i>
                                        Nomor telepon otomatis diambil dari data akun terdaftar pengguna.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Ticket Selection Card (Feedback-6 Poin 4 & Feedback-7 Poin 3: Dynamic Ticket Categories Clean Layout) -->
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
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div>
                                                                <div class="fw-bold fs-5 text-dark"><?= e($t['nama_tiket']) ?></div>
                                                                <div class="text-muted small mt-1">
                                                                    Kategori resmi pemandian &bull; Sumber alami Patemon
                                                                </div>
                                                            </div>
                                                            <div class="metric-icon-box blue flex-shrink-0 ms-2" style="width: 44px; height: 44px; font-size: 1.2rem; border-radius: 12px;">
                                                                <i class="fa-solid <?= e($t['ikon']) ?>"></i>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="pt-2 border-top">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <span class="text-muted small d-block" style="font-size: 0.75rem;">Tarif Tiket:</span>
                                                                <div class="fw-extrabold text-primary fs-5">
                                                                    <?= format_rupiah($t['harga']) ?>
                                                                </div>
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
                                                        <div class="d-flex justify-content-between align-items-center text-muted small mt-2 pt-1 border-top border-light">
                                                            <span>Subtotal:</span>
                                                            <strong id="subtotalTicketTxt_<?= $t['id_tiket'] ?>" class="text-dark fs-6">Rp 0</strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Payment Method Card (Feedback-7 Poin 3: Non-Cash Proof Upload) -->
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

                                <!-- Dynamic Non-Cash Proof Upload (Feedback-7 Poin 3 & Poin 7) -->
                                <div id="secProofUpload" class="p-3 rounded-4 border bg-light-subtle mb-3" style="display: none;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-bold text-dark mb-0" style="font-size: 0.875rem;">
                                            <i class="fa-solid fa-camera me-1 text-primary"></i> Unggah Bukti Pembayaran Non-Tunai
                                        </label>
                                        <span class="badge bg-primary text-white" style="font-size: 0.7rem;">QRIS / E-Wallet / Transfer</span>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="triggerCameraCapture()" style="border-radius: 8px;">
                                            <i class="fa-solid fa-camera me-1"></i> Ambil Foto Langsung
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('bukti_pembayaran_file').click()" style="border-radius: 8px;">
                                            <i class="fa-solid fa-upload me-1"></i> Upload dari Perangkat
                                        </button>
                                    </div>
                                    <input type="file" name="bukti_pembayaran" id="bukti_pembayaran_file" class="d-none" accept="image/*" onchange="previewPaymentProof(this)">

                                    <div id="proofPreviewBox" class="mt-2 text-center p-2 border rounded-3 bg-white" style="display: none;">
                                        <img id="proofPreviewImg" src="" style="max-height: 160px; max-width: 100%; border-radius: 8px; object-fit: contain;">
                                        <div class="mt-2 d-flex justify-content-center gap-2">
                                            <span class="badge bg-success small"><i class="fa-solid fa-check me-1"></i> Foto Siap Diunggah</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="clearPaymentProof()" style="font-size: 0.75rem; border-radius: 6px;">
                                                <i class="fa-solid fa-trash me-1"></i> Hapus
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2" style="font-size: 0.775rem;">
                                        Format: JPG, PNG, WebP (Maksimal 2MB). Foto struk transfer atau bukti scan QRIS pelanggan.
                                    </small>
                                </div>

                                <div class="form-group mb-0">
                                    <label class="form-label fw-semibold text-secondary" style="font-size: 0.875rem;">Status Transaksi:</label>
                                    <div class="d-flex align-items-center gap-3 p-2 bg-light rounded-3 border">
                                        <div class="form-check form-switch mb-0 fs-5">
                                            <input class="form-check-input" type="checkbox" role="switch" id="statusSwitch" checked onchange="toggleStatusSwitch(this)">
                                        </div>
                                        <div>
                                            <span id="statusSwitchBadge" class="badge bg-success-subtle text-success fw-bold px-2.5 py-1.5 rounded-pill">
                                                <i class="fa-solid fa-circle-check me-1"></i> Lunas (Sudah Dibayar)
                                            </span>
                                            <input type="hidden" name="status" id="statusHiddenInput" value="done">
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-1" style="font-size: 0.775rem;">
                                        Geser switch untuk mengubah status: Lunas (Sudah Dibayar) / Belum Dibayar (Pending).
                                    </small>
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

                            <!-- Kalkulator Kembalian Uang Tunai (Feedback-7 Poin 3: Deficit Minus Logic) -->
                            <div id="secCashCalculator" class="mb-3">
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
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    <span class="quick-cash-chip text-primary fw-bold" onclick="setCashPas()"><i class="fa-solid fa-check me-1"></i>Uang Pas</span>
                                    <span class="quick-cash-chip" onclick="setExactCash(500)">500</span>
                                    <span class="quick-cash-chip" onclick="setExactCash(1000)">1.000</span>
                                    <span class="quick-cash-chip" onclick="setExactCash(2000)">2.000</span>
                                    <span class="quick-cash-chip" onclick="setExactCash(5000)">5.000</span>
                                    <span class="quick-cash-chip" onclick="setExactCash(10000)">10.000</span>
                                    <span class="quick-cash-chip" onclick="setExactCash(20000)">20.000</span>
                                    <span class="quick-cash-chip" onclick="setExactCash(50000)">50.000</span>
                                    <span class="quick-cash-chip" onclick="setExactCash(100000)">100.000</span>
                                    <span class="quick-cash-chip text-danger" onclick="clearCash()"><i class="fa-solid fa-rotate-left me-1"></i>Reset</span>
                                </div>
                            </div>

                            <div class="pos-kembalian-box d-flex justify-content-between align-items-center p-3 rounded-3 mb-4" id="boxKembalian" style="background: rgba(100, 116, 139, 0.08);">
                                <span class="fw-bold small" id="lblKembalian">Uang Kembalian:</span>
                                <strong class="fs-5" id="uangKembalianTxt">Rp 0</strong>
                            </div>

                            <button type="submit" class="btn-brand w-100 py-3 fs-5" id="btnSubmit">
                                <i class="fa-solid fa-print me-1"></i> Proses & Cetak Nota
                            </button>

                            <div class="mt-3 p-2 rounded-3 bg-light text-center border" style="font-size: 0.76rem;">
                                <span class="text-muted"><i class="fa-solid fa-keyboard me-1 text-primary"></i> <strong>Pintasan POS:</strong></span>
                                <span class="badge bg-secondary ms-1">F2</span> Baru
                                <span class="badge bg-secondary ms-1">F4</span> Uang Diterima
                                <span class="badge bg-secondary ms-1">Enter</span> Bayar & Cetak
                            </div>

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

    <!-- Modal Live WebRTC Camera Capture (Feedback-8 Poin 2) -->
    <div class="modal fade" id="modalCameraCapture" tabindex="-1" aria-labelledby="modalCameraLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 class="modal-title fs-6 fw-bold text-white" id="modalCameraLabel">
                        <i class="fa-solid fa-camera text-primary me-2"></i> Ambil Foto Bukti Pembayaran
                    </h5>
                    <button type="button" class="btn-close btn-close-white" onclick="closeCameraModal()" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3 bg-black text-center position-relative" style="min-height: 280px; display: flex; align-items: center; justify-content: center;">
                    <div id="cameraLoadingSpinner" class="py-5 text-white">
                        <div class="spinner-border text-primary mb-2" role="status"></div>
                        <div class="small">Mengakses kamera perangkat...</div>
                    </div>
                    <video id="cameraVideo" autoplay playsinline class="w-100 rounded-3 shadow-sm" style="max-height: 380px; object-fit: cover; display: none; background: #000;"></video>
                    <canvas id="cameraCanvas" style="display: none;"></canvas>
                </div>
                <div class="modal-footer bg-light border-0 justify-content-between p-3">
                    <button type="button" class="btn btn-secondary btn-sm px-3" onclick="closeCameraModal()" style="border-radius: 8px;">
                        <i class="fa-solid fa-xmark me-1"></i> Batal
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-dark btn-sm" id="btnSwitchCamera" onclick="switchCameraFacing()" style="border-radius: 8px; display: none;">
                            <i class="fa-solid fa-arrows-rotate me-1"></i> Balik Kamera
                        </button>
                        <button type="button" class="btn btn-primary px-4 fw-bold shadow-sm" id="btnSnapPhoto" onclick="capturePhotoFromStream()" style="border-radius: 8px;">
                            <i class="fa-solid fa-camera-retro me-1"></i> Ambil Foto
                        </button>
                    </div>
                </div>
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

    // Kalkulator Kembalian Uang Tunai (Mendukung Tampilan Minus/Defisit)
    function hitungKembalian() {
        let grandTotal = 0;
        for (const [id, t] of Object.entries(ticketCatalog)) {
            const input = document.getElementById('ticket_qty_' + id);
            const qty = input ? (parseInt(input.value) || 0) : 0;
            grandTotal += (qty * t.harga);
        }

        const bayarInput = document.getElementById('uangBayar');
        const bayar = parseInt(bayarInput.value) || 0;
        document.getElementById('hiddenUangBayar').value = bayar;

        const box = document.getElementById('boxKembalian');
        const lbl = document.getElementById('lblKembalian');
        const txt = document.getElementById('uangKembalianTxt');

        if (grandTotal === 0) {
            lbl.className = 'text-muted small fw-bold';
            lbl.innerText = 'Uang Kembalian:';
            txt.className = 'fs-5 text-muted';
            txt.innerText = 'Rp 0';
            box.style.background = 'rgba(100, 116, 139, 0.08)';
            box.style.border = 'none';
            return;
        }

        const selisih = bayar - grandTotal;
        if (selisih < 0) {
            // Defisit / Kurang (Feedback-7 Poin 3)
            lbl.className = 'text-danger fw-bold small';
            lbl.innerText = 'Kekurangan Uang:';
            txt.className = 'fs-5 text-danger fw-bold';
            txt.innerText = '- ' + formatRupiah(Math.abs(selisih));
            box.style.background = 'rgba(239, 68, 68, 0.1)';
            box.style.border = '1px solid rgba(239, 68, 68, 0.3)';
        } else {
            lbl.className = 'text-success fw-bold small';
            lbl.innerText = 'Uang Kembalian:';
            txt.className = 'fs-5 text-success fw-bold';
            txt.innerText = formatRupiah(selisih);
            box.style.background = 'rgba(16, 185, 129, 0.1)';
            box.style.border = '1px solid rgba(16, 185, 129, 0.3)';
        }
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

    function toggleStatusSwitch(elem) {
        const hidden = document.getElementById('statusHiddenInput');
        const badge = document.getElementById('statusSwitchBadge');
        if (elem.checked) {
            hidden.value = 'done';
            badge.className = 'badge bg-success-subtle text-success fw-bold px-2.5 py-1.5 rounded-pill';
            badge.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> Lunas (Sudah Dibayar)';
        } else {
            hidden.value = 'pending';
            badge.className = 'badge bg-warning-subtle text-warning fw-bold px-2.5 py-1.5 rounded-pill';
            badge.innerHTML = '<i class="fa-solid fa-clock me-1"></i> Belum Dibayar (Pending)';
        }
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

    function setExactCash(amount) {
        document.getElementById('uangBayar').value = amount;
        hitungKembalian();
    }

    function resetPosForm() {
        Swal.fire({
            title: 'Transaksi Baru?',
            text: 'Kosongkan formulir loket dan mulai transaksi baru? (F2)',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Reset',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#0284c7'
        }).then((result) => {
            if (result.isConfirmed) {
                for (const id of Object.keys(ticketCatalog)) {
                    const input = document.getElementById('ticket_qty_' + id);
                    if (input) input.value = 0;
                }
                document.getElementById('uangBayar').value = '';
                const namaCust = document.getElementById('nama_pemesan');
                if (namaCust) namaCust.value = '';
                const telpCust = document.getElementById('no_telepon_pemesan');
                if (telpCust) telpCust.value = '';
                clearPaymentProof();
                recalculate();
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'info',
                    title: 'Formulir loket telah dibersihkan',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        });
    }

    // Global POS Keyboard Shortcuts
    window.addEventListener('keydown', function(e) {
        // Jangan aktifkan jika sedang membuka modal camera
        if (document.getElementById('modalCameraCapture')?.classList.contains('show')) return;

        // F2: Transaksi Baru / Reset
        if (e.key === 'F2') {
            e.preventDefault();
            resetPosForm();
        }
        // F4: Fokus ke input Uang Diterima
        else if (e.key === 'F4') {
            e.preventDefault();
            const uangInput = document.getElementById('uangBayar');
            if (uangInput) {
                uangInput.focus();
                uangInput.select();
            }
        }
    });

    // Enter pada uangBayar langsung submit jika nominal sudah mencukupi
    document.addEventListener('DOMContentLoaded', () => {
        const ubInput = document.getElementById('uangBayar');
        if (ubInput) {
            ubInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('btnSubmit').click();
                }
            });
        }
    });

    // Payment Method Switching Listener
    document.querySelectorAll('input[name="metode_pembayaran"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const proofSec = document.getElementById('secProofUpload');
            const cashCalcSec = document.getElementById('secCashCalculator');
            if (this.value === 'Tunai') {
                if (proofSec) proofSec.style.display = 'none';
                if (cashCalcSec) cashCalcSec.style.display = 'block';
            } else {
                if (proofSec) proofSec.style.display = 'block';
                if (cashCalcSec) cashCalcSec.style.display = 'none';
                setCashPas();
            }
        });
    });

    // Real WebRTC Camera & Upload Handlers (Feedback-8 Poin 2)
    let cameraStream = null;
    let currentFacingMode = 'environment';

    async function triggerCameraCapture() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            Swal.fire({
                title: 'Kamera Tidak Didukung',
                text: 'Peramban Anda tidak mendukung akses WebRTC kamera langsung. Silakan gunakan tombol Upload dari Perangkat.',
                icon: 'warning',
                confirmButtonColor: '#0284c7'
            });
            return;
        }

        const modalEl = document.getElementById('modalCameraCapture');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        startCameraStream();
    }

    async function startCameraStream() {
        const video = document.getElementById('cameraVideo');
        const spinner = document.getElementById('cameraLoadingSpinner');
        const btnSwitch = document.getElementById('btnSwitchCamera');

        spinner.style.display = 'block';
        video.style.display = 'none';

        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }

        try {
            const constraints = {
                video: {
                    facingMode: { ideal: currentFacingMode },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            };

            cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = cameraStream;
            await video.play();

            spinner.style.display = 'none';
            video.style.display = 'block';
            if (btnSwitch) btnSwitch.style.display = 'inline-block';
        } catch (err) {
            console.error('Camera stream error:', err);
            spinner.style.display = 'none';
            closeCameraModal();
            Swal.fire({
                title: 'Akses Kamera Ditolak / Tidak Ditemukan',
                html: 'Browser memblokir izin kamera atau webcam tidak terdeteksi.<br><small class="text-muted">Pastikan Anda telah mengizinkan akses kamera pada peramban web atau gunakan tombol <b>Upload dari Perangkat</b>.</small>',
                icon: 'error',
                confirmButtonColor: '#0284c7'
            });
        }
    }

    function switchCameraFacing() {
        currentFacingMode = (currentFacingMode === 'environment') ? 'user' : 'environment';
        startCameraStream();
    }

    function closeCameraModal() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
        const modalEl = document.getElementById('modalCameraCapture');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }

    function capturePhotoFromStream() {
        const video = document.getElementById('cameraVideo');
        const canvas = document.getElementById('cameraCanvas');
        if (!video || !video.videoWidth) {
            Swal.fire('Kamera Belum Siap', 'Silakan tunggu video kamera aktif.', 'info');
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(blob => {
            if (!blob) {
                Swal.fire('Gagal Mengambil Foto', 'Terjadi kesalahan saat memproses tangkapan gambar.', 'error');
                return;
            }

            const fileName = 'struk_kamera_' + Date.now() + '.jpg';
            const file = new File([blob], fileName, { type: 'image/jpeg' });

            try {
                const dt = new DataTransfer();
                dt.items.add(file);
                const fileInput = document.getElementById('bukti_pembayaran_file');
                fileInput.files = dt.files;

                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('proofPreviewImg').src = e.target.result;
                    document.getElementById('proofPreviewBox').style.display = 'block';
                };
                reader.readAsDataURL(file);

                closeCameraModal();

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Foto struk berhasil diambil!',
                    showConfirmButton: false,
                    timer: 2000
                });
            } catch (err) {
                console.error('DataTransfer error:', err);
                closeCameraModal();
            }
        }, 'image/jpeg', 0.85);
    }

    function previewPaymentProof(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('proofPreviewImg').src = e.target.result;
                document.getElementById('proofPreviewBox').style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    }

    function clearPaymentProof() {
        const mainInput = document.getElementById('bukti_pembayaran_file');
        if (mainInput) mainInput.value = '';
        document.getElementById('proofPreviewImg').src = '';
        document.getElementById('proofPreviewBox').style.display = 'none';
    }

    // Form Submit Interceptor & Validation
    document.getElementById('posForm').addEventListener('submit', function(e) {
        let grandTotal = 0;
        let grandQty = 0;
        for (const [id, t] of Object.entries(ticketCatalog)) {
            const input = document.getElementById('ticket_qty_' + id);
            const qty = input ? (parseInt(input.value) || 0) : 0;
            grandTotal += (qty * t.harga);
            grandQty += qty;
        }

        if (grandQty <= 0) {
            e.preventDefault();
            Swal.fire({
                title: 'Tiket Belum Dipilih',
                text: 'Silakan pilih minimal 1 lembar tiket masuk untuk melanjutkan transaksi loket.',
                icon: 'warning',
                confirmButtonColor: '#0284c7'
            });
            return false;
        }

        const payMethod = document.querySelector('input[name="metode_pembayaran"]:checked')?.value || 'Tunai';
        const bayar = parseInt(document.getElementById('uangBayar').value) || 0;

        if (payMethod === 'Tunai' && bayar < grandTotal) {
            e.preventDefault();
            const kurang = grandTotal - bayar;
            Swal.fire({
                title: 'Uang Pembayaran Kurang',
                html: 'Total tagihan adalah <b>' + formatRupiah(grandTotal) + '</b>, sedangkan uang yang diterima <b>' + formatRupiah(bayar) + '</b>.<br><span class="text-danger fw-bold">Kekurangan uang: ' + formatRupiah(kurang) + '</span>.',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
            return false;
        }
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

    document.addEventListener('DOMContentLoaded', () => {
        const currentTheme = localStorage.getItem('patemon_theme') || 'light';
        applyPatemonTheme(currentTheme);
        recalculate();
    });
    </script>
    <script src="<?= public_url('js/image-upload-validator.js') ?>"></script>
</body>
</html>
