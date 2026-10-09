<?php
require '../../app/config.php';

// Hak akses: Super Admin (1), Admin (2), dan Staf Kasir (3)
check_auth([1, 2, 3]);

$user_level = (int)($_SESSION['level'] ?? 0);
$user_id    = (int)($_SESSION['id_user'] ?? 0);

$active_menu     = 'kasir';
$page_title      = 'Panel Kasir Loket - Pemandian Patemon';
$page_heading    = 'Panel Kasir Loket';
$page_subheading = 'Kelola tiket masuk pengunjung, transaksi tunai/QRIS, dan cetak struk nota.';

$header_actions = '
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-warning text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#modalTutupShift">
            <i class="fa-solid fa-calculator me-1"></i> Rekap Kasir & Tutup Shift
        </button>
        <a href="' . route_url('transaksi_tambah') . '" class="btn btn-brand">
            <i class="fa-solid fa-plus me-1"></i> Transaksi Baru (POS)
        </a>
        <a href="' . route_url('laporan') . '" class="btn btn-soft-danger">
            <i class="fa-solid fa-file-pdf me-1"></i> PDF
        </a>
    </div>
';

$search = trim($_GET['search'] ?? ($_GET['id_transaksi'] ?? ''));

// Tampilkan transaksi aktif:
// Kasir Loket (Level 3) dapat melihat transaksi yang diinputnya sendiri SERTA transaksi online mandiri pengunjung
// sehingga kasir loket dapat memvalidasi dan memproses tiket pesanan pengunjung yang datang ke loket.
$staff_filter_sql = "";
if ($user_level === 3) {
    // Tampilkan transaksi milik kasir ini ATAU transaksi pesanan online mandiri pengunjung (kasir_id IS NULL dan id_user bukan staf kasir lain)
    $staff_filter_sql = " AND (transaksi.kasir_id = {$user_id} OR (transaksi.kasir_id IS NULL AND users.level = 0))";
}

