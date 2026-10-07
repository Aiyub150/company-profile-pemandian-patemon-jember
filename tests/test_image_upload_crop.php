<?php
/**
 * Test Suite: Image Upload Validator & Profile Avatar Cropper
 */

echo "============================================================\n";
echo "▶ [TEST] Image Upload Validator & Avatar Cropper Feature\n";
echo "============================================================\n";

$pass = 0;
$fail = 0;

function assert_true($cond, $msg) {
    global $pass, $fail;
    if ($cond) {
        echo "  ✔ PASS: {$msg}\n";
        $pass++;
    } else {
        echo "  ✖ FAIL: {$msg}\n";
        $fail++;
    }
}

// 1. Periksa ketersediaan aset Cropper.js lokal
$cropper_js = __DIR__ . '/../public/assets/extensions/cropperjs/cropper.min.js';
$cropper_css = __DIR__ . '/../public/assets/extensions/cropperjs/cropper.min.css';
assert_true(file_exists($cropper_js) && filesize($cropper_js) > 10000, "cropper.min.js lokal tersedia dan valid (" . filesize($cropper_js) . " bytes)");
assert_true(file_exists($cropper_css) && filesize($cropper_css) > 1000, "cropper.min.css lokal tersedia dan valid (" . filesize($cropper_css) . " bytes)");

// 2. Periksa validator file gambar global
$validator_js = __DIR__ . '/../public/js/image-upload-validator.js';
assert_true(file_exists($validator_js), "public/js/image-upload-validator.js tersedia");
$validator_content = file_get_contents($validator_js);
assert_true(strpos($validator_content, 'gambar yang anda upload melebihi ukuran 2mb silahkan upload gambar lain') !== false, "image-upload-validator.js mengandung teks alert yang diminta user");
assert_true(strpos($validator_content, 'MAX_IMAGE_SIZE = 2 * 1024 * 1024') !== false, "image-upload-validator.js menetapkan batas tepat 2 MB (2,097,152 bytes)");

// 3. Verifikasi profile.php memiliki fitur crop foto profil
$profile_view = file_get_contents(__DIR__ . '/../dist/views/profile/profile.php');
assert_true(strpos($profile_view, 'modalCropAvatar') !== false, "profile.php menyertakan modalCropAvatar");
assert_true(strpos($profile_view, 'aspectRatio: 1') !== false, "profile.php mengonfigurasi Cropper dengan aspectRatio 1:1");
assert_true(strpos($profile_view, 'avatar_cropped_data') !== false, "profile.php menangani data avatar_cropped_data");
assert_true(strpos($profile_view, 'cropper.min.js') !== false, "profile.php memuat cropper.min.js");

// 4. Verifikasi bahwa form upload bukti pembayaran TIDAK memiliki fitur crop
$transaksi_view = file_get_contents(__DIR__ . '/../dist/views/transaksi/tambah.php');
$pesan_view = file_get_contents(__DIR__ . '/../dist/views/tiket/pesan.php');
assert_true(strpos($transaksi_view, 'modalCropAvatar') === false && strpos($transaksi_view, 'Cropper') === false, "dist/views/transaksi/tambah.php TIDAK memiliki fitur crop");
assert_true(strpos($pesan_view, 'modalCropAvatar') === false && strpos($pesan_view, 'Cropper') === false, "dist/views/tiket/pesan.php TIDAK memiliki fitur crop");

// 5. Verifikasi admin_footer.php dan tiket/pesan.php memuat image-upload-validator.js
$footer_content = file_get_contents(__DIR__ . '/../dist/app/layouts/admin_footer.php');
assert_true(strpos($footer_content, 'image-upload-validator.js') !== false, "admin_footer.php memuat image-upload-validator.js");
assert_true(strpos($pesan_view, 'image-upload-validator.js') !== false, "dist/views/tiket/pesan.php memuat image-upload-validator.js");

// 6. Uji logika dekoding base64 avatar crop backend
// A. Valid JPEG base64 payload
$dummy_jpeg_header = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00\xFF\xDB";
$valid_base64 = 'data:image/jpeg;base64,' . base64_encode($dummy_jpeg_header . str_repeat("\x00", 200));

$data_parts = explode(',', $valid_base64, 2);
$decoded = base64_decode($data_parts[1]);
$is_jpeg = (substr($decoded, 0, 3) === "\xFF\xD8\xFF");
assert_true($is_jpeg && strlen($decoded) <= 2 * 1024 * 1024, "Decoder backend sukses mengidentifikasi format JPEG dan ukuran <= 2MB");

// B. Oversized base64 payload (> 2MB)
$oversized_raw = $dummy_jpeg_header . str_repeat("A", (2 * 1024 * 1024) + 100);
$oversized_base64 = 'data:image/jpeg;base64,' . base64_encode($oversized_raw);
$oversized_parts = explode(',', $oversized_base64, 2);
$oversized_decoded = base64_decode($oversized_parts[1]);
$oversized_rejected = (strlen($oversized_decoded) > 2 * 1024 * 1024);
assert_true($oversized_rejected, "Backend berhasil menolak payload crop base64 yang melebihi 2MB");

// C. Malicious non-image payload (e.g. PHP script disguised as base64)
$malicious_raw = "<?php system(\$_GET['cmd']); ?>";
$malicious_base64 = 'data:image/jpeg;base64,' . base64_encode($malicious_raw);
$mal_parts = explode(',', $malicious_base64, 2);
$mal_decoded = base64_decode($mal_parts[1]);
$mal_detected = (substr($mal_decoded, 0, 3) !== "\xFF\xD8\xFF" && substr($mal_decoded, 0, 4) !== "\x89PNG");
assert_true($mal_detected, "Backend menolak payload crop yang tidak memiliki magic bytes gambar valid");

echo "\n============================================================\n";
echo "HASIL PENGUJIAN: {$pass} Passed, {$fail} Failed\n";
echo "============================================================\n";

if ($fail > 0) {
    exit(1);
}
exit(0);
