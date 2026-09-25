<?php
require '../../app/config.php';
check_auth([1, 2, 3]);

$active_menu = (in_array((int)$_SESSION['level'], [2, 3], true)) ? 'kasir' : 'transaksi';
$base_view = '..';

$id_transaksi = (int)($_GET["id"] ?? 0);

if ($id_transaksi <= 0) {
    $redirect_url = (in_array((int)$_SESSION['level'], [2, 3], true)) ? route_url('kasir') : route_url('transaksi');
    header("Location: " . $redirect_url);
    exit();
}

$stmt = $conn->prepare("SELECT detail_transaksi.*, transaksi.tgl_pemesanan, transaksi.total_harga, transaksi.metode_pembayaran, transaksi.status, transaksi.bukti_pembayaran, users.nama 
    FROM detail_transaksi 
    INNER JOIN transaksi ON detail_transaksi.id_transaksi = transaksi.id_transaksi 
    INNER JOIN users ON transaksi.id_user = users.id_user 
    WHERE detail_transaksi.id_transaksi = ?");
$stmt->bind_param("i", $id_transaksi);
$stmt->execute();
$result = $stmt->get_result();

$transaksi_info = null;
$details = [];
while ($row = $result->fetch_assoc()) {
    if (!$transaksi_info) {
        $transaksi_info = $row;
    }
    $details[] = $row;
}
$stmt->close();

$back_link = (in_array((int)$_SESSION['level'], [2, 3], true)) ? route_url('kasir') : route_url('transaksi');

$page_title      = "Faktur Transaksi #{$id_transaksi} - Pemandian Patemon";
$page_heading    = "Faktur Transaksi #{$id_transaksi}";
$page_subheading = "Rincian detail pemesanan tiket pengunjung pemandian.";
$header_actions  = '
    <div class="d-flex gap-2">
        <a href="' . $back_link . '" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali
        </a>
        <a href="' . route_url('nota', ['id' => $id_transaksi]) . '" class="btn btn-brand">
            <i class="fa-solid fa-print me-1"></i> Cetak Struk Nota
        </a>
    </div>
';

require '../../app/layouts/admin_header.php';
?>

<div class="page-content">
    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="modern-card">
                <div class="modern-card-header">
                    <span class="fw-bold text-dark"><i class="fa-solid fa-receipt text-primary me-2"></i> Rincian Tiket Pesanan</span>
                    <span class="badge badge-modern-primary">ID: #<?= $id_transaksi ?></span>
                </div>

                <div class="modern-card-body">
                    <?php if ($transaksi_info): ?>
                        <!-- Info Box -->
                        <div class="order-info-box p-3 rounded-3 mb-4 border">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="text-muted small fw-semibold">Nama Pemesan:</div>
                                    <div class="fw-bold text-dark fs-6"><?= e($transaksi_info['nama']) ?></div>
                                    <div class="text-muted small fw-semibold mt-2">Tanggal Transaksi:</div>
                                    <div class="fw-semibold text-secondary"><?= date('d F Y', strtotime($transaksi_info['tgl_pemesanan'])) ?></div>
                                </div>
                                <div class="col-sm-6 text-sm-end">
                                    <div class="text-muted small fw-semibold">Metode Pembayaran:</div>
                                    <div class="fw-bold text-dark fs-6"><?= strtoupper(e($transaksi_info['metode_pembayaran'])) ?></div>
                                    <div class="text-muted small fw-semibold mt-2">Status Pembayaran:</div>
                                    <div>
                                        <?php if ($transaksi_info['status'] === 'done'): ?>
                                            <span class="badge-modern badge-modern-success">
                                                <i class="fa-solid fa-circle-check"></i> Sudah Dibayar (Lunas)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-modern badge-modern-warning">
                                                <i class="fa-solid fa-clock"></i> Belum Dibayar
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Table Items -->
                        <div class="table-responsive">
                            <table class="table-modern">
                                <thead>
                                    <tr>
                                        <th>Kategori Tiket</th>
                                        <th class="text-center">Jumlah</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($details as $d): ?>
                                    <tr>
                                        <td class="fw-bold text-dark">
                                            <i class="fa-solid fa-ticket text-primary me-2"></i>
                                            <?= e($d['jenis_tiket']) ?>
                                        </td>
                                        <td class="text-center fw-semibold"><?= (int)$d['quantity'] ?> lembar</td>
                                        <td class="text-end fw-bold text-primary"><?= format_rupiah($d['sub_total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td colspan="2" class="text-end fs-6">TOTAL KESELURUHAN:</td>
                                        <td class="text-end text-success fs-5"><?= format_rupiah($transaksi_info['total_harga']) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">Data transaksi tidak ditemukan.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Bukti Pembayaran / Sisi Kanan -->
        <div class="col-12 col-lg-4">
            <div class="modern-card">
                <div class="modern-card-header">
                    <span class="fw-bold text-dark"><i class="fa-solid fa-image text-primary me-2"></i> Bukti Pembayaran</span>
                </div>
                <div class="modern-card-body text-center">
                    <?php 
                    $bukti = $transaksi_info['bukti_pembayaran'] ?? '';
                    if (!empty($bukti) && strtolower($bukti) !== 'bayar di loket' && file_exists(__DIR__ . '/../../app/payment/' . $bukti)): 
                    ?>
                        <a href="<?= payment_url($bukti) ?>" target="_blank" title="Klik untuk memperbesar">
                            <img src="<?= payment_url($bukti) ?>" alt="Bukti Transfer" class="img-fluid rounded-3 border shadow-sm" style="max-height: 280px; object-fit: contain;">
                        </a>
                        <div class="mt-2 text-muted small"><i class="fa-solid fa-magnifying-glass-plus me-1"></i> Klik gambar untuk ukuran penuh</div>
                    <?php else: ?>
                        <div class="p-4 bg-light rounded-3 text-muted">
                            <i class="fa-solid fa-hand-holding-dollar fs-1 mb-2 text-secondary d-block"></i>
                            Pembayaran langsung di loket kasir (Tunai / Loket).
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require '../../app/layouts/admin_footer.php';
?>