// Query Transaksi
if (!empty($search)) {
    $id_search = 0;
    if (preg_match('/(?:TRX-\d{8}-)?0*(\d+)/i', $search, $m)) {
        $id_search = (int)$m[1];
    }
    $search_like = "%" . $search . "%";

    // Jika pencarian teks umum pada akun kasir level 3
    if ($user_level === 3 && $id_search === 0) {
        $stmt = $conn->prepare("
            SELECT transaksi.*, users.nama, users.no_telepon as user_telp 
            FROM transaksi 
            INNER JOIN users ON transaksi.id_user = users.id_user 
            WHERE transaksi.deleted_at IS NULL 
              AND (transaksi.kasir_id = ? OR (transaksi.kasir_id IS NULL AND users.level = 0))
              AND (users.nama LIKE ? 
               OR transaksi.nama_pemesan LIKE ?
               OR transaksi.metode_pembayaran LIKE ? 
               OR transaksi.status LIKE ?
               OR transaksi.tgl_pemesanan LIKE ?)
            ORDER BY tgl_pemesanan DESC, id_transaksi DESC
        ");
        $stmt->bind_param("isssss", $user_id, $search_like, $search_like, $search_like, $search_like, $search_like);
    } else {
        $stmt = $conn->prepare("
            SELECT transaksi.*, users.nama, users.no_telepon as user_telp 
            FROM transaksi 
            INNER JOIN users ON transaksi.id_user = users.id_user 
            WHERE transaksi.deleted_at IS NULL 
              AND (transaksi.id_transaksi = ? 
               OR users.nama LIKE ? 
               OR transaksi.nama_pemesan LIKE ?
               OR transaksi.metode_pembayaran LIKE ? 
               OR transaksi.status LIKE ?
               OR transaksi.tgl_pemesanan LIKE ?)
            ORDER BY tgl_pemesanan DESC, id_transaksi DESC
        ");
        $stmt->bind_param("isssss", $id_search, $search_like, $search_like, $search_like, $search_like, $search_like);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    // Tampilkan transaksi aktif
    $sql = "SELECT transaksi.*, users.nama, users.no_telepon as user_telp 
            FROM transaksi 
            INNER JOIN users ON transaksi.id_user = users.id_user 
            WHERE transaksi.deleted_at IS NULL 
            {$staff_filter_sql}
            ORDER BY tgl_pemesanan DESC, id_transaksi DESC";
    $result = $conn->query($sql);
}

// Rekap Omzet Hari Ini Khusus Kasir Ini (hanya transaksi yang diproses/diinput kasir bersangkutan)
$today_omzet = 0;
$today_tickets = 0;
$today_unpaid = 0;
$total_all_tickets = 0;
$recap_kasir_sql = " AND (transaksi.kasir_id = {$user_id} OR (transaksi.kasir_id IS NULL AND transaksi.id_user = {$user_id}))";
$recap_sql = "SELECT 
    SUM(CASE WHEN DATE(tgl_pemesanan) = CURDATE() AND status = 'done' THEN total_harga ELSE 0 END) as omzet,
    SUM(CASE WHEN DATE(tgl_pemesanan) = CURDATE() AND status = 'done' THEN 1 ELSE 0 END) as done_today_cnt,
    SUM(CASE WHEN DATE(tgl_pemesanan) = CURDATE() AND status != 'done' THEN 1 ELSE 0 END) as unpaid_today_cnt,
    COUNT(*) as total_cnt 
FROM transaksi 
WHERE deleted_at IS NULL " . ($user_level === 3 ? $recap_kasir_sql : "");
$recap = $conn->query($recap_sql);
if ($recap && $rc = $recap->fetch_assoc()) {
    $today_omzet = (float)($rc['omzet'] ?? 0);
    $today_tickets = (int)($rc['done_today_cnt'] ?? 0);
    $today_unpaid = (int)($rc['unpaid_today_cnt'] ?? 0);
    $total_all_tickets = (int)($rc['total_cnt'] ?? 0);
}

// Rekap Rincian Tunai vs Non-Tunai Shift Kasir (Balancing Kasir)
$cash_sql = "SELECT SUM(total_harga) as cash_omzet, COUNT(*) as cash_cnt FROM transaksi WHERE DATE(tgl_pemesanan) = CURDATE() AND status = 'done' AND deleted_at IS NULL AND (metode_pembayaran = 'Tunai' OR metode_pembayaran IS NULL) " . ($user_level === 3 ? $recap_kasir_sql : "");
$cash_res = $conn->query($cash_sql);
$cash_rc = $cash_res ? $cash_res->fetch_assoc() : [];
$shift_cash_omzet = (float)($cash_rc['cash_omzet'] ?? 0);
$shift_cash_cnt = (int)($cash_rc['cash_cnt'] ?? 0);

$noncash_sql = "SELECT SUM(total_harga) as noncash_omzet, COUNT(*) as noncash_cnt FROM transaksi WHERE DATE(tgl_pemesanan) = CURDATE() AND status = 'done' AND deleted_at IS NULL AND (metode_pembayaran != 'Tunai' AND metode_pembayaran IS NOT NULL) " . ($user_level === 3 ? $recap_kasir_sql : "");
$noncash_res = $conn->query($noncash_sql);
$noncash_rc = $noncash_res ? $noncash_res->fetch_assoc() : [];
$shift_noncash_omzet = (float)($noncash_rc['noncash_omzet'] ?? 0);
$shift_noncash_cnt = (int)($noncash_rc['noncash_cnt'] ?? 0);

// Rincian Tiket Terjual pada Shift Hari Ini
$shift_ticket_details = [];
$tkt_sql = "SELECT detail_transaksi.jenis_tiket AS nama_tiket, 
                   COALESCE(tiket.harga, ROUND(SUM(detail_transaksi.sub_total) / NULLIF(SUM(detail_transaksi.quantity), 0))) AS harga, 
                   SUM(detail_transaksi.quantity) AS total_lembar, 
                   SUM(detail_transaksi.sub_total) AS subtotal
            FROM detail_transaksi
            INNER JOIN transaksi ON detail_transaksi.id_transaksi = transaksi.id_transaksi
            LEFT JOIN tiket ON detail_transaksi.jenis_tiket = tiket.nama_tiket
            WHERE DATE(transaksi.tgl_pemesanan) = CURDATE()
              AND transaksi.status = 'done'
              AND transaksi.deleted_at IS NULL
              " . ($user_level === 3 ? $recap_kasir_sql : "") . "
            GROUP BY detail_transaksi.jenis_tiket
            ORDER BY total_lembar DESC";
$tkt_res = $conn->query($tkt_sql);
$shift_total_lembar = 0;
if ($tkt_res) {
    while ($tr = $tkt_res->fetch_assoc()) {
        $shift_ticket_details[] = $tr;
        $shift_total_lembar += (int)$tr['total_lembar'];
    }
}

$extra_css = '
<script src="' . public_url('js/html5-qrcode.min.js') . '"></script>
';

require '../../app/layouts/admin_header.php';
?>

<div class="page-content">
    <!-- Stats Kasir Lengkap (Feedback-2 Poin 10: Sudah Dibayar vs Belum Dibayar) -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="modern-card p-3 d-flex align-items-center gap-3">
                <div class="metric-icon-box green" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Omzet Loket Hari Ini</div>
                    <div class="fw-bold text-success" style="font-size: 1.35rem;"><?= format_rupiah($today_omzet) ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="modern-card p-3 d-flex align-items-center gap-3">
                <div class="metric-icon-box blue" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Sudah Dibayar</div>
                    <div class="fw-bold text-primary" style="font-size: 1.35rem;"><?= $today_tickets ?> Transaksi</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="modern-card p-3 d-flex align-items-center gap-3">
                <div class="metric-icon-box amber" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Belum Dibayar</div>
                    <div class="fw-bold text-warning" style="font-size: 1.35rem;"><?= $today_unpaid ?> Transaksi</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="modern-card p-3 d-flex align-items-center gap-3">
                <div class="metric-icon-box purple" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Total Transaksi</div>
                    <div class="fw-bold text-dark" style="font-size: 1.35rem;"><?= $total_all_tickets ?> Transaksi</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modern-card">
        <!-- Search & Filter Toolbar Interaktif (Live Tanpa Reload) -->
        <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2 flex-grow-1 flex-wrap" style="max-width: 780px;">
                <!-- Live Search Input -->
                <div class="input-icon-group flex-grow-1" style="min-width: 220px;">
                    <i class="fa-solid fa-magnifying-glass input-icon"></i>
                    <input 
                        class="form-control-modern" 
                        type="text" 
                        id="searchInput" 
                        placeholder="Cari ID, Nama, Kode TRX, Metode, Status..." 
                        value="<?= e($search) ?>"
                    >
                </div>

                <!-- Filter Waktu (Feedback-2 Poin 9) -->
                <div class="d-flex align-items-center gap-1">
                    <select id="dateFilterSelect" class="form-select-modern" style="min-width: 140px;">
                        <option value="all">Semua Waktu</option>
                        <option value="today">Hari Ini</option>
                        <option value="7days">7 Hari Terakhir</option>
                        <option value="month">Bulan Ini</option>
                        <option value="custom">Pilih Rentang...</option>
                    </select>
                </div>

                <!-- Kontainer Rentang Tanggal Kustom -->
                <div id="customDateContainer" class="d-none d-flex align-items-center gap-1">
                    <input type="date" id="dateStartInput" class="form-control-modern" style="width: 130px;" title="Mulai Tanggal">
                    <span class="text-muted small">s/d</span>
                    <input type="date" id="dateEndInput" class="form-control-modern" style="width: 130px;" title="Sampai Tanggal">
                </div>

                <!-- Filter Status (Lunas / Belum Dibayar) -->
                <div>
                    <select id="statusFilterSelect" class="form-select-modern" style="min-width: 135px;">
                        <option value="all">Semua Status</option>
                        <option value="done">Lunas</option>
                        <option value="pending">Belum Dibayar</option>
                    </select>
                </div>
            </div>

            <!-- Tombol Pemindai QR & Barcode -->
            <div>
                <button class="btn btn-soft-primary" type="button" data-bs-toggle="collapse" data-bs-target="#scannerCollapse">
                    <i class="fa-solid fa-qrcode me-1"></i> Scan Barcode / QR Nota
                </button>
            </div>
        </div>

        <!-- Barcode Scanner Collapse Area Modern (Feedback-2 Poin 8 & 11) -->
        <div class="collapse p-4 border-bottom bg-white" id="scannerCollapse">
            <div class="text-center" style="max-width: 500px; margin: auto;">
                <h5 class="fw-bold mb-2">Pindai Barcode / QR Code Nota</h5>
                <p class="text-muted small mb-3">Arahkan kamera ke barcode struk transaksi. Hasil akan memfilter tabel seketika tanpa reload.</p>
                
                <!-- Pilihan Kamera Bahasa Indonesia Ramah Pengguna -->
                <div class="d-flex align-items-center justify-content-center gap-2 mb-3 flex-wrap">
                    <label class="form-label small fw-semibold text-muted mb-0">
                        <i class="fa-solid fa-camera-rotate me-1 text-primary"></i>Pilih Kamera:
                    </label>
                    <select id="cameraSelect" class="form-select-modern" style="max-width: 250px;">
                        <option value="">Memuat perangkat kamera...</option>
                    </select>
                    <button id="btnToggleScanner" type="button" class="btn btn-sm btn-brand">
                        <i class="fa-solid fa-play me-1"></i>Mulai Kamera
                    </button>
                </div>

                <!-- Viewfinder Pemindai Modern -->
                <div id="cameraViewport" class="position-relative mx-auto rounded-3 overflow-hidden shadow-sm border" style="max-width: 440px; min-height: 270px; background: #0f172a; display: flex; align-items: center; justify-content: center;">
                    <div id="my-qr-reader" style="width: 100%;"></div>
                    <div class="scanner-laser d-none" id="scannerLaser"></div>
                    <div id="scannerOverlayPrompt" class="text-white text-center p-3">
                        <i class="fa-solid fa-qrcode mb-2 text-primary" style="font-size: 2.2rem;"></i>
                        <div class="fw-semibold">Kamera Belum Aktif</div>
                        <div class="small text-white-50">Pilih kamera di atas lalu klik "Mulai Kamera"</div>
                    </div>
                </div>

                <div id="your-qr-result" class="mt-2 text-center"></div>
            </div>
        </div>

        <style>
            /* Styling Scanner HTML5-QRCode agar responsif dan tombol tidak overflow di smartphone */
            #my-qr-reader {
                width: 100% !important;
                max-width: 100% !important;
                border: 1px solid var(--border-color, #e2e8f0) !important;
                background: #f8fafc;
                margin: 0 auto !important;
                box-sizing: border-box;
            }
            #my-qr-reader img[alt="Info icon"] {
                display: none !important;
            }
            #my-qr-reader button {
                background: #0284c7 !important;
                color: #ffffff !important;
                border: none !important;
                border-radius: 8px !important;
                padding: 8px 16px !important;
                font-size: 0.85rem !important;
                font-weight: 600 !important;
                margin: 6px 4px !important;
                cursor: pointer !important;
                max-width: 90% !important;
                white-space: normal !important;
                word-break: break-word !important;
                display: inline-block !important;
                box-shadow: 0 2px 4px rgba(2, 132, 199, 0.2) !important;
            }
            #my-qr-reader button:hover {
                background: #0369a1 !important;
            }
            #my-qr-reader select {
                padding: 6px 12px !important;
                border-radius: 8px !important;
                border: 1px solid #cbd5e1 !important;
                font-size: 0.85rem !important;
                max-width: 90% !important;
                margin: 6px auto !important;
            }
            #my-qr-reader__scan_region {
                background: #000000;
            }
            #my-qr-reader__dashboard_section_csr button {
                white-space: normal !important;
            }
            [data-bs-theme="dark"] #scannerCollapse,
            [data-theme="dark"] #scannerCollapse {
                background-color: var(--card-bg, #1e293b) !important;
                color: var(--text-primary, #f1f5f9) !important;
            }
            [data-bs-theme="dark"] #my-qr-reader,
            [data-theme="dark"] #my-qr-reader {
                background: #0f172a !important;
                border-color: #334155 !important;
                color: #f1f5f9 !important;
            }
        </style>

        <!-- Table Data -->
        <div class="table-responsive">
            <table class="table-modern" id="dataTable">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th style="width: 170px;">Kode Referensi</th>
                        <th>Nama Pemesan</th>
                        <th>Tgl Transaksi</th>
                        <th>Metode Bayar</th>
                        <th>Total Bayar</th>
                        <th>Bukti Pembayaran</th>
                        <th>Status</th>
                        <th class="action-column text-center" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php 
                    $no = 1;
                    while ($row = $result->fetch_assoc()): 
                        $kode_trx = format_kode_transaksi($row["id_transaksi"], $row["tgl_pemesanan"]);
                        $is_done = ($row["status"] === 'done');
                        $cust_name = !empty($row["nama_pemesan"]) ? $row["nama_pemesan"] : $row["nama"];
                        $pay_method = $row["metode_pembayaran"] ?? 'TUNAI';
                        $search_metadata = strtolower($kode_trx . ' #' . $row["id_transaksi"] . ' ' . $cust_name . ' ' . $pay_method . ' ' . ($is_done ? 'lunas' : 'belum dibayar pending'));
                    ?>
                        <tr data-id="<?= (int)$row["id_transaksi"] ?>" 
                            data-date="<?= date('Y-m-d', strtotime($row["tgl_pemesanan"])) ?>" 
                            data-status="<?= $is_done ? 'done' : 'pending' ?>"
                            data-search="<?= e($search_metadata) ?>">
                            <td class="text-center text-muted fw-semibold"><?= $no++ ?></td>
                            <td>
                                <strong class="text-primary font-monospace" style="font-size: 0.85rem;"><?= e($kode_trx) ?></strong>
                                <small class="text-muted d-block">ID: #<?= (int)$row["id_transaksi"] ?></small>
                            </td>
                            <td class="fw-semibold text-dark">
                                <?= e(!empty($row["nama_pemesan"]) ? $row["nama_pemesan"] : $row["nama"]) ?>
                                <?php if (!empty($row["nama_pemesan"])): ?>
                                    <span class="badge bg-light text-secondary border ms-1" style="font-size: 0.68rem; font-weight: normal;">Loket</span>
                                <?php endif; ?>
                                <?php 
                                $cust_phone = !empty($row["no_telepon_pemesan"]) ? $row["no_telepon_pemesan"] : ($row["user_telp"] ?? '');
                                if (!empty($cust_phone)): ?>
                                    <small class="text-muted d-block" style="font-size: 0.725rem;"><i class="fa-solid fa-phone me-1 text-primary"></i><?= e($cust_phone) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= date('d M Y', strtotime($row["tgl_pemesanan"])) ?></td>
                            <td>
                                <span class="badge badge-payment-method" style="font-weight: 600; font-size: 0.75rem;">
                                    <?= strtoupper(e($row["metode_pembayaran"] ?? 'TUNAI')) ?>
                                </span>
                            </td>
                            <td class="fw-bold text-dark"><?= format_rupiah($row["total_harga"]) ?></td>
                            <td>
                                <?php 
                                $bukti = $row["bukti_pembayaran"];
                                if (!empty($bukti) && strtolower($bukti) !== 'bayar di loket' && file_exists(__DIR__ . '/../../app/payment/' . $bukti)): 
                                ?>
                                    <a href="<?= payment_url($bukti) ?>" target="_blank" title="Lihat Bukti Transfer">
                                        <img src="<?= payment_url($bukti) ?>" alt="Bukti" style="width: 42px; height: 42px; object-fit: cover; border-radius: 8px; border: 1.5px solid #e2e8f0; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-light text-secondary border" style="font-weight: 500;">Loket Kasir</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row["status"] === 'done'): ?>
                                    <span class="badge-modern badge-modern-success">
                                        <i class="fa-solid fa-circle-check"></i> Selesai
                                    </span>
                                <?php else: ?>
                                    <span class="badge-modern badge-modern-warning">
                                        <i class="fa-solid fa-clock"></i> <?= e($row["status"]) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="action-column text-center">
                                <div class="d-inline-flex gap-1">
                                    <a class="btn btn-sm btn-soft-primary btn-action-icon" title="Cetak Struk Nota" href="<?= route_url('nota', ['id' => $row['id_transaksi']]) ?>">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                    <a class="btn btn-sm btn-soft-primary btn-action-icon" title="Detail Tiket" href="<?= route_url('transaksi_detail', ['id' => $row['id_transaksi']]) ?>">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a class="btn btn-sm btn-soft-primary btn-action-icon" title="Update Transaksi" href="<?= route_url('transaksi_update', ['id' => $row['id_transaksi']]) ?>">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-soft-danger btn-action-icon" title="Hapus Transaksi" onclick="confirmDelete(<?= (int)$row['id_transaksi'] ?>, '<?= e($kode_trx) ?>')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="fa-solid fa-receipt fs-2 mb-2 d-block text-secondary"></i>
                            Tidak ada data transaksi yang ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Table Pagination Footer Interaktif (Feedback-2 Poin 2 & 11) -->
        <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2 bg-light">
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">Tampilkan:</span>
                <select id="pageSizeSelect" class="form-select-modern form-select-sm" style="width: auto;">
                    <option value="10">10 data</option>
                    <option value="25">25 data</option>
                    <option value="50">50 data</option>
                    <option value="all">Semua</option>
                </select>
                <span id="paginationInfo" class="text-muted small ms-2"></span>
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0" id="paginationNav"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Modal Rekap Kasir & Tutup Shift (UX Phase 1) -->
<div class="modal fade" id="modalTutupShift" tabindex="-1" aria-labelledby="modalTutupShiftLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-warning-subtle text-dark border-0 py-3">
                <h5 class="modal-title fw-bold" id="modalTutupShiftLabel">
                    <i class="fa-solid fa-calculator me-2 text-warning"></i> Rekap Kasir & Tutup Shift
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Info Kasir & Waktu -->
                <div class="p-3 bg-light rounded-3 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="small text-muted fw-bold text-uppercase">Petugas Kasir</div>
                        <div class="fw-bold text-dark fs-5"><?= e($_SESSION['nama'] ?? $_SESSION['username']) ?></div>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted fw-bold text-uppercase">Waktu Rekap</div>
                        <div class="fw-semibold text-secondary"><?= date('d F Y, H:i') ?> WIB</div>
                    </div>
                </div>

                <!-- 3 Kartu Metrik Shift -->
                <div class="row g-2 mb-3">
                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 bg-success-subtle text-center">
                            <span class="small text-success fw-bold text-uppercase d-block mb-1">Penerimaan Tunai (Laci)</span>
                            <h4 class="fw-bold text-success mb-0"><?= format_rupiah($shift_cash_omzet) ?></h4>
                            <small class="text-muted"><?= $shift_cash_cnt ?> Transaksi</small>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 bg-primary-subtle text-center">
                            <span class="small text-primary fw-bold text-uppercase d-block mb-1">Non-Tunai (QRIS/Bank)</span>
                            <h4 class="fw-bold text-primary mb-0"><?= format_rupiah($shift_noncash_omzet) ?></h4>
                            <small class="text-muted"><?= $shift_noncash_cnt ?> Transaksi</small>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="border rounded-3 p-3 bg-dark-subtle text-center">
                            <span class="small text-dark fw-bold text-uppercase d-block mb-1">Total Omzet Shift</span>
                            <h4 class="fw-bold text-dark mb-0"><?= format_rupiah($today_omzet) ?></h4>
                            <small class="text-muted"><?= $today_tickets ?> Transaksi</small>
                        </div>
                    </div>
                </div>

                <!-- Rincian Tiket Terjual -->
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small text-uppercase mb-2">Rincian Tiket Terjual Hari Ini:</label>
                    <div class="table-responsive border rounded-3">
                        <table class="table table-sm table-striped mb-0" style="font-size: 0.85rem;" id="tableRekapTiket">
                            <thead class="table-light">
                                <tr>
                                    <th>Kategori Tiket</th>
                                    <th class="text-center">Tarif</th>
                                    <th class="text-center">Jumlah</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($shift_ticket_details)): ?>
                                    <?php foreach ($shift_ticket_details as $st): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= e($st['nama_tiket']) ?></td>
                                            <td class="text-center"><?= format_rupiah($st['harga']) ?></td>
                                            <td class="text-center fw-bold text-primary"><?= (int)$st['total_lembar'] ?> Lembar</td>
                                            <td class="text-end fw-semibold"><?= format_rupiah($st['subtotal']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-warning fw-bold">
                                        <td>TOTAL TIKET TERJUAL</td>
                                        <td></td>
                                        <td class="text-center"><?= $shift_total_lembar ?> Lembar</td>
                                        <td class="text-end"><?= format_rupiah($today_omzet) ?></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">Belum ada tiket terjual pada shift hari ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Kalkulator Fisik Uang Laci Kasir (Balancing) -->
                <div class="p-3 border rounded-3 bg-light">
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-coins me-1 text-warning"></i> Hitung Fisik Uang di Laci Kasir</h6>
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted mb-1">Nominal Uang Fisik Terhitung di Laci:</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">Rp</span>
                                <input type="number" id="inputUangLaci" class="form-control" placeholder="0" min="0" oninput="hitungSelisihLaci(<?= $shift_cash_omzet ?>)">
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted mb-1">Hasil Pencocokan Kasir (Balancing):</label>
                            <div id="boxStatusBalancing" class="p-2 rounded-2 text-center fw-bold border bg-white" style="font-size: 0.9rem;">
                                <span class="text-muted">Masukkan uang fisik</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-3 d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-success fw-bold" onclick="cetakStrukShift()">
                    <i class="fa-solid fa-print me-1"></i> Cetak Struk Tutup Shift (Thermal 80mm)
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '
<script>
function confirmDelete(id, kode) {
    Swal.fire({
        title: "Pindahkan Transaksi " + kode + " ke Tempat Sampah?",
        text: "Data akan disembunyikan dan diarsipkan ke riwayat audit (Soft Delete).",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Hapus",
        cancelButtonText: "Batal"
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "' . route_url('staf_delete') . '";
            const idInput = document.createElement("input");
            idInput.type = "hidden";
            idInput.name = "id";
            idInput.value = id;
            const csrfInput = document.createElement("input");
            csrfInput.type = "hidden";
            csrfInput.name = "csrf_token";
            csrfInput.value = "' . csrf_token() . '";
            form.appendChild(idInput);
            form.appendChild(csrfInput);
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function printTable(tableId, title) {
    const table = document.getElementById(tableId);
    const win = window.open("", "_blank");
    win.document.write("<html><head><title>" + title + "</title>");
    win.document.write("<style>body{font-family:sans-serif;padding:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:8px;text-align:left}.action-column{display:none}</style>");
    win.document.write("</head><body><h2>" + title + "</h2>");
    win.document.write(table.outerHTML);
    win.document.write("</body></html>");
    win.document.close();
    win.print();
}

function hitungSelisihLaci(targetTunai) {
    const inputFisik = parseFloat(document.getElementById("inputUangLaci").value) || 0;
    const box = document.getElementById("boxStatusBalancing");
    if (!document.getElementById("inputUangLaci").value) {
        box.innerHTML = \'<span class="text-muted">Masukkan uang fisik</span>\';
        box.className = "p-2 rounded-2 text-center fw-bold border bg-white";
        return;
    }

    const selisih = inputFisik - targetTunai;
    if (selisih === 0) {
        box.innerHTML = \'<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i> UANG LACI PAS (Rp \' + Number(inputFisik).toLocaleString("id-ID") + \')</span>\';
        box.className = "p-2 rounded-2 text-center fw-bold border bg-success-subtle";
    } else if (selisih > 0) {
        box.innerHTML = \'<span class="text-primary"><i class="fa-solid fa-arrow-trend-up me-1"></i> LEBIH Rp \' + Number(selisih).toLocaleString("id-ID") + \'</span>\';
        box.className = "p-2 rounded-2 text-center fw-bold border bg-primary-subtle";
    } else {
        box.innerHTML = \'<span class="text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i> KURANG Rp \' + Number(Math.abs(selisih)).toLocaleString("id-ID") + \'</span>\';
        box.className = "p-2 rounded-2 text-center fw-bold border bg-danger-subtle";
    }
}

function cetakStrukShift() {
    const kasirNama = "' . addslashes(e($_SESSION['nama'] ?? $_SESSION['username'])) . '";
    const tglRekap = "' . date('d/m/Y H:i:s') . '";
    const totalTunai = "' . format_rupiah($shift_cash_omzet) . '";
    const cntTunai = "' . $shift_cash_cnt . '";
    const totalNonTunai = "' . format_rupiah($shift_noncash_omzet) . '";
    const cntNonTunai = "' . $shift_noncash_cnt . '";
    const totalOmzet = "' . format_rupiah($today_omzet) . '";
    const totalLembar = "' . $shift_total_lembar . '";
    const uangFisikVal = parseFloat(document.getElementById("inputUangLaci").value) || 0;
    const uangFisikTxt = "Rp " + Number(uangFisikVal).toLocaleString("id-ID");
    const selisihVal = uangFisikVal - ' . $shift_cash_omzet . ';
    let selisihTxt = "PAS (Rp 0)";
    if (selisihVal > 0) selisihTxt = "LEBIH Rp " + Number(selisihVal).toLocaleString("id-ID");
    if (selisihVal < 0) selisihTxt = "KURANG Rp " + Number(Math.abs(selisihVal)).toLocaleString("id-ID");

    const win = window.open("", "_blank", "width=400,height=600");
    win.document.write(\'<!DOCTYPE html><html><head><title>Struk Tutup Shift Kasir</title>\');
    win.document.write(\'<style>\');
    win.document.write(\'@page { size: 80mm auto; margin: 0; }\');
    win.document.write(\'body { width: 78mm; margin: 0 auto; padding: 12px 6px; font-family: "Courier New", Courier, monospace; font-size: 11px; color: #000; }\');
    win.document.write(\'.text-center { text-align: center; }\');
    win.document.write(\'.text-end { text-align: right; }\');
    win.document.write(\'.fw-bold { font-weight: bold; }\');
    win.document.write(\'.divider { border-top: 1px dashed #000; margin: 6px 0; }\');
    win.document.write(\'.row-flex { display: flex; justify-content: space-between; margin-bottom: 3px; }\');
    win.document.write(\'table { width: 100%; border-collapse: collapse; margin: 4px 0; font-size: 10px; }\');
    win.document.write(\'th, td { padding: 2px 0; text-align: left; }\');
    win.document.write(\'</style></head><body>\');
    win.document.write(\'<div class="text-center">\');
    win.document.write(\'<div class="fw-bold" style="font-size: 13px;">WISATA PEMANDIAN PATEMON</div>\');
    win.document.write(\'<div style="font-size: 10px;">UPTD Pariwisata & Kebudayaan Jember</div>\');
    win.document.write(\'<div class="divider"></div>\');
    win.document.write(\'<div class="fw-bold">REKAP TUTUP SHIFT KASIR</div>\');
    win.document.write(\'</div>\');
    win.document.write(\'<div class="divider"></div>\');
    win.document.write(\'<div class="row-flex"><span>Kasir:</span><span class="fw-bold">\' + kasirNama + \'</span></div>\');
    win.document.write(\'<div class="row-flex"><span>Waktu:</span><span>\' + tglRekap + \'</span></div>\');
    win.document.write(\'<div class="divider"></div>\');
    win.document.write(\'<div class="fw-bold" style="margin-bottom: 3px;">RINCIAN TIKET:</div>\');
    
    // Ambil baris tabel tiket
    const rows = document.querySelectorAll("#tableRekapTiket tbody tr");
    win.document.write(\'<table>\');
    rows.forEach(r => {
        const cells = r.querySelectorAll("td");
        if (cells.length === 4) {
            win.document.write(\'<tr><td>\' + cells[0].innerText + \'</td><td class="text-center">\' + cells[2].innerText + \'</td><td class="text-end">\' + cells[3].innerText + \'</td></tr>\');
        }
    });
    win.document.write(\'</table>\');
    win.document.write(\'<div class="divider"></div>\');
    win.document.write(\'<div class="row-flex"><span>Total Tunai (\' + cntTunai + \' trx):</span><span class="fw-bold">\' + totalTunai + \'</span></div>\');
    win.document.write(\'<div class="row-flex"><span>Total Non-Tunai (\' + cntNonTunai + \' trx):</span><span>\' + totalNonTunai + \'</span></div>\');
    win.document.write(\'<div class="row-flex fw-bold" style="font-size: 12px; margin-top: 3px;"><span>TOTAL OMZET:</span><span>\' + totalOmzet + \'</span></div>\');
    win.document.write(\'<div class="divider"></div>\');
    win.document.write(\'<div class="row-flex"><span>Fisik Uang Laci:</span><span class="fw-bold">\' + (uangFisikVal > 0 ? uangFisikTxt : "(Belum diisi)") + \'</span></div>\');
    win.document.write(\'<div class="row-flex"><span>Status Balancing:</span><span class="fw-bold">\' + selisihTxt + \'</span></div>\');
    win.document.write(\'<div class="divider"></div>\');
    win.document.write(\'<div style="margin-top: 15px; display: flex; justify-content: space-between; text-align: center; font-size: 10px;">\');
    win.document.write(\'<div>Kasir Bertugas,<br><br><br><br>(\' + kasirNama + \')</div>\');
    win.document.write(\'<div>Supervisor / Bendahara,<br><br><br><br>( .................... )</div>\');
    win.document.write(\'</div>\');
    win.document.write(\'<div class="divider" style="margin-top: 15px;"></div>\');
    win.document.write(\'<div class="text-center" style="font-size: 9px; margin-top: 4px;">Simpan bukti ini sebagai lampiran serah terima kasir.</div>\');
    win.document.write(\'</body></html>\');
    win.document.close();
    setTimeout(() => { win.print(); }, 400);
}
</script>
<script src="' . public_url('js/html5-qrcode.min.js') . '"></script>
<script src="' . public_url('js/transaction-table-controller.js') . '"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    // 1. Inisialisasi Pengontrol Tabel Transaksi Interaktif (Live Search, Waktu & Pagination)
    const tableManager = new TransactionTableManager({
        tableId: "dataTable",
        searchInputId: "searchInput",
        datePresetId: "dateFilterSelect",
        dateStartId: "dateStartInput",
        dateEndId: "dateEndInput",
        statusFilterId: "statusFilterSelect",
        pageSizeId: "pageSizeSelect",
        paginationInfoId: "paginationInfo",
        paginationNavId: "paginationNav"
    });

    // 2. Inisialisasi Scanner Modern Berbahasa Indonesia Ramah Pengguna
    new ModernScannerController({
        collapseId: "scannerCollapse",
        readerContainerId: "my-qr-reader",
        cameraSelectId: "cameraSelect",
        btnToggleId: "btnToggleScanner",
        laserId: "scannerLaser",
        promptId: "scannerOverlayPrompt",
        resultId: "your-qr-result",
        onScanSuccess: (decodedText) => {
            const searchInput = document.getElementById("searchInput");
            if (searchInput) {
                searchInput.value = decodedText;
            }
            tableManager.applyFilters();
            tableManager.highlightMatchingRow(decodedText);
        }
    });
});
</script>
';
require '../../app/layouts/admin_footer.php';
?>
