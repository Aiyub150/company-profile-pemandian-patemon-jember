/**
 * Modul Multi-Bahasa Universal (i18n Engine: Bahasa Indonesia <-> English)
 * Pemandian Patemon Jember
 * Menyediakan translasi teks menyeluruh tanpa merusak elemen child, icon, atau event listener.
 */

(function() {
    'use strict';

    // SVG Flags untuk render visual sempurna di Windows/Mac/Linux/Android/iOS
    const FLAG_ID_SVG = `<svg class="flag-icon-svg" viewBox="0 0 640 480" width="18" height="13" style="border-radius:2px; vertical-align:middle; display:inline-block; box-shadow:0 0 1px rgba(0,0,0,0.5); margin-right:4px;"><g fill-rule="evenodd" stroke-width="1pt"><path fill="#e70011" d="M0 0h640v240H0z"/><path fill="#ffffff" d="M0 240h640v240H0z"/></g></svg>`;

    const FLAG_EN_SVG = `<svg class="flag-icon-svg" viewBox="0 0 60 30" width="18" height="11" style="border-radius:2px; vertical-align:middle; display:inline-block; box-shadow:0 0 1px rgba(0,0,0,0.5); margin-right:4px;"><clipPath id="uk-flag-clip"><path d="M0,0 v30 h60 v-30 z"/></clipPath><clipPath id="uk-flag-diag"><path d="M30,15 h30 v15 z v15 h-30 z h-30 v-15 z v-15 h30 z"/></clipPath><g clip-path="url(#uk-flag-clip)"><path d="M0,0 v30 h60 v-30 z" fill="#012169"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" stroke-width="6"/><path d="M0,0 L60,30 M60,0 L0,30" clip-path="url(#uk-flag-diag)" stroke="#C8102E" stroke-width="4"/><path d="M30,0 v30 M0,15 h60" stroke="#fff" stroke-width="10"/><path d="M30,0 v30 M0,15 h60" stroke="#C8102E" stroke-width="6"/></g></svg>`;

    // Kamus Lengkap Bahasa Indonesia -> English
    const DICTIONARY = {
        // --- NAVBAR & HEADER ---
        "PEMANDIAN PATEMON": "PEMANDIAN PATEMON",
        "Wisata Alam Tanggul • Jember": "Tanggul Nature Tourism • Jember",
        "Wisata Alam Tanggul &bull; Jember": "Tanggul Nature Tourism &bull; Jember",
        "Beranda": "Home",
        "Fasilitas": "Facilities",
        "Galeri": "Gallery",
        "Tarif Tiket": "Ticket Rates",
        "Lokasi & Peta": "Location & Map",
        "Kontak Kami": "Contact Us",
        "Kritik & Saran": "Feedback & Reviews",
        "Pesan Tiket": "Book Tickets",
        "Login": "Login",
        "Logout": "Logout",
        "Keluar / Logout": "Exit / Logout",
        "Panel Kasir": "Cashier Panel",
        "Tema": "Theme",
        "Gelap": "Dark",
        "Terang": "Light",

        // --- HERO SECTION (LANDING PAGE) ---
        "Wisata Pemandian Alami Terfavorit di Jember": "Top Favorite Natural Bathing Destination in Jember",
        "WISATA PEMANDIAN ALAMI TERFAVORIT DI JEMBER": "TOP FAVORITE NATURAL BATHING DESTINATION IN JEMBER",
        "Pengalaman Pemandian Alami Yang Segar & Menenangkan": "Fresh & Relaxing Natural Spring Water Experience",
        "Rasakan kejernihan mata air pegunungan alami yang dingin dan menyejukkan. Destinasi rekreasi sempurna untuk kebersamaan keluarga dan sahabat.": "Experience the purity of cold, refreshing natural mountain spring water. The perfect recreational destination for family and friends.",
        "Pesan Tiket Sekarang": "Book Tickets Now",
        "Jelajahi Fasilitas": "Explore Facilities",
        "3+": "3+",
        "Tingkat Kedalaman Kolam": "Pool Depth Levels",
        "100%": "100%",
        "Sumber Air Alami Pegunungan": "Natural Mountain Spring",
        "10.000+": "10,000+",
        "Pengunjung Puas Per Tahun": "Happy Visitors Per Year",

        // --- SERVICES / FASILITAS ---
        "Fasilitas Utama": "Key Facilities",
        "Layanan & Kenyamanan Pengunjung": "Visitor Services & Comfort",
        "Kami memastikan setiap momen liburan Anda aman, bersih, dan berkesan.": "We ensure every moment of your holiday is safe, clean, and memorable.",
        "Kolam Renang Alami": "Natural Swimming Pools",
        "Tersedia beberapa tingkatan kolam untuk dewasa, remaja, hingga anak-anak dengan sirkulasi mata air alami yang selalu jernih dan higienis.": "Multiple pool levels available for adults, teens, and children with continuous circulation of clear, hygienic natural spring water.",
        "Warung Kuliner Asri": "Scenic Culinary Stalls",
        "Nikmati kelezatan aneka sajian kuliner khas, camilan hangat, dan minuman segar di area santai yang rindang setelah puas berenang.": "Enjoy delicious traditional culinary treats, warm snacks, and refreshing drinks in a shaded relaxation area after swimming.",
        "Keamanan & Kebersihan": "Safety & Cleanliness",
        "Dilengkapi pos pengawas keselamatan (lifeguard), loker penitipan barang, serta ruang bilas dan toilet yang terawat demi kenyamanan Anda.": "Equipped with lifeguard surveillance, storage lockers, and well-maintained shower rooms and restrooms for your comfort.",

        // --- GALLERY / PORTFOLIO (LANDING PAGE & DB ITEMS) ---
        "Galeri Wisata": "Tourism Gallery",
        "Pesona Wisata Pemandian Patemon": "The Charm of Pemandian Patemon",
        "Dokumentasi fasilitas terkini, panorama mata air pegunungan alami, serta sejarah peninjauan destinasi.": "Documentation of recent facilities, natural mountain spring panorama, and destination history.",
        // Default items
        "Wahana Kolam & Waterpark": "Pool Rides & Waterpark",
        "Fasilitas Rekreasi Keluarga di Pemandian Patemon Tanggul": "Family Recreational Facilities at Pemandian Patemon Tanggul",
        "Pemandian Patemon menyediakan kolam renang bertingkat serta wahana seluncuran air yang aman dan menyenangkan untuk anak-anak maupun dewasa. Air kolam di Pemandian Patemon dialirkan langsung secara alami dari sumber mata air tanpa kaporit.": "Pemandian Patemon provides tiered swimming pools and safe, enjoyable water slides for both children and adults. The pool water is naturally sourced directly from mountain springs without chlorine.",
        "Kunjungan Mantan Bupati Jember": "Former Jember Regent Visit",
        "Peninjauan Pemandian Patemon Tanggul (Periode 2021-2025)": "Inspection of Pemandian Patemon Tanggul (2021-2025 Period)",
        "Mantan Bupati Jember, Ir. H. Hendy Siswanto, ST. IPU. (periode 2021-2025), melakukan peninjauan langsung ke Pemandian Patemon untuk mengecek kelayakan fasilitas wisata.": "Former Regent of Jember, Ir. H. Hendy Siswanto, ST. IPU. (2021-2025 term), conducted a direct inspection of Pemandian Patemon to check the feasibility of tourism facilities.",
        "Mata Air Alami Argopuro": "Argopuro Natural Spring",
        "Kejernihan Sumber Air Alami Pemandian Patemon Tanggul": "Clarity of the Natural Spring Water at Pemandian Patemon Tanggul",
        "Keistimewaan utama Pemandian Patemon adalah limpahan mata air alami dari lereng Pegunungan Argopuro yang mengalir jernih, dingin, dan murni tanpa kaporit.": "The main specialty of Pemandian Patemon is the abundance of natural spring water from the slopes of Mount Argopuro that flows clear, cold, and pure without chlorine.",
        // Active DB Gallery items
        "Libur Lebaran 2026": "Eid al-Fitr Holiday 2026",
        "Libur Lebaran 2026 membawa berkah bagi sektor pariwisata Kabupaten Jember": "The 2026 Eid Holiday brings blessings to Jember Regency's tourism sector",
        "29 Maret 2026 - Berbagai destinasi unggulan seperti Pemandian Patemon di Kecamatan Tanggul diserbu ribuan wisatawan hingga akhir masa liburan. Lonjakan kunjungan ini tidak hanya mencerminkan meningkatnya minat masyarakat terhadap wisata lokal, tetapi juga membuktikan keberhasilan kebijakan revitalisasi fasilitas dan penyesuaian tarif yang diterapkan Pemerintah Kabupaten Jember.": "March 29, 2026 - Leading destinations such as Pemandian Patemon in Tanggul District were visited by thousands of tourists through the end of the holiday period. This surge in visits not only reflects rising public interest in local tourism, but also proves the success of facility revitalization policies and rate adjustments implemented by the Jember Regency Government.",
        "Kunjungan Bupati Jember": "Jember Regent Visit",
        "Peninjauan Pemandian Patemon oleh Bupati Jember Ir. H. Hendy Siswanto, ST. IPU.,": "Inspection of Pemandian Patemon by Jember Regent Ir. H. Hendy Siswanto, ST. IPU.,",
        "Peninjauan Pemandian Patemon oleh Bupati Jember Ir. H. Hendy Siswanto, ST. IPU.": "Inspection of Pemandian Patemon by Jember Regent Ir. H. Hendy Siswanto, ST. IPU.",
        "Bupati Jember, Ir. H. Hendy Siswanto, ST. IPU., (Periode Tahun 2021-2025) meninjau Pemandian Patemon di Kecamatan Tanggul, Jember, pada Senin, 21 Februari 2022. Peninjauan ini dilakukan untuk menghidupkan kembali aset milik Pemkab Jember yang lama terbengkalai dan ditutup, guna menggerakkan ekonomi warga dan pedagang sekitar.": "Regent of Jember, Ir. H. Hendy Siswanto, ST. IPU., (2021-2025 Period) inspected Pemandian Patemon in Tanggul District, Jember, on Monday, February 21, 2022. This inspection was carried out to revitalize the long-neglected asset belonging to Jember Regency Government, driving the economy of local residents and merchants.",
        "Long Weekend, Pemandian Patemon Dibanjiri Wisatawan": "Long Weekend: Pemandian Patemon Flooded with Tourists",
        "Pemandian Patemon dibanjiri banyak pengunjung dari berbagai tempat": "Pemandian Patemon was flooded with many visitors from various places",
        "Senin (12/12/2016) - hari libur oleh sebagian warga diisi dengan liburan, seperti tempat Wisata Pemandian Patemon Tanggul Jember, masyarakat dari berbagai daerah di Jember memanfaatkan liburan kali ini dengan mengajak serta keluarganya, seperti terlihat ratusan pengunjung yang sedang berenang bersama dengan keluarganya": "Monday (12/12/2016) - holidays were spent on vacation by many residents at Pemandian Patemon Tanggul Jember, where people from various regions in Jember took advantage of this holiday by bringing their families, as seen by hundreds of visitors swimming together with their families.",
        // Gallery Admin Management
        "Kelola Galeri": "Manage Gallery",
        "Kelola Galeri Wisata": "Manage Tourism Gallery",
        "Atur gambar dan deskripsi 3 konten galeri yang tampil di halaman utama website.": "Manage images and descriptions of the 3 gallery items displayed on the main website.",
        "Daftar Konten Galeri": "Gallery Content List",
        "Klik Edit untuk mengubah gambar atau deskripsi tiap slot galeri.": "Click Edit to change the image or description for each gallery slot.",
        "Edit Galeri Slot": "Edit Gallery Slot",
        "Judul Konten": "Content Title",
        "Deskripsi Singkat (Tampil di Kartu)": "Short Description (Shown on Card)",
        "Deskripsi Lengkap (Tampil di Pop-up Modal)": "Full Description (Shown in Pop-up Modal)",
        "Gambar Kartu (Resolusi 16:10 / 800x500)": "Card Image (16:10 / 800x500 resolution)",
        "Gambar Pop-up (Resolusi 16:9 / 1200x675)": "Pop-up Image (16:9 / 1200x675 resolution)",
        "Pilih File Gambar Baru": "Choose New Image File",
        "Simpan Perubahan Galeri": "Save Gallery Changes",
        "Judul konten galeri...": "Gallery content title...",
        "Judul galeri wajib diisi.": "Gallery title is required.",
        "ID galeri tidak valid.": "Invalid gallery ID.",

        // --- PRICING SECTION ---
        "Tarif Tiket Masuk": "Admission Ticket Rates",
        "Harga Tiket Terjangkau": "Affordable Ticket Rates",
        "Dapatkan akses penuh ke kolam dan seluruh fasilitas alam dengan tarif ramah kantong.": "Get full access to all pools and natural facilities at wallet-friendly prices.",
        "Kategori Terpopuler": "Most Popular Category",
        "Tiket Dewasa": "Adult Ticket",
        "Untuk pengunjung usia dewasa & remaja": "For adult & teen visitors",
        "/ orang": "/ person",
        "Akses Kolam Renang Dewasa & Menengah": "Access to Adult & Intermediate Pools",
        "Kamar Mandi & Ruang Bilas Bersih": "Clean Bathrooms & Shower Rooms",
        "Area Gazebo & Tempat Duduk Teduh": "Gazebo Area & Shaded Seating",
        "Parkir Aman & Terjaga": "Safe & Monitored Parking",
        "Pesan Tiket Dewasa": "Book Adult Tickets",
        "Khusus Anak-Anak": "Special for Children",
        "Tiket Anak-Anak": "Children's Ticket",
        "Untuk balita dan anak-anak": "For toddlers and children",
        "/ anak": "/ child",
        "Akses Kolam Anak dengan Kedalaman Aman": "Access to Children's Pool with Safe Depth",
        "Air Alami Tanpa Kaporit Menyengat": "Natural Water Without Harsh Chlorine",
        "Pengawasan Lifeguard Khusus Area Anak": "Dedicated Lifeguard Supervision for Kids Area",
        "Wahana Bermain Air Menyenangkan": "Fun Water Play Amenities",
        "Pesan Tiket Anak": "Book Children Tickets",

        // --- REGIONAL LEADERS / PIMPINAN DAERAH ---
        "Pimpinan Daerah": "Regional Leaders",
        "Pimpinan Pemerintah Kabupaten Jember": "Leaders of Jember Regency Government",
        "Mendukung penuh pelestarian lingkungan, pengelolaan profesional, dan pengembangan destinasi wisata Pemandian Patemon.": "Fully supporting environmental conservation, professional management, and development of Pemandian Patemon tourism.",
        "Periode 2025–2030": "2025–2030 Term",
        "Bupati Jember": "Regent of Jember",
        "Bupati Pemerintah Kabupaten Jember yang berkomitmen memajukan sektor pariwisata daerah, memberdayakan UMKM lokal, dan melestarikan potensi wisata alam kebanggaan warga Jember.": "The Regent of Jember Regency Government committed to advancing local tourism, empowering MSMEs, and preserving natural tourist attractions.",
        "Wakil Bupati Jember": "Vice Regent of Jember",
        "Wakil Bupati Pemerintah Kabupaten Jember yang senantiasa mengawal peningkatan sarana prasarana wisata, mutu pelayanan publik, dan sinergi kemajuan pariwisata terpadu.": "The Vice Regent of Jember Regency Government continuously overseeing tourism infrastructure improvements, public service quality, and integrated tourism synergy.",

        // --- MAPS & LOCATION ---
        "Lokasi Destinasi": "Destination Location",
        "Peta Lokasi Wisata Pemandian Patemon": "Map Location of Pemandian Patemon",
        "Temukan rute tercepat dan termudah menuju segarnya sumber mata air alami Pemandian Patemon di Tanggul, Jember.": "Find the fastest and easiest route to the refreshing natural spring water of Pemandian Patemon in Tanggul, Jember.",
        "Desa Patemon, Kecamatan Tanggul, Kabupaten Jember, Jawa Timur 68155": "Patemon Village, Tanggul District, Jember Regency, East Java 68155",
        "Buka Navigasi Rute (Google Maps)": "Open Route Navigation (Google Maps)",

        // --- CONTACTS & WADUL GUS'E ---
        "Saluran Informasi & Pengaduan Resmi": "Official Information & Complaint Channels",
        "Kontak Resmi, Wadul Gus'e & Pemkab Jember": "Official Contacts, Wadul Gus'e & Jember Regency",
        "Sampaikan laporan, keluhan, dan aspirasi melalui Wadul Gus’e serta kanal resmi Pemerintah Kabupaten Jember secara cepat dan transparan.": "Submit reports, complaints, and aspirations via Wadul Gus’e and official Jember Regency channels quickly and transparently.",
        "PORTAL RESMI PEMKAB": "OFFICIAL REGENCY PORTAL",
        "Online 24 Jam": "Online 24/7",
        "Wadul Gus'e Jember": "Wadul Gus'e Jember",
        "Layanan Resmi Pengaduan Masyarakat Kabupaten Jember untuk menyampaikan laporan, keluhan, dan aspirasi warga secara cepat dan transparan.": "Official Community Complaint Service of Jember Regency to submit citizen reports, grievances, and feedback transparently.",
        "Buka Portal: wadulgus.jemberkab.go.id": "Open Portal: wadulgus.jemberkab.go.id",
        "Pusat Pemerintahan Daerah": "Regional Government Center",
        "Kantor Pemerintah Kabupaten Jember": "Jember Regency Government Office",
        "Alamat Kantor:": "Office Address:",
        "Situs Resmi:": "Official Website:",
        "Telepon / Call Center:": "Telephone / Call Center:",
        "Pengelola Pariwisata & Rekreasi": "Tourism & Recreation Management",
        "Disparbud & UPT Wisata Patemon": "Tourism Office & Patemon Tourism Management",
        "Kantor Disparbud:": "Tourism Office Address:",
        "Lokasi Wisata & Loket:": "Tourism Site & Ticket Counters:",
        "Jam Operasional:": "Operational Hours:",
        "Buka Setiap Hari (07.00 - 17.00 WIB)": "Open Daily (07:00 - 17:00 WIB)",

        // --- FEEDBACK & REVIEWS ---
        "Bantu Kami Berkembang": "Help Us Improve",
        "Kesan dan pengalaman Anda sangat berharga untuk meningkatkan kualitas pelayanan Pemandian Patemon.": "Your impressions and experiences are invaluable for improving the service quality of Pemandian Patemon.",
        "Nama Anda": "Your Name",
        "Masukkan nama Anda (maks. 50 karakter)": "Enter your name (max. 50 chars)",
        "Maksimal 50 karakter.": "Maximum 50 characters.",
        "Alamat Email": "Email Address",
        "Nomor WhatsApp / HP": "WhatsApp / Phone Number",
        "Format nomor Indonesia diawali angka 0 (9–15 digit angka).": "Indonesian phone format starting with 0 (9–15 digits).",
        "Ulasan, Kritik & Saran": "Feedback, Review & Suggestions",
        "Tuliskan pengalaman atau saran Anda mengenai kebersihan, kolam, dan fasilitas kami (maksimal 500 karakter)...": "Write your experience or suggestions regarding our cleanliness, pools, and facilities (max. 500 chars)...",
        "Kirim Ulasan Sekarang": "Submit Review Now",
        "Wisata Pemandian Alam Tanggul • Jember, Jawa Timur": "Tanggul Natural Bathing Tourism • Jember, East Java",
        "Tutup": "Close",

        // --- TICKET RESERVATION (PESAN TIKET) ---
        "Reservasi Tiket Masuk": "Admission Ticket Reservation",
        "Pengalaman pemandian alami air pegunungan yang asri, bersih, dan menyegarkan.": "Experience scenic, clean, and refreshing natural mountain spring bathing.",
        "Formulir Pemesanan Tiket": "Ticket Booking Form",
        "Isi data kunjungan dan pilih tiket yang diinginkan.": "Fill in your visit details and select the desired tickets.",
        "Pilih Kategori Tiket": "Select Ticket Categories",
        "Metode Pembayaran": "Payment Method",
        "Ringkasan Pemesanan": "Order Summary",
        "Total Jumlah Tiket:": "Total Ticket Quantity:",
        "Total Bayar:": "Total Payment:",
        "Konfirmasi & Pesan Tiket": "Confirm & Book Tickets",
        "Belum ada tiket yang dipilih.": "No tickets selected yet.",
        "Bayar Di Loket": "Pay at Counter",
        "QRIS": "QRIS",
        "Transfer Bank": "Bank Transfer",
        "Rekening Penerimaan Resmi Pemkab Jember:": "Official Jember Regency Receiving Account:",
        "Salin No. Rekening": "Copy Account Number",
        "Scan Kode QRIS": "Scan QRIS Code",
        "UPTD Pemandian Patemon - Pemkab Jember": "UPTD Pemandian Patemon - Jember Regency Government",
        "Buka aplikasi m-Banking (BCA, Mandiri, BRI, Bank Jatim) atau e-Wallet (GoPay, OVO, Dana).": "Open your m-Banking app (BCA, Mandiri, BRI, Bank Jatim) or e-Wallet (GoPay, OVO, Dana).",
        "Pindai QR Code di samping dan masukkan nominal yang tertera pada ringkasan pesanan.": "Scan the QR Code alongside and enter the amount indicated in the order summary.",
        "Unggah bukti tangkapan layar pembayaran pada formulir di bawah.": "Upload the screenshot of payment proof in the form below.",
        "Transfer via ATM, m-Banking, atau Internet Banking ke nomor rekening di atas.": "Transfer via ATM, m-Banking, or Internet Banking to the account number above.",
        "Simpan resi / mutasi transfer bank, lalu unggah fotonya pada formulir di bawah.": "Save the bank transfer receipt/mutation, then upload its photo in the form below.",
        "Verifikasi mutasi rekening dilakukan secara otomatis oleh sistem loket kasir.": "Account mutation verification is processed by the cashier counter system.",
        "Unggah Bukti Pembayaran": "Upload Payment Proof",
        "Klik atau seret foto bukti transfer di sini": "Click or drag transfer proof photo here",
        "Format didukung: JPG, PNG, WEBP (Maksimal 2 MB)": "Supported formats: JPG, PNG, WEBP (Max 2 MB)",
        "Nama Pemesan": "Customer Name",
        "Tanggal Kunjungan": "Visit Date",
        "Email / No. HP": "Email / Phone Number",
        "Dewasa": "Adult",
        "Anak-Anak": "Children",
        "Lansia": "Elderly",
        "Pelajar": "Student",
        "lembar": "pcs",
        "lbr": "pcs",
        "Tiket": "Tickets",
        "0 Tiket": "0 Tickets",

        // --- STRUK TIKET / NOTA ---
        "Struk Tiket": "Ticket Receipt",
        "NOMOR REFERENSI TRANSAKSI": "TRANSACTION REFERENCE NUMBER",
        "Tunjukkan QR Code / Barcode ini kepada petugas loket untuk verifikasi tiket masuk": "Show this QR Code / Barcode to counter staff for admission verification",
        "Nama Pengunjung:": "Visitor Name:",
        "Tanggal Kunjungan:": "Visit Date:",
        "Metode Pembayaran:": "Payment Method:",
        "RINCIAN TIKET MASUK": "ADMISSION TICKET DETAILS",
        "Tiket Masuk": "Admission Ticket",
        "Total Pembayaran:": "Total Payment:",
        "LUNAS / TERVERIFIKASI": "PAID / VERIFIED",
        "MENUNGGU PEMBAYARAN": "AWAITING PAYMENT",
        "Terima kasih atas kunjungan Anda di Pemandian Patemon.": "Thank you for visiting Pemandian Patemon.",
        "Harap simpan struk ini sebagai bukti akses masuk kolam.": "Please keep this receipt as proof of pool admission.",
        "Download Gambar Nota": "Download Receipt Image",
        "Menyiapkan Gambar...": "Preparing Image...",
        "Nota Transaksi Tidak Ditemukan": "Transaction Receipt Not Found",
        "Anda tidak memiliki akses ke transaksi ini atau nomor ID salah.": "You do not have access to this transaction or invalid ID.",

        // --- BUKU PANDUAN PENGGUNAAN SISTEM (GUIDE) ---
        "Buku Panduan": "User Guide",
        "Buku Panduan Penggunaan Sistem": "System User Guide",
        "Buku Panduan Penggunaan Sistem - Pemandian Patemon": "System User Guide - Pemandian Patemon",
        "Buku Panduan Operasional Sistem": "System Operational Manual",
        "Buku manual kasir & POS": "Cashier & POS manual book",
        "Pedoman standar operasional (SOP) kasir, loket, pemesanan tiket, validasi barcode, dan pelaporan akuntabilitas.": "Standard operating procedures (SOP) for cashier, ticketing, barcode validation, and accountability reporting.",
        "Panduan Pengoperasian Wisata Pemandian Patemon": "Operational Guide for Pemandian Patemon Tourism",
        "Dokumentasi alur kerja terpadu untuk pengelola, staf loket, dan pengunjung.": "Integrated workflow documentation for managers, counter staff, and visitors.",
        "Buka Dokumen PDF Panduan": "Open PDF User Guide",
        "1. Alur Pemesanan Tiket Online (Pengunjung)": "1. Online Ticket Booking Flow (Visitors)",
        "Pengunjung membuka website dan memilih tombol": "Visitors open the website and click the",
        "Pengunjung membuka website dan memilih tombol Pesan Tiket.": "Visitors open the website and click the Book Tickets button.",
        "Pengunjung menentukan jumlah lembar tiket untuk tiap kategori (Dewasa, Anak-Anak, Lansia, dll.).": "Visitors specify the number of tickets for each category (Adult, Children, Elderly, etc.).",
        "Sistem secara otomatis menghitung total biaya di sisi server (*server-side authoritative calculation*).": "The system automatically calculates the total cost on the server side (server-side authoritative calculation).",
        "Pengunjung memilih metode pembayaran:": "Visitors choose a payment method:",
        "Scan QRIS": "Scan QRIS",
        "Scan gambar QRIS resmi dari e-wallet/mobile banking lalu unggah bukti transfer.": "Scan official QRIS image from e-wallet/mobile banking then upload transfer proof.",
        "Transfer Bank: Salin nomor rekening resmi, lakukan transfer, lalu unggah struk mutasi.": "Bank Transfer: Copy official bank account number, make transfer, then upload bank receipt.",
        "Salin nomor rekening resmi, lakukan transfer, lalu unggah struk mutasi.": "Copy official bank account number, make transfer, then upload bank receipt.",
        "Bayar di Loket: Bayar tunai setibanya di loket pintu masuk pemandian.": "Pay at Counter: Pay cash upon arrival at the bathing entrance counter.",
        "Bayar tunai setibanya di loket pintu masuk pemandian.": "Pay cash upon arrival at the bathing entrance counter.",
        "Setelah form dikirim, sistem otomatis menerbitkan": "After the form is submitted, the system automatically issues",
        "Nota Digital Ber-Barcode": "Barcoded Digital Receipt",
        "unik.": "unique.",
        "Setelah form dikirim, sistem otomatis menerbitkan Nota Digital Ber-Barcode unik.": "After the form is submitted, the system automatically issues a unique Barcoded Digital Receipt.",
        "2. SOP Staf Kasir Loket (Input POS)": "2. Cashier Counter Staff SOP (POS Input)",
        "Staf masuk ke menu": "Staff navigate to the",
        "atau": "or",
        "Input POS Baru": "New POS Input",
        "Pilih nama pembeli atau masukkan kategori pengunjung rombongan/umum.": "Select customer name or enter group/general visitor category.",
        "Input kuantitas lembar tiket yang dibeli secara langsung di tempat.": "Enter the quantity of tickets purchased directly on site.",
        "Terima uang tunai atau verifikasi transfer/QRIS pengunjung secara teliti.": "Receive cash or carefully verify visitor transfer/QRIS payment.",
        "Klik": "Click",
        "Simpan & Cetak Struk Tiket": "Save & Print Ticket Receipt",
        "untuk mencetak struk masuk dengan barcode.": "to print the entrance voucher with barcode.",
        "Serahkan struk kepada pengunjung sebagai tiket akses kolam pemandian.": "Hand the receipt to the visitor as access ticket to the swimming pools.",
        "3. Prosedur Validasi Barcode di Pintu Masuk": "3. Entrance Gate Barcode Validation Procedure",
        "Petugas loket membuka tab": "Counter staff open the",
        "Scan Barcode Nota": "Scan Receipt Barcode",
        "pada menu Transaksi Kasir.": "tab on the Cashier Transactions menu.",
        "Arahkan kamera perangkat atau barcode scanner ke barcode pada struk pengunjung.": "Point the device camera or barcode scanner at the barcode on the visitor's receipt.",
        "Sistem langsung mencocokkan kode unik transaksi (contoh:": "The system instantly matches the unique transaction code (e.g.:",
        "Jika status pembayaran": "If payment status is",
        "Done": "Done",
        ", izinkan pengunjung masuk ke area kolam.": ", allow the visitor to enter the pool area.",
        "Jika status masih": "If status is still",
        "Not Yet (Belum Lunas)": "Not Yet (Unpaid)",
        "Belum Lunas": "Unpaid",
        ", mintakan pelunasan tunai di loket.": ", request cash payment at the counter.",
        "4. Laporan Retribusi Standar Pemkab Jember": "4. Revenue Reporting Jember Regency Standard",
        "Administrator mengakses menu": "Administrator accesses the",
        "Laporan Omzet": "Revenue Reports",
        "(Harian, Bulanan, Tahunan).": "(Daily, Monthly, Yearly).",
        "Tentukan filter tanggal atau periode bulan yang ingin direkapitulasi.": "Specify the date filter or monthly period to recapitulate.",
        "Pratinjau Dokumen Pemkab": "Preview Regency Document",
        "untuk melihat format resmi naskah dinas.": "to view official government document format.",
        "Periksa kesesuaian rincian lembar tiket, akumulasi nominal pendapatan, dan tanda tangan pimpinan.": "Check the ticket breakdown, total revenue accumulation, and leadership signatures.",
        "Cetak dokumen pada kertas ukuran A4 atau ekspor ke format PDF untuk arsip resmi dinas.": "Print document on A4 paper or export to PDF format for official department archives.",

        // --- INFORMASI VERSI & SISTEM (VERSION) ---
        "Informasi Versi": "Version Info",
        "Informasi Versi & Sistem": "Version & System Information",
        "Informasi Versi & Sistem - Pemandian Patemon": "Version & System Information - Pemandian Patemon",
        "Info rilis & audit teknis": "Release info & technical audit",
        "Spesifikasi lingkungan server, arsitektur keamanan, dan riwayat pembaruan sistem.": "Server environment specifications, security architecture, and system update history.",
        "Sistem Kasir & Portofolio Wisata Pemandian Patemon": "Cashier System & Tourism Portfolio Pemandian Patemon",
        "Aplikasi manajemen retribusi loket tiket terpadu, pemesanan tiket online, pelaporan akuntabilitas keuangan daerah, dan portofolio wisata alam Tanggul, Jember.": "Integrated ticket counter revenue management, online ticket booking, regional financial accountability reporting, and natural tourism portfolio of Tanggul, Jember.",
        "Versi": "Version",
        "Pemkab Jember - Disparbud": "Jember Regency - Tourism & Culture Office",
        "Dibuat oleh:": "Created by:",
        "Dibuat oleh": "Created by",
        "Repositori GitHub": "GitHub Repository",
        "Kunjungi Repositori GitHub": "Visit GitHub Repository",
        "Informasi Pengembang": "Developer Information",
        "Dibuat oleh Aiyub Heriyanto": "Created by Aiyub Heriyanto",
        "Kode sumber resmi dan pemeliharaan aplikasi Sistem Kasir & Portofolio Wisata Pemandian Patemon Jember.": "Official source code and maintenance of the Cashier System & Tourism Portfolio of Pemandian Patemon Jember.",
        "Lingkungan Server & Runtime": "Server & Runtime Environment",
        "Versi Runtime PHP": "PHP Runtime Version",
        "Database Engine": "Database Engine",
        "Web Server Runtime": "Web Server Runtime",
        "Sistem Operasi Host": "Host Operating System",
        "Zona Waktu (Timezone)": "Timezone",
        "Koneksi Database Aktif": "Active Database Connection",
        "Kepatuhan & Fitur Keamanan": "Security Compliance & Features",
        "CSRF Token Protection": "CSRF Token Protection",
        "Setiap mutasi form POST dilindungi token acak berbasis sesi dengan perbandingan string aman `hash_equals`.": "Every POST form mutation is protected by a session-based random token with secure `hash_equals` string comparison.",
        "SQL Injection Mitigation": "SQL Injection Mitigation",
        "Seluruh operasi pembacaan dan penyimpanan data menerapkan PDO / MySQLi Prepared Statements berparameter.": "All data read and write operations apply parameterized PDO / MySQLi Prepared Statements.",
        "Bcrypt Password Hashing": "Bcrypt Password Hashing",
        "Kredensial pengguna dienkripsi dengan fungsi hash satu arah Bcrypt dengan salt dinamis.": "User credentials are encrypted using one-way Bcrypt hashing with dynamic salt.",
        "Role-Based Access Control (RBAC)": "Role-Based Access Control (RBAC)",
        "Pemisahan hak akses ketat antara Super Admin (Level 1), Admin (Level 2), Staf Kasir (Level 3), dan Pengunjung Publik (Level 0).": "Strict access separation between Super Admin (Level 1), Admin (Level 2), Cashier Staff (Level 3), and Public Visitors (Level 0).",
        "Riwayat Pembaruan Sistem (Changelog)": "System Update History (Changelog)",
        "Riwayat Pembaruan Sistem": "System Update History",
        "Pembaruan Besar Standar Tata Kelola Pemda Jember": "Major Upgrade for Jember Regency Governance Standards",
        "Pembersihan autoloader Composer dan integrasi pustaka dompdf/dompdf & picqer/php-barcode-generator.": "Composer autoloader cleanup and integration of dompdf/dompdf & picqer/php-barcode-generator libraries.",
        "Implementasi Clean Routing Front Controller (URL bersih tanpa ekstensi .php).": "Clean Routing Front Controller implementation (clean URLs without .php extension).",
        "Arsitektur Master Layout terpadu untuk efisiensi kode dan konsistensi UI.": "Unified Master Layout architecture for code efficiency and UI consistency.",
        "Pemisahan metode pembayaran Scan QRIS manual statis dan Transfer Bank dengan nomor rekening resmi.": "Separation of static manual QRIS Scan and Bank Transfer with official bank account number.",
        "Penyediaan arsitektur modular Dynamic QRIS / Payment Gateway (siap integrasi Midtrans/Xendit).": "Provision of modular Dynamic QRIS / Payment Gateway architecture (ready for Midtrans/Xendit integration).",
        "Active tag navbar dinamis dengan IntersectionObserver.": "Dynamic navbar active state with IntersectionObserver.",
        "Penambahan kolom ikon dinamis pada kategori tiket (Lansia, Dewasa, Anak, VIP, dll.).": "Addition of dynamic icon column for ticket categories (Elderly, Adult, Child, VIP, etc.).",
        "Standarisasi Laporan Kedinasan format resmi Pemerintah Kabupaten Jember (Dinas Pariwisata dan Kebudayaan) dengan Halaman Pratinjau PDF sebelum cetak.": "Official government reporting format standardization for Jember Regency (Tourism & Culture Office) with PDF Preview page before printing.",
        "Validasi form kritik dan saran serta penyediaan pop-up modal detail ulasan bagi admin.": "Feedback & review form validation and review detail modal pop-up for admins.",
        "Multi-field search bar (Nama, ID/Kode, Tanggal, Metode, Status) di seluruh panel kasir & admin.": "Multi-field search bar (Name, ID/Code, Date, Method, Status) across all cashier & admin panels.",
        "Pemisahan nomor urut tabel dan format kode referensi standar TRX-YYYYMMDD-XXXX.": "Separation of table sequence numbers and standard reference code format TRX-YYYYMMDD-XXXX.",
        "Adopsi standar SIM-ASET: Dashboard widget Libur Nasional (Kemendesa API), Profil Pengguna, Buku Panduan Pengguna, dan Informasi Versi.": "SIM-ASET standard adoption: National Holiday dashboard widget (Kemendesa API), User Profile, User Guide, and Version Info.",
        "Rilis Awal Kasir & Portofolio": "Initial Cashier & Portfolio Release"
    };

    // Ekstensi Kamus untuk AUTH, DASHBOARD, SIDEBAR, TRANSAKSI
    Object.assign(DICTIONARY, {
        // --- AUTH PAGES ---
        "Selamat Datang": "Welcome",
        "Selamat Datang Kembali": "Welcome Back",
        "Masukkan akun Anda untuk masuk ke sistem loket kasir.": "Enter your credentials to access the cashier counter system.",
        "Silakan masuk ke akun Anda untuk melanjutkan akses sistem.": "Please sign in to your account to continue.",
        "Username / Email": "Username / Email",
        "Masukkan username atau email": "Enter your username or email",
        "Kata Sandi": "Password",
        "Password": "Password",
        "Masukkan password Anda": "Enter your password",
        "Lupa Password?": "Forgot Password?",
        "Lupa Kata Sandi?": "Forgot Password?",
        "Masuk Sekarang": "Sign In Now",
        "Belum punya akun?": "Don't have an account?",
        "Daftar Akun Baru": "Register New Account",
        "Kembali ke Beranda": "Back to Homepage",
        "Buat Akun Baru": "Create New Account",
        "Lengkapi formulir di bawah ini untuk mendaftar akun.": "Complete the form below to register an account.",
        "Nama Lengkap": "Full Name",
        "Nama Lengkap (maks. 50 karakter)": "Full Name (max. 50 chars)",
        "Username (maks. 30 karakter)": "Username (max. 30 chars)",
        "Konfirmasi Password": "Confirm Password",
        "Konfirmasi Kata Sandi": "Confirm Password",
        "Ulangi password": "Repeat password",
        "Min. 6 karakter": "Min. 6 characters",
        "Sudah memiliki akun?": "Already have an account?",
        "Sudah punya akun?": "Already have an account?",
        "Masuk di sini": "Sign in here",
        "Pemulihan Kata Sandi": "Password Recovery",
        "Pemulihan Akun": "Account Recovery",
        "Reset password akun staf atau pengunjung dengan verifikasi nomor telepon terdaftar.": "Reset staff or visitor account password with registered phone number verification.",
        "Verifikasi Akun": "Account Verification",
        "Langkah 1: Verifikasi Akun": "Step 1: Account Verification",
        "Langkah 2: Buat Kata Sandi Baru": "Step 2: Create New Password",
        "Nomor Telepon / WhatsApp Terdaftar": "Registered Phone / WhatsApp Number",
        "Lanjut ke Langkah 2": "Proceed to Step 2",
        "Kata Sandi Baru": "New Password",
        "Ulangi Kata Sandi Baru": "Repeat New Password",
        "Simpan Kata Sandi Baru": "Save New Password",
        "Kata Sandi Berhasil Diperbarui!": "Password Successfully Updated!",
        "Masuk dengan Kata Sandi Baru": "Log In with New Password",

        // --- DASHBOARD & SIDEBAR ---
        "Dashboard": "Dashboard",
        "Dashboard Staf Kasir": "Cashier Staff Dashboard",
        "Dashboard Ringkasan Eksekutif": "Executive Summary Dashboard",
        "Lihat Website": "View Website",
        "Navigasi Utama": "Main Navigation",
        "Loket & Kasir": "Ticketing & Cashier",
        "LOKET & KASIR": "TICKETING & CASHIER",
        "NAVIGASI UTAMA": "MAIN NAVIGATION",
        "ANALITIK & LAPORAN": "ANALYTICS & REPORTS",
        "Analitik & Laporan": "Analytics & Reports",
        "PENGATURAN & STANDAR": "SETTINGS & SYSTEM",
        "Pengaturan & Standar": "Settings & System",
        "Transaksi Kasir": "Cashier Transactions",
        "Kelola Transaksi Tiket": "Manage Ticket Transactions",
        "Kategori Tiket": "Ticket Categories",
        "Tarif & Kategori Tiket": "Ticket Rates & Categories",
        "Kasir Loket": "Cashier POS",
        "Kasir Loket (POS)": "Cashier POS",
        "Laporan Omzet": "Revenue Reports",
        "Laporan Saya": "My Reports",
        "Manajemen User": "User Management",
        "Profil Saya": "My Profile",
        "Profil Akun & Keamanan": "Account Profile & Security",
        "TOTAL TRANSAKSI": "TOTAL TRANSACTIONS",
        "TOTAL OMZET LOKET": "TOTAL CASHIER REVENUE",
        "TOTAL OMZET": "TOTAL REVENUE",
        "TIKET TERVERIFIKASI": "VERIFIED TICKETS",
        "TIKET TERJUAL": "TICKETS SOLD",
        "TOTAL PENGGUNA": "TOTAL USERS",
        "TRANSAKSI TERBARU": "RECENT TRANSACTIONS",
        "TRANSAKSI HARI INI": "TODAY'S TRANSACTIONS",
        "OMZET HARI INI": "TODAY'S REVENUE",
        "ULASAN MASUK": "INCOMING REVIEWS",
        "TOTAL TRANSAKSI LOKET": "TOTAL CASHIER TRANSACTIONS",
        "TOTAL TIKET TERVERIFIKASI": "TOTAL VERIFIED TICKETS",
        "Pemandian Patemon - Sistem Kasir & Portofolio": "Pemandian Patemon - Cashier System & Portfolio",

        // --- POS / TRANSAKSI ---
        "Transaksi Baru (POS)": "New Transaction (POS)",
        "Scan Barcode Nota": "Scan Receipt Barcode",
        "Buka Scanner Barcode": "Open Barcode Scanner",
        "Tutup Scanner": "Close Scanner",
        "Tambah Transaksi": "Add Transaction",
        "Export Excel": "Export Excel",
        "Cetak Tabel": "Print Table",
        "Cari": "Search",
        "Cari transaksi...": "Search transactions...",
        "Filter Tanggal": "Date Filter",
        "Semua": "All",
        "NO": "NO",
        "KODE REFERENSI": "REFERENCE CODE",
        "NAMA PEMESAN": "CUSTOMER NAME",
        "TGL KUNJUNGAN": "VISIT DATE",
        "METODE BAYAR": "PAYMENT METHOD",
        "TOTAL BAYAR": "TOTAL PAYMENT",
        "BUKTI BAYAR": "PAYMENT PROOF",
        "STATUS": "STATUS",
        "AKSI": "ACTION",
        "Selesai": "Completed",
        "Belum Selesai": "Pending",
        "Sudah Dibayar (Lunas)": "Paid (Completed)",
        "Menunggu Pembayaran": "Awaiting Payment",
        "TUNAI": "CASH",
        "Tunai": "Cash",
        "Tunai (Cash)": "Cash (Tunai)",
        "TRANSFER BANK": "BANK TRANSFER",
        "Ringkasan Tagihan": "Billing Summary",
        "TOTAL TAGIHAN LOKET:": "TOTAL CASHIER BILL:",
        "Nominal Uang Diterima (Rp):": "Cash Received (Rp):",
        "Uang Kembalian:": "Change Amount:",
        "Proses & Cetak Nota": "Process & Print Receipt",
        "Batalkan Transaksi": "Cancel Transaction",
        "Pilih Kategori & Jumlah Tiket": "Select Categories & Ticket Quantities",
        "Uang Pas": "Exact Cash",
        "Faktur Transaksi": "Transaction Invoice",
        "Rincian detail pemesanan tiket pengunjung pemandian.": "Detail breakdown of visitor ticket reservation.",
        "Cetak Struk Nota": "Print Receipt Voucher",
        "Rincian Tiket Pesanan": "Ordered Ticket Details",
        "Bukti Pembayaran": "Payment Proof",
        "Tanggal Transaksi:": "Transaction Date:",
        "Status Pembayaran:": "Payment Status:",
        "KATEGORI TIKET": "TICKET CATEGORY",
        "JUMLAH": "QUANTITY",
        "SUBTOTAL": "SUBTOTAL",
        "TOTAL KESELURUHAN:": "GRAND TOTAL:",
        "Kembali": "Back",
        "Cetak": "Print",
        "Unduh": "Download",
        "Simpan": "Save",
        "Edit": "Edit",
        "Hapus": "Delete",
        "Batal": "Cancel",
        "Ya, Hapus": "Yes, Delete",
        "Aksi": "Action",
        "Status": "Status",

        // --- SUBHEADINGS ACROSS ADMIN PANEL (Feedback-5 Poin 2) ---
        "Riwayat penjualan tiket loket, validasi bukti transfer, dan cetak struk nota.": "Ticket sales history, payment validation, and receipt printing.",
        "Ringkasan data operasional kasir, tiket, pendapatan retribusi, dan ulasan pengunjung.": "Summary of cashier operations, tickets, revenue, and visitor reviews.",
        "Pencatatan transaksi loket, verifikasi bukti transfer, dan cetak struk masuk.": "Cashier counter transaction records, transfer verification, and entrance ticket receipt printing.",
        "Daftar tarif retribusi tiket pemandian alam resmi berdasarkan peraturan daerah.": "Official natural bathing ticket revenue rates list based on regional regulations.",
        "Rekapitulasi penjualan tiket dan realisasi penerimaan retribusi daerah secara berkala.": "Recapitulation of ticket sales and periodic regional revenue realization.",
        "Tinjau testimoni, masukan publik, dan evaluasi kepuasan layanan Pemandian Patemon.": "Review testimonials, public feedback, and service satisfaction evaluations of Pemandian Patemon.",
        "Kelola akun administrator, staf kasir loket, dan pengunjung terdaftar.": "Manage administrator accounts, counter cashier staff, and registered visitors.",
        "Atur gambar dan deskripsi 3 konten galeri yang tampil di halaman utama website.": "Manage images and descriptions of 3 gallery items displayed on the main website homepage.",
        "Kelola informasi identitas, foto profil, dan kata sandi akun Anda.": "Manage your identity information, profile photo, and account password.",
        "Pedoman standar operasional (SOP) kasir, loket, pemesanan tiket, validasi barcode, dan pelaporan akuntabilitas.": "Standard operating procedures (SOP) for cashier, counter, ticket booking, barcode validation, and accountability reporting.",
        "Spesifikasi lingkungan server, arsitektur keamanan, dan riwayat pembaruan sistem.": "Server environment specifications, security architecture, and system update history.",
        "Kelola sensor kata kasar untuk pendaftaran akun, ulasan publik, dan formulir sistem.": "Manage profanity sensors for account registration, public reviews, and system forms.",
        "Pemantauan real-time aktivitas permintaan HTTP, respons status server, dan latency.": "Real-time monitoring of HTTP request activity, server response status, and latency.",
        "Audit trail perubahan data sistem serta pemantauan & pemulihan transaksi yang dihapus.": "System data modification audit trail with soft-deleted transaction tracking and recovery.",

        // --- ADMINISTRATOR SETTINGS & LOG MONITORING (Feedback-5 Poin 4 & 7) ---
        "Administrator Settings": "Administrator Settings",
        "Filter Kata Terlarang": "Prohibited Words Filter",
        "Filter Kata Kasar": "Profanity Filter",
        "Log Server": "Server Logs",
        "Log Server & Trafik Jaringan": "Server Logs & Network Traffic",
        "Log History": "History Logs",
        "Log History & Sampah": "History Logs & Trash",
        "Log History & Pemulihan Data": "History Logs & Data Recovery",
        "Sampah Transaksi": "Transaction Trash",
        "Sampah Transaksi (Soft Delete)": "Transaction Trash (Soft Delete)",
        "Log Aktivitas (Audit Trail)": "Activity Logs (Audit Trail)",
        "Total Kata Terlarang": "Total Prohibited Words",
        "Aktif di seluruh formulir publik": "Active across all public forms",
        "Uji Coba Filter Kata Kasar (Simulator)": "Profanity Filter Test (Simulator)",
        "Ketik kalimat apa saja di bawah untuk menguji apakah sistem berhasil mendeteksi kata terlarang secara real-time.": "Type any sentence below to test if the system detects prohibited words in real time.",
        "Periksa": "Check",
        "Tambah Kata Terlarang": "Add Prohibited Word",
        "Kata / Frasa Terlarang": "Prohibited Words / Phrases",
        "Simpan ke Daftar Hitam": "Save to Blacklist",
        "Daftar Kata Terlarang Aktif": "Active Prohibited Words List",
        "Kata-kata ini akan otomatis disensor dan ditolak saat pengisian form.": "These words will be automatically censored and rejected upon form submission.",
        "Total Request": "Total Requests",
        "Permintaan terekam": "Recorded requests",
        "Status 2xx (Sukses)": "2xx Status (Success)",
        "Status 4xx (Client Error)": "4xx Status (Client Error)",
        "Rata-rata Respon": "Average Response Time",
        "Kecepatan server rata-rata": "Average server speed",
        "Semua Method": "All Methods",
        "Semua Status Kode": "All Status Codes",
        "Bersihkan Log": "Clear Logs",
        "Path URI Permintaan": "Request URI Path",
        "Alamat IP": "IP Address",
        "Waktu Request": "Request Time",
        "Waktu & Tanggal": "Time & Date",
        "Pelaku (User)": "Actor (User)",
        "Keterangan Perubahan": "Change Description",
        "Pulihkan": "Restore",
        "Pulihkan Data": "Restore Data",
        "Pulihkan Transaksi": "Restore Transaction",
        "Hapus Permanen": "Delete Permanently",
        "Jumlah Transaksi Dihapus": "Total Deleted Transactions",
        "Tersimpan di arsip soft delete & dapat dipulihkan": "Saved in soft-delete archive & can be restored",
        "Total Nilai Arsip Sampah": "Total Trash Archive Value",
        "Tidak dihitung ke dalam laporan omzet aktif": "Excluded from active revenue reports",
        "Dihapus Pada": "Deleted At",
        "Aksi Pemulihan": "Recovery Action",
        "Tempat sampah transaksi kosong. Tidak ada data yang dihapus.": "Transaction trash is empty. No deleted data found.",
        "Tidak ada catatan log aktivitas yang ditemukan.": "No activity log entries found.",
        "Tidak ada catatan log server yang sesuai dengan filter.": "No server log records match the filter.",
        "Arsip Transaksi Terhapus (Sampah)": "Deleted Transactions Archive (Trash)",
        "Transaksi di bawah disembunyikan dari kasir dan laporan, tetapi dapat dipulihkan kapan saja oleh Super Admin.": "Transactions below are hidden from cashier and reports, but can be restored anytime by Super Admin.",
        "Semua Modul": "All Modules",
        "Semua Aksi": "All Actions",

        // --- PANDUAN & GUIDE OPERATIONAL ---
        "Buku Panduan": "User Guide",
        "Buku Panduan Operasional Sistem": "System Operational User Manual",
        "Panduan Pengoperasian Wisata Pemandian Patemon": "Operational Manual for Pemandian Patemon Tourism",
        "Dokumentasi alur kerja terpadu untuk pengelola, staf loket, dan pengunjung.": "Integrated workflow documentation for managers, counter staff, and visitors.",
        "Buka Dokumen PDF Panduan": "Open PDF Manual Document",
        "1. Alur Pemesanan Tiket Online (Pengunjung)": "1. Online Ticket Booking Flow (Visitors)",
        "2. SOP Staf Kasir Loket (Input POS)": "2. Counter Cashier Staff SOP (POS Input)",
        "3. Prosedur Validasi Barcode di Pintu Masuk": "3. Barcode Validation Procedure at Entrance Gate",
        "4. Laporan Retribusi Standar Pemkab Jember": "4. Jember Regency Standard Revenue Reporting",
        "Pengunjung membuka website dan memilih tombol": "Visitors open the website and click",
        "Pengunjung menentukan jumlah lembar tiket untuk tiap kategori": "Visitors specify the number of tickets for each category",
        "Setelah form dikirim, sistem otomatis menerbitkan": "After submitting the form, the system automatically issues a",
        "Nota Digital Ber-Barcode": "Barcode Digital Receipt"
    });

    /**
     * Smart Translation Finder:
     * Menangani pencocokan eksak, variasi tanda baca (titik, titik dua, kurung),
     * pola prefix versi (Versi 2.0.0), slot galeri, dan rincian kuantitas.
     */
    function findTranslation(text) {
        if (!text) return null;
        const trimmed = text.trim();
        if (!trimmed) return null;

        // 1. Direct exact match
        if (typeof DICTIONARY[trimmed] !== 'undefined') {
            return DICTIONARY[trimmed];
        }

        // 2. Case-insensitive exact match
        const lower = trimmed.toLowerCase();
        for (const [key, val] of Object.entries(DICTIONARY)) {
            if (key.toLowerCase() === lower) {
                return val;
            }
        }

        // 3. Dynamic patterns: "Versi X.Y.Z"
        const verMatch = trimmed.match(/^Versi\s+([0-9vV\.\-]+.*)$/i);
        if (verMatch) {
            return "Version " + verMatch[1];
        }

        // 4. Dynamic patterns: "Edit Galeri Slot #X"
        const slotMatch = trimmed.match(/^Edit Galeri Slot\s+#?(\d+)$/i);
        if (slotMatch) {
            return "Edit Gallery Slot #" + slotMatch[1];
        }

        // 5. Dynamic patterns: "RINCIAN TIKET MASUK (TOTAL X TIKET)"
        const rincianMatch = trimmed.match(/^RINCIAN TIKET MASUK\s*\(TOTAL\s+(\d+)\s+TIKET\)$/i);
        if (rincianMatch) {
            return "ADMISSION TICKET DETAILS (TOTAL " + rincianMatch[1] + " TICKETS)";
        }

        // 6. Leading colon / symbols (e.g. ": Scan gambar...", ": Salin...")
        if (/^[:,\-\s]+/.test(trimmed)) {
            const clean = trimmed.replace(/^[:,\-\s]+/, '');
            const trans = findTranslation(clean);
            if (trans) {
                const prefix = trimmed.match(/^[:,\-\s]+/)[0];
                return prefix + trans;
            }
        }

        // 7. Trailing punctuation (e.g. "teks.", "teks,", "teks:")
        if (/[:,\.!?]+$/.test(trimmed)) {
            const clean = trimmed.replace(/[:,\.!?]+$/, '');
            const trans = findTranslation(clean);
            if (trans) {
                const suffix = trimmed.match(/[:,\.!?]+$/)[0];
                return trans + suffix;
            }
        }

        // 8. Surrounding brackets or quotes
        if ((trimmed.startsWith('(') && trimmed.endsWith(')')) ||
            (trimmed.startsWith('[') && trimmed.endsWith(']')) ||
            (trimmed.startsWith('"') && trimmed.endsWith('"')) ||
            (trimmed.startsWith('“') && trimmed.endsWith('”'))) {
            const inner = trimmed.slice(1, -1).trim();
            const trans = findTranslation(inner);
            if (trans) {
                return trimmed[0] + trans + trimmed[trimmed.length - 1];
            }
        }

        return null;
    }

    /**
     * Walk text nodes safely without destroying DOM elements or listeners
     */
    function translateDOM(targetLang) {
        if (!document.body) return;

        const isEnglish = (targetLang === 'en');

        // 1. Walker untuk Text Node
        const walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function(node) {
                    if (!node.nodeValue || !node.nodeValue.trim()) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    const parent = node.parentElement;
                    if (!parent) return NodeFilter.FILTER_REJECT;

                    const tag = parent.tagName.toLowerCase();
                    // Abaikan tag kode, script, style, textarea, input, svg, dll.
                    if (['script', 'style', 'textarea', 'input', 'select', 'svg', 'code', 'pre', 'noscript'].includes(tag)) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Abaikan elemen switcher bahasa atau yang memiliki class / attribute khusus
                    if (parent.closest('.btn-lang-switcher') || parent.closest('[data-no-translate]') || parent.classList.contains('font-monospace')) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            }
        );

        const nodesToProcess = [];
        while (walker.nextNode()) {
            nodesToProcess.push(walker.currentNode);
        }

        nodesToProcess.forEach(node => {
            // Simpan teks asli (Bahasa Indonesia) di properti node
            if (typeof node._patemonOrigText === 'undefined') {
                node._patemonOrigText = node.nodeValue;
            }

            const orig = node._patemonOrigText;
            const trimmed = orig.trim();

            if (isEnglish) {
                const translated = findTranslation(trimmed);
                if (translated) {
                    const leadingMatch = orig.match(/^\s*/);
                    const trailingMatch = orig.match(/\s*$/);
                    const leading = leadingMatch ? leadingMatch[0] : '';
                    const trailing = trailingMatch ? trailingMatch[0] : '';
                    node.nodeValue = leading + translated + trailing;
                }
            } else {
                node.nodeValue = orig;
            }
        });

        // 2. Translasi Placeholder (input & textarea)
        document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach(el => {
            if (!el.getAttribute('data-i18n-orig-ph')) {
                el.setAttribute('data-i18n-orig-ph', el.getAttribute('placeholder'));
            }
            const origPh = el.getAttribute('data-i18n-orig-ph');
            const trimmedPh = origPh.trim();
            if (isEnglish) {
                const trans = findTranslation(trimmedPh);
                if (trans) {
                    el.setAttribute('placeholder', trans);
                }
            } else {
                el.setAttribute('placeholder', origPh);
            }
        });

        // 3. Translasi Title / Tooltip
        document.querySelectorAll('[title]:not(.btn-lang-switcher)').forEach(el => {
            if (!el.getAttribute('data-i18n-orig-title')) {
                el.setAttribute('data-i18n-orig-title', el.getAttribute('title'));
            }
            const origTitle = el.getAttribute('data-i18n-orig-title');
            const trimmedTitle = origTitle.trim();
            if (isEnglish) {
                const trans = findTranslation(trimmedTitle);
                if (trans) {
                    el.setAttribute('title', trans);
                }
            } else {
                el.setAttribute('title', origTitle);
            }
        });

        // 4. Update elemen khusus dengan data-i18n eksplisit
        document.querySelectorAll('[data-i18n]').forEach(el => {
            if (!el.getAttribute('data-i18n-orig-val')) {
                el.setAttribute('data-i18n-orig-val', el.textContent.trim());
            }
            const key = el.getAttribute('data-i18n');
            if (isEnglish) {
                const trans = findTranslation(key) || (DICTIONARY[key] ?? null);
                if (trans) {
                    el.textContent = trans;
                }
            } else {
                el.textContent = el.getAttribute('data-i18n-orig-val');
            }
        });
    }

    /**
     * Render icon bendera visual SVG pada tombol switcher
     */
    function updateLanguageSwitcherButtons(lang) {
        document.querySelectorAll('.btn-lang-switcher').forEach(btn => {
            if (lang === 'en') {
                btn.innerHTML = `${FLAG_EN_SVG}<strong>EN</strong>`;
                btn.setAttribute('title', 'Beralih ke Bahasa Indonesia');
                btn.setAttribute('aria-label', 'Switch to Indonesian Language');
            } else {
                btn.innerHTML = `${FLAG_ID_SVG}<strong>ID</strong>`;
                btn.setAttribute('title', 'Switch to English Language');
                btn.setAttribute('aria-label', 'Switch to English Language');
            }
        });
    }

    /**
     * Terapkan bahasa pilihan
     */
    function applyLanguage(lang) {
        lang = (lang === 'en') ? 'en' : 'id';
        localStorage.setItem('patemon_lang', lang);
        document.documentElement.setAttribute('lang', lang);

        updateLanguageSwitcherButtons(lang);
        translateDOM(lang);

        // Broadcast event agar modul waktu dinamis ikut mengupdate format hari/tanggal
        window.dispatchEvent(new CustomEvent('patemon_language_changed', { detail: { lang: lang } }));
    }

    /**
     * Beralih antara ID dan EN
     */
    function toggleLanguage() {
        const currentLang = localStorage.getItem('patemon_lang') || 'id';
        const newLang = (currentLang === 'id') ? 'en' : 'id';
        applyLanguage(newLang);
    }

    // Ekspor fungsi ke global scope
    window.setPatemonLanguage = applyLanguage;
    window.togglePatemonLanguage = toggleLanguage;
    window.getPatemonLanguage = function() {
        return localStorage.getItem('patemon_lang') || 'id';
    };

    // Inisialisasi saat DOM siap
    document.addEventListener('DOMContentLoaded', function() {
        const savedLang = localStorage.getItem('patemon_lang') || 'id';
        if (savedLang === 'en') {
            applyLanguage('en');
        } else {
            updateLanguageSwitcherButtons('id');
        }
    });

    // Handle Bootstrap modal jika konten dinamis dibuka
    document.addEventListener('shown.bs.modal', function() {
        const currentLang = localStorage.getItem('patemon_lang') || 'id';
        if (currentLang === 'en') {
            translateDOM('en');
        }
    });

})();
