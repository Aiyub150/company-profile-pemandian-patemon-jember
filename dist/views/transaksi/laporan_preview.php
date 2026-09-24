<?php
/**
 * Dokumen Laporan Resmi Standar Pemkab Jember (PDF Preview Inline)
 * Sesuai Standar Tata Naskah Dinas Pemerintah Daerah
 * Feedback-2 Poin 2, 3 & 9
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Hak Akses: Super Admin (1), Admin (2), dan Staf Kasir (3)
check_auth([1, 2, 3], route_url('login'));

$user_id    = (int)($_SESSION['id_user'] ?? 0);
$user_level = (int)($_SESSION['level'] ?? 0);

// Isolasi Staf: Jika Staf, otomatis batasi hanya transaksinya sendiri
if ($user_level === 3) {
    $selected_staff = $user_id;
} else {
    $selected_staff = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
}

$tipe = $_GET['tipe'] ?? 'bulanan'; // harian, mingguan, bulanan, tahunan
$filter_date  = $_GET['date'] ?? date('Y-m-d');
$filter_start = $_GET['start'] ?? date('Y-m-d', strtotime('-6 days'));
$filter_end   = $_GET['end'] ?? date('Y-m-d');
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$filter_year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$nama_bulan_arr = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Susun Query Berdasarkan Filter
$where = ["t.status = 'done'"];
$params = [];
$types = "";

if ($tipe === 'harian') {
    $periode_label = "Tanggal: " . format_tanggal_indonesia($filter_date, true);
    $where[] = "t.tgl_pemesanan = ?";
    $params[] = $filter_date;
    $types .= "s";
} elseif ($tipe === 'mingguan') {
    $periode_label = "Rentang: " . format_tanggal_indonesia($filter_start) . " s.d. " . format_tanggal_indonesia($filter_end);
    $where[] = "t.tgl_pemesanan BETWEEN ? AND ?";
    $params[] = $filter_start;
    $params[] = $filter_end;
    $types .= "ss";
} elseif ($tipe === 'tahunan') {
    $periode_label = "Tahun Anggaran: " . $filter_year;
    $where[] = "YEAR(t.tgl_pemesanan) = ?";
    $params[] = $filter_year;
    $types .= "i";
} else {
    $tipe = 'bulanan';
    $periode_label = "Bulan: " . ($nama_bulan_arr[$filter_month] ?? '') . " " . $filter_year;
    $where[] = "MONTH(t.tgl_pemesanan) = ? AND YEAR(t.tgl_pemesanan) = ?";
    $params[] = $filter_month;
    $params[] = $filter_year;
    $types .= "ii";
}

if ($selected_staff > 0) {
    $where[] = "t.id_user = ?";
    $params[] = $selected_staff;
    $types .= "i";
}

$where_sql = implode(" AND ", $where);
$query = "SELECT t.id_transaksi, t.tgl_pemesanan, t.total_harga, t.metode_pembayaran, t.status, u.nama as nama_user
          FROM transaksi t
          LEFT JOIN users u ON t.id_user = u.id_user
          WHERE {$where_sql}
          ORDER BY t.tgl_pemesanan ASC, t.id_transaksi ASC";

$stmt = $conn->prepare($query);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ambil detail tiket untuk tiap transaksi yang ditemukan
$trans_ids = array_column($transactions, 'id_transaksi');
$ticket_details = [];
$rekap_kategori = [];

if (!empty($trans_ids)) {
    $in_clause = implode(',', array_map('intval', $trans_ids));
    $dt_res = $conn->query("SELECT id_transaksi, jenis_tiket, quantity, sub_total FROM detail_transaksi WHERE id_transaksi IN ($in_clause)");
    if ($dt_res) {
        while ($d = $dt_res->fetch_assoc()) {
            $ticket_details[$d['id_transaksi']][] = $d;
            $cat = $d['jenis_tiket'];
            if (!isset($rekap_kategori[$cat])) {
                $rekap_kategori[$cat] = ['qty' => 0, 'subtotal' => 0];
            }
            $rekap_kategori[$cat]['qty'] += (int)$d['quantity'];
            $rekap_kategori[$cat]['subtotal'] += (float)$d['sub_total'];
        }
    }
}

$total_pendapatan = array_sum(array_column($transactions, 'total_harga'));
$total_lembar_tiket = array_sum(array_column($rekap_kategori, 'qty'));

// Helper Terbilang
if (!function_exists('terbilang')) {
    function terbilang($angka) {
        $angka = abs((float)$angka);
        $baca = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        $terbilang = '';
        if ($angka < 12) {
            $terbilang = ' ' . $baca[(int)$angka];
        } elseif ($angka < 20) {
            $terbilang = terbilang($angka - 10) . ' Belas';
        } elseif ($angka < 100) {
            $terbilang = terbilang($angka / 10) . ' Puluh' . terbilang($angka % 10);
        } elseif ($angka < 200) {
            $terbilang = ' Seratus' . terbilang($angka - 100);
        } elseif ($angka < 1000) {
            $terbilang = terbilang($angka / 100) . ' Ratus' . terbilang($angka % 100);
        } elseif ($angka < 2000) {
            $terbilang = ' Seribu' . terbilang($angka - 1000);
        } elseif ($angka < 1000000) {
            $terbilang = terbilang($angka / 1000) . ' Ribu' . terbilang($angka % 1000);
        } elseif ($angka < 1000000000) {
            $terbilang = terbilang($angka / 1000000) . ' Juta' . terbilang($angka % 1000000);
        }
        return trim($terbilang);
    }
}
$terbilang_teks = $total_pendapatan > 0 ? terbilang($total_pendapatan) . " Rupiah" : "Nol Rupiah";

// Nomor Surat & Lampiran
$nomor_lampiran = "900 / " . str_pad($filter_year, 4, '0', STR_PAD_LEFT) . " / DISPARBUD / " . date('Y');

// Mulai buffer HTML
ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Retribusi Tiket - Pemkab Jember</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 10pt;
            line-height: 1.35;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .kop-surat {
            text-align: center;
            position: relative;
        }
        .kop-surat h2 {
            font-size: 13pt;
            font-weight: bold;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .kop-surat h3 {
            font-size: 12pt;
            font-weight: bold;
            margin: 2px 0;
        }
        .kop-surat h4 {
            font-size: 11pt;
            font-weight: bold;
            margin: 2px 0;
            text-transform: uppercase;
        }
        .kop-surat p {
            font-size: 8.5pt;
            margin: 2px 0;
            font-style: italic;
        }
        .kop-divider {
            border-top: 2.5px solid #000;
            border-bottom: 1px solid #000;
            height: 2px;
            margin: 8px 0 14px;
        }
        .doc-title {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .doc-nomor {
            text-align: center;
            font-size: 9pt;
            margin-bottom: 14px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 10px;
            font-size: 9.5pt;
        }
        .meta-table td {
            padding: 2px 4px;
        }
        table.gov-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9pt;
        }
        table.gov-table th, table.gov-table td {
            border: 1px solid #000;
            padding: 4px 6px;
        }
        table.gov-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }

        .ttd-container {
            margin-top: 25px;
            width: 100%;
            page-break-inside: avoid;
        }
        .ttd-table {
            width: 100%;
            border: none;
        }
        .ttd-table td {
            border: none;
            text-align: center;
            vertical-align: top;
            font-size: 9.5pt;
        }
        .ttd-space {
            height: 55px;
        }
    </style>
</head>
<body>

    <div class="kop-surat">
        <h2>PEMERINTAH KABUPATEN JEMBER</h2>
        <h3>DINAS PARIWISATA DAN KEBUDAYAAN</h3>
        <h4>UPTD KAWASAN WISATA ALAM PEMANDIAN PATEMON</h4>
        <p>Jl. Pemandian Patemon, Dusun Krajan, Desa Patemon, Kec. Tanggul, Kab. Jember, Jawa Timur 68155</p>
        <p>Laman Resmi: www.jemberkab.go.id &bull; Pos-el: disparbud@jemberkab.go.id &bull; Kode Pos: 68155</p>
        <div class="kop-divider"></div>
    </div>

    <div class="doc-title">REKAPITULASI PENERIMAAN RETRIBUSI TIKET MASUK WISATA</div>
    <div class="doc-nomor">Nomor Lampiran: <?= e($nomor_lampiran) ?></div>

    <table class="meta-table">
        <tr>
            <td style="width: 18%;"><strong>Objek Retribusi</strong></td>
            <td style="width: 2%;">:</td>
            <td style="width: 45%;">Pemandian Alam Patemon Tanggul</td>
            <td style="width: 15%;"><strong>Periode</strong></td>
            <td style="width: 2%;">:</td>
            <td style="width: 18%;"><?= strtoupper(e($tipe)) ?></td>
        </tr>
        <tr>
            <td><strong>Dasar Hukum</strong></td>
            <td>:</td>
            <td>Perda Kab. Jember tentang Retribusi Jasa Usaha</td>
            <td><strong>Waktu Data</strong></td>
            <td>:</td>
            <td><?= e($periode_label) ?></td>
        </tr>
        <tr>
            <td><strong>Pengelola</strong></td>
            <td>:</td>
            <td>UPTD Disparbud Kab. Jember</td>
            <td><strong>Petugas Kasir</strong></td>
            <td>:</td>
            <td><?= ($selected_staff > 0) ? 'Petugas #' . $selected_staff : 'Seluruh Loket' ?></td>
        </tr>
    </table>

    <div style="font-weight: bold; margin-bottom: 5px; font-size: 9.5pt;">A. RINCIAN TRANSAKSI PENJUALAN TIKET LUNAS</div>
    <table class="gov-table">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 22%;">Kode Referensi</th>
                <th style="width: 14%;">Tanggal</th>
                <th style="width: 20%;">Petugas / Pemesan</th>
                <th style="width: 24%;">Rincian Tiket Masuk</th>
                <th style="width: 16%;">Penerimaan (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($transactions)): ?>
                <?php 
                $no = 1;
                foreach ($transactions as $trx): 
                    $t_id = $trx['id_transaksi'];
                    $detail_str_arr = [];
                    if (!empty($ticket_details[$t_id])) {
                        foreach ($ticket_details[$t_id] as $td) {
                            $detail_str_arr[] = $td['jenis_tiket'] . ' (' . $td['quantity'] . 'x)';
                        }
                    }
                    $detail_text = !empty($detail_str_arr) ? implode(', ', $detail_str_arr) : 'Tiket Masuk';
                    $nama_pel = $trx['nama_user'] ?: 'Pengunjung Loket';
                ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td class="text-center font-monospace" style="font-size: 8pt;"><?= format_kode_transaksi($trx['id_transaksi'], $trx['tgl_pemesanan']) ?></td>
                        <td class="text-center"><?= date('d/m/Y', strtotime($trx['tgl_pemesanan'])) ?></td>
                        <td><?= e($nama_pel) ?></td>
                        <td style="font-size: 8pt;"><?= e($detail_text) ?></td>
                        <td class="text-end fw-bold"><?= number_format($trx['total_harga'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center" style="padding: 15px; font-style: italic;">
                        Tidak terdapat data transaksi lunas pada periode ini.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #f1f5f9;">
                <th colspan="5" class="text-end fw-bold">TOTAL PENERIMAAN RETRIBUSI (Rp)</th>
                <th class="text-end fw-bold"><?= number_format($total_pendapatan, 0, ',', '.') ?></th>
            </tr>
        </tfoot>
    </table>

    <div style="font-weight: bold; margin-bottom: 5px; margin-top: 10px; font-size: 9.5pt;">B. REKAPITULASI BERDASARKAN KATEGORI TIKET</div>
    <table class="gov-table" style="width: 80%;">
        <thead>
            <tr>
                <th style="width: 8%;">No</th>
                <th style="width: 42%;">Kategori Tiket Masuk</th>
                <th style="width: 25%;">Total Tiket Terjual</th>
                <th style="width: 25%;">Akumulasi Retribusi (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $c_no = 1;
            foreach ($rekap_kategori as $kat => $val): 
            ?>
                <tr>
                    <td class="text-center"><?= $c_no++ ?></td>
                    <td>Tiket <?= e($kat) ?></td>
                    <td class="text-center"><?= number_format($val['qty'], 0, ',', '.') ?> Lembar</td>
                    <td class="text-end"><?= number_format($val['subtotal'], 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rekap_kategori)): ?>
                <tr>
                    <td colspan="4" class="text-center">- Belum ada data tiket -</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #f1f5f9;">
                <th colspan="2" class="text-end fw-bold">JUMLAH KESELURUHAN</th>
                <th class="text-center fw-bold"><?= number_format($total_lembar_tiket, 0, ',', '.') ?> Lembar</th>
                <th class="text-end fw-bold"><?= number_format($total_pendapatan, 0, ',', '.') ?></th>
            </tr>
        </tfoot>
    </table>

    <div style="font-size: 9pt; margin-top: 6px;">
        <em>Terbilang: <strong><?= e($terbilang_teks) ?></strong></em>
    </div>

    <!-- Tanda Tangan Kedinasan -->
    <div class="ttd-container">
        <table class="ttd-table">
            <tr>
                <td style="width: 50%;">
                    Mengetahui,<br><strong>Kepala UPTD Pemandian Patemon</strong><br>Dinas Pariwisata dan Kebudayaan Kab. Jember
                    <div class="ttd-space"></div>
                    <strong><u>H. SLAMET RIYADI, S.Sos., M.Si.</u></strong><br>Pembina Tingkat I<br>NIP. 19740512 199803 1 004
                </td>
                <td style="width: 50%;">
                    Tanggul, <?= format_tanggal_indonesia(date('Y-m-d')) ?><br><strong>Bendahara Penerimaan Pembantu</strong><br>UPTD Pemandian Patemon Tanggul
                    <div class="ttd-space"></div>
                    <strong><u>AHMAD FATHONI, S.E.</u></strong><br>Penata Muda Tk. I<br>NIP. 19820914 200801 1 009
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
<?php
$html = ob_get_clean();

// Jika parameter ?html=1 diberikan, tampilkan HTML langsung untuk debug
if (isset($_GET['html'])) {
    echo $html;
    exit();
}

// Render langsung sebagai PDF inline di browser (Feedback-2 Poin 2)
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Times-Roman');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "Laporan_Retribusi_Patemon_{$tipe}_" . date('Ymd') . ".pdf";
$dompdf->stream($filename, ["Attachment" => false]);
exit();
