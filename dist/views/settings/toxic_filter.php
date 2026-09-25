<?php
/**
 * Modul Filter Kata Kasar / Toxic Words Management
 * Role: Super Admin (Level 1) & Admin (Level 2)
 */
require_once __DIR__ . '/../../app/config.php';
check_auth([1, 2]);

// Lightweight test API response if queried via ajax
if (isset($_GET['action']) && $_GET['action'] === 'test') {
    header('Content-Type: application/json');
    $q = $_GET['q'] ?? '';
    $detected = find_toxic_words($q);
    echo json_encode([
        'is_toxic' => !empty($detected),
        'detected' => $detected
    ]);
    exit();
}

$active_menu     = 'settings_toxic';
$page_title      = 'Filter Kata Terlarang - Pengaturan Sistem';
$page_heading    = 'Filter Kata Terlarang';
$page_subheading = 'Kelola sensor kata kasar untuk pendaftaran akun, ulasan publik, dan formulir sistem.';

$csrf_token = get_csrf_token();
$msg_success = '';
$msg_error   = '';

// Handle Form Submission (Add Word / Batch Words)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $posted_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($posted_token)) {
        $msg_error = 'Token keamanan tidak valid atau telah kadaluarsa. Silakan muat ulang halaman.';
    } elseif ($action === 'add') {
        $raw_input = trim($_POST['words'] ?? '');
        if (empty($raw_input)) {
            $msg_error = 'Silakan masukkan minimal satu kata terlarang.';
        } else {
            // Support comma, newline, or space separated words
            $items = preg_split('/[\r\n,]+/', $raw_input);
            $added = 0;
            $duplicates = 0;
            
            $stmt = $conn->prepare("INSERT IGNORE INTO toxic_words (word) VALUES (?)");
            foreach ($items as $item) {
                $w = strtolower(trim($item));
                if (strlen($w) >= 2) {
                    $stmt->bind_param("s", $w);
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) {
                        $added++;
                    } else {
                        $duplicates++;
                    }
                }
            }
            $stmt->close();
            
            if ($added > 0) {
                log_activity('TAMBAH', 'toxic_words', "Menambahkan {$added} kata terlarang baru.");
                $msg_success = "Berhasil menambahkan {$added} kata terlarang." . ($duplicates > 0 ? " ({$duplicates} kata sudah ada sebelumnya)." : "");
            } else {
                $msg_error = "Tidak ada kata baru yang ditambahkan (kata sudah terdaftar atau terlalu pendek).";
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Get word first for logging
            $stmt_get = $conn->prepare("SELECT word FROM toxic_words WHERE id = ? LIMIT 1");
            $stmt_get->bind_param("i", $id);
            $stmt_get->execute();
            $res = $stmt_get->get_result()->fetch_assoc();
            $stmt_get->close();
            $target_word = $res['word'] ?? "ID #{$id}";
            
            $stmt = $conn->prepare("DELETE FROM toxic_words WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                log_activity('DELETE', 'toxic_words', "Menghapus kata terlarang: {$target_word}");
                $msg_success = "Kata '{$target_word}' berhasil dihapus dari filter sensor.";
            } else {
                $msg_error = "Gagal menghapus kata terlarang.";
            }
            $stmt->close();
        }
    }
}

// Search & Pagination
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 30;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) as total FROM toxic_words";
$data_sql  = "SELECT id, word, created_at FROM toxic_words";

