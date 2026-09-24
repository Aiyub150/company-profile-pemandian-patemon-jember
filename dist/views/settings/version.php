<?php
/**
 * Modul Informasi Versi Sistem & Lingkungan
 * Standar Tata Kelola Pemerintahan Daerah (SIM-ASET Standard)
 */
require_once __DIR__ . '/../../app/config.php';

// Hak Akses: Semua pengguna terautentikasi (Admin & Staf)
check_auth([0, 1, 2, 3], route_url('login'));

$active_menu = 'version';
$page_title = 'Informasi Versi & Sistem - Pemandian Patemon';
$page_heading = 'Informasi Versi & Sistem';
$page_subheading = 'Spesifikasi lingkungan server, arsitektur keamanan, dan riwayat pembaruan sistem.';

// Deteksi parameter lingkungan server
$php_version    = phpversion();
$mysql_version  = $conn->server_info ?? 'MySQL / MariaDB';
$server_os      = php_uname('s') . ' (' . php_uname('r') . ')';
$server_soft    = $_SERVER['SERVER_SOFTWARE'] ?? 'PHP Built-in Web Server';
$timezone       = date_default_timezone_get();
$db_host        = $host ?? '127.0.0.1';
$db_name        = $database ?? 'pemandian';

include __DIR__ . '/../../app/layouts/admin_header.php';
?>

