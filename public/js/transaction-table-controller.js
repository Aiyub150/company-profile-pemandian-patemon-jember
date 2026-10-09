/**
 * Transaction Table Controller & Modern Barcode Scanner
 * Pemandian Patemon - Modern Loket & Kasir UX
 * 
 * Fitur:
 * 1. Live Instant Search (tanpa reload halaman)
 * 2. Filter Waktu & Rentang Tanggal (Live)
 * 3. Filter Status (Lunas / Belum Dibayar)
 * 4. Pagination Tabel Interaktif (10, 25, 50, Semua)
 * 5. Modern Barcode/QR Scanner dengan antarmuka ramah pengguna (Kamera Belakang / Depan)
 * 6. Highlighting otomatis transaksi yang dipindai tanpa reload
 */

(function (window, document) {
    'use strict';

    function playBeepSound() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime); // 880Hz A5 pleasant chime
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.18);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.2);
        } catch (e) {
            // Audio context not allowed or unsupported
        }
    }

    class TransactionTableManager {
        constructor(config) {
            this.table = document.getElementById(config.tableId || 'dataTable');
            this.searchInput = document.getElementById(config.searchInputId || 'searchInput');
            this.datePreset = document.getElementById(config.datePresetId || 'dateFilterSelect');
            this.dateStart = document.getElementById(config.dateStartId || 'dateStartInput');
            this.dateEnd = document.getElementById(config.dateEndId || 'dateEndInput');
            this.statusFilter = document.getElementById(config.statusFilterId || 'statusFilterSelect');
            this.pageSizeSelect = document.getElementById(config.pageSizeId || 'pageSizeSelect');
            this.paginationInfo = document.getElementById(config.paginationInfoId || 'paginationInfo');
            this.paginationNav = document.getElementById(config.paginationNavId || 'paginationNav');

            this.pageSize = 10;
            this.currentPage = 1;
            this.allRows = [];
            this.filteredRows = [];

            if (this.table) {
                this.init();
            }
        }

        init() {
            const tbody = this.table.querySelector('tbody');
            if (!tbody) return;

            // Kumpulkan seluruh baris data asli (kecuali pesan kosong)
            const rawRows = Array.from(tbody.querySelectorAll('tr'));
            this.allRows = rawRows.filter(r => !r.classList.contains('empty-row-msg'));

            // Buat row penampung ketika tidak ada data hasil filter
            this.emptyRow = document.createElement('tr');
            this.emptyRow.className = 'empty-row-msg text-center text-muted';
            this.emptyRow.style.display = 'none';
            const colCount = (this.table.querySelector('thead tr') || {}).children?.length || 9;
            this.emptyRow.innerHTML = `<td colspan="${colCount}" class="py-4">
                <i class="fa-solid fa-inbox fa-2x mb-2 text-muted opacity-50 d-block"></i>
                <span class="fw-semibold">Tidak ada transaksi yang cocok dengan filter atau pencarian.</span>
            </td>`;
            tbody.appendChild(this.emptyRow);

            this.bindEvents();
            this.applyFilters();
        }

        bindEvents() {
            // Live Search Input dengan debounce
            if (this.searchInput) {
                let debounceTimer;
                this.searchInput.addEventListener('input', () => {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        this.currentPage = 1;
                        this.applyFilters();
                    }, 150);
                });

                // Hindari submit form reload jika menekan enter pada input
                this.searchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.currentPage = 1;
                        this.applyFilters();
                    }
                });
            }

            // Filter Preset Waktu
            if (this.datePreset) {
                this.datePreset.addEventListener('change', () => {
                    const val = this.datePreset.value;
                    const customContainer = document.getElementById('customDateContainer');
                    if (customContainer) {
                        if (val === 'custom') {
                            customContainer.classList.remove('d-none');
                        } else {
                            customContainer.classList.add('d-none');
                        }
                    }
                    this.currentPage = 1;
                    this.applyFilters();
                });
            }

            // Rentang Tanggal Kustom
            if (this.dateStart) {
                this.dateStart.addEventListener('change', () => {
                    this.currentPage = 1;
                    this.applyFilters();
                });
            }
            if (this.dateEnd) {
                this.dateEnd.addEventListener('change', () => {
                    this.currentPage = 1;
                    this.applyFilters();
                });
            }

            // Filter Status (Lunas / Belum Dibayar)
            if (this.statusFilter) {
                this.statusFilter.addEventListener('change', () => {
                    this.currentPage = 1;
                    this.applyFilters();
                });
            }

            // Page Size Selector
            if (this.pageSizeSelect) {
                this.pageSizeSelect.addEventListener('change', () => {
                    const val = this.pageSizeSelect.value;
                    this.pageSize = val === 'all' ? 999999 : parseInt(val, 10);
                    this.currentPage = 1;
                    this.renderPage();
                });
            }
        }

        applyFilters() {
            const query = (this.searchInput ? this.searchInput.value : '').toLowerCase().trim();
            const dateMode = this.datePreset ? this.datePreset.value : 'all';
            const statusMode = this.statusFilter ? this.statusFilter.value : 'all';

            const now = new Date();
            const todayStr = now.toISOString().slice(0, 10);
            
            // 7 Hari Lalu
            const d7 = new Date();
            d7.setDate(d7.getDate() - 7);
            const d7Str = d7.toISOString().slice(0, 10);

            // Awal Bulan Ini
            const startMonthStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-01';

            const customStart = this.dateStart ? this.dateStart.value : '';
            const customEnd = this.dateEnd ? this.dateEnd.value : '';

            this.filteredRows = this.allRows.filter(row => {
                // 1. Text Search Matching
                if (query) {
                    const rowText = (row.getAttribute('data-search') || row.textContent).toLowerCase();
                    if (!rowText.includes(query)) {
                        return false;
                    }
                }

                // 2. Status Matching
                if (statusMode !== 'all') {
                    const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
                    if (statusMode === 'done' && rowStatus !== 'done') {
                        return false;
                    }
                    if (statusMode === 'pending' && rowStatus === 'done') {
                        return false;
                    }
                }

                // 3. Date Matching
                const rowDate = row.getAttribute('data-date') || '';
                if (rowDate) {
                    if (dateMode === 'today' && rowDate !== todayStr) {
                        return false;
                    } else if (dateMode === '7days' && rowDate < d7Str) {
                        return false;
                    } else if (dateMode === 'month' && rowDate < startMonthStr) {
                        return false;
                    } else if (dateMode === 'custom') {
                        if (customStart && rowDate < customStart) return false;
                        if (customEnd && rowDate > customEnd) return false;
                    }
                }

                return true;
            });

            this.renderPage();
        }

        renderPage() {
            const total = this.filteredRows.length;
            const totalPages = Math.max(1, Math.ceil(total / this.pageSize));

            if (this.currentPage > totalPages) {
                this.currentPage = totalPages;
            }

            // Sembunyikan seluruh baris data asli terlebih dahulu
            this.allRows.forEach(r => { r.style.display = 'none'; });

            if (total === 0) {
                if (this.emptyRow) this.emptyRow.style.display = '';
                if (this.paginationInfo) {
                    this.paginationInfo.textContent = 'Menampilkan 0 dari 0 transaksi';
                }
                if (this.paginationNav) {
                    this.paginationNav.innerHTML = '';
                }
                return;
            }

            if (this.emptyRow) this.emptyRow.style.display = 'none';

            const startIndex = (this.currentPage - 1) * this.pageSize;
            const endIndex = Math.min(startIndex + this.pageSize, total);

            for (let i = startIndex; i < endIndex; i++) {
                this.filteredRows[i].style.display = '';
            }

            // Update Pagination Info
            if (this.paginationInfo) {
                const totalAll = this.allRows.length;
                if (total < totalAll) {
                    this.paginationInfo.innerHTML = `Menampilkan <strong>${startIndex + 1} - ${endIndex}</strong> dari <strong>${total}</strong> hasil cocok <span class="text-muted small">(${totalAll} total)</span>`;
                } else {
                    this.paginationInfo.innerHTML = `Menampilkan <strong>${startIndex + 1} - ${endIndex}</strong> dari <strong>${total}</strong> transaksi`;
                }
            }

            // Render Navigasi Halaman
            this.buildPaginationNav(totalPages);
        }

        buildPaginationNav(totalPages) {
            if (!this.paginationNav) return;
            this.paginationNav.innerHTML = '';

            if (totalPages <= 1) return;

            // Tombol Sebelumnya
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${this.currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<button class="page-link" type="button" aria-label="Sebelumnya"><i class="fa-solid fa-chevron-left"></i></button>`;
            if (this.currentPage > 1) {
                prevLi.querySelector('button').addEventListener('click', () => {
                    this.currentPage--;
                    this.renderPage();
                });
            }
            this.paginationNav.appendChild(prevLi);

            // Tombol Nomor Halaman
            const maxVisibleButtons = 5;
            let startPage = Math.max(1, this.currentPage - 2);
            let endPage = Math.min(totalPages, startPage + maxVisibleButtons - 1);
            if (endPage - startPage < maxVisibleButtons - 1) {
                startPage = Math.max(1, endPage - maxVisibleButtons + 1);
            }

            if (startPage > 1) {
                this.addPageNumberBtn(1);
                if (startPage > 2) {
                    const dotsLi = document.createElement('li');
                    dotsLi.className = 'page-item disabled';
                    dotsLi.innerHTML = '<span class="page-link">…</span>';
                    this.paginationNav.appendChild(dotsLi);
                }
            }

            for (let p = startPage; p <= endPage; p++) {
                this.addPageNumberBtn(p);
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    const dotsLi = document.createElement('li');
                    dotsLi.className = 'page-item disabled';
                    dotsLi.innerHTML = '<span class="page-link">…</span>';
                    this.paginationNav.appendChild(dotsLi);
                }
                this.addPageNumberBtn(totalPages);
            }

            // Tombol Berikutnya
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${this.currentPage === totalPages ? 'disabled' : ''}`;
            nextLi.innerHTML = `<button class="page-link" type="button" aria-label="Berikutnya"><i class="fa-solid fa-chevron-right"></i></button>`;
            if (this.currentPage < totalPages) {
                nextLi.querySelector('button').addEventListener('click', () => {
                    this.currentPage++;
                    this.renderPage();
                });
            }
            this.paginationNav.appendChild(nextLi);
        }

        addPageNumberBtn(pageNum) {
            const li = document.createElement('li');
            li.className = `page-item ${this.currentPage === pageNum ? 'active' : ''}`;
            li.innerHTML = `<button class="page-link" type="button">${pageNum}</button>`;
            li.querySelector('button').addEventListener('click', () => {
                this.currentPage = pageNum;
                this.renderPage();
            });
            this.paginationNav.appendChild(li);
        }

        highlightMatchingRow(code) {
            const cleanCode = code.trim().toLowerCase();
            const targetRow = this.allRows.find(r => {
                const text = (r.getAttribute('data-search') || r.textContent).toLowerCase();
                return text.includes(cleanCode);
            });

            if (targetRow) {
                // Pindahkan ke halaman baris yang cocok
                const matchIndex = this.filteredRows.indexOf(targetRow);
                if (matchIndex !== -1) {
                    this.currentPage = Math.floor(matchIndex / this.pageSize) + 1;
                    this.renderPage();
                }

                targetRow.style.transition = 'background-color 0.4s ease';
                targetRow.style.backgroundColor = '#dcfce7'; // green highlight
                targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });

                setTimeout(() => {
                    targetRow.style.backgroundColor = '';
                }, 3500);
            }
        }
    }

    /**
     * Modern Barcode & QR Code Scanner Controller
     */
    class ModernScannerController {
        constructor(config) {
            this.collapseEl = document.getElementById(config.collapseId || 'scannerCollapse');
            this.readerContainerId = config.readerContainerId || 'my-qr-reader';
            this.cameraSelect = document.getElementById(config.cameraSelectId || 'cameraSelect');
            this.btnToggle = document.getElementById(config.btnToggleId || 'btnToggleScanner');
            this.laserEl = document.getElementById(config.laserId || 'scannerLaser');
            this.promptEl = document.getElementById(config.promptId || 'scannerOverlayPrompt');
            this.resultEl = document.getElementById(config.resultId || 'your-qr-result');
            this.onScanSuccessCallback = config.onScanSuccess;

            this.html5QrCode = null;
            this.isScanning = false;
            this.cameras = [];

            if (this.collapseEl) {
                this.init();
            }
        }

        init() {
            // Ketika akordion dibuka, ambil daftar kamera secara otomatis
            this.collapseEl.addEventListener('shown.bs.collapse', () => {
                this.loadCameras();
            });

            // Ketika akordion ditutup, pastikan kamera dimatikan
            this.collapseEl.addEventListener('hidden.bs.collapse', () => {
                this.stopScanning();
            });

            if (this.btnToggle) {
                this.btnToggle.addEventListener('click', () => {
                    if (this.isScanning) {
                        this.stopScanning();
                    } else {
                        this.startScanning();
                    }
                });
            }
        }

        async loadCameras() {
            if (typeof Html5Qrcode === 'undefined') {
                if (this.resultEl) {
                    this.resultEl.innerHTML = '<span class="text-danger small">Library scanner belum dimuat.</span>';
                }
                return;
            }

            try {
                const devices = await Html5Qrcode.getCameras();
                if (!devices || devices.length === 0) {
                    if (this.cameraSelect) {
                        this.cameraSelect.innerHTML = '<option value="">Tidak ada kamera terdeteksi</option>';
                    }
                    return;
                }

                this.cameras = devices;
                if (this.cameraSelect) {
                    this.cameraSelect.innerHTML = '';
                    devices.forEach((dev, idx) => {
                        const opt = document.createElement('option');
                        opt.value = dev.id;
                        
                        // Terjemahkan label teknis menjadi nama yang ramah pengguna
                        const labelLower = (dev.label || '').toLowerCase();
                        if (labelLower.includes('back') || labelLower.includes('rear') || labelLower.includes('environment')) {
                            opt.textContent = `📷 Kamera Belakang (Utama) ${devices.length > 2 ? '#' + (idx + 1) : ''}`;
                        } else if (labelLower.includes('front') || labelLower.includes('user') || labelLower.includes('selfie')) {
                            opt.textContent = `🤳 Kamera Depan (Selfie) ${devices.length > 2 ? '#' + (idx + 1) : ''}`;
                        } else {
                            opt.textContent = `📷 Kamera ${idx + 1}${dev.label ? ' - ' + dev.label : ''}`;
                        }
                        this.cameraSelect.appendChild(opt);
                    });

                    // Preferensikan kamera belakang sebagai pilihan awal
                    const backCam = devices.find(d => {
                        const l = (d.label || '').toLowerCase();
                        return l.includes('back') || l.includes('rear') || l.includes('environment');
                    });
                    if (backCam) {
                        this.cameraSelect.value = backCam.id;
                    }
                }

                // Otomatis aktifkan scanner saat dibuka jika belum aktif
                if (!this.isScanning) {
                    this.startScanning();
                }
            } catch (err) {
                console.warn('Gagal memuat kamera:', err);
                if (this.cameraSelect) {
                    this.cameraSelect.innerHTML = '<option value="">Kamera belum diizinkan</option>';
                }
            }
        }

        async startScanning() {
            if (!this.cameraSelect || !this.cameraSelect.value) {
                try {
                    await this.loadCameras();
                } catch (e) {}
            }

            const cameraId = this.cameraSelect ? this.cameraSelect.value : null;
            if (!cameraId) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Kamera Tidak Ditemukan',
                        text: 'Pastikan browser Anda memiliki izin akses kamera.',
                        confirmButtonColor: '#0284c7'
                    });
                }
                return;
            }

            try {
                if (!this.html5QrCode) {
                    this.html5QrCode = new Html5Qrcode(this.readerContainerId);
                }

                if (this.promptEl) this.promptEl.classList.add('d-none');
                if (this.laserEl) this.laserEl.classList.remove('d-none');
                if (this.btnToggle) {
                    this.btnToggle.className = 'btn btn-sm btn-outline-danger';
                    this.btnToggle.innerHTML = '<i class="fa-solid fa-stop me-1"></i>Hentikan Kamera';
                }

                await this.html5QrCode.start(
                    cameraId,
                    {
                        fps: 15,
                        qrbox: (vfWidth, vfHeight) => {
                            const minEdge = Math.min(vfWidth, vfHeight);
                            const edge = Math.max(Math.floor(minEdge * 0.75), 200);
                            return { width: edge, height: edge };
                        },
                        aspectRatio: 1.0
                    },
                    (decodedText) => {
                        this.handleSuccess(decodedText);
                    },
                    (errorMessage) => {
                        // Abaikan frame scan error berkala
                    }
                );

                this.isScanning = true;
            } catch (err) {
                console.error('Gagal menjalankan scanner:', err);
                if (this.promptEl) this.promptEl.classList.remove('d-none');
                if (this.laserEl) this.laserEl.classList.add('d-none');
                if (this.btnToggle) {
                    this.btnToggle.className = 'btn btn-sm btn-brand';
                    this.btnToggle.innerHTML = '<i class="fa-solid fa-play me-1"></i>Mulai Kamera';
                }
            }
        }

        async stopScanning() {
            if (this.html5QrCode && this.isScanning) {
                try {
                    await this.html5QrCode.stop();
                } catch (e) {
                    console.warn(e);
                }
            }
            this.isScanning = false;
            if (this.promptEl) this.promptEl.classList.remove('d-none');
            if (this.laserEl) this.laserEl.classList.add('d-none');
            if (this.btnToggle) {
                this.btnToggle.className = 'btn btn-sm btn-brand';
                this.btnToggle.innerHTML = '<i class="fa-solid fa-play me-1"></i>Mulai Kamera';
            }
        }

        handleSuccess(decodedText) {
            playBeepSound();

            if (this.resultEl) {
                this.resultEl.innerHTML = `<span class="badge bg-success fs-6"><i class="fa-solid fa-check me-1"></i> Terbaca: ${decodedText}</span>`;
            }

            if (typeof this.onScanSuccessCallback === 'function') {
                this.onScanSuccessCallback(decodedText);
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Barcode Berhasil Dipindai',
                    text: decodedText,
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }

            // Hentikan kamera dan tutup akordion scanner secara mulus
            this.stopScanning();
            if (typeof bootstrap !== 'undefined' && this.collapseEl) {
                const bsCollapse = bootstrap.Collapse.getInstance(this.collapseEl);
                if (bsCollapse) {
                    setTimeout(() => { bsCollapse.hide(); }, 800);
                }
            }
        }
    }

    // Ekspor ke window global
    window.TransactionTableManager = TransactionTableManager;
    window.ModernScannerController = ModernScannerController;

})(window, document);