if (!empty($search)) {
    $search_like = '%' . $search . '%';
    $stmt_c = $conn->prepare("SELECT COUNT(*) as total FROM toxic_words WHERE word LIKE ?");
    $stmt_c->bind_param("s", $search_like);
    $stmt_c->execute();
    $total_rows = (int)($stmt_c->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt_c->close();
    
    $stmt_d = $conn->prepare("SELECT id, word, created_at FROM toxic_words WHERE word LIKE ? ORDER BY word ASC LIMIT ? OFFSET ?");
    $stmt_d->bind_param("sii", $search_like, $limit, $offset);
    $stmt_d->execute();
    $words_result = $stmt_d->get_result();
} else {
    $c_res = $conn->query($count_sql);
    $total_rows = (int)($c_res->fetch_assoc()['total'] ?? 0);
    
    $stmt_d = $conn->prepare("SELECT id, word, created_at FROM toxic_words ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt_d->bind_param("ii", $limit, $offset);
    $stmt_d->execute();
    $words_result = $stmt_d->get_result();
}

$total_pages = ceil($total_rows / $limit);

require_once __DIR__ . '/../../app/layouts/admin_header.php';
?>

<div class="row g-4 mb-4">
    <!-- Quick Stats -->
    <div class="col-12 col-md-4">
        <div class="card h-100 border-0 shadow-sm" style="border-radius: 16px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(220, 38, 38, 0.02)); border-left: 4px solid #ef4444 !important;">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px; background: rgba(239, 68, 68, 0.15); color: #dc2626;">
                    <i class="fa-solid fa-shield-halved fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Kata Terlarang</div>
                    <h3 class="fw-bold mb-0 text-danger"><?= number_format($total_rows) ?></h3>
                    <div class="text-muted small">Aktif di seluruh formulir publik</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sandbox Simulator -->
    <div class="col-12 col-md-8">
        <div class="card h-100 border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-2 text-primary d-flex align-items-center gap-2">
                    <i class="fa-solid fa-vial-virus"></i> Uji Coba Filter Kata Kasar (Simulator)
                </h6>
                <p class="text-muted small mb-2">Ketik kalimat apa saja di bawah untuk menguji apakah sistem berhasil mendeteksi kata terlarang secara real-time.</p>
                <div class="input-group">
                    <input type="text" id="testSentenceInput" class="form-control" placeholder="Contoh kalimat: Tempat ini sangat anjing dan jelek...">
                    <button type="button" class="btn btn-primary" onclick="testFilter()">
                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Periksa
                    </button>
                </div>
                <div id="testResultArea" class="mt-2" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($msg_success)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert" style="border-radius: 12px;">
        <i class="fa-solid fa-circle-check me-2"></i> <?= e($msg_success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($msg_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert" style="border-radius: 12px;">
        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= e($msg_error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form Tambah Kata -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-1"><i class="fa-solid fa-plus-circle text-primary me-2"></i> Tambah Kata Terlarang</h5>
                <p class="text-muted small mb-0">Masukkan kata kotor atau tidak pantas.</p>
            </div>
            <div class="card-body p-4">
                <form action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="words" class="form-label fw-semibold small">Kata / Frasa Terlarang</label>
                        <textarea name="words" id="words" rows="5" class="form-control" placeholder="Contoh:&#10;bodoh&#10;bajingan&#10;sampah&#10;(Gunakan baris baru atau tanda koma untuk batch input)" required></textarea>
                        <div class="form-text small">Huruf kapital akan otomatis dikonversi menjadi huruf kecil. Duplikat akan diabaikan.</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100" style="border-radius: 10px;">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan ke Daftar Hitam
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Tabel Daftar Kata -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="fw-bold mb-1"><i class="fa-solid fa-list-check text-primary me-2"></i> Daftar Kata Terlarang Aktif</h5>
                    <p class="text-muted small mb-0">Kata-kata ini akan otomatis disensor dan ditolak saat pengisian form.</p>
                </div>
                <form action="" method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari kata..." value="<?= e($search) ?>" style="min-width: 160px; border-radius: 8px;">
                    <button type="submit" class="btn btn-sm btn-outline-primary" style="border-radius: 8px;"><i class="fa-solid fa-search"></i></button>
                    <?php if (!empty($search)): ?>
                        <a href="<?= route_url('settings_toxic') ?>" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4" style="width: 60px;">No</th>
                                <th>Kata Terlarang</th>
                                <th>Tanggal Ditambahkan</th>
                                <th class="text-center pe-4" style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($words_result && $words_result->num_rows > 0): ?>
                                <?php 
                                $no = $offset + 1;
                                while ($row = $words_result->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= $no++ ?></td>
                                    <td>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fs-6 fw-semibold font-monospace" style="border-radius: 8px;">
                                            <i class="fa-solid fa-ban me-1"></i> <?= e($row['word']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?= date('d M Y, H:i', strtotime($row['created_at'])) ?>
                                    </td>
                                    <td class="text-center pe-4">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-action-icon" title="Hapus Kata" onclick="confirmDeleteWord(<?= (int)$row['id'] ?>, '<?= e($row['word']) ?>')">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-check-circle fs-2 text-success mb-2 d-block"></i>
                                        <?= !empty($search) ? 'Tidak ditemukan kata yang cocok dengan pencarian.' : 'Belum ada kata terlarang yang didaftarkan.' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-transparent border-0 py-3 px-4 d-flex justify-content-between align-items-center">
                <span class="text-muted small">Menampilkan <?= min($total_rows, $offset + 1) ?> - <?= min($total_rows, $offset + $limit) ?> dari <?= $total_rows ?> kata</span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden POST form for Delete -->
<form id="deleteWordForm" action="" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteWordId" value="">
</form>

<?php
$extra_js = '
<script>
function confirmDeleteWord(id, word) {
    Swal.fire({
        title: "Hapus Kata Terlarang?",
        text: "Kata \'" + word + "\' akan dihapus dari filter sensor.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Ya, Hapus!",
        cancelButtonText: "Batal",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById("deleteWordId").value = id;
            document.getElementById("deleteWordForm").submit();
        }
    });
}

function testFilter() {
    const text = document.getElementById("testSentenceInput").value.trim();
    const resultArea = document.getElementById("testResultArea");
    if (!text) {
        resultArea.style.display = "none";
        return;
    }
    
    // Test locally via current word list or basic regex
    // We can also fetch via lightweight endpoint or test client-side against loaded words
    fetch("' . route_url('settings_toxic') . '?action=test&q=" + encodeURIComponent(text))
        .then(res => res.json())
        .then(data => {
            resultArea.style.display = "block";
            if (data.is_toxic) {
                resultArea.innerHTML = `<div class="alert alert-danger mb-0 p-2 small"><i class="fa-solid fa-triangle-exclamation me-1"></i> <strong>Terdeteksi Kata Kasar:</strong> ${data.detected.join(", ")}</div>`;
            } else {
                resultArea.innerHTML = `<div class="alert alert-success mb-0 p-2 small"><i class="fa-solid fa-check me-1"></i> <strong>Bersih!</strong> Tidak ada kata terlarang yang terdeteksi.</div>`;
            }
        })
        .catch(() => {
            resultArea.style.display = "block";
            resultArea.innerHTML = `<div class="alert alert-info mb-0 p-2 small">Periksa kata selesai. Sistem sensor backend akan otomatis memfilter saat data dikirimkan.</div>`;
        });
}
</script>
';

require_once __DIR__ . '/../../app/layouts/admin_footer.php';
?>
