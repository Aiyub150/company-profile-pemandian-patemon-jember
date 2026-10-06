<?php
require_once __DIR__ . '/../app/config.php';

$review_success = false;
$review_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_ulasan'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $review_error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        // Sanitasi ketat untuk mencegah serangan XSS (Cross Site Scripting)
        $raw_username = trim($_POST['username'] ?? '');
        $username     = htmlspecialchars(strip_tags($raw_username), ENT_QUOTES, 'UTF-8');
        $username     = mb_substr($username, 0, 50);

        $email_input  = trim($_POST['email'] ?? '');
        $email        = !empty($email_input) ? filter_var($email_input, FILTER_SANITIZE_EMAIL) : '';

        $no_telepon   = trim($_POST['no_telepon'] ?? '');

        $raw_ulasan   = trim($_POST['ulasan'] ?? '');
        $ulasan       = htmlspecialchars(strip_tags($raw_ulasan), ENT_QUOTES, 'UTF-8');
        $ulasan       = mb_substr($ulasan, 0, 500);
        $tgl_ulasan   = date("Y-m-d");

        // Proteksi Anti-Spam / Rate Limiting (Maksimal 1 ulasan per 2 menit per IP dan per sesi)
        $now = time();
        $ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $two_mins_ago = $now - 120;

        $last_review_session = $_SESSION['last_review_time'] ?? 0;
        if (($now - $last_review_session) < 120) {
            $sisa = 120 - ($now - $last_review_session);
            $review_error = "Anda baru saja mengirim ulasan. Mohon tunggu {$sisa} detik sebelum mengirim ulasan kembali.";
        } else {
            $stmtSpam = $conn->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE ip_address = ? AND username = 'review_spam' AND attempt_time > ?");
            $stmtSpam->bind_param("si", $ip, $two_mins_ago);
            $stmtSpam->execute();
            $recent_reviews = (int)($stmtSpam->get_result()->fetch_assoc()['cnt'] ?? 0);
            $stmtSpam->close();

            if ($recent_reviews > 0) {
                $review_error = "Terlalu banyak permintaan ulasan dari perangkat Anda. Mohon tunggu 2 menit sebelum mengirim ulasan kembali.";
            } elseif (empty($username) || empty($ulasan)) {
                $review_error = 'Nama Anda dan isi ulasan wajib diisi.';
            } elseif (mb_strlen($raw_username) > 50) {
                $review_error = 'Panjang nama pengirim maksimal 50 karakter.';
            } elseif (!preg_match("/^[a-zA-Z\s\.\']+$/", $raw_username)) {
                $review_error = 'Nama pengirim hanya boleh berisi huruf, spasi, titik, atau tanda petik.';
            } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $review_error = 'Format alamat email tidak valid.';
            } elseif (!empty($no_telepon) && !preg_match('/^0[0-9]{8,14}$/', $no_telepon)) {
                $review_error = 'Nomor telepon / WhatsApp tidak valid. Gunakan format angka diawali angka 0 (9–15 digit angka).';
            } elseif (has_toxic_words($raw_username) || has_toxic_words($raw_ulasan)) {
                $toxicHits = array_merge(find_toxic_words($raw_username), find_toxic_words($raw_ulasan));
                $review_error = 'Ulasan ditolak: Mengandung kata yang tidak pantas (' . e(implode(', ', array_unique($toxicHits))) . '). Mohon gunakan bahasa yang santun.';
                if (function_exists('log_activity')) {
                    log_activity('TOXIC_BLOCKED', 'ulasan', "Kritik & saran publik diblokir karena kata terlarang: " . implode(', ', array_unique($toxicHits)));
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO ulasan (username, email, no_telepon, ulasan, tgl_ulasan) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sssss", $username, $email, $no_telepon, $ulasan, $tgl_ulasan);
                    if ($stmt->execute()) {
                        $review_success = true;
                        $_SESSION['last_review_time'] = $now;

                        // Catat attempt untuk rate limiting IP
                        $stmtRec = $conn->prepare("INSERT INTO login_attempts (ip_address, username, attempt_time) VALUES (?, 'review_spam', ?)");
                        $stmtRec->bind_param("si", $ip, $now);
                        $stmtRec->execute();
                        $stmtRec->close();

                        if (function_exists('log_activity')) {
                            log_activity('TAMBAH', 'ulasan', "Ulasan publik baru dari {$username}");
                        }
                    } else {
                        $review_error = 'Terjadi kesalahan sistem saat menyimpan ulasan.';
                    }
                    $stmt->close();
                } else {
                    $review_error = 'Gagal menyiapkan query database.';
                }
            }
        }
    }
}

// Ambil harga tiket resmi dari database
$res_t = $conn->query("SELECT nama_tiket, harga FROM tiket");
$harga_tiket = [];
if ($res_t) {
    while ($r = $res_t->fetch_assoc()) {
        $harga_tiket[$r['nama_tiket']] = (int)$r['harga'];
    }
}
$harga_dewasa = $harga_tiket['Dewasa'] ?? 10000;
$harga_anak   = $harga_tiket['Anak-Anak'] ?? 5000;
$harga_mahasiswa = $harga_tiket['Mahasiswa'] ?? 8000;

// Ambil item galeri dari database (dengan fallback ke default)
$gallery_items_pub = [];
$g_table_exists = $conn->query("SHOW TABLES LIKE 'gallery'");
if ($g_table_exists && $g_table_exists->num_rows > 0) {
    $res_gallery = $conn->query("SELECT * FROM `gallery` ORDER BY urutan ASC, id_gallery ASC LIMIT 3");
    if ($res_gallery) {
        while ($gr = $res_gallery->fetch_assoc()) {
            $gallery_items_pub[] = $gr;
        }
    }
}
if (empty($gallery_items_pub)) {
    $gallery_items_pub = [
        ['id_gallery' => 1, 'judul' => 'Wahana Kolam & Waterpark',       'deskripsi_card' => 'Fasilitas Rekreasi Keluarga di Pemandian Patemon Tanggul',  'deskripsi_popup' => 'Pemandian Patemon menyediakan kolam renang bertingkat serta wahana seluncuran air yang aman dan menyenangkan untuk anak-anak maupun dewasa. Air kolam di Pemandian Patemon dialirkan langsung secara alami dari sumber mata air tanpa kaporit.',     'gambar_card' => 'gambar5.png', 'gambar_popup' => 'gambar9.png'],
        ['id_gallery' => 2, 'judul' => 'Kunjungan Mantan Bupati Jember', 'deskripsi_card' => 'Peninjauan Pemandian Patemon Tanggul (Periode 2021-2025)', 'deskripsi_popup' => 'Mantan Bupati Jember, Ir. H. Hendy Siswanto, ST. IPU. (periode 2021-2025), melakukan peninjauan langsung ke Pemandian Patemon untuk mengecek kelayakan fasilitas wisata.', 'gambar_card' => 'gambar7.png', 'gambar_popup' => 'gambar8.png'],
        ['id_gallery' => 3, 'judul' => 'Mata Air Alami Argopuro',        'deskripsi_card' => 'Kejernihan Sumber Air Alami Pemandian Patemon Tanggul',   'deskripsi_popup' => 'Keistimewaan utama Pemandian Patemon adalah limpahan mata air alami dari lereng Pegunungan Argopuro yang mengalir jernih, dingin, dan murni tanpa kaporit.',                                                                                                                         'gambar_card' => 'gambar4.png', 'gambar_popup' => 'gambar4.png'],
    ];
}

// Ambil data event aktif untuk pop-up notifikasi beranda (Feedback-7 Poin 6)
$active_events = [];
$res_events = $conn->query("SELECT * FROM events WHERE is_active = 1 AND deleted_at IS NULL ORDER BY urutan ASC, created_at DESC LIMIT 5");
if ($res_events) {
    while ($ev = $res_events->fetch_assoc()) {
        $active_events[] = $ev;
    }
}

