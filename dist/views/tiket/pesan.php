<?php 
require '../../app/config.php';

// Pastikan user sudah login
if (!isset($_SESSION['id_user'])) {
    header('Location: ../login.php');
    exit();
}

$id_user = (int)$_SESSION['id_user'];
$user_nama = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Pengunjung';
$error_msg = '';

// Ambil tarif resmi tiket dari database
$harga_tiket = [];
$res_t = $conn->query("SELECT nama_tiket, harga FROM tiket");
if ($res_t) {
    while ($r = $res_t->fetch_assoc()) {
        $harga_tiket[$r['nama_tiket']] = (int)$r['harga'];
    }
}
$harga_dewasa = $harga_tiket['Dewasa'] ?? 10000;
$harga_anak   = $harga_tiket['Anak-Anak'] ?? 5000;

if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST") {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Token keamanan sesi kedaluwarsa. Silakan muat ulang halaman.";
    } else {
        $qty_dewasa = max(0, (int)($_POST['quantity1'] ?? 0));
        $qty_anak   = max(0, (int)($_POST['quantity2'] ?? 0));
        $metode_pembayaran = trim($_POST['metode_pembayaran'] ?? 'Bayar Di Loket');

        if ($qty_dewasa === 0 && $qty_anak === 0) {
            $error_msg = "Silakan pilih minimal 1 tiket untuk melanjutkan pemesanan.";
        } else {
            // 1. Hitung total secara otoritatif di sisi server (mencegah price tampering)
            $subtotal_dewasa = $qty_dewasa * $harga_dewasa;
            $subtotal_anak   = $qty_anak * $harga_anak;
            $total_harga     = $subtotal_dewasa + $subtotal_anak;
            $tgl_pemesanan   = date('Y-m-d');
            $status          = 'notyet';
            $nama_gambar     = null;

            // 2. Kelola unggahan bukti pembayaran jika metode QRIS / Transfer
            if (($metode_pembayaran === 'Qris' || $metode_pembayaran === 'Transfer') && isset($_FILES["bukti_pembayaran"]) && $_FILES["bukti_pembayaran"]["error"] === UPLOAD_ERR_OK) {
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
                    $error_msg = "Format bukti pembayaran harus berupa gambar (JPG, PNG, atau WEBP).";
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
                        $error_msg = "Gagal menyimpan file bukti pembayaran.";
                    }
                }
            }

            if (empty($error_msg)) {
                // 3. Simpan data menggunakan DB Transaction
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO transaksi (id_user, tgl_pemesanan, total_harga, metode_pembayaran, bukti_pembayaran, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isisss", $id_user, $tgl_pemesanan, $total_harga, $metode_pembayaran, $nama_gambar, $status);
                    $stmt->execute();
                    $new_id_transaksi = $conn->insert_id;
                    $stmt->close();

                    // Simpan detail tiket
                    $stmt_detail = $conn->prepare("INSERT INTO detail_transaksi (id_transaksi, jenis_tiket, quantity, sub_total) VALUES (?, ?, ?, ?)");
                    if ($qty_dewasa > 0) {
                        $nama_dewasa = 'Dewasa';
                        $stmt_detail->bind_param("isii", $new_id_transaksi, $nama_dewasa, $qty_dewasa, $subtotal_dewasa);
                        $stmt_detail->execute();
                    }
                    if ($qty_anak > 0) {
                        $nama_anak = 'Anak-Anak';
                        $stmt_detail->bind_param("isii", $new_id_transaksi, $nama_anak, $qty_anak, $subtotal_anak);
                        $stmt_detail->execute();
                    }
                    $stmt_detail->close();

                    $conn->commit();

                    $_SESSION['id_transaksi'] = $new_id_transaksi;
                    header("Location: nota.php?id_transaksi=" . $new_id_transaksi);
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_msg = "Gagal memproses transaksi: " . e($e->getMessage());
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
    <title>Pesan Tiket Wisata - Pemandian Patemon</title>
    <link rel="icon" type="image/x-icon" href="../../../public/img/icon.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../public/assets/css/main/app.css">
    <link rel="stylesheet" href="../../../public/css/modern-theme.css">
    <style>
        body {
            background-color: #f8fafc;
            min-height: 100vh;
        }

        .booking-header {
            background: linear-gradient(rgba(15, 23, 42, 0.75), rgba(2, 132, 199, 0.85)), url('../../../public/img/background.png');
            background-size: cover;
            background-position: center;
            color: #ffffff;
            padding: 3rem 1.5rem 4.5rem;
            text-align: center;
            position: relative;
        }

        .booking-container {
            max-width: 1050px;
            margin: -3.5rem auto 3rem;
            padding: 0 1rem;
            position: relative;
            z-index: 10;
        }

        .ticket-pick-card {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.5rem;
            background: #ffffff;
            transition: all 0.2s ease;
        }

        .ticket-pick-card.active {
            border-color: #0284c7;
            background: #f0f9ff;
        }

        .summary-box {
            position: sticky;
            top: 2rem;
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            padding: 2rem 1.75rem;
        }

        .pay-method-pill input[type="radio"]:checked + label {
            border-color: #0284c7;
            background: #e0f2fe;
            color: #0369a1;
        }

        .pay-method-pill label {
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.85rem 1.25rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: #334155;
            background: #ffffff;
            transition: all 0.15s ease;
            width: 100%;
        }

        .dropzone-upload {
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            padding: 2rem 1.5rem;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .dropzone-upload:hover {
            border-color: #0284c7;
            background: #f0f9ff;
        }
    </style>
</head>
<body>

<!-- Header Banner -->
<div class="booking-header">
    <div style="max-width: 600px; margin: auto;">
        <a href="../index.php" class="text-decoration-none d-inline-flex align-items-center gap-3 mb-3">
            <img src="../../../public/img/icon.png" alt="Logo Pemandian Patemon" style="height: 56px; width: auto; object-fit: contain;">
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

    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" id="hargaDewasa" value="<?= $harga_dewasa ?>">
        <input type="hidden" id="hargaAnak" value="<?= $harga_anak ?>">

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
                        <a href="../index.php" class="btn btn-sm btn-soft-primary">
                            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Beranda
                        </a>
                    </div>
                </div>

                <!-- Step 1: Ticket Selection -->
                <div class="modern-card mb-4">
                    <div class="modern-card-header">
                        <span class="fw-bold" style="color: #0f172a;"><i class="fa-solid fa-ticket text-primary me-2"></i> 1. Tentukan Jumlah Tiket</span>
                    </div>
                    <div class="modern-card-body">
                        <div class="row g-3">
                            <!-- Card Tiket Dewasa -->
                            <div class="col-12 col-sm-6">
                                <div class="ticket-pick-card" id="cardDewasa">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="fw-bold fs-5" style="color: #0f172a;">Dewasa</div>
                                        <div class="metric-icon-box blue" style="width: 38px; height: 38px; font-size: 1rem;">
                                            <i class="fa-solid fa-person"></i>
                                        </div>
                                    </div>
                                    <div class="text-muted small mb-3">Akses kolam dewasa & seluruh fasilitas umum</div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="fw-extrabold text-primary fs-5"><?= format_rupiah($harga_dewasa) ?></div>
                                        <div class="qty-stepper">
                                            <button type="button" onclick="changeQty('quantity1', -1)"><i class="fa-solid fa-minus"></i></button>
                                            <input type="number" id="quantity1" name="quantity1" value="0" min="0" readonly style="color: #0f172a;">
                                            <button type="button" onclick="changeQty('quantity1', 1)"><i class="fa-solid fa-plus"></i></button>
                                        </div>
                                    </div>
                                    <div class="text-end text-muted small mt-2">
                                        Subtotal: <strong id="subDewasaTxt" style="color: #0284c7; font-weight: 700;">Rp 0</strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Tiket Anak-Anak -->
                            <div class="col-12 col-sm-6">
                                <div class="ticket-pick-card" id="cardAnak">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="fw-bold fs-5" style="color: #0f172a;">Anak-Anak</div>
                                        <div class="metric-icon-box amber" style="width: 38px; height: 38px; font-size: 1rem;">
                                            <i class="fa-solid fa-child-reaching"></i>
                                        </div>
                                    </div>
                                    <div class="text-muted small mb-3">Akses kolam anak khusus dengan kedalaman aman</div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="fw-extrabold fs-5" style="color: #d97706;"><?= format_rupiah($harga_anak) ?></div>
                                        <div class="qty-stepper">
                                            <button type="button" onclick="changeQty('quantity2', -1)"><i class="fa-solid fa-minus"></i></button>
                                            <input type="number" id="quantity2" name="quantity2" value="0" min="0" readonly style="color: #0f172a;">
                                            <button type="button" onclick="changeQty('quantity2', 1)"><i class="fa-solid fa-plus"></i></button>
                                        </div>
                                    </div>
                                    <div class="text-end text-muted small mt-2">
                                        Subtotal: <strong id="subAnakTxt" style="color: #d97706; font-weight: 700;">Rp 0</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Payment Method -->
                <div class="modern-card mb-4">
                    <div class="modern-card-header">
                        <span class="fw-bold" style="color: #0f172a;"><i class="fa-solid fa-wallet text-primary me-2"></i> 2. Metode Pembayaran</span>
                    </div>
                    <div class="modern-card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-12 col-sm-6 pay-method-pill">
                                <input type="radio" class="d-none" name="metode_pembayaran" id="m_loket" value="Bayar Di Loket" checked onchange="togglePaymentProof()">
                                <label for="m_loket">
                                    <i class="fa-solid fa-hand-holding-dollar text-success fs-5"></i>
                                    <span style="color: #0f172a;">Bayar di Loket (Tunai)</span>
                                </label>
                            </div>
                            <div class="col-12 col-sm-6 pay-method-pill">
                                <input type="radio" class="d-none" name="metode_pembayaran" id="m_qris" value="Qris" onchange="togglePaymentProof()">
                                <label for="m_qris">
                                    <i class="fa-solid fa-qrcode text-primary fs-5"></i>
                                    <span style="color: #0f172a;">QRIS / Transfer Bank</span>
                                </label>
                            </div>
                        </div>

                        <!-- Info Rekening & Upload Bukti (Ditampilkan jika memilih QRIS/Transfer) -->
                        <div id="qrisDetailSection" class="d-none p-3 rounded-3 mt-3" style="background: #f0f9ff; border: 1.5px solid #bae6fd;">
                            <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold">
                                <i class="fa-solid fa-building-columns"></i>
                                <span>Rekening Pembayaran Resmi:</span>
                            </div>
                            <div class="bg-white p-3 rounded-3 border mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <div class="text-muted small">Bank Mandiri / BCA</div>
                                    <div class="fw-extrabold fs-4" style="color: #0f172a; letter-spacing: 0.04em;" id="rekNumber">142-00-18293-881</div>
                                    <div class="text-muted small">a.n. Wisata Pemandian Patemon</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-soft-primary" onclick="copyRekening()" id="btnCopyRek">
                                    <i class="fa-regular fa-copy me-1"></i> Salin No Rekening
                                </button>
                            </div>

                            <label class="form-label fw-bold small mb-2" style="color: #0f172a;">Unggah Bukti Transfer (Opsional saat ini, dapat ditunjukkan di loket):</label>
                            <label for="bukti_pembayaran" class="modern-file-upload-card" id="dropzoneBox">
                                <i class="fa-solid fa-cloud-arrow-up text-primary fs-2 mb-2 d-block"></i>
                                <div class="fw-semibold mb-1" id="fileUploadName" style="color: #0f172a;">Klik untuk memilih foto bukti transfer</div>
                                <div class="text-muted small">Mendukung format JPG, PNG, atau WEBP (Maksimal 2 MB)</div>
                                <input 
                                    type="file" 
                                    name="bukti_pembayaran" 
                                    id="bukti_pembayaran" 
                                    class="d-none"
                                    accept="image/jpeg,image/png,image/webp"
                                    onchange="handleFileChange(this)"
                                >
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Live Summary Box -->
            <div class="col-12 col-lg-5">
                <div class="summary-box">
                    <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <div class="fw-bold fs-5" style="color: #0f172a;">Total Pemesanan</div>
                        <span class="badge badge-modern-primary"><i class="fa-solid fa-water"></i> Patemon</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2 text-muted" style="font-size: 0.925rem;">
                        <span>Tiket Dewasa (<span id="sumQtyDewasa">0</span>x)</span>
                        <strong id="sumSubDewasa" style="color: #0f172a; font-weight: 700;">Rp 0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3 text-muted" style="font-size: 0.925rem;">
                        <span>Tiket Anak (<span id="sumQtyAnak">0</span>x)</span>
                        <strong id="sumSubAnak" style="color: #0f172a; font-weight: 700;">Rp 0</strong>
                    </div>

                    <div class="p-3 rounded-3 mb-4 text-center" style="background: #f8fafc; border: 2px dashed #cbd5e1;">
                        <div class="small fw-bold text-uppercase mb-1" style="color: #64748b; letter-spacing: 0.05em;">Total yang harus dibayar:</div>
                        <div class="fw-extrabold text-primary" id="totalHargaTxt" style="font-size: 2.25rem; line-height: 1.1;">Rp 0</div>
                    </div>

                    <div class="text-muted small mb-4 d-flex align-items-start gap-2">
                        <i class="fa-solid fa-shield-halved text-success mt-1"></i>
                        <span>Nota struk dengan barcode akan otomatis terbit setelah Anda menekan tombol di bawah.</span>
                    </div>

                    <button type="submit" class="btn-brand w-100 py-3 fs-5" id="btnSubmit">
                        <i class="fa-solid fa-circle-check me-2"></i> Konfirmasi & Pesan Tiket
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<footer class="text-center py-4 text-muted small">
    &copy; <?= date('Y') ?> Wisata Pemandian Patemon. All rights reserved.
</footer>

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

    document.getElementById('subDewasaTxt').innerText = formatRupiah(subDewasa);
    document.getElementById('subAnakTxt').innerText   = formatRupiah(subAnak);

    document.getElementById('sumQtyDewasa').innerText = qDewasa;
    document.getElementById('sumSubDewasa').innerText = formatRupiah(subDewasa);

    document.getElementById('sumQtyAnak').innerText = qAnak;
    document.getElementById('sumSubAnak').innerText = formatRupiah(subAnak);

    document.getElementById('totalHargaTxt').innerText = formatRupiah(total);

    document.getElementById('cardDewasa').classList.toggle('active', qDewasa > 0);
    document.getElementById('cardAnak').classList.toggle('active', qAnak > 0);
}

function togglePaymentProof() {
    const isQris = document.getElementById('m_qris').checked;
    const section = document.getElementById('qrisDetailSection');
    if (isQris) {
        section.classList.remove('d-none');
    } else {
        section.classList.add('d-none');
    }
}

function copyRekening() {
    const rek = document.getElementById('rekNumber').innerText.trim();
    navigator.clipboard.writeText(rek).then(() => {
        const btn = document.getElementById('btnCopyRek');
        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Tersalin!';
        btn.classList.remove('btn-soft-primary');
        btn.classList.add('btn-soft-success');
        setTimeout(() => {
            btn.innerHTML = '<i class="fa-regular fa-copy me-1"></i> Salin No Rekening';
            btn.classList.remove('btn-soft-success');
            btn.classList.add('btn-soft-primary');
        }, 2000);
    });
}

function handleFileChange(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const nameBox = document.getElementById('fileUploadName');
        nameBox.innerHTML = '<span class="text-success"><i class="fa-solid fa-file-image me-1"></i> ' + file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)</span>';
        document.getElementById('dropzoneBox').style.borderColor = '#10b981';
        document.getElementById('dropzoneBox').style.background = '#ecfdf5';
    }
}
</script>
</body>
</html>
