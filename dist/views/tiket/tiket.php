<?php
require '../../app/config.php';
check_auth([1, 2]);

$active_menu     = 'tiket';
$page_title      = 'Tarif & Kategori Tiket - Pemandian Patemon';
$page_heading    = 'Tarif & Kategori Tiket';
$page_subheading = 'Atur kategori tiket masuk pengunjung untuk loket kasir dan pemesanan online.';

$header_actions = '
    <a href="' . route_url('tiket_tambah') . '" class="btn btn-brand">
        <i class="fa-solid fa-plus me-1"></i> Tambah Kategori Tiket
    </a>
';

$sql = "SELECT * FROM tiket ORDER BY id_tiket ASC";
$result = $conn->query($sql);

require '../../app/layouts/admin_header.php';
?>

<div class="page-content">
    <div class="modern-card">
        <div class="modern-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-bold fs-6 text-dark">
                <i class="fa-solid fa-tags text-primary me-2"></i> Daftar Kategori Tiket Masuk
            </span>
            <div class="d-flex align-items-center gap-2">
                <div class="input-icon-group" style="width: 250px;">
                    <i class="fa-solid fa-search input-icon"></i>
                    <input type="text" id="tiketSearch" class="form-control-modern form-control-sm" placeholder="Cari kategori...">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table-modern" id="tableTiket">
                <thead>
                    <tr>
                        <th style="width: 60px;" class="text-center">No</th>
                        <th style="width: 90px;">Kode</th>
                        <th>Kategori & Ikon</th>
                        <th>Tarif Tiket</th>
                        <th class="text-center" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php 
                    $no = 1;
                    while ($row = $result->fetch_assoc()): 
                        $iconClass = get_ticket_icon($row['nama_tiket'], $row['ikon'] ?? null);
                        $colorClass = get_ticket_color($row['nama_tiket']);
                    ?>
                        <tr>
                            <td class="text-center text-muted fw-semibold"><?= $no++ ?></td>
                            <td><strong class="text-secondary">#TKT-<?= sprintf('%02d', (int)$row["id_tiket"]) ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="metric-icon-box <?= $colorClass ?>" style="width: 40px; height: 40px; font-size: 1.1rem; border-radius: 10px;">
                                        <i class="fa-solid <?= e($iconClass) ?>"></i>
                                    </div>
                                    <div>
                                        <span class="fw-bold d-block text-dark" style="font-size: 0.95rem;"><?= e($row["nama_tiket"]) ?></span>
                                        <small class="text-muted"><i class="fa-solid <?= e($iconClass) ?> me-1"></i> Ikon: <code><?= e($iconClass) ?></code></small>
                                    </div>
                                </div>
                            </td>
                            <td class="fw-extrabold text-primary fs-6"><?= format_rupiah($row["harga"]) ?></td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a class="btn btn-sm btn-soft-primary btn-action-icon" title="Ubah Tarif / Ikon" href="<?= route_url('tiket_update', ['id' => $row['id_tiket']]) ?>">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-soft-danger btn-action-icon" title="Hapus Tiket" onclick="confirmDelete(<?= (int)$row['id_tiket'] ?>, '<?= e(addslashes($row['nama_tiket'])) ?>')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            Belum ada kategori tiket terdaftar.
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
setupTableSearch("tiketSearch", "tableTiket");

function confirmDelete(id, name) {
    Swal.fire({
        title: "Hapus Kategori Tiket " + name + "?",
        text: "Pastikan tiket ini tidak sedang digunakan pada transaksi aktif!",
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
            form.action = "' . route_url('tiket_delete') . '";
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
</script>
';
require '../../app/layouts/admin_footer.php';
?>
