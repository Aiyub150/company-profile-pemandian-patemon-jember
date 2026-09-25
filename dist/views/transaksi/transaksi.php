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
        SELECT transaksi.*, users.nama 
        FROM transaksi 
        INNER JOIN users ON transaksi.id_user = users.id_user 
        WHERE transaksi.deleted_at IS NULL 
          AND (transaksi.id_transaksi = ? 
           OR users.nama LIKE ? 
           OR transaksi.metode_pembayaran LIKE ? 
           OR transaksi.status LIKE ?
           OR transaksi.tgl_pemesanan LIKE ?)
        ORDER BY tgl_pemesanan DESC, id_transaksi DESC
    ");
    $stmt->bind_param("issss", $id_search, $search_like, $search_like, $search_like, $search_like);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $sql = "SELECT transaksi.*, users.nama FROM transaksi INNER JOIN users ON transaksi.id_user = users.id_user WHERE transaksi.deleted_at IS NULL ORDER BY tgl_pemesanan DESC, id_transaksi DESC";
    $result = $conn->query($sql);
}

// Hitung rekap cepat
$total_count = 0;
$total_omzet = 0;
$total_done  = 0;
$recap_res = $conn->query("SELECT COUNT(*) as cnt, SUM(total_harga) as omzet, SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_cnt FROM transaksi WHERE deleted_at IS NULL");
if ($recap_res && $recap = $recap_res->fetch_assoc()) {
    $total_count = (int)$recap['cnt'];
    $total_omzet = (float)($recap['omzet'] ?? 0);
    $total_done  = (int)$recap['done_cnt'];
}

$extra_css = '
<script src="https://unpkg.com/html5-qrcode"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
';

require '../../app/layouts/admin_header.php';
?>

<div class="page-content">
    <!-- Mini Overview Stats -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-4">
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
        <div class="col-12 col-sm-4">
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
        <div class="col-12 col-sm-4">
            <div class="modern-card p-3 d-flex align-items-center gap-3">
                <div class="metric-icon-box amber" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Tiket Terverifikasi</div>
                    <div class="fw-bold text-warning" style="font-size: 1.35rem;"><?= number_format($total_done, 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modern-card">
        <!-- Search & Scanner Toolbar -->
        <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET" action="" class="d-flex align-items-center gap-2" style="max-width: 480px; width: 100%;">
                <div class="input-icon-group flex-grow-1">
                    <i class="fa-solid fa-magnifying-glass input-icon"></i>
                    <input 
                        class="form-control-modern" 
                        type="text" 
                        name="search" 
                        id="searchInput" 
                        placeholder="Cari ID, Nama, Kode TRX, Metode, Status..." 
                        value="<?= e($search) ?>"
                    >
                </div>
                <button type="submit" class="btn btn-brand">Cari</button>
                <?php if (!empty($search)): ?>
                    <a href="<?= route_url('transaksi') ?>" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </form>
            <div>
                <button class="btn btn-soft-primary" type="button" data-bs-toggle="collapse" data-bs-target="#scannerCollapse">
                    <i class="fa-solid fa-qrcode me-1"></i> Scan Barcode Nota
                </button>
            </div>
        </div>

        <!-- Barcode Scanner Collapse Area -->
        <div class="collapse p-4 border-bottom bg-white" id="scannerCollapse">
            <div class="text-center" style="max-width: 500px; margin: auto;">
                <h5 class="fw-bold mb-2">Pindai Barcode / QR Code Nota</h5>
                <p class="text-muted small mb-3">Arahkan barcode nota struk loket ke kamera untuk mencari transaksi secara instan.</p>
                <div id="my-qr-reader" class="rounded-3 overflow-hidden shadow-sm border"></div>
                <div id="your-qr-result" class="mt-3 text-success fw-bold"></div>
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
                    ?>
                        <tr>
                            <td class="text-center text-muted fw-semibold"><?= $no++ ?></td>
                            <td>
                                <strong class="text-primary font-monospace" style="font-size: 0.85rem;"><?= e($kode_trx) ?></strong>
                                <small class="text-muted d-block">ID: #<?= (int)$row["id_transaksi"] ?></small>
                            </td>
                            <td class="fw-semibold text-dark"><?= e($row["nama"]) ?></td>
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
<script>
// HTML5 QR & Barcode Scanner Otomatis
let html5QrcodeScanner;
document.addEventListener("DOMContentLoaded", () => {
    const collapseElem = document.getElementById("scannerCollapse");
    collapseElem.addEventListener("shown.bs.collapse", () => {
        if (!html5QrcodeScanner) {
            html5QrcodeScanner = new Html5QrcodeScanner(
                "my-qr-reader", 
                { 
                    fps: 20, 
                    qrbox: (vfWidth, vfHeight) => ({
                        width: Math.min(Math.floor(vfWidth * 0.92), 480),
                        height: Math.min(Math.floor(vfHeight * 0.65), 240)
                    }),
                    aspectRatio: 1.777778,
                    experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                    formatsToSupport: [
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.QR_CODE,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.UPC_A
                    ]
                },
                false
            );
            html5QrcodeScanner.render((decodedText) => {
                const resEl = document.getElementById("your-qr-result");
                if (resEl) {
                    resEl.innerHTML = \'<span class="badge bg-success fs-6"><i class="fa-solid fa-check me-1"></i> Terbaca: \' + decodedText + \'</span>\';
                }
                setTimeout(() => {
                    window.location.href = window.location.pathname + "?search=" + encodeURIComponent(decodedText);
                }, 700);
            });
        }
    });
});
</script>
';
require '../../app/layouts/admin_footer.php';
?>