// Status Kepadatan Wahana Hari Ini (Real-time Crowd Density Indicator)
$today_visitors = 0;
$res_crowd = $conn->query("
    SELECT COALESCE(SUM(dt.quantity), 0) AS total_today 
    FROM transaksi t 
    JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi 
    WHERE t.tgl_pemesanan = CURDATE() AND t.status = 'done' AND t.deleted_at IS NULL
");
if ($res_crowd) {
    $row_c = $res_crowd->fetch_assoc();
    $today_visitors = (int)($row_c['total_today'] ?? 0);
}

if ($today_visitors < 100) {
    $crowd_level = 'Lengang';
    $crowd_badge_class = 'badge-crowd-green';
    $crowd_desc = 'Kondisi kolam sangat nyaman & leluasa untuk berenang.';
    $crowd_icon = 'fa-circle-check';
} elseif ($today_visitors <= 350) {
    $crowd_level = 'Ramai Lancar';
    $crowd_badge_class = 'badge-crowd-yellow';
    $crowd_desc = 'Aktivitas kolam sedang hangat dan kondusif.';
    $crowd_icon = 'fa-users';
} else {
    $crowd_level = 'Padat';
    $crowd_badge_class = 'badge-crowd-red';
    $crowd_desc = 'Kunjungan tinggi, disarankan memesan tiket lebih awal.';
    $crowd_icon = 'fa-triangle-exclamation';
}
?>
<!DOCTYPE html>

<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="Pemandian Patemon - Destinasi Wisata Pemandian Alami Terfavorit di Tanggul, Jember. Pemesanan tiket masuk mudah dan cepat." />
    <title>Wisata Pemandian Patemon - Jember</title>

    <link rel="icon" type="image/x-icon" href="../../public/img/icon.png" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="../../public/css/styles.css" />
    <link rel="stylesheet" href="../../public/css/modern-theme.css" />
    <script src="<?= public_url('js/patemon-i18n.js') ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* Event Notification Pop-up Modal (Feedback-7 Poin 6) */
        .patemon-event-overlay {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(15, 23, 42, 0.78);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .patemon-event-overlay.show {
            opacity: 1;
        }
        .patemon-event-dialog {
            width: 90vw;
            max-width: 400px;
            aspect-ratio: 9 / 16;
            max-height: 85vh;
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            background: #0f172a;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 255, 255, 0.1);
            transform: scale(0.92);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .patemon-event-overlay.show .patemon-event-dialog {
            transform: scale(1);
        }
        .patemon-event-slider {
            width: 100%;
            height: 100%;
            position: relative;
        }
        .patemon-event-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.35s ease, visibility 0.35s ease;
            overflow: hidden;
        }
        .patemon-event-slide.active {
            opacity: 1;
            visibility: visible;
        }
        .patemon-event-backdrop {
            position: absolute;
            inset: -15px;
            background-size: cover;
            background-position: center;
            filter: blur(14px) brightness(0.65);
            transform: scale(1.1);
        }
        .patemon-event-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            z-index: 2;
        }
        .patemon-event-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.2s ease;
            backdrop-filter: blur(6px);
        }
        .patemon-event-nav-btn:hover {
            background: rgba(2, 132, 199, 0.9);
            border-color: #38bdf8;
            transform: translateY(-50%) scale(1.08);
        }
        .patemon-event-nav-prev {
            left: 12px;
        }
        .patemon-event-nav-next {
            right: 12px;
        }
        .patemon-event-close-btn {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 20;
            transition: all 0.2s ease;
            backdrop-filter: blur(6px);
        }
        .patemon-event-close-btn:hover {
            background: #ef4444;
            border-color: #ef4444;
            transform: rotate(90deg) scale(1.08);
        }
        .patemon-event-dots {
            position: absolute;
            bottom: 14px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 15;
            background: rgba(15, 23, 42, 0.5);
            padding: 6px 12px;
            border-radius: 9999px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .patemon-event-dot {
            width: 8px;
            height: 8px;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .patemon-event-dot.active {
            width: 22px;
            background: #38bdf8;
        }

        /* Modern Navbar Styling */
        #mainNav {
            background-color: rgba(15, 23, 42, 0.94) !important;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            transition: all 0.3s ease;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
        }
        #mainNav.navbar-shrink {
            padding: 0.4rem 0;
            background-color: rgba(15, 23, 42, 0.98) !important;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.35);
        }
        #mainNav .navbar-brand {
            margin-right: 0.75rem !important;
            max-width: 250px;
        }
        #mainNav .navbar-brand img {
            height: 36px;
        }
        #mainNav .navbar-nav .nav-item {
            margin-right: 0.15rem !important;
        }
        #mainNav .navbar-nav .nav-item:last-child {
            margin-right: 0 !important;
        }
        #mainNav .nav-link {
            font-weight: 600;
            color: #cbd5e1 !important;
            padding: 0.35rem 0.5rem !important;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.785rem !important;
            letter-spacing: 0.3px;
            white-space: nowrap !important;
            display: inline-block;
        }
        #mainNav .nav-link:hover, #mainNav .nav-link.active {
            color: #38bdf8 !important;
            background-color: rgba(56, 189, 248, 0.12);
        }

        /* Action Buttons in Navbar */
        .btn-pesan-nav {
            background: linear-gradient(135deg, #ea580c, #c2410c);
            border: 1px solid rgba(251, 146, 60, 0.4);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.785rem;
            letter-spacing: 0.3px;
            padding: 0.38rem 0.75rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 10px rgba(234, 88, 12, 0.35);
            white-space: nowrap !important;
            display: inline-flex;
            align-items: center;
            text-transform: uppercase;
        }
        .btn-pesan-nav:hover {
            background: linear-gradient(135deg, #c2410c, #9a3412);
            border-color: #fdba74;
            box-shadow: 0 4px 14px rgba(234, 88, 12, 0.55);
            transform: translateY(-1px);
            color: #ffffff !important;
        }

        /* Distinct Unmistakable Login Button */
        .btn-login-nav {
            background: linear-gradient(135deg, #0284c7, #0369a1);
            border: 1px solid rgba(56, 189, 248, 0.45);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.785rem;
            letter-spacing: 0.3px;
            padding: 0.38rem 0.85rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 10px rgba(2, 132, 199, 0.35);
            white-space: nowrap !important;
            display: inline-flex;
            align-items: center;
            text-transform: uppercase;
        }
        .btn-login-nav:hover {
            background: linear-gradient(135deg, #0369a1, #075985);
            border-color: #38bdf8;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.55);
            transform: translateY(-1px);
            color: #ffffff !important;
        }

        /* Panel Kasir & Logout Buttons */
        .btn-panel-nav {
            background: linear-gradient(135deg, #059669, #047857);
            border: 1px solid rgba(52, 211, 153, 0.4);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.8125rem;
            letter-spacing: 0.4px;
            padding: 0.42rem 0.95rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 10px rgba(5, 150, 105, 0.35);
            white-space: nowrap !important;
            display: inline-flex;
            align-items: center;
            text-transform: uppercase;
        }
        .btn-panel-nav:hover {
            background: linear-gradient(135deg, #047857, #065f46);
            border-color: #6ee7b7;
            box-shadow: 0 4px 14px rgba(5, 150, 105, 0.55);
            transform: translateY(-1px);
            color: #ffffff !important;
        }

        .btn-logout-nav {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5 !important;
            font-weight: 700;
            font-size: 0.8125rem;
            letter-spacing: 0.4px;
            padding: 0.42rem 0.85rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            white-space: nowrap !important;
            display: inline-flex;
            align-items: center;
            text-transform: uppercase;
        }
        .btn-logout-nav:hover {
            background: #ef4444;
            border-color: #dc2626;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
            transform: translateY(-1px);
        }

        @media (max-width: 1199.98px) {
            #navbarResponsive {
                background: rgba(15, 23, 42, 0.98);
                border-radius: 12px;
                padding: 1.25rem 1rem;
                margin-top: 0.75rem;
                border: 1px solid rgba(255, 255, 255, 0.1);
            }
            #mainNav .navbar-nav .nav-item {
                width: 100%;
                text-align: center;
                margin-bottom: 0.35rem;
            }
            #mainNav .navbar-nav {
                flex-wrap: wrap !important;
            }
            #mainNav .btn-pesan-nav,
            #mainNav .btn-login-nav,
            #mainNav .btn-panel-nav,
            #mainNav .btn-logout-nav {
                width: 100%;
                justify-content: center;
                margin-top: 0.4rem;
            }
        }

        /* Modern Hero Section */
        header.masthead-modern {
            padding-top: 10.5rem;
            padding-bottom: 7.5rem;
            text-align: center;
            color: #fff;
            background: linear-gradient(rgba(15, 23, 42, 0.72), rgba(2, 132, 199, 0.78)), url("../../public/img/background.png");
            background-repeat: no-repeat;
            background-attachment: scroll;
            background-position: center center;
            background-size: cover;
            position: relative;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1.25rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 1.5rem;
        }
        .hero-title {
            font-size: 3.25rem;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 1.25rem;
            text-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
        }
        .hero-subtitle {
            font-size: 1.25rem;
            color: #e2e8f0;
            max-width: 680px;
            margin: 0 auto 2.5rem;
            line-height: 1.6;
        }
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 2.5rem;
            flex-wrap: wrap;
            margin-top: 3.5rem;
        }
        .hero-stat-item {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 1.75rem;
            border-radius: 16px;
            text-align: center;
        }
        .hero-stat-num {
            font-size: 1.75rem;
            font-weight: 800;
            color: #38bdf8;
        }
        .hero-stat-label {
            font-size: 0.85rem;
            color: #e2e8f0;
            font-weight: 500;
        }

        /* Modern Service Cards */
        .service-card-modern {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            height: 100%;
        }
        .service-card-modern:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 35px -10px rgba(2, 132, 199, 0.15);
            border-color: #38bdf8;
        }
        .service-icon-wrapper {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.85rem;
        }

        /* Pricing Card */
        .pricing-card-modern {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 24px;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            position: relative;
        }
        .pricing-card-modern:hover {
            border-color: #0284c7;
            transform: translateY(-6px);
            box-shadow: 0 20px 40px -10px rgba(2, 132, 199, 0.2);
        }
        .pricing-card-modern.featured {
            border-color: #0284c7;
            background: linear-gradient(180deg, #f0f9ff 0%, #ffffff 100%);
        }
        .pricing-price {
            font-size: 2.5rem;
            font-weight: 800;
            color: #0f172a;
            margin: 1.25rem 0;
        }

        /* Contact Section */
        .pulse-indicator-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            position: relative;
        }
        .pulse-indicator-dot::after {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            border-radius: 50%;
            animation: pulse-ring 1.8s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }
        .dot-green {
            background-color: #22c55e;
            box-shadow: 0 0 10px #22c55e;
        }
        .dot-green::after {
            border: 2px solid #22c55e;
        }
        .dot-yellow {
            background-color: #f59e0b;
            box-shadow: 0 0 10px #f59e0b;
        }
        .dot-yellow::after {
            border: 2px solid #f59e0b;
        }
        .dot-red {
            background-color: #ef4444;
            box-shadow: 0 0 10px #ef4444;
        }
        .dot-red::after {
            border: 2px solid #ef4444;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.6); opacity: 0.9; }
            100% { transform: scale(1.8); opacity: 0; }
        }

        #contact {
            background: linear-gradient(rgba(15, 23, 42, 0.88), rgba(15, 23, 42, 0.95)), url("../../public/img/background.png");
            background-size: cover;
            background-position: center;
            padding: 6rem 0;
            color: #ffffff;
        }

        @media (max-width: 768px) {
            .hero-title { font-size: 2.25rem; }
            .hero-subtitle { font-size: 1.05rem; }
            .hero-stats { gap: 1rem; }
        }
    </style>
