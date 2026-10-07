/**
 * Universal Image Upload Validator
 * Mencegah pengunggahan gambar > 2 MB ke server (Nginx 413 Entity Too Large)
 * Menampilkan notifikasi SweetAlert2 jika ukuran file melebihi 2 MB.
 */
(function() {
    'use strict';

    const MAX_IMAGE_SIZE = 2 * 1024 * 1024; // 2.097.152 bytes (2 MB)
    const OVERSIZE_MESSAGE = 'gambar yang anda upload melebihi ukuran 2mb silahkan upload gambar lain';

    function isImageFile(file, input) {
        if (!file) return false;
        if (file.type && file.type.toLowerCase().startsWith('image/')) return true;
        
        // Cek ekstensi nama file
        const name = (file.name || '').toLowerCase();
        if (/\.(jpe?g|png|webp|gif|bmp|svg|ico)$/i.test(name)) return true;

        // Cek atribut accept pada input
        const accept = (input && input.getAttribute('accept') || '').toLowerCase();
        if (accept.includes('image') || accept.includes('.jpg') || accept.includes('.jpeg') || accept.includes('.png') || accept.includes('.webp')) {
            return true;
        }

        return false;
    }

    function showOversizeAlert() {
        if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
            Swal.fire({
                icon: 'warning',
                title: 'Ukuran Terlalu Besar',
                text: OVERSIZE_MESSAGE,
                confirmButtonColor: '#0284c7',
                confirmButtonText: 'Paham'
            });
        } else {
            alert(OVERSIZE_MESSAGE);
        }
    }

    function clearAssociatedPreviews(input) {
        if (!input) return;
        
        // Reset preview avatar profil jika ada
        if (input.id === 'avatarInput') {
            const avatarCropped = document.getElementById('avatarCroppedData');
            if (avatarCropped) avatarCropped.value = '';
            const cropperTarget = document.getElementById('cropperImageTarget');
            if (cropperTarget) cropperTarget.src = '';
            const avatarPreviewImg = document.getElementById('avatarPreviewImg');
            const avatarPreviewIcon = document.getElementById('avatarPreviewIcon');
            if (avatarPreviewImg && avatarPreviewImg.dataset.initialSrc) {
                avatarPreviewImg.src = avatarPreviewImg.dataset.initialSrc;
                avatarPreviewImg.style.display = 'block';
                if (avatarPreviewIcon) avatarPreviewIcon.style.display = 'none';
            } else if (avatarPreviewImg) {
                avatarPreviewImg.src = '';
                avatarPreviewImg.style.display = 'none';
                if (avatarPreviewIcon) avatarPreviewIcon.style.display = 'block';
            }
        }
        
        // Reset preview bukti bayar kasir jika ada
        if (input.id === 'bukti_pembayaran_file') {
            const proofBox = document.getElementById('proofPreviewBox');
            if (proofBox) proofBox.style.display = 'none';
            const proofImg = document.getElementById('proofPreviewImg');
            if (proofImg) proofImg.src = '';
        }

        // Reset preview event flyer jika ada
        if (input.id === 'gambar') {
            const previewImg = document.getElementById('previewEventImg');
            const previewBackdrop = document.getElementById('previewBackdrop');
            const placeholder = document.getElementById('previewPlaceholder');
            if (previewImg) { previewImg.src = ''; previewImg.style.display = 'none'; }
            if (previewBackdrop) previewBackdrop.style.backgroundImage = 'none';
            if (placeholder) placeholder.style.display = 'flex';
        }

        // Reset preview upload tiket pemesanan jika ada
        if (input.id === 'bukti_pembayaran') {
            const previewImg = document.getElementById('preview_img');
            const previewArea = document.getElementById('preview_area');
            if (previewImg) previewImg.src = '';
            if (previewArea) previewArea.classList.add('d-none');
        }
    }

    // Tangkap perubahan input file pada fase capture (berjalan sebelum inline onchange)
    document.addEventListener('change', function(e) {
        const input = e.target;
        if (!input || input.type !== 'file') return;

        const files = input.files;
        if (!files || files.length === 0) return;

        let hasOversized = false;
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (isImageFile(file, input) && file.size > MAX_IMAGE_SIZE) {
                hasOversized = true;
                break;
            }
        }

        if (hasOversized) {
            // Kosongkan file input agar tidak diproses dan tidak terkirim
            input.value = '';
            clearAssociatedPreviews(input);
            
            // Hentikan eksekusi handler lanjutan (seperti preview upload inline)
            e.stopImmediatePropagation();
            e.preventDefault();

            // Tampilkan notifikasi SweetAlert2
            showOversizeAlert();
        }
    }, true);

    // Tangkap saat form akan di-submit sebagai perlindungan lapis kedua
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || !form.querySelectorAll) return;

        const fileInputs = form.querySelectorAll('input[type="file"]');
        let hasOversized = false;

        fileInputs.forEach(function(input) {
            const files = input.files;
            if (!files || files.length === 0) return;
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (isImageFile(file, input) && file.size > MAX_IMAGE_SIZE) {
                    hasOversized = true;
                    input.value = '';
                    clearAssociatedPreviews(input);
                }
            }
        });

        if (hasOversized) {
            e.preventDefault();
            e.stopImmediatePropagation();
            showOversizeAlert();
        }
    }, true);

})();
