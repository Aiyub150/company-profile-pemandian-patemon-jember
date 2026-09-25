<?php 
require '../../app/config.php';

// Pastikan user sudah login
if (!isset($_SESSION['id_user'])) {
    header('Location: ' . route_url('login'));
    exit();
}

$id_user = (int)$_SESSION['id_user'];
$user_nama = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Pengunjung';
$error_msg = '';

// Ambil seluruh daftar kategori tiket dari database secara dinamis
$daftar_tiket = [];
$res_t = $conn->query("SELECT * FROM tiket ORDER BY id_tiket ASC");
if ($res_t) {
    while ($r = $res_t->fetch_assoc()) {
        $daftar_tiket[$r['id_tiket']] = [
            'id'    => (int)$r['id_tiket'],
            'nama'  => $r['nama_tiket'],
            'harga' => (int)$r['harga'],
            'ikon'  => $r['ikon'] ?? get_ticket_icon($r['nama_tiket'])
        ];
    }
}

if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Token keamanan sesi kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        // Ambil jumlah tiket yang dipesan (mendukung array tickets[$id] maupun input legacy)
        $order_items = [];
        $total_qty = 0;
        $total_harga = 0;

        if (isset($_POST['tickets']) && is_array($_POST['tickets'])) {
            foreach ($_POST['tickets'] as $id_tkt => $qty) {
                $id_tkt = (int)$id_tkt;
                $qty = max(0, (int)$qty);
                if ($qty > 0 && isset($daftar_tiket[$id_tkt])) {
                    $item_info = $daftar_tiket[$id_tkt];
                    $subtotal = $qty * $item_info['harga'];
                    $order_items[] = [
                        'id_tiket' => $id_tkt,
                        'nama'     => $item_info['nama'],
                        'qty'      => $qty,
                        'harga'    => $item_info['harga'],
                        'subtotal' => $subtotal
                    ];
                    $total_qty += $qty;
                    $total_harga += $subtotal;
                }
            }
        } else {
            // Fallback backward-compatible legacy inputs
            $qty_dewasa = max(0, (int)($_POST['quantity1'] ?? 0));
            $qty_anak   = max(0, (int)($_POST['quantity2'] ?? 0));
            foreach ($daftar_tiket as $tkt) {
                if ($tkt['nama'] === 'Dewasa' && $qty_dewasa > 0) {
                    $sub = $qty_dewasa * $tkt['harga'];
                    $order_items[] = ['id_tiket' => $tkt['id'], 'nama' => 'Dewasa', 'qty' => $qty_dewasa, 'harga' => $tkt['harga'], 'subtotal' => $sub];
                    $total_qty += $qty_dewasa;
                    $total_harga += $sub;
                } elseif ($tkt['nama'] === 'Anak-Anak' && $qty_anak > 0) {
                    $sub = $qty_anak * $tkt['harga'];
                    $order_items[] = ['id_tiket' => $tkt['id'], 'nama' => 'Anak-Anak', 'qty' => $qty_anak, 'harga' => $tkt['harga'], 'subtotal' => $sub];
                    $total_qty += $qty_anak;
                    $total_harga += $sub;
                }
            }
        }

        $metode_pembayaran = trim($_POST['metode_pembayaran'] ?? 'Bayar Di Loket');
        // Validasi pilihan metode
        $valid_methods = ['Bayar Di Loket', 'Qris', 'Transfer Bank'];
        if (!in_array($metode_pembayaran, $valid_methods, true)) {
            $metode_pembayaran = 'Bayar Di Loket';
        }

        if ($total_qty === 0) {
            $error_msg = "Silakan pilih minimal 1 tiket untuk melanjutkan pemesanan.";
        } else {
            $tgl_pemesanan   = date('Y-m-d');
            $status          = 'notyet';
            $nama_gambar     = null;

            // Kelola unggahan bukti pembayaran jika memilih QRIS atau Transfer Bank
            if (($metode_pembayaran === 'Qris' || $metode_pembayaran === 'Transfer Bank') && isset($_FILES["bukti_pembayaran"]) && $_FILES["bukti_pembayaran"]["error"] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES["bukti_pembayaran"]["tmp_name"];
                $original_name = $_FILES["bukti_pembayaran"]["name"];
                $file_size = $_FILES["bukti_pembayaran"]["size"];
                $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $tmp_name);
                finfo_close($finfo);

                $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];

                if (!in_array($ext, $allowed_ext, true) || !in_array($mime_type, $allowed_mime, true)) {
                    $error_msg = "Format bukti pembayaran harus berupa gambar valid (JPG, PNG, atau WEBP).";
                } elseif ($file_size > 2 * 1024 * 1024) { // max 2MB
                    $error_msg = "Ukuran file bukti pembayaran maksimal adalah 2 MB.";
                } else {
                    $target_dir = __DIR__ . '/../../app/payment/';
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }
                    $safe_filename = 'pay_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    if (move_uploaded_file($tmp_name, $target_dir . $safe_filename)) {
                        $nama_gambar = $safe_filename;
                    } else {
                        $error_msg = "Gagal menyimpan file bukti pembayaran ke server.";
                    }
                }
            }

            // =========================================================================
            // MODULAR DYNAMIC PAYMENT GATEWAY STUB (Midtrans / Xendit / Duitku)
            // =========================================================================
            // Fitur ini dinonaktifkan secara default dan dapat diaktifkan melalui:
            // dist/app/config.php -> define('FEATURE_PAYMENT_GATEWAY', true);
            if (defined('FEATURE_PAYMENT_GATEWAY') && FEATURE_PAYMENT_GATEWAY) {
                /*
                 * CONTOH INTEGRASI MODULAR GATEWAY:
                 * require_once __DIR__ . '/../../../vendor/midtrans/midtrans-php/Midtrans.php';
                 * \Midtrans\Config::$serverKey = 'YOUR_SERVER_KEY';
                 * \Midtrans\Config::$isProduction = false;
                 * \Midtrans\Config::$isSanitized = true;
                 * \Midtrans\Config::$is3ds = true;
                 * 
                 * $params = [
                 *     'transaction_details' => [
                 *         'order_id' => 'TRX-' . time() . '-' . $id_user,
                 *         'gross_amount' => $total_harga,
                 *     ],
                 *     'customer_details' => [
                 *         'first_name' => $user_nama,
                 *     ],
                 * ];
                 * $snapToken = \Midtrans\Snap::getSnapToken($params);
                 */
            }

            if (empty($error_msg)) {
                // Simpan data transaksi menggunakan DB Transaction
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO transaksi (id_user, tgl_pemesanan, total_harga, metode_pembayaran, bukti_pembayaran, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isisss", $id_user, $tgl_pemesanan, $total_harga, $metode_pembayaran, $nama_gambar, $status);
                    $stmt->execute();
                    $new_id_transaksi = $conn->insert_id;
                    $stmt->close();

                    // Simpan seluruh rincian tiket yang dipesan
                    $stmt_detail = $conn->prepare("INSERT INTO detail_transaksi (id_transaksi, jenis_tiket, quantity, sub_total) VALUES (?, ?, ?, ?)");
                    foreach ($order_items as $item) {
                        $stmt_detail->bind_param("isii", $new_id_transaksi, $item['nama'], $item['qty'], $item['subtotal']);
                        $stmt_detail->execute();
                    }
                    $stmt_detail->close();

                    $conn->commit();
                    header("Location: " . route_url('nota', ['id' => $new_id_transaksi]));
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_msg = "Terjadi kesalahan saat memproses transaksi: " . e($e->getMessage());
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
    <title>Pemesanan Tiket Masuk - Pemandian Patemon</title>

    <link rel="icon" type="image/x-icon" href="<?= public_url('img/icon.png') ?>" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= public_url('assets/css/main/app.css') ?>">
    <link rel="stylesheet" href="<?= public_url('css/modern-theme.css') ?>">
    <script src="<?= public_url('js/patemon-i18n.js') ?>"></script>
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
        body {
            background-color: var(--bg-body, #f8fafc);
            color: var(--text-main, #0f172a);
        }

        .booking-header {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            padding: 3rem 1.5rem 4rem;
            text-align: center;
            border-bottom-left-radius: 36px;
            border-bottom-right-radius: 36px;
            margin-bottom: -2.5rem;
            box-shadow: 0 10px 30px -10px rgba(2, 132, 199, 0.4);
        }

        .booking-container {
            max-width: 1040px;
            margin: 0 auto;
            padding: 0 1rem 4rem;
            position: relative;
            z-index: 10;
        }

        .ticket-pick-card {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.25rem;
            background: #ffffff;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .ticket-pick-card:hover {
            border-color: #0284c7;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(2, 132, 199, 0.12);
        }

        .ticket-pick-card.active {
            border-color: #0284c7;
            background: #f0f9ff;
        }

        .qty-stepper {
            display: inline-flex;
            align-items: center;
            background: #f1f5f9;
            border-radius: 12px;
            padding: 3px;
            border: 1px solid #e2e8f0;
        }

        .qty-stepper button {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: #ffffff;
            color: #0f172a;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: all 0.15s ease;
        }

        .qty-stepper button:hover {
            background: #0284c7;
            color: #ffffff;
        }

        .qty-stepper input {
            width: 44px;
            text-align: center;
            border: none;
            background: transparent;
            font-weight: 700;
            font-size: 1rem;
        }

        .pay-method-pill label {
            cursor: pointer;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #ffffff;
            transition: all 0.2s ease;
            font-weight: 600;
            font-size: 0.95rem;
            height: 100%;
        }

        .pay-method-pill input:checked + label {
            border-color: #0284c7;
            background: #f0f9ff;
            color: #0369a1;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }

        .dropzone-upload {
            border: 2px dashed #93c5fd;
            border-radius: 14px;
            padding: 1.5rem;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .dropzone-upload:hover {
            border-color: #0284c7;
            background: #f0f9ff;
        }

        .copy-btn {
            background: #e0f2fe;
            color: #0284c7;
            border: none;
            border-radius: 8px;
            padding: 0.35rem 0.75rem;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .copy-btn:hover {
            background: #0284c7;
            color: #ffffff;
        }
    </style>
</head>
<body>
<script>
    if (localStorage.getItem('patemon_theme') === 'dark') {
        document.body.classList.add('theme-dark');
    }
</script>

<!-- Header Banner -->
<div class="booking-header position-relative">
    <div style="position: absolute; top: 1.25rem; right: 1.5rem; z-index: 99; display: flex; align-items: center; gap: 0.5rem;">
        <button type="button" class="btn-lang-switcher" onclick="togglePatemonLanguage()" title="Beralih Bahasa / Switch Language">
            <svg class="flag-icon-svg" viewBox="0 0 640 480" width="18" height="13" style="border-radius:2px; vertical-align:middle; display:inline-block; box-shadow:0 0 1px rgba(0,0,0,0.5); margin-right:4px;"><g fill-rule="evenodd" stroke-width="1pt"><path fill="#e70011" d="M0 0h640v240H0z"/><path fill="#ffffff" d="M0 240h640v240H0z"/></g></svg><strong>ID</strong>
        </button>
        <button type="button" id="themeToggleBtn" class="btn-theme-switcher" onclick="togglePatemonTheme()" title="Beralih Mode Gelap / Terang">
            <span class="theme-icon-moon"><i class="fa-solid fa-moon"></i></span>
            <span class="theme-icon-sun"><i class="fa-solid fa-sun"></i></span>
            <span class="d-none d-sm-inline ms-1" id="themeLabelText">Tema</span>
        </button>
    </div>
    <div style="max-width: 600px; margin: auto;">
        <a href="<?= route_url('home') ?>" class="text-decoration-none d-inline-flex align-items-center gap-3 mb-3">
            <img src="<?= public_url('img/icon.png') ?>" alt="Logo Pemandian Patemon" style="height: 56px; width: auto; object-fit: contain;">
            <div class="text-start">
                <div class="fw-bold text-white text-uppercase lh-1" style="font-size: 1.35rem; letter-spacing: 0.5px; text-shadow: 0 2px 8px rgba(0,0,0,0.6);">Pemandian Patemon</div>
                <div class="text-warning small text-uppercase fw-semibold mt-1" style="letter-spacing: 1.5px; text-shadow: 0 1px 4px rgba(0,0,0,0.6);">Wisata Alam Tanggul &bull; Jember</div>
            </div>
        </a>
        <h1 class="fw-bold mb-2" style="color: #ffffff !important; text-shadow: 0 3px 16px rgba(0, 0, 0, 0.75); font-size: 2.25rem;">Reservasi Tiket Masuk</h1>
        <p class="mb-0" style="color: #f1f5f9 !important; text-shadow: 0 2px 6px rgba(0, 0, 0, 0.55); font-size: 1.05rem;">Pengalaman pemandian alami air pegunungan yang asri, bersih, dan menyegarkan.</p>
    </div>
</div>

<div class="booking-container">
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 shadow-sm">
            <i class="fa-solid fa-circle-exclamation fs-5"></i>
            <div><?= e($error_msg) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" id="formPesan">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="row g-4">
            <!-- Left: Options & Inputs -->
            <div class="col-12 col-lg-7">
                <!-- User Greeting Card -->
                <div class="modern-card mb-4" style="border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); border: 1px solid #e2e8f0;">
                    <div class="modern-card-body d-flex align-items-center justify-content-between flex-wrap gap-2 py-3 px-4">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width: 44px; height: 44px; border-radius: 12px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                                <i class="fa-solid fa-user-check"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Memesan Sebagai:</div>
                                <div class="fw-bold fs-6" style="color: #0f172a;"><?= e($user_nama) ?></div>
                            </div>
                        </div>
                        <a href="<?= route_url('home') ?>" class="btn btn-sm btn-soft-primary">
                            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Beranda
                        </a>
                    </div>
                </div>

                <!-- Step 1: Ticket Selection (Dynamic Loop) -->
                <div class="modern-card mb-4">
                    <div class="modern-card-header">
                        <span class="fw-bold" style="color: #0f172a;"><i class="fa-solid fa-ticket text-primary me-2"></i> 1. Tentukan Jumlah Tiket</span>
                    </div>
                    <div class="modern-card-body">
                        <div class="row g-3">
                            <?php foreach ($daftar_tiket as $tkt): 
                                $colorClass = get_ticket_color($tkt['nama']);
                                $iconClass = get_ticket_icon($tkt['nama'], $tkt['ikon']);
                            ?>
                                <div class="col-12 col-sm-6">
                                    <div class="ticket-pick-card" id="card_<?= $tkt['id'] ?>">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="fw-bold fs-5" style="color: #0f172a;"><?= e($tkt['nama']) ?></div>
                                                <div class="metric-icon-box <?= $colorClass ?>" style="width: 38px; height: 38px; font-size: 1rem;">
                                                    <i class="fa-solid <?= e($iconClass) ?>"></i>
                                                </div>
                                            </div>
                                            <div class="text-muted small mb-3">Tiket masuk resmi kategori <?= e($tkt['nama']) ?></div>
                                        </div>
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="fw-extrabold text-primary fs-5"><?= format_rupiah($tkt['harga']) ?></div>
                                                <div class="qty-stepper">
                                                    <button type="button" onclick="adjustQty(<?= $tkt['id'] ?>, -1)"><i class="fa-solid fa-minus"></i></button>
                                                    <input type="number" id="qty_<?= $tkt['id'] ?>" name="tickets[<?= $tkt['id'] ?>]" data-price="<?= $tkt['harga'] ?>" data-name="<?= e($tkt['nama']) ?>" value="0" min="0" readonly>
                                                    <button type="button" onclick="adjustQty(<?= $tkt['id'] ?>, 1)"><i class="fa-solid fa-plus"></i></button>
                                                </div>
                                            </div>
                                            <div class="text-end text-muted small mt-2">
                                                Subtotal: <strong id="sub_txt_<?= $tkt['id'] ?>" style="color: #0284c7; font-weight: 700;">Rp 0</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Payment Method (Separated QRIS, Transfer Bank, Loket) -->
                <div class="modern-card mb-4">
                    <div class="modern-card-header">
                        <span class="fw-bold" style="color: #0f172a;"><i class="fa-solid fa-wallet text-primary me-2"></i> 2. Metode Pembayaran</span>
                    </div>
                    <div class="modern-card-body">
                        <div class="row g-2 mb-3">
                            <!-- Opsi 1: Bayar di Loket -->
                            <div class="col-12 col-md-4 pay-method-pill">
                                <input type="radio" class="d-none" name="metode_pembayaran" id="m_loket" value="Bayar Di Loket" checked onchange="handlePaymentChange()">
                                <label for="m_loket" class="flex-column align-items-start text-start">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fa-solid fa-hand-holding-dollar text-success fs-5"></i>
                                        <span class="fw-bold">Bayar di Loket</span>
                                    </div>
                                    <small class="text-muted fw-normal" style="font-size: 0.8rem;">Bayar tunai di pintu masuk pemandian</small>
                                </label>
                            </div>

                            <!-- Opsi 2: Scan QRIS -->
                            <div class="col-12 col-md-4 pay-method-pill">
                                <input type="radio" class="d-none" name="metode_pembayaran" id="m_qris" value="Qris" onchange="handlePaymentChange()">
                                <label for="m_qris" class="flex-column align-items-start text-start">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fa-solid fa-qrcode text-primary fs-5"></i>
                                        <span class="fw-bold">Scan QRIS</span>
                                    </div>
                                    <small class="text-muted fw-normal" style="font-size: 0.8rem;">BCA, GoPay, OVO, DANA, ShopeePay</small>
                                </label>
                            </div>

                            <!-- Opsi 3: Transfer Bank -->
                            <div class="col-12 col-md-4 pay-method-pill">
                                <input type="radio" class="d-none" name="metode_pembayaran" id="m_transfer" value="Transfer Bank" onchange="handlePaymentChange()">
                                <label for="m_transfer" class="flex-column align-items-start text-start">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fa-solid fa-building-columns text-info fs-5"></i>
                                        <span class="fw-bold">Transfer Bank</span>
                                    </div>
                                    <small class="text-muted fw-normal" style="font-size: 0.8rem;">Bank Jatim / Rekening Resmi Pemkab</small>
                                </label>
                            </div>
                        </div>

                        <!-- Card Detail Scan QRIS -->
                        <div id="qrisSection" class="d-none p-3 rounded-3 mt-3" style="background: #f0f9ff; border: 1.5px solid #bae6fd;">
                            <div class="row align-items-center g-3">
                                <div class="col-12 col-md-4 text-center">
                                    <div class="bg-white p-2 rounded-3 border shadow-sm d-inline-block">
                                        <img src="../../../public/img/qris.png" alt="QRIS Pemandian Patemon" style="width: 140px; height: 140px; object-fit: contain;" onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=PEMANDIAN-PATEMON-QRIS'">
                                    </div>
                                    <div class="small fw-bold text-muted mt-1"><i class="fa-solid fa-expand me-1"></i> Scan Kode QRIS</div>
                                </div>
                                <div class="col-12 col-md-8">
                                    <h6 class="fw-bold text-primary mb-1">UPTD Pemandian Patemon - Pemkab Jember</h6>
                                    <div class="text-muted small mb-2">NMID: <strong>ID1020081293810</strong></div>
                                    <ol class="small text-secondary ps-3 mb-0" style="line-height: 1.5;">
                                        <li>Buka aplikasi m-Banking (BCA, Mandiri, BRI, Bank Jatim) atau e-Wallet (GoPay, OVO, Dana).</li>
                                        <li>Pindai QR Code di samping dan masukkan nominal yang tertera pada ringkasan pesanan.</li>
                                        <li>Unggah bukti tangkapan layar pembayaran pada formulir di bawah.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <!-- Card Detail Transfer Bank -->
                        <div id="transferSection" class="d-none order-info-box p-3 rounded-3 mt-3">
                            <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold">
                                <i class="fa-solid fa-building-columns"></i>
                                <span>Rekening Penerimaan Resmi Pemkab Jember:</span>
                            </div>
                            <div class="bg-white p-3 rounded-3 border mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="badge bg-primary text-white mb-1"><?= BANK_NAME ?></div>
                                    <div class="fw-extrabold fs-4" style="color: #0f172a; letter-spacing: 0.05em;" id="bankRekText"><?= BANK_REK ?></div>
                                    <div class="text-muted small">Atas Nama: <strong><?= BANK_AN ?></strong></div>
                                </div>
                                <button type="button" class="copy-btn" onclick="copyRekening('<?= BANK_REK ?>')">
                                    <i class="fa-regular fa-copy me-1"></i> Salin No. Rekening
                                </button>
                            </div>
                            <div class="small text-muted">
                                <i class="fa-solid fa-circle-info text-info me-1"></i> Pastikan nominal transfer sesuai dengan total tagihan tiket agar verifikasi berjalan otomatis.
                            </div>
                        </div>

                        <!-- Upload Bukti Pembayaran -->
                        <div id="proofUploadSection" class="d-none mt-3">
                            <label class="form-label fw-bold text-dark small">Unggah Bukti Pembayaran <span class="text-danger">*</span></label>
                            <div class="dropzone-upload" onclick="document.getElementById('bukti_pembayaran').click()">
                                <i class="fa-solid fa-cloud-arrow-up fs-2 text-primary mb-2"></i>
                                <div class="fw-semibold text-dark" id="fileNamePreview">Klik atau seret foto bukti transfer di sini</div>
                                <div class="text-muted small">Format didukung: JPG, PNG, WEBP (Maksimal 2 MB)</div>
                            </div>
                            <input type="file" id="bukti_pembayaran" name="bukti_pembayaran" class="d-none" accept="image/*" onchange="previewFile(this)">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Order Summary Sticky Card -->
            <div class="col-12 col-lg-5">
                <div class="modern-card sticky-top" style="top: 1.5rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); border: 1px solid #e2e8f0;">
                    <div class="modern-card-header bg-white py-3">
                        <span class="fw-bold fs-6" style="color: #0f172a;"><i class="fa-solid fa-receipt text-primary me-2"></i> Ringkasan Pemesanan</span>
                    </div>
                    <div class="modern-card-body p-4">
                        <div class="mb-3 pb-3 border-bottom" id="summaryList">
                            <div class="text-muted small text-center py-2" id="emptyNotice">Belum ada tiket yang dipilih.</div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Total Jumlah Tiket:</span>
                            <span class="fw-bold fs-6" id="summaryTotalQty">0 Tiket</span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Metode Pembayaran:</span>
                            <span class="badge bg-light text-primary border" id="summaryMethod">Bayar Di Loket</span>
                        </div>

                        <div class="p-3 rounded-3 mb-4" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                            <div class="d-flex justify-content-between align-items-baseline">
                                <span class="fw-bold text-success">Total Bayar:</span>
                                <span class="fw-extrabold text-success fs-4" id="summaryTotalPrice">Rp 0</span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-brand w-100 py-3 fw-bold fs-6 shadow-sm" id="btnSubmitOrder" disabled>
                            <i class="fa-solid fa-lock me-1"></i> Konfirmasi & Pesan Tiket
                        </button>

                        <div class="text-center text-muted small mt-3">
                            <i class="fa-solid fa-shield-halved text-success me-1"></i> Transaksi Anda aman & terverifikasi resmi oleh UPTD Disparbud Jember.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="../../../public/assets/js/bootstrap.js"></script>
<script>
const ticketsData = <?= json_encode($daftar_tiket) ?>;

function adjustQty(id, delta) {
    const input = document.getElementById('qty_' + id);
    if (!input) return;

    let current = parseInt(input.value) || 0;
    current = Math.max(0, current + delta);
    input.value = current;

    const card = document.getElementById('card_' + id);
    if (card) {
        if (current > 0) {
            card.classList.add('active');
        } else {
            card.classList.remove('active');
        }
    }

    const price = parseInt(input.dataset.price) || 0;
    const subtotal = current * price;
    const subTxt = document.getElementById('sub_txt_' + id);
    if (subTxt) {
        subTxt.textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
    }

    recalcTotal();
}

function recalcTotal() {
    let totalQty = 0;
    let totalPrice = 0;
    const summaryContainer = document.getElementById('summaryList');
    summaryContainer.innerHTML = '';

    let hasItem = false;

    Object.keys(ticketsData).forEach(id => {
        const input = document.getElementById('qty_' + id);
        if (input) {
            const qty = parseInt(input.value) || 0;
            const price = parseInt(input.dataset.price) || 0;
            const name = input.dataset.name || 'Tiket';
            if (qty > 0) {
                hasItem = true;
                totalQty += qty;
                const sub = qty * price;
                totalPrice += sub;

                const row = document.createElement('div');
                row.className = 'd-flex justify-content-between align-items-center mb-2 small';
                row.innerHTML = `
                    <span>${name} <strong class="text-primary">(${qty}x)</strong></span>
                    <span class="fw-semibold">Rp ${sub.toLocaleString('id-ID')}</span>
                `;
                summaryContainer.appendChild(row);
            }
        }
    });

    const isEn = ((localStorage.getItem('patemon_lang') || 'id') === 'en');

    if (!hasItem) {
        summaryContainer.innerHTML = `<div class="text-muted small text-center py-2">${isEn ? 'No tickets selected yet.' : 'Belum ada tiket yang dipilih.'}</div>`;
    }

    document.getElementById('summaryTotalQty').textContent = totalQty + (isEn ? ' Tickets' : ' Tiket');
    document.getElementById('summaryTotalPrice').textContent = 'Rp ' + totalPrice.toLocaleString('id-ID');

    const submitBtn = document.getElementById('btnSubmitOrder');
    if (submitBtn) {
        submitBtn.disabled = totalQty <= 0;
    }
}

function handlePaymentChange() {
    const selected = document.querySelector('input[name="metode_pembayaran"]:checked')?.value || 'Bayar Di Loket';
    const qrisSection = document.getElementById('qrisSection');
    const transferSection = document.getElementById('transferSection');
    const proofSection = document.getElementById('proofUploadSection');
    const summaryMethod = document.getElementById('summaryMethod');

    const isEn = ((localStorage.getItem('patemon_lang') || 'id') === 'en');
    let displayMethod = selected;
    if (isEn) {
        if (selected === 'Bayar Di Loket') displayMethod = 'Pay at Counter';
        else if (selected === 'Transfer Bank') displayMethod = 'Bank Transfer';
    }
    summaryMethod.textContent = displayMethod;

    if (selected === 'Qris') {
        qrisSection.classList.remove('d-none');
        transferSection.classList.add('d-none');
        proofSection.classList.remove('d-none');
    } else if (selected === 'Transfer Bank') {
        qrisSection.classList.add('d-none');
        transferSection.classList.remove('d-none');
        proofSection.classList.remove('d-none');
    } else {
        qrisSection.classList.add('d-none');
        transferSection.classList.add('d-none');
        proofSection.classList.add('d-none');
    }
}

window.addEventListener('patemon_language_changed', function() {
    recalcTotal();
    handlePaymentChange();
});

function copyRekening(rek) {
    navigator.clipboard.writeText(rek).then(() => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Nomor rekening ' + rek + ' berhasil disalin!',
            showConfirmButton: false,
            timer: 2500
        });
    });
}

function previewFile(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('fileNamePreview').textContent = 'Terpilih: ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    }
}

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
        if (btn) btn.classList.add('active-dark');
        if (label) label.textContent = 'Gelap';
    } else {
        document.body.classList.remove('theme-dark');
        document.documentElement.classList.remove('theme-dark');
        document.documentElement.setAttribute('data-bs-theme', 'light');
        if (btn) btn.classList.remove('active-dark');
        if (label) label.textContent = 'Terang';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    applyPatemonTheme(localStorage.getItem('patemon_theme') || 'light');
    recalcTotal();
    handlePaymentChange();
});
</script>
</body>
</html>