<div class="row g-4">
    <!-- Header Banner Sistem -->
    <div class="col-12">
        <div class="modern-card text-center p-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%); border: 1.5px solid #bae6fd;">
            <div class="mb-3">
                <img src="<?= public_url('img/icon.png') ?>" alt="Logo Patemon" style="height: 72px; width: auto; object-fit: contain;">
            </div>
            <h3 class="fw-bold mb-1" style="color: #0f172a;">Sistem Kasir & Portofolio Wisata Pemandian Patemon</h3>
            <p class="text-muted mb-3" style="max-width: 650px; margin: auto;">
                Aplikasi manajemen retribusi loket tiket terpadu, pemesanan tiket online, pelaporan akuntabilitas keuangan daerah, dan portofolio wisata alam Tanggul, Jember.
            </p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <span class="badge badge-modern-primary fs-6 px-3 py-2"><i class="fa-solid fa-code-commit me-1"></i> Versi <?= APP_VERSION ?></span>
                <span class="badge badge-modern-purple fs-6 px-3 py-2"><i class="fa-solid fa-building me-1"></i> Pemkab Jember - Disparbud</span>
            </div>
        </div>
    </div>

    <!-- Spesifikasi Lingkungan Server -->
    <div class="col-12 col-md-6">
        <div class="modern-card h-100">
            <div class="modern-card-header">
                <span class="fw-bold" style="color: #0f172a;"><i class="fa-solid fa-server text-primary me-2"></i> Lingkungan Server & Runtime</span>
            </div>
            <div class="p-3">
                <table class="table table-borderless align-middle mb-0" style="font-size: 0.885rem;">
                    <tbody>
                        <tr class="border-bottom">
                            <td class="text-muted py-2.5">Versi Runtime PHP</td>
                            <td class="text-end fw-bold text-dark py-2.5">PHP <?= $php_version ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2.5">Database Engine</td>
                            <td class="text-end fw-bold text-dark py-2.5"><?= e($mysql_version) ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2.5">Web Server Runtime</td>
                            <td class="text-end fw-bold text-dark py-2.5"><?= e($server_soft) ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2.5">Sistem Operasi Host</td>
                            <td class="text-end fw-bold text-dark py-2.5"><?= e($server_os) ?></td>
                        </tr>
                        <tr class="border-bottom">
                            <td class="text-muted py-2.5">Zona Waktu (Timezone)</td>
                            <td class="text-end fw-bold text-dark py-2.5"><?= e($timezone) ?> (WITA / +08:00)</td>
                        </tr>
                        <tr>
                            <td class="text-muted py-2.5">Koneksi Database Aktif</td>
                            <td class="text-end fw-bold text-success py-2.5"><?= e($db_name) ?> @ <?= e($db_host) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Standar Keamanan & Proteksi -->
    <div class="col-12 col-md-6">
        <div class="modern-card h-100">
            <div class="modern-card-header">
                <span class="fw-bold" style="color: #0f172a;"><i class="fa-solid fa-shield-halved text-success me-2"></i> Kepatuhan & Fitur Keamanan</span>
            </div>
            <div class="p-4">
                <div class="d-flex align-items-start gap-3 mb-3">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                        <i class="fa-solid fa-fingerprint"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">CSRF Token Protection</div>
                        <p class="text-muted small mb-0">Setiap mutasi form POST dilindungi token acak berbasis sesi dengan perbandingan string aman `hash_equals`.</p>
                    </div>
                </div>

                <div class="d-flex align-items-start gap-3 mb-3">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                        <i class="fa-solid fa-database"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">SQL Injection Mitigation</div>
                        <p class="text-muted small mb-0">Seluruh operasi pembacaan dan penyimpanan data menerapkan PDO / MySQLi Prepared Statements berparameter.</p>
                    </div>
                </div>

                <div class="d-flex align-items-start gap-3 mb-3">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                        <i class="fa-solid fa-user-lock"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">Bcrypt Password Hashing</div>
                        <p class="text-muted small mb-0">Kredensial pengguna dienkripsi dengan fungsi hash satu arah Bcrypt dengan salt dinamis.</p>
                    </div>
                </div>

                <div class="d-flex align-items-start gap-3">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #ede9fe; color: #7c3aed; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                        <i class="fa-solid fa-network-wired"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">Role-Based Access Control (RBAC)</div>
                        <p class="text-muted small mb-0">Pemisahan hak akses ketat antara Super Admin (Level 1), Admin (Level 2), Staf Kasir (Level 3), dan Pengunjung Publik (Level 0).</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Pembaruan & Changelog -->
    <div class="col-12">
        <div class="modern-card">
            <div class="modern-card-header">
                <span class="fw-bold" style="color: #0f172a;"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Riwayat Pembaruan Sistem (Changelog)</span>
            </div>
            <div class="p-4">
                <div class="border-start border-3 border-primary ps-3 mb-4">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge badge-modern-primary fw-bold">v2.0.0</span>
                        <span class="text-muted small">September 2026</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Pembaruan Besar Standar Tata Kelola Pemda Jember</h5>
                    <ul class="text-muted small mb-0" style="line-height: 1.7;">
                        <li>Pembersihan autoloader Composer dan integrasi pustaka <code>dompdf/dompdf</code> & <code>picqer/php-barcode-generator</code>.</li>
                        <li>Implementasi Clean Routing Front Controller (URL bersih tanpa ekstensi <code>.php</code>).</li>
                        <li>Arsitektur Master Layout terpadu untuk efisiensi kode dan konsistensi UI.</li>
                        <li>Pemisahan metode pembayaran Scan QRIS manual statis dan Transfer Bank dengan nomor rekening resmi.</li>
                        <li>Penyediaan arsitektur modular Dynamic QRIS / Payment Gateway (siap integrasi Midtrans/Xendit).</li>
                        <li>Active tag navbar dinamis dengan IntersectionObserver.</li>
                        <li>Penambahan kolom ikon dinamis pada kategori tiket (Lansia, Dewasa, Anak, VIP, dll.).</li>
                        <li>Standarisasi Laporan Kedinasan format resmi Pemerintah Kabupaten Jember (Dinas Pariwisata dan Kebudayaan) dengan Halaman Pratinjau PDF sebelum cetak.</li>
                        <li>Validasi form kritik dan saran serta penyediaan pop-up modal detail ulasan bagi admin.</li>
                        <li>Multi-field search bar (Nama, ID/Kode, Tanggal, Metode, Status) di seluruh panel kasir & admin.</li>
                        <li>Pemisahan nomor urut tabel dan format kode referensi standar <code>TRX-YYYYMMDD-XXXX</code>.</li>
                        <li>Adopsi standar SIM-ASET: Dashboard widget Libur Nasional (Kemendesa API), Profil Pengguna, Buku Panduan Pengguna, dan Informasi Versi.</li>
                    </ul>
                </div>

                <div class="border-start border-3 border-secondary ps-3 opacity-75">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-secondary text-white fw-bold">v1.0.0</span>
                        <span class="text-muted small">Mei 2023</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Rilis Awal Kasir & Portofolio</h5>
                    <p class="text-muted small mb-0">Implementasi fungsionalitas dasar landing page, kasir loket, dan pencatatan transaksi tiket.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../../app/layouts/admin_footer.php';
?>
