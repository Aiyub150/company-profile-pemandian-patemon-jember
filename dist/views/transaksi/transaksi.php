<?php
require '../../app/config.php';

// Hak akses: Admin (1) dan Staff (2)
check_auth([1, 2]);

$active_menu     = 'transaksi';
$page_title      = 'Kelola Transaksi Tiket - Pemandian Patemon';
$page_heading    = 'Kelola Transaksi Tiket';
$page_subheading = 'Riwayat penjualan tiket loket, validasi bukti transfer, dan cetak struk nota.';

$header_actions = '
    <div class="d-flex gap-2 flex-wrap">
        <a href="' . route_url('transaksi_tambah') . '" class="btn btn-brand">
            <i class="fa-solid fa-plus me-1"></i> Transaksi Baru (POS)
        </a>
        <a href="' . route_url('laporan_preview') . '" class="btn btn-soft-danger">
            <i class="fa-solid fa-file-pdf me-1"></i> PDF
        </a>
        <button onclick="exportToExcel(\'dataTable\', \'transaksi_patemon\')" class="btn btn-soft-success">
            <i class="fa-solid fa-file-excel me-1"></i> Excel
        </button>
    </div>
';

$search = trim($_GET['search'] ?? ($_GET['id_transaksi'] ?? ''));

// Multi-field search query: ID, Nama, Metode, Status, Tanggal
if (!empty($search)) {
    $id_search = 0;
    if (preg_match('/(?:TRX-\d{8}-)?0*(\d+)/i', $search, $m)) {
        $id_search = (int)$m[1];
    }
    $search_like = "%" . $search . "%";

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
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $sql = "SELECT transaksi.*, users.nama, users.no_telepon as user_telp FROM transaksi INNER JOIN users ON transaksi.id_user = users.id_user WHERE transaksi.deleted_at IS NULL ORDER BY tgl_pemesanan DESC, id_transaksi DESC";
    $result = $conn->query($sql);
}

// Hitung rekap cepat (Termasuk Transaksi Belum Dibayar)
$total_count = 0;
$total_omzet = 0;
$total_done  = 0;
$total_pending = 0;
$recap_res = $conn->query("SELECT 
    COUNT(*) as cnt, 
    SUM(CASE WHEN status = 'done' THEN total_harga ELSE 0 END) as omzet, 
    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_cnt,
    SUM(CASE WHEN status != 'done' THEN 1 ELSE 0 END) as pending_cnt
FROM transaksi 
WHERE deleted_at IS NULL");
if ($recap_res && $recap = $recap_res->fetch_assoc()) {
    $total_count   = (int)$recap['cnt'];
    $total_omzet   = (float)($recap['omzet'] ?? 0);
    $total_done    = (int)$recap['done_cnt'];
    $total_pending = (int)$recap['pending_cnt'];
}

$extra_css = '
<script src="' . public_url('js/html5-qrcode.min.js') . '"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
';

require '../../app/layouts/admin_header.php';
?>

<div class="page-content">
    <!-- Overview Stats Transaksi Lengkap (Feedback-2 Poin 10) -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="modern-card p-3 d-flex align-items-center gap-3">
                <div class="metric-icon-box blue" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Total Transaksi</div>
                    <div class="fw-bold" style="font-size: 1.35rem; color: #0f172a;"><?= number_format($total_count, 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="modern-card p-3 d-flex align-items-center gap-3">
                <div class="metric-icon-box green" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fa-solid fa-money-bill-trend-up"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Total Omzet Loket</div>
                    <div class="fw-bold text-success" style="font-size: 1.35rem;"><?= format_rupiah($total_omzet) ?></div>
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
                    <div class="fw-bold text-primary" style="font-size: 1.35rem;"><?= number_format($total_done, 0, ',', '.') ?></div>
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
                    <div class="fw-bold text-warning" style="font-size: 1.35rem;"><?= number_format($total_pending, 0, ',', '.') ?></div>
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
                <p class="text-muted small mb-3">Arahkan barcode nota struk loket ke kamera. Hasil akan langsung memfilter transaksi seketika.</p>
                
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

        <!-- Table Data -->
        <div class="table-responsive">
            <table class="table-modern" id="dataTable">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">No</th>
                        <th style="width: 170px;">Kode Referensi</th>
                        <th>Nama Pemesan</th>
                        <th>Tgl Kunjungan</th>
                        <th>Metode Bayar</th>
                        <th>Total Bayar</th>
                        <th>Bukti Bayar</th>
                        <th>Status</th>
                        <th class="action-column text-center" style="width: 160px;">Aksi</th>
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
                                    <a class="btn btn-sm btn-soft-primary btn-action-icon" title="Edit Transaksi" href="<?= route_url('transaksi_update', ['id' => $row['id_transaksi']]) ?>">
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
            form.action = "' . route_url('transaksi_delete') . '";
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

// Print Table
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
</script>
<script src="' . public_url('js/exportToExcel.js') . '"></script>
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
