<?php
require_once __DIR__ . '/../app/config.php';

$review_success = false;
$review_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_ulasan'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $review_error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $username   = trim($_POST['username'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $no_telepon = trim($_POST['no_telepon'] ?? '');
        $ulasan     = trim($_POST['ulasan'] ?? '');
        $tgl_ulasan = date("Y-m-d");

        if (empty($username) || empty($ulasan)) {
            $review_error = 'Nama dan ulasan wajib diisi.';
        } else {
            $stmt = $conn->prepare("INSERT INTO ulasan (username, email, no_telepon, ulasan, tgl_ulasan) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssss", $username, $email, $no_telepon, $ulasan, $tgl_ulasan);
                if ($stmt->execute()) {
                    $review_success = true;
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* Modern Navbar Styling */
        #mainNav {
            background-color: rgba(15, 23, 42, 0.94) !important;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            transition: all 0.3s ease;
            padding: 0.65rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
        }
        #mainNav.navbar-shrink {
            padding: 0.5rem 0;
            background-color: rgba(15, 23, 42, 0.98) !important;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.35);
        }
        #mainNav .navbar-brand img {
            height: 38px;
        }
        #mainNav .navbar-nav .nav-item {
            margin-right: 0.2rem !important;
        }
        #mainNav .navbar-nav .nav-item:last-child {
            margin-right: 0 !important;
        }
        #mainNav .nav-link {
            font-weight: 600;
            color: #cbd5e1 !important;
            padding: 0.38rem 0.65rem !important;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.8125rem !important;
            letter-spacing: 0.35px;
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
            font-size: 0.8125rem;
            letter-spacing: 0.4px;
            padding: 0.42rem 0.95rem;
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
            font-size: 0.8125rem;
            letter-spacing: 0.5px;
            padding: 0.42rem 1.05rem;
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

        @media (max-width: 991.98px) {
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
<body id="page-top">

    <!-- Navigation Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
        <div class="container-fluid px-3 px-md-4 px-xl-5">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#page-top">
                <img src="../../public/img/icon.png" alt="Logo Pemandian Patemon" style="height: 38px; width: auto; object-fit: contain;" />
                <div class="d-none d-sm-flex flex-column text-start">
                    <span class="fw-extrabold text-white lh-1" style="font-size: 1.05rem; letter-spacing: 0.5px;">PEMANDIAN PATEMON</span>
                    <span class="text-warning small text-uppercase fw-semibold" style="font-size: 0.62rem; letter-spacing: 1.2px;">Wisata Alam Tanggul &bull; Jember</span>
                </div>
            </a>
            <button class="navbar-toggler border-0 p-2 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars fs-5"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarResponsive">
                <ul class="navbar-nav text-uppercase ms-auto py-3 py-lg-0 align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#services">Fasilitas</a></li>
                    <li class="nav-item"><a class="nav-link" href="#portfolio">Galeri</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">Tarif Tiket</a></li>
                    <li class="nav-item"><a class="nav-link" href="#team">Pimpinan Daerah</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Kritik & Saran</a></li>
                    
                    <li class="nav-item ms-lg-2 my-1 my-lg-0">
                        <a class="btn btn-pesan-nav text-white" href="tiket/pesan.php">
                            <i class="fa-solid fa-ticket me-1"></i> Pesan Tiket
                        </a>
                    </li>
                    <?php if (isset($_SESSION['id_user'])): ?>
                        <?php if (isset($_SESSION['level']) && ($_SESSION['level'] == '1' || $_SESSION['level'] == '2')): ?>
                            <li class="nav-item ms-lg-2 my-1 my-lg-0">
                                <a class="btn btn-panel-nav text-white" href="<?= ($_SESSION['level'] == 1) ? 'dashboard/dashboard.php' : 'transaksi/staf.php' ?>">
                                    <i class="fa-solid fa-gauge me-1"></i> Panel Kasir
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item ms-lg-2 my-1 my-lg-0">
                            <a class="btn btn-logout-nav" href="logout.php" title="Keluar dari Akun">
                                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-2 my-1 my-lg-0">
                            <a class="btn btn-login-nav text-white" href="login.php" title="Masuk ke Akun / Petugas">
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
            <div class="hero-badge">
                <i class="fa-solid fa-water"></i> Wisata Pemandian Alami Terfavorit di Jember
            </div>
            <h1 class="hero-title">Pengalaman Pemandian Alami Yang Segar & Menenangkan</h1>
            <p class="hero-subtitle">
                Rasakan kejernihan mata air pegunungan alami yang dingin dan menyejukkan. Destinasi rekreasi sempurna untuk kebersamaan keluarga dan sahabat.
            </p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a class="btn btn-accent btn-lg px-4 py-3" href="tiket/pesan.php">
                    <i class="fa-solid fa-ticket me-2"></i> Pesan Tiket Sekarang
                </a>
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
                <!-- Galeri 1: Wahana Waterpark & Kolam Anak -->
                <div class="col-lg-4 col-sm-6">
                    <div class="portfolio-item modern-card overflow-hidden h-100">
                        <a class="portfolio-link d-block position-relative" data-bs-toggle="modal" href="#portfolioModal4">
                            <img class="img-fluid w-100" src="../../public/img/gambar5.png" alt="Wahana Waterpark Patemon" style="height: 240px; width: 100%; object-fit: cover; aspect-ratio: 16 / 10;" />
                        </a>
                        <div class="p-3 text-center">
                            <h5 class="fw-bold mb-1" style="color: #0f172a;">Wahana Kolam & Waterpark</h5>
                            <p class="text-muted small mb-0">Fasilitas Rekreasi Keluarga Modern & Asri</p>
                        </div>
                    </div>
                </div>
                <!-- Galeri 2: Kunjungan Mantan Bupati Jember Ir. H. Hendy Siswanto -->
                <div class="col-lg-4 col-sm-6">
                    <div class="portfolio-item modern-card overflow-hidden h-100">
                        <a class="portfolio-link d-block position-relative" data-bs-toggle="modal" href="#portfolioModal5">
                            <img class="img-fluid w-100" src="../../public/img/gambar7.png" alt="Kunjungan Mantan Bupati Jember" style="height: 240px; width: 100%; object-fit: cover; aspect-ratio: 16 / 10;" />
                        </a>
                        <div class="p-3 text-center">
                            <h5 class="fw-bold mb-1" style="color: #0f172a;">Kunjungan Mantan Bupati Jember</h5>
                            <p class="text-muted small mb-0">Peninjauan Pemandian Patemon (Periode 2021–2025)</p>
                        </div>
                    </div>
                </div>
                <!-- Galeri 3: Panorama Sumber Mata Air Alami Pegunungan -->
                <div class="col-lg-4 col-sm-6">
                    <div class="portfolio-item modern-card overflow-hidden h-100">
                        <a class="portfolio-link d-block position-relative" data-bs-toggle="modal" href="#portfolioModal6">
                            <img class="img-fluid w-100" src="../../public/img/gambar4.png" alt="Mata Air Alami Gunung Argopuro" style="height: 240px; width: 100%; object-fit: cover; aspect-ratio: 16 / 10;" />
                        </a>
                        <div class="p-3 text-center">
                            <h5 class="fw-bold mb-1" style="color: #0f172a;">Mata Air Alami Argopuro</h5>
                            <p class="text-muted small mb-0">Air Dingin Jernih Tanpa Bahan Kaporit</p>
                        </div>
                    </div>
                </div>
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
                <div class="col-12 col-md-6 col-lg-5">
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
                        <a href="tiket/pesan.php" class="btn btn-brand w-100 py-3 fs-6">
                            <i class="fa-solid fa-cart-shopping me-1"></i> Pesan Tiket Dewasa
                        </a>
                    </div>
                </div>

                <!-- Tiket Anak-Anak -->
                <div class="col-12 col-md-6 col-lg-5">
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
                        <a href="tiket/pesan.php" class="btn btn-accent w-100 py-3 fs-6">
                            <i class="fa-solid fa-cart-shopping me-1"></i> Pesan Tiket Anak
                        </a>
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

    <!-- Location & Socials -->
    <div class="py-5 bg-white border-top border-bottom">
        <div class="container text-center">
            <h5 class="fw-bold text-dark mb-4">Kunjungi & Ikuti Informasi Terkini Kami</h5>
            <div class="row align-items-center justify-content-center g-4">
                <div class="col-md-3 col-6">
                    <a href="https://maps.app.goo.gl/PemandianPatemon" target="_blank" class="d-inline-flex align-items-center gap-2 text-decoration-none text-dark fw-semibold p-2 rounded hover-bg">
                        <img src="../../public/img/google_maps.png" alt="Google Maps" style="height: 32px; width: auto; object-fit: contain;" />
                        <span>Google Maps</span>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="https://facebook.com/pemandian.patemon" target="_blank" class="d-inline-flex align-items-center gap-2 text-decoration-none text-dark fw-semibold p-2 rounded hover-bg">
                        <i class="fa-brands fa-facebook fs-2 text-primary"></i>
                        <span>Facebook</span>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="https://instagram.com" target="_blank" class="d-inline-flex align-items-center gap-2 text-decoration-none text-dark fw-semibold p-2 rounded hover-bg">
                        <i class="fa-brands fa-instagram fs-2 text-danger"></i>
                        <span>Instagram</span>
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="https://youtube.com" target="_blank" class="d-inline-flex align-items-center gap-2 text-decoration-none text-dark fw-semibold p-2 rounded hover-bg">
                        <i class="fa-brands fa-youtube fs-2 text-danger"></i>
                        <span>YouTube</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

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
                    <form id="contactForm" method="post" action="index.php#contact" class="p-4 p-md-5 rounded-4" style="background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(14px); border: 1px solid rgba(255, 255, 255, 0.15);">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-light small fw-semibold">Nama Anda <span class="text-danger">*</span></label>
                                <input class="form-control-modern" id="name" name="username" type="text" placeholder="Masukkan nama Anda" required />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-light small fw-semibold">Alamat Email</label>
                                <input class="form-control-modern" id="email" name="email" type="email" placeholder="nama@email.com" />
                            </div>
                            <div class="col-12">
                                <label class="form-label text-light small fw-semibold">Nomor WhatsApp / HP</label>
                                <input class="form-control-modern" name="no_telepon" id="phone" type="tel" placeholder="08xxxxxxxxxx" />
                            </div>
                            <div class="col-12">
                                <label class="form-label text-light small fw-semibold">Ulasan, Kritik & Saran <span class="text-danger">*</span></label>
                                <textarea class="form-control-modern" name="ulasan" id="message" placeholder="Tuliskan pengalaman atau saran Anda mengenai kebersihan, kolam, dan fasilitas kami..." required style="min-height: 140px;"></textarea>
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

    <!-- Portfolio Modals -->
    <div class="modal fade" id="portfolioModal4" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
            <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
                <div class="modal-header border-0 pb-0 pt-3 px-4 d-flex justify-content-between align-items-center">
                    <span class="badge badge-modern-primary">FASILITAS WISATA PATEMON</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center px-4 py-3">
                    <h4 class="fw-bold mb-1" style="color: #0f172a;">Wahana Kolam & Waterpark</h4>
                    <p class="text-muted small mb-3">Fasilitas Rekreasi Keluarga di Pemandian Patemon Tanggul</p>
                    <img class="img-fluid d-block mx-auto rounded-3 mb-3 shadow-sm" src="../../public/img/gambar5.png" alt="Wahana Kolam & Waterpark" style="max-height: 230px; width: 100%; object-fit: cover; aspect-ratio: 16 / 9;" />
                    <p class="text-muted small mb-3" style="line-height: 1.6;">Pemandian Patemon menyediakan kolam renang bertingkat serta wahana seluncuran air yang aman dan menyenangkan untuk anak-anak maupun dewasa. Air kolam di Pemandian Patemon dialirkan langsung secara alami dari sumber mata air tanpa kaporit, menghadirkan kesegaran alami di tengah suasana asri Pemandian Patemon.</p>
                    <button class="btn btn-secondary btn-sm px-4 py-2" data-bs-dismiss="modal" type="button"><i class="fas fa-xmark me-1"></i> Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="portfolioModal5" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
            <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
                <div class="modal-header border-0 pb-0 pt-3 px-4 d-flex justify-content-between align-items-center">
                    <span class="badge badge-modern-primary">PENINJAUAN PEMANDIAN PATEMON</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center px-4 py-3">
                    <h4 class="fw-bold mb-1" style="color: #0f172a;">Kunjungan Mantan Bupati Jember</h4>
                    <p class="text-muted small mb-3">Peninjauan Pemandian Patemon Tanggul (Periode 2021–2025)</p>
                    <img class="img-fluid d-block mx-auto rounded-3 mb-3 shadow-sm" src="../../public/img/gambar7.png" alt="Kunjungan Mantan Bupati Jember" style="max-height: 230px; width: 100%; object-fit: cover; aspect-ratio: 16 / 9;" />
                    <p class="text-muted small mb-3" style="line-height: 1.6;">Mantan Bupati Jember, Ir. H. Hendy Siswanto, ST. IPU. (periode 2021–2025), melakukan peninjauan langsung ke Pemandian Patemon di Tanggul untuk mengecek kelayakan kolam, kebersihan sumber mata air, serta fasilitas penunjang wisata. Kunjungan ini difokuskan untuk memastikan percepatan perbaikan sarana Pemandian Patemon agar aman, nyaman, dan kembali menggerakkan usaha warga di sekitar Pemandian Patemon.</p>
                    <button class="btn btn-secondary btn-sm px-4 py-2" data-bs-dismiss="modal" type="button"><i class="fas fa-xmark me-1"></i> Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="portfolioModal6" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
            <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
                <div class="modal-header border-0 pb-0 pt-3 px-4 d-flex justify-content-between align-items-center">
                    <span class="badge badge-modern-primary">MATA AIR ALAMI PATEMON</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center px-4 py-3">
                    <h4 class="fw-bold mb-1" style="color: #0f172a;">Mata Air Alami Pemandian Patemon</h4>
                    <p class="text-muted small mb-3">Kejernihan Sumber Air Alami Pemandian Patemon Tanggul</p>
                    <img class="img-fluid d-block mx-auto rounded-3 mb-3 shadow-sm" src="../../public/img/gambar4.png" alt="Mata Air Alami Patemon" style="max-height: 230px; width: 100%; object-fit: cover; aspect-ratio: 16 / 9;" />
                    <p class="text-muted small mb-3" style="line-height: 1.6;">Keistimewaan utama Pemandian Patemon adalah limpahan mata air alami dari lereng Pegunungan Argopuro yang mengalir jernih, dingin, dan murni tanpa zat kimia kaporit. Suasana rindang dan sejuk di kawasan Pemandian Patemon menjadikannya destinasi favorit keluarga untuk berenang dan menyegarkan tubuh.</p>
                    <button class="btn btn-secondary btn-sm px-4 py-2" data-bs-dismiss="modal" type="button"><i class="fas fa-xmark me-1"></i> Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../public/js/scripts.js"></script>

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
