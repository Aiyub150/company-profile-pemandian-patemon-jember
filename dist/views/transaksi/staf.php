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
    <div class="d-flex gap-2">
        <a href="' . route_url('transaksi_tambah') . '" class="btn btn-brand">
            <i class="fa-solid fa-plus me-1"></i> Transaksi Baru (POS)
        </a>
        <a href="' . route_url('laporan') . '" class="btn btn-soft-danger">
            <i class="fa-solid fa-file-pdf me-1"></i> PDF
        </a>
    </div>
';

$search = trim($_GET['search'] ?? ($_GET['id_transaksi'] ?? ''));

// Query Transaksi dengan isolasi Staf jika level 3
if ($user_level === 3) {
    if (!empty($search)) {
        $id_search = 0;
        if (preg_match('/(?:TRX-\d{8}-)?(\d+)/i', $search, $m)) {
            $id_search = (int)$m[1];
        }
        $search_like = "%" . $search . "%";

        $stmt = $conn->prepare("
            SELECT transaksi.*, users.nama 
            FROM transaksi 
            INNER JOIN users ON transaksi.id_user = users.id_user 
            WHERE transaksi.id_user = ? AND (
                transaksi.id_transaksi = ? 
                OR users.nama LIKE ? 
                OR transaksi.metode_pembayaran LIKE ? 
                OR transaksi.status LIKE ?
                OR transaksi.tgl_pemesanan LIKE ?
            )
            ORDER BY tgl_pemesanan DESC, id_transaksi DESC
        ");
        $stmt->bind_param("iissss", $user_id, $id_search, $search_like, $search_like, $search_like, $search_like);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("SELECT transaksi.*, users.nama FROM transaksi INNER JOIN users ON transaksi.id_user = users.id_user WHERE transaksi.id_user = ? ORDER BY tgl_pemesanan DESC, id_transaksi DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    }

    // Rekap Staf Hari Ini
    $stmt_rc = $conn->prepare("SELECT SUM(total_harga) as omzet, COUNT(*) as cnt FROM transaksi WHERE DATE(tgl_pemesanan) = CURDATE() AND status = 'done' AND id_user = ?");
    $stmt_rc->bind_param("i", $user_id);
    $stmt_rc->execute();
    $rc = $stmt_rc->get_result()->fetch_assoc();
    $stmt_rc->close();
    $today_omzet = (float)($rc['omzet'] ?? 0);
    $today_tickets = (int)($rc['cnt'] ?? 0);
} else {
    if (!empty($search)) {
        $id_search = 0;
        if (preg_match('/(?:TRX-\d{8}-)?(\d+)/i', $search, $m)) {
            $id_search = (int)$m[1];
        }
        $search_like = "%" . $search . "%";

        $stmt = $conn->prepare("
            SELECT transaksi.*, users.nama 
            FROM transaksi 
            INNER JOIN users ON transaksi.id_user = users.id_user 
            WHERE transaksi.id_transaksi = ? 
               OR users.nama LIKE ? 
               OR transaksi.metode_pembayaran LIKE ? 
               OR transaksi.status LIKE ?
               OR transaksi.tgl_pemesanan LIKE ?
            ORDER BY tgl_pemesanan DESC, id_transaksi DESC
        ");
        $stmt->bind_param("issss", $id_search, $search_like, $search_like, $search_like, $search_like);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $sql = "SELECT transaksi.*, users.nama FROM transaksi INNER JOIN users ON transaksi.id_user = users.id_user ORDER BY tgl_pemesanan DESC, id_transaksi DESC";
        $result = $conn->query($sql);
    }

    $today_omzet = 0;
    $today_tickets = 0;
    $recap = $conn->query("SELECT SUM(total_harga) as omzet, COUNT(*) as cnt FROM transaksi WHERE DATE(tgl_pemesanan) = CURDATE() AND status = 'done'");
    if ($recap && $rc = $recap->fetch_assoc()) {
        $today_omzet = (float)($rc['omzet'] ?? 0);
        $today_tickets = (int)$rc['cnt'];
    }
}

$extra_css = '
<script src="https://unpkg.com/html5-qrcode"></script>
';

require '../../app/layouts/admin_header.php';
?>

<div class="page-content">
    <!-- Stats Hari Ini -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6">
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
        <div class="col-12 col-sm-6">
            <div class="modern-card p-3 d-flex align-items-center gap-3">
                <div class="metric-icon-box blue" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fa-solid fa-ticket"></i>
                </div>
                <div>
                    <div class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Transaksi Lunas Hari Ini</div>
                    <div class="fw-bold text-primary" style="font-size: 1.35rem;"><?= $today_tickets ?> Transaksi</div>
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
                    <a href="<?= route_url('kasir') ?>" class="btn btn-outline-secondary">Reset</a>
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
                <p class="text-muted small mb-3">Arahkan barcode nota struk ke kamera untuk verifikasi tiket masuk.</p>
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
                                <span class="badge" style="background: #f1f5f9; color: #334155; font-weight: 600; font-size: 0.75rem;">
                                    <?= strtoupper(e($row["metode_pembayaran"] ?? 'TUNAI')) ?>
                                </span>
                            </td>
                            <td class="fw-bold text-dark"><?= format_rupiah($row["total_harga"]) ?></td>
                            <td>
                                <?php 
                                $bukti = $row["bukti_pembayaran"];
                                if (!empty($bukti) && strtolower($bukti) !== 'bayar di loket' && file_exists(__DIR__ . '/../../app/payment/' . $bukti)): 
                                ?>
                                    <a href="../../app/payment/<?= e($bukti) ?>" target="_blank" title="Lihat Bukti Transfer">
                                        <img src="../../app/payment/<?= e($bukti) ?>" alt="Bukti" style="width: 42px; height: 42px; object-fit: cover; border-radius: 8px; border: 1.5px solid #e2e8f0;">
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

// HTML5 QR Scanner
let html5QrcodeScanner;
document.addEventListener("DOMContentLoaded", () => {
    const collapseElem = document.getElementById("scannerCollapse");
    collapseElem.addEventListener("shown.bs.collapse", () => {
        if (!html5QrcodeScanner) {
            html5QrcodeScanner = new Html5QrcodeScanner("my-qr-reader", { fps: 10, qrbox: 250 });
            html5QrcodeScanner.render((decodedText) => {
                document.getElementById("your-qr-result").innerText = "Ditemukan: " + decodedText;
                setTimeout(() => {
                    window.location.href = "staf.php?search=" + encodeURIComponent(decodedText);
                }, 800);
            });
        }
    });
});
</script>
';
require '../../app/layouts/admin_footer.php';
?>