</head>
<?php 
$todayClosure = function_exists('get_active_closure_today') ? get_active_closure_today() : null;
?>
<body id="page-top" data-bs-spy="scroll" data-bs-target="#mainNav" data-bs-offset="100">

    <!-- Navigation Navbar -->
    <nav class="navbar navbar-expand-xl navbar-dark fixed-top" id="mainNav">
        <div class="container-fluid px-2 px-sm-3 px-xl-4">
            <a class="navbar-brand d-flex align-items-center gap-2 flex-shrink-0" href="#page-top">
                <img src="../../public/img/icon.png" alt="Logo Pemandian Patemon" style="height: 36px; width: auto; object-fit: contain;" />
                <div class="d-flex flex-column text-start">
                    <span class="fw-extrabold text-white lh-1" style="font-size: 0.95rem; letter-spacing: 0.5px; white-space: nowrap;">PEMANDIAN PATEMON</span>
                    <span class="text-warning small text-uppercase fw-semibold" style="font-size: 0.58rem; letter-spacing: 1px; white-space: nowrap;">Wisata Alam Tanggul &bull; Jember</span>
                </div>
            </a>
            <button class="navbar-toggler border-0 p-2 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars fs-5"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarResponsive">
                <ul class="navbar-nav text-uppercase ms-auto py-2 py-xl-0 align-items-center flex-nowrap">
                    <li class="nav-item"><a class="nav-link" href="#services" data-i18n="nav_facilities">Fasilitas</a></li>
                    <li class="nav-item"><a class="nav-link" href="#portfolio" data-i18n="nav_gallery">Galeri</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing" data-i18n="nav_pricing">Tarif Tiket</a></li>
                    <li class="nav-item"><a class="nav-link" href="#lokasi" data-i18n="nav_location">Lokasi & Peta</a></li>
                    <li class="nav-item"><a class="nav-link" href="#kontak-info" data-i18n="nav_contact">Kontak Kami</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact" data-i18n="nav_feedback">Kritik & Saran</a></li>
                    
                    <li class="nav-item ms-lg-2 my-1 my-lg-0">
                        <button type="button" class="btn-lang-switcher" onclick="togglePatemonLanguage()" title="Beralih Bahasa / Switch Language" style="height: 34px; padding: 0.25rem 0.65rem; border: 1px solid rgba(255,255,255,0.25); background: rgba(255,255,255,0.12); color: #fff;">
                            <svg class="flag-icon-svg" viewBox="0 0 640 480" width="18" height="13" style="border-radius:2px; vertical-align:middle; display:inline-block; box-shadow:0 0 1px rgba(0,0,0,0.5); margin-right:4px;"><g fill-rule="evenodd" stroke-width="1pt"><path fill="#e70011" d="M0 0h640v240H0z"/><path fill="#ffffff" d="M0 240h640v240H0z"/></g></svg><strong>ID</strong>
                        </button>
                    </li>

                    <li class="nav-item ms-lg-2 my-1 my-lg-0">
                        <?php if ($todayClosure): ?>
                            <button type="button" class="btn btn-pesan-nav text-white opacity-75" onclick="showClosedNotice()" title="Pemandian Tutup Pemeliharaan Hari Ini" data-no-i18n="true">
                                <i class="fa-solid fa-ban me-1"></i> <span data-no-i18n="true">Tutup</span>
                            </button>
                        <?php else: ?>
                            <a class="btn btn-pesan-nav text-white" href="<?= route_url('tiket_pesan') ?>">
                                <i class="fa-solid fa-ticket me-1"></i> <span>Pesan Tiket</span>
                            </a>
                        <?php endif; ?>
                    </li>
                    <?php if (isset($_SESSION['id_user'])): ?>
                        <?php if (isset($_SESSION['level']) && in_array((int)$_SESSION['level'], [1, 2, 3])): ?>
                            <li class="nav-item ms-lg-2 my-1 my-lg-0">
                                <a class="btn btn-panel-nav text-white" href="<?= route_url('dashboard') ?>">
                                    <i class="fa-solid fa-gauge me-1"></i> Dashboard
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item ms-lg-2 my-1 my-lg-0">
                            <a class="btn btn-logout-nav" href="<?= route_url('logout') ?>" title="Keluar dari Akun">
                                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-2 my-1 my-lg-0">
                            <a class="btn btn-login-nav text-white" href="<?= route_url('login') ?>" title="Masuk ke Akun / Petugas">
                                <i class="fa-solid fa-right-to-bracket me-1"></i> Login
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Masthead Hero -->
    <header class="masthead-modern">
        <div class="container">
            <?php 
            if ($todayClosure): 
            ?>
            <div class="alert border-0 shadow-lg mb-4 text-start mx-auto p-3" style="max-width: 820px; border-radius: 14px; background: rgba(254, 242, 242, 0.96); border-left: 5px solid #ef4444 !important; backdrop-filter: blur(8px);">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle p-2 d-flex align-items-center justify-content-center text-danger" style="width: 44px; height: 44px; background: rgba(239, 68, 68, 0.15); flex-shrink: 0;">
                        <i class="fa-solid fa-triangle-exclamation fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-danger text-uppercase" style="font-size: 0.825rem; letter-spacing: 0.5px;">Pemberitahuan Operasional Wisata Hari Ini (<?= format_tanggal_indonesia(date('Y-m-d')) ?>)</div>
                        <div class="fw-bold text-dark mb-1" style="font-size: 1.05rem;">
                            <?= ($todayClosure['tipe'] === 'tutup_pemeliharaan') ? 'Wisata Sedang Tutup Pemeliharaan' : 'Wisata Sedang Libur / Tutup Operasional' ?>: <?= e($todayClosure['keterangan']) ?>
                        </div>
                        <small class="text-muted">Operasional loket dan pemandian ditutup sementara sesuai jadwal resmi pengelola. Harap jadwalkan kunjungan Anda pada hari berikutnya.</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="hero-badge">
                <i class="fa-solid fa-water"></i> Wisata Pemandian Alami Terfavorit di Jember
            </div>
            <h1 class="hero-title">Pengalaman Pemandian Alami Yang Segar & Menenangkan</h1>
            <p class="hero-subtitle">
                Rasakan kejernihan mata air pegunungan alami yang dingin dan menyejukkan. Destinasi rekreasi sempurna untuk kebersamaan keluarga dan sahabat.
            </p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <?php if ($todayClosure): ?>
                    <button type="button" class="btn btn-secondary btn-lg px-4 py-3 opacity-75" onclick="showClosedNotice()">
                        <i class="fa-solid fa-ban me-2"></i> Tiket Tidak Tersedia (Pemeliharaan)
                    </button>
                <?php else: ?>
                    <a class="btn btn-accent btn-lg px-4 py-3" href="<?= route_url('tiket_pesan') ?>">
                        <i class="fa-solid fa-ticket me-2"></i> <span data-i18n="hero_btn_book">Pesan Tiket Sekarang</span>
                    </a>
                <?php endif; ?>
                <a class="btn btn-outline-light btn-lg px-4 py-3" href="#services">
                    <i class="fa-solid fa-compass me-2"></i> Jelajahi Fasilitas
                </a>
            </div>

            <!-- Stats Ribbon -->
            <div class="hero-stats">
                <div class="hero-stat-item">
                    <div class="hero-stat-num">3+</div>
                    <div class="hero-stat-label">Tingkat Kedalaman Kolam</div>
                </div>
                <div class="hero-stat-item">
                    <div class="hero-stat-num">100%</div>
                    <div class="hero-stat-label">Sumber Air Alami Pegunungan</div>
                </div>
                <div class="hero-stat-item">
                    <div class="hero-stat-num">10.000+</div>
                    <div class="hero-stat-label">Pengunjung Puas Per Tahun</div>
                </div>
                <div class="hero-stat-item" style="border-left: 2px dashed rgba(255,255,255,0.25);">
                    <?php if ($todayClosure): ?>
                        <div class="hero-stat-num d-flex align-items-center justify-content-center gap-2">
                            <span class="pulse-indicator-dot dot-red"></span>
                            <span style="font-size: 1.25rem; font-weight: 800; color: #fca5a5;">Tutup</span>
                        </div>
                        <div class="hero-stat-label text-warning fw-semibold">
                            <i class="fa-solid fa-wrench me-1"></i> Pemeliharaan Kolam
                        </div>
                    <?php else: ?>
                        <div class="hero-stat-num d-flex align-items-center justify-content-center gap-2">
                            <span class="pulse-indicator-dot <?= ($crowd_level === 'Lengang') ? 'dot-green' : (($crowd_level === 'Ramai Lancar') ? 'dot-yellow' : 'dot-red') ?>"></span>
                            <span style="font-size: 1.35rem; font-weight: 800;"><?= e($crowd_level) ?></span>
                        </div>
                        <div class="hero-stat-label">Status Kolam Hari Ini (<?= $today_visitors ?> Tiket)</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Services / Fasilitas -->
    <section class="page-section py-5 my-5" id="services">
        <div class="container">
            <div class="text-center mb-5">
                <span class="badge badge-modern-primary mb-2 text-uppercase">Fasilitas Utama</span>
                <h2 class="section-heading fw-extrabold" style="font-size: 2.25rem;">Layanan & Kenyamanan Pengunjung</h2>
                <p class="text-muted" style="max-width: 600px; margin: auto;">Kami memastikan setiap momen liburan Anda aman, bersih, dan berkesan.</p>
            </div>
            <div class="row g-4 text-center">
                <div class="col-md-4">
                    <div class="service-card-modern">
                        <div class="service-icon-wrapper" style="background: #e0f2fe; color: #0284c7;">
                            <i class="fa-solid fa-water-ladder"></i>
                        </div>
                        <h4 class="fw-bold mb-3 text-dark">Kolam Renang Alami</h4>
                        <p class="text-muted mb-0">Tersedia beberapa tingkatan kolam untuk dewasa, remaja, hingga anak-anak dengan sirkulasi mata air alami yang selalu jernih dan higienis.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card-modern">
                        <div class="service-icon-wrapper" style="background: #fef3c7; color: #d97706;">
                            <i class="fa-solid fa-utensils"></i>
                        </div>
                        <h4 class="fw-bold mb-3 text-dark">Warung Kuliner Asri</h4>
                        <p class="text-muted mb-0">Nikmati kelezatan aneka sajian kuliner khas, camilan hangat, dan minuman segar di area santai yang rindang setelah puas berenang.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card-modern">
                        <div class="service-icon-wrapper" style="background: #d1fae5; color: #059669;">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <h4 class="fw-bold mb-3 text-dark">Keamanan & Kebersihan</h4>
                        <p class="text-muted mb-0">Dilengkapi pos pengawas keselamatan (lifeguard), loker penitipan barang, serta ruang bilas dan toilet yang terawat demi kenyamanan Anda.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery / Portfolio Grid -->
    <section class="page-section bg-light py-5" id="portfolio">
        <div class="container">
            <div class="text-center mb-5">
                <span class="badge badge-modern-primary mb-2 text-uppercase">Galeri Wisata</span>
                <h2 class="section-heading fw-extrabold" style="font-size: 2.25rem; color: #0f172a;">Pesona Wisata Pemandian Patemon</h2>
                <p class="text-muted" style="max-width: 650px; margin: auto;">Dokumentasi fasilitas terkini, panorama mata air pegunungan alami, serta sejarah peninjauan destinasi.</p>
            </div>
            <div class="row g-4">
                <?php
                $modal_ids = ['portfolioModal4', 'portfolioModal5', 'portfolioModal6'];
                foreach ($gallery_items_pub as $idx => $gi):
                    $modal_id  = $modal_ids[$idx] ?? ('portfolioModal' . ($idx + 4));
                    $img_card  = e($gi['gambar_card']);
                    $img_popup = e($gi['gambar_popup']);
                    $judul     = e($gi['judul']);
                    $desc_card = e($gi['deskripsi_card'] ?? '');
                ?>
                <div class="col-lg-4 col-sm-6">
                    <div class="portfolio-item modern-card overflow-hidden h-100">
                        <a class="portfolio-link d-block position-relative" data-bs-toggle="modal" href="#<?= $modal_id ?>">
                            <img class="img-fluid w-100" src="../../public/img/<?= $img_card ?>" alt="<?= $judul ?>" style="height: 240px; width: 100%; object-fit: cover; aspect-ratio: 16 / 10;" onerror="this.src='../../public/img/gambar5.png'" />
                        </a>
                        <div class="p-3 text-center">
                            <h5 class="fw-bold mb-1" style="color: #0f172a;"><?= $judul ?></h5>
                            <p class="text-muted small mb-0"><?= $desc_card ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>


    <!-- Pricing Section (Tarif Tiket Masuk) -->
    <section class="page-section py-5 my-5" id="pricing">
        <div class="container">
            <div class="text-center mb-5">
                <span class="badge badge-modern-primary mb-2 text-uppercase">Tarif Tiket Masuk</span>
                <h2 class="section-heading fw-extrabold" style="font-size: 2.25rem;">Harga Tiket Terjangkau</h2>
                <p class="text-muted" style="max-width: 600px; margin: auto;">Dapatkan akses penuh ke kolam dan seluruh fasilitas alam dengan tarif ramah kantong.</p>
            </div>

            <div class="row g-4 justify-content-center">
                <!-- Tiket Dewasa -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="pricing-card-modern featured">
                        <span class="badge badge-modern-primary mb-3">Kategori Terpopuler</span>
                        <h3 class="fw-bold text-dark mb-1">Tiket Dewasa</h3>
                        <p class="text-muted small">Untuk pengunjung usia dewasa & remaja</p>
                        <div class="pricing-price"><?= format_rupiah($harga_dewasa) ?> <small style="font-size: 1rem; color: #64748b; font-weight: 500;">/ orang</small></div>
                        <ul class="list-unstyled text-start my-4 d-flex flex-column gap-2" style="font-size: 0.95rem; color: #334155;">
                            <li><i class="fa-solid fa-circle-check text-success me-2"></i> Akses Kolam Renang Dewasa & Menengah</li>
                            <li><i class="fa-solid fa-circle-check text-success me-2"></i> Kamar Mandi & Ruang Bilas Bersih</li>
                            <li><i class="fa-solid fa-circle-check text-success me-2"></i> Area Gazebo & Tempat Duduk Teduh</li>
                            <li><i class="fa-solid fa-circle-check text-success me-2"></i> Parkir Aman & Terjaga</li>
                        </ul>
                        <?php if ($todayClosure): ?>
                            <button type="button" class="btn btn-secondary w-100 py-3 fs-6 opacity-75" onclick="showClosedNotice()">
                                <i class="fa-solid fa-ban me-1"></i> Tiket Ditutup (Pemeliharaan)
                            </button>
                        <?php else: ?>
                            <a href="<?= route_url('tiket_pesan') ?>" class="btn btn-brand w-100 py-3 fs-6">
                                <i class="fa-solid fa-cart-shopping me-1"></i> Pesan Tiket Dewasa
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Promo Mahasiswa -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="pricing-card-modern" style="border-color: #10b981;">
                        <span class="badge badge-modern-success mb-3">Khusus Mahasiswa</span>
                        <h3 class="fw-bold text-dark mb-1">Promo Mahasiswa</h3>
                        <p class="text-muted small">Khusus pelajar & mahasiswa aktif</p>
                        <div class="pricing-price" style="color: #059669;"><?= format_rupiah($harga_mahasiswa) ?> <small style="font-size: 1rem; color: #64748b; font-weight: 500;">/ orang</small></div>
                        <ul class="list-unstyled text-start my-4 d-flex flex-column gap-2" style="font-size: 0.95rem; color: #334155;">
                            <li><i class="fa-solid fa-circle-check text-success me-2"></i> Akses Seluruh Kolam Renang Alam</li>
                            <li><i class="fa-solid fa-circle-check text-success me-2"></i> Diskon Khusus Mahasiswa & Pelajar</li>
                            <li><i class="fa-solid fa-circle-check text-success me-2"></i> Wajib Tunjukkan KTM / Kartu Pelajar</li>
                            <li><i class="fa-solid fa-circle-check text-success me-2"></i> Kamar Mandi & Ruang Bilas Bersih</li>
                        </ul>
                        <?php if ($todayClosure): ?>
                            <button type="button" class="btn btn-secondary w-100 py-3 fs-6 opacity-75" onclick="showClosedNotice()">
                                <i class="fa-solid fa-ban me-1"></i> Tiket Ditutup (Pemeliharaan)
                            </button>
                        <?php else: ?>
                            <a href="<?= route_url('tiket_pesan') ?>" class="btn w-100 py-3 fs-6 text-white" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 12px; font-weight: 700; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);">
                                <i class="fa-solid fa-cart-shopping me-1"></i> Pesan Tiket Mahasiswa
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tiket Anak-Anak -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="pricing-card-modern">
                        <span class="badge badge-modern-warning mb-3">Khusus Anak-Anak</span>
                        <h3 class="fw-bold text-dark mb-1">Tiket Anak-Anak</h3>
                        <p class="text-muted small">Untuk balita dan anak-anak</p>
                        <div class="pricing-price" style="color: #d97706;"><?= format_rupiah($harga_anak) ?> <small style="font-size: 1rem; color: #64748b; font-weight: 500;">/ anak</small></div>
                        <ul class="list-unstyled text-start my-4 d-flex flex-column gap-2" style="font-size: 0.95rem; color: #334155;">
                            <li><i class="fa-solid fa-circle-check text-success me-2"></i> Akses Kolam Anak dengan Kedalaman Aman</li>
                            <li><i class="fa-solid fa-circle-check text-success me-2"></i> Air Alami Tanpa Kaporit Menyengat</li>
                            <li><i class="fa-solid fa-circle-check text-success me-2"></i> Pengawasan Lifeguard Khusus Area Anak</li>
                            <li><i class="fa-solid fa-circle-check text-success me-2"></i> Wahana Bermain Air Menyenangkan</li>
                        </ul>
                        <?php if ($todayClosure): ?>
                            <button type="button" class="btn btn-secondary w-100 py-3 fs-6 opacity-75" onclick="showClosedNotice()">
                                <i class="fa-solid fa-ban me-1"></i> Tiket Ditutup (Pemeliharaan)
                            </button>
                        <?php else: ?>
                            <a href="<?= route_url('tiket_pesan') ?>" class="btn btn-accent w-100 py-3 fs-6">
                                <i class="fa-solid fa-cart-shopping me-1"></i> Pesan Tiket Anak
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team / Pimpinan Daerah Section -->
    <section class="page-section bg-light py-5" id="team">
        <div class="container">
            <div class="text-center mb-5">
                <span class="badge badge-modern-primary mb-2 text-uppercase">Pimpinan Daerah</span>
                <h2 class="section-heading fw-extrabold" style="font-size: 2.25rem; color: #0f172a;">Pimpinan Pemerintah Kabupaten Jember</h2>
                <p class="text-muted" style="max-width: 650px; margin: auto;">Mendukung penuh pelestarian lingkungan, pengelolaan profesional, dan pengembangan destinasi wisata Pemandian Patemon.</p>
            </div>
            <div class="row g-4 justify-content-center text-center">
                <!-- Card 1: Bupati Jember -->
                <div class="col-lg-5 col-md-6">
                    <div class="p-4 bg-white rounded-4 shadow-sm border h-100 d-flex flex-column align-items-center">
                        <div class="position-relative mb-3">
                            <img class="shadow-sm" src="../../public/img/bupati.jpg" alt="Dr. H. Muhammad Fawait, S.E., M.Sc." style="width: 140px; height: 180px; object-fit: cover; object-position: top; border-radius: 18px; border: 3px solid #0284c7;" />
                            <span class="position-absolute start-50 translate-middle-x badge badge-modern-primary px-3 shadow-sm" style="white-space: nowrap; bottom: -12px !important;">
                                <i class="fa-solid fa-landmark me-1"></i> Periode 2025–2030
                            </span>
                        </div>
                        <h4 class="fw-bold mb-1 mt-3" style="font-size: 1.25rem; color: #0f172a;">Dr. H. Muhammad Fawait, S.E., M.Sc.</h4>
                        <div class="text-primary fw-bold mb-2" style="font-size: 0.95rem;">Bupati Jember</div>
                        <p class="text-muted small mb-0" style="line-height: 1.5;">Bupati Pemerintah Kabupaten Jember yang berkomitmen memajukan sektor pariwisata daerah, memberdayakan UMKM lokal, dan melestarikan potensi wisata alam kebanggaan warga Jember.</p>
                    </div>
                </div>

                <!-- Card 2: Wakil Bupati Jember -->
                <div class="col-lg-5 col-md-6">
                    <div class="p-4 bg-white rounded-4 shadow-sm border h-100 d-flex flex-column align-items-center">
                        <div class="position-relative mb-3">
                            <img class="shadow-sm" src="../../public/img/wakil_bupati.jpg" alt="Dr. H. Djoko Susanto, S.H., M.H." style="width: 140px; height: 180px; object-fit: cover; object-position: top; border-radius: 18px; border: 3px solid #0ea5e9;" />
                            <span class="position-absolute start-50 translate-middle-x badge badge-modern-primary px-3 shadow-sm" style="white-space: nowrap; bottom: -12px !important;">
                                <i class="fa-solid fa-landmark me-1"></i> Periode 2025–2030
                            </span>
                        </div>
                        <h4 class="fw-bold mb-1 mt-3" style="font-size: 1.25rem; color: #0f172a;">Dr. H. Djoko Susanto, S.H., M.H.</h4>
                        <div class="text-info fw-bold mb-2" style="font-size: 0.95rem; color: #0284c7 !important;">Wakil Bupati Jember</div>
                        <p class="text-muted small mb-0" style="line-height: 1.5;">Wakil Bupati Pemerintah Kabupaten Jember yang senantiasa mengawal peningkatan sarana prasarana wisata, mutu pelayanan publik, dan sinergi kemajuan pariwisata terpadu.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Google Maps Lokasi Pemandian Patemon (Feedback-2 Poin 11) -->
    <section class="py-5 bg-white border-top border-bottom" id="lokasi">
        <div class="container">
            <div class="text-center mb-4">
                <span class="badge badge-modern-primary mb-2 text-uppercase"><i class="fa-solid fa-map-location-dot me-1"></i> Lokasi Destinasi</span>
                <h3 class="fw-extrabold text-dark" style="font-size: 2rem;">Peta Lokasi Wisata Pemandian Patemon</h3>
                <p class="text-muted" style="max-width: 650px; margin: auto;">Temukan rute tercepat dan termudah menuju segarnya sumber mata air alami Pemandian Patemon di Tanggul, Jember.</p>
            </div>
            
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="ratio ratio-21x9" style="min-height: 380px;">
                    <iframe 
                        src="https://maps.google.com/maps?q=Pemandian+Patemon+Tanggul+Jember&t=&z=15&ie=UTF8&iwloc=&output=embed" 
                        style="border:0; width: 100%; height: 100%;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade"
                        title="Peta Lokasi Pemandian Patemon">
                    </iframe>
                </div>
                <div class="card-body bg-light p-3 px-md-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2 text-secondary small">
                        <i class="fa-solid fa-location-dot text-danger fs-5"></i>
                        <span>Desa Patemon, Kecamatan Tanggul, Kabupaten Jember, Jawa Timur 68155</span>
                    </div>
                    <div>
                        <a href="https://maps.app.goo.gl/G8KMqbT6vdZRJE8F6" target="_blank" rel="noopener noreferrer" class="btn btn-primary fw-bold px-4 py-2 rounded-pill shadow-sm">
                            <i class="fa-solid fa-diamond-turn-right me-1"></i> Buka Navigasi Rute (Google Maps)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Kontak Resmi, Wadul Gus'e, & Pemkab Jember (Feedback-2 Poin 12 & Data Resmi wadulgus.jemberkab.go.id) -->
    <section class="py-5 bg-light" id="kontak-info">
        <div class="container">
            <div class="text-center mb-5">
                <span class="badge badge-modern-warning mb-2 text-uppercase"><i class="fa-solid fa-headset me-1"></i> Saluran Informasi & Pengaduan Resmi</span>
                <h3 class="fw-extrabold text-dark" style="font-size: 2rem;">Kontak Resmi, Wadul Gus'e & Pemkab Jember</h3>
                <p class="text-muted" style="max-width: 650px; margin: auto;">Sampaikan laporan, keluhan, dan aspirasi melalui Wadul Gus’e serta kanal resmi Pemerintah Kabupaten Jember secara cepat dan transparan.</p>
            </div>

            <div class="row g-4 justify-content-center">
                <!-- Card 1: Wadul Gus'e (Layanan Pengaduan Masyarakat Jember) -->
                <div class="col-lg-5 col-md-6">
                    <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center" style="background: linear-gradient(145deg, #ffffff, #fdf2f8); border: 2px solid #fbcfe8 !important;">
                        <div class="d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 76px; height: 76px; border-radius: 22px; background: linear-gradient(135deg, #ff005c, #be123c); color: #fff; box-shadow: 0 10px 25px rgba(255, 0, 92, 0.35);">
                            <i class="fa-solid fa-bullhorn fs-1"></i>
                        </div>
                        <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                            <span class="badge" style="background: #ff005c; color: #fff; font-size: 0.75rem; letter-spacing: 0.5px;">PORTAL RESMI PEMKAB</span>
                            <span class="badge bg-white text-dark border small">Online 24 Jam</span>
                        </div>
                        <h4 class="fw-bold mb-1" style="color: #0f172a; font-size: 1.35rem;">Wadul Gus'e Jember</h4>
                        <p class="text-muted small mb-3">
                            Layanan Resmi Pengaduan Masyarakat Kabupaten Jember untuk menyampaikan laporan, keluhan, dan aspirasi warga secara cepat dan transparan.
                        </p>
                        
                        <!-- Link Web Portal Wadul Gus'e -->
                        <div class="mb-3">
                            <a href="https://wadulgus.jemberkab.go.id/" target="_blank" rel="noopener noreferrer" class="btn btn-sm w-100 fw-bold py-2 shadow-sm d-flex align-items-center justify-content-center gap-2" style="background: #ff005c; color: #ffffff; border-radius: 10px;">
                                <i class="fa-solid fa-globe"></i>
                                <span>Buka Portal: wadulgus.jemberkab.go.id</span>
                                <i class="fa-solid fa-arrow-up-right-from-square small ms-1"></i>
                            </a>
                        </div>

                        <!-- Kanal WhatsApp & Social Media Wadul Gus'e -->
                        <div class="d-flex flex-column gap-2 text-start mt-auto pt-2 border-top">
                            <a href="https://wa.me/6281130311188" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success fw-semibold d-flex align-items-center justify-content-between px-3 py-2 rounded-3 small">
                                <span><i class="fa-brands fa-whatsapp text-success fs-5 me-2"></i> WhatsApp: <strong>+62 811-3031-1188</strong></span>
                                <i class="fa-solid fa-arrow-up-right-from-square small text-muted"></i>
                            </a>
                            <div class="row g-2">
                                <div class="col-6">
                                    <a href="https://www.instagram.com/wadul.guse/" target="_blank" rel="noopener noreferrer" class="btn btn-outline-danger fw-semibold w-100 d-flex align-items-center justify-content-between px-2.5 py-1.5 rounded-3 small">
                                        <span class="text-truncate"><i class="fa-brands fa-instagram me-1"></i> @wadul.guse</span>
                                        <i class="fa-solid fa-arrow-up-right-from-square small text-muted"></i>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="https://www.tiktok.com/@wadulguse" target="_blank" rel="noopener noreferrer" class="btn btn-outline-dark fw-semibold w-100 d-flex align-items-center justify-content-between px-2.5 py-1.5 rounded-3 small">
                                        <span class="text-truncate"><i class="fa-brands fa-tiktok me-1"></i> @wadulguse</span>
                                        <i class="fa-solid fa-arrow-up-right-from-square small text-muted"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="text-muted small mt-2" style="font-size: 0.76rem;">
                                <i class="fa-solid fa-location-dot text-danger me-1"></i> <strong>Command Center:</strong> Jember Nusantara, Jl. PB Sudirman, Kec. Patrang, Kab. Jember 68118
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Kantor Pemkab Jember & Dinas Pariwisata -->
                <div class="col-lg-7 col-md-6">
                    <div class="row g-3 h-100">
                        <!-- Kantor Pemkab Jember -->
                        <div class="col-12">
                            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white border h-100">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="d-inline-flex align-items-center justify-content-center" style="width: 54px; height: 54px; border-radius: 14px; background: linear-gradient(135deg, #475569, #1e293b); color: #fff; box-shadow: 0 6px 16px rgba(30, 41, 59, 0.2); flex-shrink: 0;">
                                        <i class="fa-solid fa-landmark-dome fs-3"></i>
                                    </div>
                                    <div>
                                        <span class="badge bg-secondary text-white px-2 py-0.5 small">Pusat Pemerintahan Daerah</span>
                                        <h5 class="fw-bold text-dark mb-0">Kantor Pemerintah Kabupaten Jember</h5>
                                    </div>
                                </div>
                                <div class="small text-muted border-top pt-2">
                                    <div class="mb-1.5"><i class="fa-solid fa-location-dot text-primary me-2"></i> <strong>Alamat Kantor:</strong> Jl. Sudarman No. 1, Jemberlor, Kec. Patrang, Kab. Jember, Jawa Timur 68118</div>
                                    <div class="mb-1.5"><i class="fa-solid fa-globe text-primary me-2"></i> <strong>Situs Resmi:</strong> <a href="https://jemberkab.go.id" target="_blank" rel="noopener noreferrer" class="text-decoration-none fw-semibold">jemberkab.go.id</a></div>
                                    <div><i class="fa-solid fa-phone text-primary me-2"></i> <strong>Telepon / Call Center:</strong> (0331) 487222 / (0331) 487223</div>
                                </div>
                            </div>
                        </div>

                        <!-- Disparbud & UPT Pemandian Patemon -->
                        <div class="col-12">
                            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white border h-100">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="d-inline-flex align-items-center justify-content-center" style="width: 54px; height: 54px; border-radius: 14px; background: linear-gradient(135deg, #0284c7, #0284c7); color: #fff; box-shadow: 0 6px 16px rgba(2, 132, 199, 0.25); flex-shrink: 0;">
                                        <i class="fa-solid fa-umbrella-beach fs-3"></i>
                                    </div>
                                    <div>
                                        <span class="badge bg-primary text-white px-2 py-0.5 small">Pengelola Pariwisata & Rekreasi</span>
                                        <h5 class="fw-bold text-dark mb-0">Disparbud & UPT Wisata Patemon</h5>
                                    </div>
                                </div>
                                <div class="small text-muted border-top pt-2">
                                    <div class="mb-1.5"><i class="fa-solid fa-building text-primary me-2"></i> <strong>Kantor Disparbud:</strong> Jl. Jawa No. 58, Kec. Sumbersari, Kab. Jember, Jawa Timur 68121</div>
                                    <div class="mb-1.5"><i class="fa-solid fa-water-ladder text-primary me-2"></i> <strong>Lokasi Wisata & Loket:</strong> Desa Patemon, Kec. Tanggul, Kab. Jember 68155</div>
                                    <div><i class="fa-solid fa-clock text-primary me-2"></i> <strong>Jam Operasional:</strong> Buka Setiap Hari (07.00 - 17.00 WIB)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact / Feedback Section -->
    <section class="page-section" id="contact">
        <div class="container">
            <div class="text-center mb-5">
                <span class="badge badge-modern-warning mb-2 text-uppercase">Kritik & Saran</span>
                <h2 class="section-heading text-white fw-extrabold" style="font-size: 2.25rem;">Bantu Kami Berkembang</h2>
                <p class="text-light opacity-90" style="max-width: 600px; margin: auto;">Kesan dan pengalaman Anda sangat berharga untuk meningkatkan kualitas pelayanan Pemandian Patemon.</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-12 col-lg-8">
                    <form id="contactForm" method="post" action="<?= route_url('home') ?>#contact" class="p-4 p-md-5 rounded-4" style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(14px); border: 1px solid rgba(255, 255, 255, 0.15);">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-light small fw-semibold">Nama Anda <span class="text-danger">*</span></label>
                                <input class="form-control-modern" id="name" name="username" type="text" placeholder="Masukkan nama Anda (maks. 50 karakter)" maxlength="50" pattern="^[a-zA-Z\s\.\']+$" title="Nama hanya boleh berisi huruf, spasi, titik, atau tanda petik (maksimal 50 karakter)" required />
                                <small class="text-light-50" style="font-size: 0.72rem; color: rgba(255,255,255,0.7);">Maksimal 50 karakter.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-light small fw-semibold">Alamat Email</label>
                                <input class="form-control-modern" id="email" name="email" type="email" placeholder="nama@email.com" />
                            </div>
                            <div class="col-12">
                                <label class="form-label text-light small fw-semibold">Nomor WhatsApp / HP</label>
                                <input class="form-control-modern" name="no_telepon" id="phone" type="tel" placeholder="08xxxxxxxxxx" pattern="^0[0-9]{8,14}$" inputmode="numeric" maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')" title="Gunakan format nomor HP diawali 0 (9-15 digit angka saja)" />
                                <small class="text-light-50" style="font-size: 0.72rem; color: rgba(255,255,255,0.7);">Format nomor Indonesia diawali angka 0 (9–15 digit angka).</small>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label text-light small fw-semibold mb-0">Ulasan, Kritik & Saran <span class="text-danger">*</span></label>
                                    <span id="charCounter" class="badge bg-secondary opacity-75 small">0 / 500 Karakter</span>
                                </div>
                                <textarea class="form-control-modern" name="ulasan" id="message" maxlength="500" placeholder="Tuliskan pengalaman atau saran Anda mengenai kebersihan, kolam, dan fasilitas kami (maksimal 500 karakter)..." required style="min-height: 140px;" oninput="updateCharCount(this)"></textarea>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button class="btn btn-accent btn-lg px-5 py-3 text-uppercase fw-bold shadow" id="submitButton" name="submit_ulasan" value="1" type="submit">
                                <i class="fas fa-paper-plane me-2"></i> Kirim Ulasan Sekarang
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-4 bg-dark text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 text-lg-start mb-2 mb-lg-0 small text-muted">
                    Copyright &copy; Pemandian Patemon <?= date('Y') ?>. Hak cipta dilindungi undang-undang.
                </div>
                <div class="col-lg-6 text-lg-end small text-muted">
                    Wisata Pemandian Alam Tanggul &bull; Jember, Jawa Timur
                </div>
            </div>
        </div>
    </footer>

    <!-- Portfolio Modals (Dynamic from DB) -->
    <?php
    $modal_ids = ['portfolioModal4', 'portfolioModal5', 'portfolioModal6'];
    foreach ($gallery_items_pub as $midx => $gm):
        $mid       = $modal_ids[$midx] ?? ('portfolioModal' . ($midx + 4));
        $gm_judul  = e($gm['judul']);
        $gm_dcard  = e($gm['deskripsi_card'] ?? '');
        $gm_dpopup = e($gm['deskripsi_popup'] ?? $gm['deskripsi_card'] ?? '');
        $gm_card   = e($gm['gambar_card']);
        $gm_popup  = e($gm['gambar_popup']);
    ?>
    <div class="modal fade" id="<?= $mid ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
            <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
                <div class="modal-header border-0 pb-0 pt-3 px-4 d-flex justify-content-between align-items-center">
                    <span class="badge badge-modern-primary"><?= strtoupper($gm_judul) ?></span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center px-4 py-3">
                    <h4 class="fw-bold mb-1" style="color: #0f172a;"><?= $gm_judul ?></h4>
                    <p class="text-muted small mb-3"><?= $gm_dcard ?></p>
                    <img class="img-fluid d-block mx-auto rounded-3 mb-3 shadow-sm" src="../../public/img/<?= $gm_popup ?>" alt="<?= $gm_judul ?>" style="max-height: 230px; width: 100%; object-fit: cover; aspect-ratio: 16 / 9;" onerror="this.src='../../public/img/<?= $gm_card ?>'" />
                    <p class="text-muted small mb-3" style="line-height: 1.6;"><?= $gm_dpopup ?></p>
                    <button class="btn btn-secondary btn-sm px-4 py-2" data-bs-dismiss="modal" type="button"><i class="fas fa-xmark me-1"></i> Tutup</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($active_events)): ?>
    <!-- Event Notification Pop-up Modal (Feedback-7 Poin 6) -->
    <div id="patemonEventOverlay" class="patemon-event-overlay" role="dialog" aria-modal="true" aria-label="Notifikasi Acara Pemandian Patemon">
        <div class="patemon-event-dialog">
            <!-- Close Button -->
            <button type="button" class="patemon-event-close-btn" id="patemonEventCloseBtn" aria-label="Tutup Notifikasi Acara" title="Tutup (Esc)">
                <i class="fa-solid fa-xmark fs-5"></i>
            </button>

            <!-- Slider Container -->
            <div class="patemon-event-slider" id="patemonEventSlider">
                <?php foreach ($active_events as $idx => $evItem): ?>
                    <?php 
                    $evtImgUrl = public_url('img/events/' . $evItem['gambar']);
                    ?>
                    <div class="patemon-event-slide <?= ($idx === 0) ? 'active' : '' ?>" data-slide-index="<?= $idx ?>">
                        <!-- Blurred Backdrop for non-9:16 images -->
                        <div class="patemon-event-backdrop" style="background-image: url('<?= e($evtImgUrl) ?>');"></div>
                        <!-- Main Flyer Image (contained, non-distorted) -->
                        <img src="<?= e($evtImgUrl) ?>" alt="<?= e($evItem['judul']) ?>" class="patemon-event-img" loading="lazy">
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($active_events) > 1): ?>
                <!-- Left / Right Slide Navigation Buttons -->
                <button type="button" class="patemon-event-nav-btn patemon-event-nav-prev" id="patemonEventPrevBtn" aria-label="Event Sebelumnya" title="Sebelumnya">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button type="button" class="patemon-event-nav-btn patemon-event-nav-next" id="patemonEventNextBtn" aria-label="Event Selanjutnya" title="Selanjutnya">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>

                <!-- Indicator Dots -->
                <div class="patemon-event-dots" id="patemonEventDots">
                    <?php foreach ($active_events as $idx => $evItem): ?>
                        <span class="patemon-event-dot <?= ($idx === 0) ? 'active' : '' ?>" data-slide-target="<?= $idx ?>" title="<?= e($evItem['judul']) ?>"></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function() {
        const overlay = document.getElementById('patemonEventOverlay');
        if (!overlay) return;

        const slides = overlay.querySelectorAll('.patemon-event-slide');
        const dots = overlay.querySelectorAll('.patemon-event-dot');
        const btnClose = document.getElementById('patemonEventCloseBtn');
        const btnPrev = document.getElementById('patemonEventPrevBtn');
        const btnNext = document.getElementById('patemonEventNextBtn');
        let currentSlide = 0;
        const totalSlides = slides.length;

        function showSlide(index) {
            if (index < 0) index = totalSlides - 1;
            if (index >= totalSlides) index = 0;
            currentSlide = index;

            slides.forEach((s, i) => {
                if (i === currentSlide) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });

            dots.forEach((d, i) => {
                if (i === currentSlide) {
                    d.classList.add('active');
                } else {
                    d.classList.remove('active');
                }
            });
        }

        if (btnPrev) {
            btnPrev.addEventListener('click', (e) => {
                e.stopPropagation();
                showSlide(currentSlide - 1);
            });
        }

        if (btnNext) {
            btnNext.addEventListener('click', (e) => {
                e.stopPropagation();
                showSlide(currentSlide + 1);
            });
        }

        dots.forEach((dot, idx) => {
            dot.addEventListener('click', (e) => {
                e.stopPropagation();
                showSlide(idx);
            });
        });

        function closeEventPopup() {
            overlay.classList.remove('show');
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 300);
            sessionStorage.setItem('patemon_event_popup_seen', 'true');
        }

        if (btnClose) {
            btnClose.addEventListener('click', closeEventPopup);
        }

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeEventPopup();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && overlay.classList.contains('show')) {
                closeEventPopup();
            }
            if (overlay.classList.contains('show')) {
                if (e.key === 'ArrowLeft') showSlide(currentSlide - 1);
                if (e.key === 'ArrowRight') showSlide(currentSlide + 1);
            }
        });

        // First-visit check per session (like ikn.go.id)
        if (!sessionStorage.getItem('patemon_event_popup_seen')) {
            setTimeout(() => {
                overlay.style.display = 'flex';
                requestAnimationFrame(() => {
                    overlay.classList.add('show');
                });
            }, 800);
        }
    })();
    </script>
    <?php endif; ?>


    <!-- Bootstrap core JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/scripts.js"></script>

    <script>
    // Live Character Counter for Feedback Form
    function updateCharCount(el) {
        const currentLength = el.value.length;
        const max = 500;
        const counter = document.getElementById('charCounter');
        if (counter) {
            const isEn = ((localStorage.getItem('patemon_lang') || 'id') === 'en');
            const unit = isEn ? 'Characters' : 'Karakter';
            counter.textContent = `${currentLength} / ${max} ${unit}`;
            if (currentLength >= max) {
                counter.className = 'badge bg-danger small';
            } else if (currentLength >= 400) {
                counter.className = 'badge bg-warning text-dark small';
            } else {
                counter.className = 'badge bg-secondary opacity-75 small';
            }
        }
    }

    // Dynamic Navbar Active State on Scroll (IntersectionObserver fallback)
    document.addEventListener('DOMContentLoaded', () => {
        const sections = document.querySelectorAll('section[id], header[id]');
        const navLinks = document.querySelectorAll('#mainNav .nav-link[href^="#"]');

        function updateActiveNav() {
            let currentId = '';
            const scrollPos = window.scrollY + 140;

            sections.forEach(section => {
                const top = section.offsetTop;
                const height = section.offsetHeight;
                if (scrollPos >= top && scrollPos < top + height) {
                    currentId = section.getAttribute('id');
                }
            });

            if (currentId) {
                navLinks.forEach(link => {
                    const href = link.getAttribute('href').replace('#', '');
                    if (href === currentId) {
                        link.classList.add('active');
                    } else {
                        link.classList.remove('active');
                    }
                });
            }
        }

        window.addEventListener('scroll', updateActiveNav, { passive: true });
        updateActiveNav();
    });
    </script>

    <?php if ($todayClosure): ?>
    <script>
    function showClosedNotice() {
        Swal.fire({
            icon: 'info',
            title: 'Loket & Kolam Tutup Sementara',
            html: `
                <p class="text-muted mb-2">Mohon maaf, operasional pemandian dan loket tiket hari ini ditutup sementara sehubungan dengan:</p>
                <div class="p-3 rounded-3 bg-light border border-danger-subtle text-danger fw-bold my-3">
                    <i class="fa-solid fa-wrench me-1"></i> <?= e($todayClosure['keterangan'] ?? 'Pemeliharaan Fasilitas Kolam') ?>
                </div>
                <p class="small text-secondary mb-0">Tiket masuk tidak dapat dipesan untuk kunjungan hari ini. Anda dapat menjadwalkan kunjungan Anda kembali di hari berikutnya. Terima kasih atas pengertiannya!</p>
            `,
            confirmButtonText: 'Saya Mengerti',
            confirmButtonColor: '#0284c7',
            customClass: {
                popup: 'rounded-4'
            }
        });
    }
    </script>
    <?php endif; ?>

    <?php if ($review_success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Terima Kasih!',
            text: 'Kritik dan saran Anda telah berhasil kami terima.',
            confirmButtonColor: '#0284c7'
        });
    </script>
    <?php elseif (!empty($review_error)): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Gagal Mengirim Ulasan',
            text: '<?= e($review_error) ?>',
            confirmButtonColor: '#ef4444'
        });
    </script>
    <?php endif; ?>
</body>
</html>
