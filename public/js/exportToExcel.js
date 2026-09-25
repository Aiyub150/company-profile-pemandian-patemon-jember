/**
 * Modul Ekspor Spreadsheet Excel Standar Resmi Pemerintah Kabupaten Jember
 * Dinas Pariwisata dan Kebudayaan - UPTD Kawasan Wisata Alam Pemandian Patemon
 * 
 * Menghasilkan file Excel (.xls) berstandar kedinasan lengkap dengan:
 * - Kop Surat Resmi Pemkab Jember & UPTD Patemon
 * - Garis Ganda Pembatas Kop Kedinasan
 * - Metadata Dokumen (Dasar Hukum Perda, Objek Retribusi, Periode, Waktu Unduh)
 * - Tabel Data Berformat Rapi (Zebra Striping, Alignment Presisi, Border Halus)
 * - Baris Total Akumulasi Penerimaan & Formula Terbilang Bahasa Indonesia
 * - Lembar Pengesahan Tanda Tangan Resmi (Kepala UPTD & Bendahara Penerimaan)
 */

function terbilangIndonesia(angka) {
    angka = Math.floor(Math.abs(Number(angka))) || 0;
    if (angka === 0) return 'Nol Rupiah';
    const satuan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
    
    function baca(n) {
        if (n < 12) return satuan[n];
        if (n < 20) return baca(n - 10) + ' Belas';
        if (n < 100) return baca(Math.floor(n / 10)) + ' Puluh' + (n % 10 ? ' ' + baca(n % 10) : '');
        if (n < 200) return 'Seratus' + (n - 100 ? ' ' + baca(n - 100) : '');
        if (n < 1000) return baca(Math.floor(n / 100)) + ' Ratus' + (n % 100 ? ' ' + baca(n % 100) : '');
        if (n < 2000) return 'Seribu' + (n - 1000 ? ' ' + baca(n - 1000) : '');
        if (n < 1000000) return baca(Math.floor(n / 1000)) + ' Ribu' + (n % 1000 ? ' ' + baca(n % 1000) : '');
        if (n < 1000000000) return baca(Math.floor(n / 1000000)) + ' Juta' + (n % 1000000 ? ' ' + baca(n % 1000000) : '');
        if (n < 1000000000000) return baca(Math.floor(n / 1000000000)) + ' Miliar' + (n % 1000000000 ? ' ' + baca(n % 1000000000) : '');
        return baca(Math.floor(n / 1000000000000)) + ' Triliun' + (n % 1000000000000 ? ' ' + baca(n % 1000000000) : '');
    }
    return baca(angka).trim() + ' Rupiah';
}

function formatTanggalIndoLengkap(dateObj = new Date()) {
    const hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const bulan = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    const d = dateObj.getDate();
    const m = bulan[dateObj.getMonth()];
    const y = dateObj.getFullYear();
    const h = String(dateObj.getHours()).padStart(2, '0');
    const min = String(dateObj.getMinutes()).padStart(2, '0');
    return `${hari[dateObj.getDay()]}, ${d} ${m} ${y} pukul ${h}:${min} WIB`;
}

function formatTanggalSederhana(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    const bulan = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return `${d.getDate()} ${bulan[d.getMonth()]} ${d.getFullYear()}`;
}

function exportToExcel(tableId, filename = 'Laporan') {
    const tableSelect = document.getElementById(tableId);
    if (!tableSelect) {
        console.error('Tabel dengan ID ' + tableId + ' tidak ditemukan.');
        alert('Tabel laporan tidak ditemukan untuk diekspor.');
        return;
    }

    // 1. Identifikasi Kolom yang Valid (Abaikan kolom aksi, nota, dsb)
    const headerRow = tableSelect.querySelector('thead tr');
    if (!headerRow) {
        console.error('Struktur thead pada tabel tidak valid.');
        return;
    }

    const thElements = Array.from(headerRow.querySelectorAll('th'));
    const validColIndices = [];
    const validHeaders = [];

    thElements.forEach((th, idx) => {
        const text = th.innerText.trim().toLowerCase();
        const isAction = th.classList.contains('action-column') || 
                         th.classList.contains('no-print') || 
                         text === 'aksi' || 
                         text === 'action' || 
                         text === 'nota' ||
                         text === 'opsi';
        if (!isAction) {
            validColIndices.push(idx);
            validHeaders.push(th.innerText.trim());
        }
    });

    const totalCols = Math.max(validColIndices.length, 6);

    // 2. Ekstraksi Data Baris (Tbody)
    const bodyRows = Array.from(tableSelect.querySelectorAll('tbody tr'));
    let rowsHtml = '';
    let totalNominal = 0;
    let foundNumericTotal = false;

    if (bodyRows.length === 0 || (bodyRows.length === 1 && bodyRows[0].querySelector('td[colspan]'))) {
        rowsHtml = `
            <tr>
                <td colspan="${totalCols}" style="text-align: center; padding: 15px; font-style: italic; color: #64748b; border: 0.5pt solid #cbd5e1;">
                    Tidak terdapat data transaksi pada periode ini.
                </td>
            </tr>
        `;
    } else {
        bodyRows.forEach((tr, rIdx) => {
            const tds = Array.from(tr.querySelectorAll('td'));
            if (tds.length === 0) return;

            // Baris kosong atau pesan colspan
            if (tds.length === 1 && tds[0].hasAttribute('colspan')) {
                rowsHtml += `
                    <tr>
                        <td colspan="${totalCols}" style="text-align: center; padding: 12px; font-style: italic; color: #64748b; border: 0.5pt solid #cbd5e1;">
                            ${tds[0].innerText.trim()}
                        </td>
                    </tr>
                `;
                return;
            }

            const isEven = rIdx % 2 === 1;
            const bgRow = isEven ? '#f8fafc' : '#ffffff';
            let rowCellsHtml = '';

            validColIndices.forEach((colIdx, posIdx) => {
                const td = tds[colIdx];
                if (!td) {
                    rowCellsHtml += `<td style="border: 0.5pt solid #cbd5e1; background-color: ${bgRow};">&nbsp;</td>`;
                    return;
                }

                // Bersihkan teks dari ikon, modal, tombol, progress bar
                let cellText = td.innerText.trim();
                const colName = (validHeaders[posIdx] || '').toLowerCase();

                // Deteksi alignment
                let align = 'left';
                let isCurrency = false;
                let isNumber = false;

                if (colName.includes('no') && posIdx === 0) {
                    align = 'center';
                } else if (colName.includes('kode') || colName.includes('referensi') || colName.includes('tanggal') || colName.includes('tgl') || colName.includes('metode') || colName.includes('status')) {
                    align = 'center';
                } else if (colName.includes('total') || colName.includes('subtotal') || colName.includes('harga') || colName.includes('pendapatan') || colName.includes('omzet') || colName.includes('retribusi') || colName.includes('bayar') || colName.includes('tarif')) {
                    align = 'right';
                    isCurrency = true;
                } else if (colName.includes('jumlah') || colName.includes('lembar') || colName.includes('volume') || colName.includes('qty')) {
                    align = 'center';
                    isNumber = true;
                } else if (colName.includes('kontribusi') || colName.includes('porsi') || colName.includes('%')) {
                    align = 'right';
                }

                // Jika kolom bukti bayar / gambar
                if (colName.includes('bukti')) {
                    align = 'center';
                    if (cellText.toLowerCase().includes('loket')) {
                        cellText = 'Loket Kasir';
                    } else if (td.querySelector('img') || td.querySelector('a')) {
                        cellText = 'Transfer Bank (Terlampir)';
                    }
                }

                // Cek nilai nominal untuk akumulasi total
                if (isCurrency) {
                    const rawNum = cellText.replace(/[^0-9]/g, '');
                    if (rawNum) {
                        const val = parseInt(rawNum, 10);
                        if (!isNaN(val) && val > 0 && (colName.includes('total') || colName.includes('subtotal') || colName.includes('pendapatan'))) {
                            totalNominal += val;
                            foundNumericTotal = true;
                        }
                    }
                }

                const fontStyle = (colName.includes('kode') || colName.includes('referensi')) ? 'font-family: \'Courier New\', Courier, monospace; font-size: 9pt;' : 'font-size: 9.5pt;';
                const fwStyle = (isCurrency && (colName.includes('total') || colName.includes('subtotal'))) ? 'font-weight: bold; color: #0f172a;' : 'color: #1e293b;';
                
                rowCellsHtml += `
                    <td style="border: 0.5pt solid #cbd5e1; background-color: ${bgRow}; text-align: ${align}; padding: 6px 10px; vertical-align: middle; ${fontStyle} ${fwStyle}">
                        ${cellText}
                    </td>
                `;
            });

            rowsHtml += `<tr height="26">${rowCellsHtml}</tr>`;
        });
    }

    // 3. Ekstraksi Footer Tabel (Tfoot) jika tersedia di DOM
    const tfootRow = tableSelect.querySelector('tfoot tr');
    let footerHtml = '';
    if (tfootRow) {
        const tfootTds = Array.from(tfootRow.querySelectorAll('th, td'));
        let tfootCellsHtml = '';
        let skipCount = 0;

        validColIndices.forEach((colIdx, posIdx) => {
            const cell = tfootTds[colIdx];
            if (!cell) {
                tfootCellsHtml += `<td style="border-top: 1.5pt solid #0284c7; border-bottom: 2.5pt double #0284c7; background-color: #e0f2fe;">&nbsp;</td>`;
                return;
            }
            const colSpan = cell.getAttribute('colspan') ? parseInt(cell.getAttribute('colspan'), 10) : 1;
            const text = cell.innerText.trim();
            const align = (posIdx === validColIndices.length - 1 || text.includes('Rp') || /[\d.,]+/.test(text)) ? 'right' : 'left';
            
            tfootCellsHtml += `
                <th style="border-top: 1.5pt solid #0284c7; border-bottom: 2.5pt double #0284c7; background-color: #e0f2fe; color: #0369a1; font-weight: bold; font-size: 10pt; text-align: ${align}; padding: 8px 10px; vertical-align: middle;">
                    ${text}
                </th>
            `;
        });

        footerHtml = `<tr height="30">${tfootCellsHtml}</tr>`;
    } else if (foundNumericTotal && totalNominal > 0) {
        // Buat footer otomatis jika tidak ada tfoot
        const labelCols = Math.max(1, validColIndices.length - 1);
        const formattedTotal = 'Rp ' + totalNominal.toLocaleString('id-ID');
        footerHtml = `
            <tr height="30">
                <th colspan="${labelCols}" style="border-top: 1.5pt solid #0284c7; border-bottom: 2.5pt double #0284c7; background-color: #e0f2fe; color: #0369a1; font-weight: bold; font-size: 10pt; text-align: right; padding: 8px 10px; vertical-align: middle;">
                    TOTAL PENERIMAAN RETRIBUSI
                </th>
                <th style="border-top: 1.5pt solid #0284c7; border-bottom: 2.5pt double #0284c7; background-color: #e0f2fe; color: #0369a1; font-weight: bold; font-size: 10pt; text-align: right; padding: 8px 10px; vertical-align: middle;">
                    ${formattedTotal}
                </th>
            </tr>
        `;
    }

    // 4. Deteksi Periode & Judul Dokumen Standar Pemkab
    let judulLaporan = 'REKAPITULASI PENERIMAAN RETRIBUSI TIKET MASUK WISATA';
    let periodeLabel = 'Seluruh Periode Tercatat';
    const dateInputElem = document.getElementById('dateInput') || document.querySelector('input[name="dateInput"]');
    
    if (dateInputElem && dateInputElem.value) {
        periodeLabel = formatTanggalSederhana(dateInputElem.value);
    } else {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('date')) {
            periodeLabel = formatTanggalSederhana(urlParams.get('date'));
        }
    }

    const fnLower = (filename || '').toLowerCase();
    if (fnLower.includes('harian')) {
        judulLaporan = 'LAPORAN HARIAN PENERIMAAN RETRIBUSI TIKET WISATA';
    } else if (fnLower.includes('bulanan')) {
        judulLaporan = 'LAPORAN BULANAN PENERIMAAN RETRIBUSI TIKET WISATA';
    } else if (fnLower.includes('tahunan')) {
        judulLaporan = 'LAPORAN TAHUNAN PENERIMAAN RETRIBUSI TIKET WISATA';
    } else if (fnLower.includes('transaksi')) {
        judulLaporan = 'BUKU KAS & LOG TRANSAKSI RETRIBUSI TIKET WISATA';
    }

    const tahunSekarang = new Date().getFullYear();
    const nomorLampiran = `556 / ${String(new Date().getMonth() + 1).padStart(2, '0')} / UPTD-PATEMON / ${tahunSekarang}`;
    const tanggalCetak = formatTanggalIndoLengkap(new Date());
    const tanggalHariIniSingkat = formatTanggalSederhana(new Date().toISOString().split('T')[0]);
    const terbilangTeks = totalNominal > 0 ? terbilangIndonesia(totalNominal) : '-';

    // Pembagian kolom tanda tangan
    const colLeft = Math.floor(totalCols / 2);
    const colRight = totalCols - colLeft;

    // 5. Susun Header Tabel Kolom (Thead)
    let theadHtml = '';
    validHeaders.forEach((hText, idx) => {
        theadHtml += `
            <th style="border: 0.5pt solid #0f172a; background-color: #1e3a8a; color: #ffffff; font-weight: bold; font-size: 9.5pt; text-align: center; vertical-align: middle; padding: 8px 6px; text-transform: uppercase; letter-spacing: 0.02em;">
                ${hText}
            </th>
        `;
    });

    // 6. Template HTML Excel Bersih & Berstandar Pemkab Jember
    const excelTemplate = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" 
              xmlns:x="urn:schemas-microsoft-com:office:excel" 
              xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <!--[if gte mso 9]>
            <xml>
                <x:ExcelWorkbook>
                    <x:ExcelWorksheets>
                        <x:ExcelWorksheet>
                            <x:Name>Laporan Retribusi</x:Name>
                            <x:WorksheetOptions>
                                <x:DisplayGridlines/>
                                <x:FitToPage/>
                                <x:Print>
                                    <x:FitWidth>1</x:FitWidth>
                                    <x:FitHeight>99</x:FitHeight>
                                    <x:ValidPrinterInfo/>
                                    <x:PaperSizeIndex>9</x:PaperSizeIndex>
                                </x:Print>
                            </x:WorksheetOptions>
                        </x:ExcelWorksheet>
                    </x:ExcelWorksheets>
                </x:ExcelWorkbook>
            </xml>
            <![endif]-->
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <style>
                body, table { font-family: 'Arial', 'Calibri', sans-serif; font-size: 10pt; color: #0f172a; }
                table { border-collapse: collapse; width: 100%; }
            </style>
        </head>
        <body>
            <table>
                <!-- KOP SURAT RESMI PEMERINTAH KABUPATEN JEMBER -->
                <tr>
                    <td colspan="${totalCols}" style="text-align: center; font-size: 14pt; font-weight: bold; color: #1e3a8a; height: 26px; vertical-align: middle;">
                        PEMERINTAH KABUPATEN JEMBER
                    </td>
                </tr>
                <tr>
                    <td colspan="${totalCols}" style="text-align: center; font-size: 12pt; font-weight: bold; color: #0f172a; height: 22px; vertical-align: middle;">
                        DINAS PARIWISATA DAN KEBUDAYAAN
                    </td>
                </tr>
                <tr>
                    <td colspan="${totalCols}" style="text-align: center; font-size: 11pt; font-weight: bold; color: #0284c7; height: 20px; vertical-align: middle;">
                        UPTD KAWASAN WISATA ALAM PEMANDIAN PATEMON
                    </td>
                </tr>
                <tr>
                    <td colspan="${totalCols}" style="text-align: center; font-size: 8.5pt; font-style: italic; color: #475569; height: 18px; vertical-align: middle;">
                        Jl. Pemandian Patemon, Dusun Krajan, Desa Patemon, Kec. Tanggul, Kab. Jember, Jawa Timur 68155
                    </td>
                </tr>
                <tr>
                    <td colspan="${totalCols}" style="text-align: center; font-size: 8.5pt; color: #64748b; height: 18px; vertical-align: middle;">
                        Laman Resmi: www.jemberkab.go.id &bull; Pos-el: disparbud@jemberkab.go.id &bull; Kode Pos: 68155
                    </td>
                </tr>
                <tr>
                    <td colspan="${totalCols}" style="border-bottom: 2.5pt double #0f172a; height: 6px;"></td>
                </tr>
                <tr height="12"><td colspan="${totalCols}"></td></tr>

                <!-- JUDUL DOKUMEN -->
                <tr>
                    <td colspan="${totalCols}" style="text-align: center; font-size: 12pt; font-weight: bold; color: #0f172a; height: 26px; vertical-align: middle; text-transform: uppercase;">
                        ${judulLaporan}
                    </td>
                </tr>
                <tr>
                    <td colspan="${totalCols}" style="text-align: center; font-size: 9.5pt; color: #475569; height: 20px; vertical-align: middle;">
                        Nomor Lampiran: ${nomorLampiran}
                    </td>
                </tr>
                <tr height="10"><td colspan="${totalCols}"></td></tr>

                <!-- TABEL METADATA KEDINASAN -->
                <tr>
                    <td colspan="2" style="background-color: #f1f5f9; font-weight: bold; font-size: 9pt; border: 0.5pt solid #cbd5e1; padding: 5px 8px;">Objek Retribusi</td>
                    <td colspan="${colLeft - 2 > 0 ? colLeft - 2 : 1}" style="background-color: #f8fafc; font-size: 9pt; border: 0.5pt solid #cbd5e1; padding: 5px 8px;">Pemandian Alam Patemon Tanggul</td>
                    <td colspan="1" style="background-color: #f1f5f9; font-weight: bold; font-size: 9pt; border: 0.5pt solid #cbd5e1; padding: 5px 8px;">Periode Data</td>
                    <td colspan="${colRight - 1 > 0 ? colRight - 1 : 2}" style="background-color: #f8fafc; font-size: 9pt; border: 0.5pt solid #cbd5e1; padding: 5px 8px;">${periodeLabel}</td>
                </tr>
                <tr>
                    <td colspan="2" style="background-color: #f1f5f9; font-weight: bold; font-size: 9pt; border: 0.5pt solid #cbd5e1; padding: 5px 8px;">Dasar Hukum</td>
                    <td colspan="${colLeft - 2 > 0 ? colLeft - 2 : 1}" style="background-color: #f8fafc; font-size: 9pt; border: 0.5pt solid #cbd5e1; padding: 5px 8px;">Perda Kab. Jember tentang Retribusi Jasa Usaha</td>
                    <td colspan="1" style="background-color: #f1f5f9; font-weight: bold; font-size: 9pt; border: 0.5pt solid #cbd5e1; padding: 5px 8px;">Waktu Ekspor</td>
                    <td colspan="${colRight - 1 > 0 ? colRight - 1 : 2}" style="background-color: #f8fafc; font-size: 9pt; border: 0.5pt solid #cbd5e1; padding: 5px 8px;">${tanggalCetak}</td>
                </tr>
                <tr>
                    <td colspan="2" style="background-color: #f1f5f9; font-weight: bold; font-size: 9pt; border: 0.5pt solid #cbd5e1; padding: 5px 8px;">Unit Pengelola</td>
                    <td colspan="${colLeft - 2 > 0 ? colLeft - 2 : 1}" style="background-color: #f8fafc; font-size: 9pt; border: 0.5pt solid #cbd5e1; padding: 5px 8px;">UPTD Disparbud Kabupaten Jember</td>
                    <td colspan="1" style="background-color: #f1f5f9; font-weight: bold; font-size: 9pt; border: 0.5pt solid #cbd5e1; padding: 5px 8px;">Petugas Loket</td>
                    <td colspan="${colRight - 1 > 0 ? colRight - 1 : 2}" style="background-color: #f8fafc; font-size: 9pt; border: 0.5pt solid #cbd5e1; padding: 5px 8px;">Seluruh Petugas Kasir Loket</td>
                </tr>
                <tr height="14"><td colspan="${totalCols}"></td></tr>

                <!-- TABEL DATA UTAMA -->
                <tr height="30">
                    ${theadHtml}
                </tr>
                ${rowsHtml}
                ${footerHtml}

                ${totalNominal > 0 ? `
                <tr height="10"><td colspan="${totalCols}"></td></tr>
                <tr>
                    <td colspan="${totalCols}" style="background-color: #f1f5f9; border: 0.5pt solid #cbd5e1; padding: 7px 10px; font-size: 9pt; font-style: italic; color: #1e293b;">
                        <em>Terbilang: <strong>${terbilangTeks}</strong></em>
                    </td>
                </tr>
                ` : ''}

                <!-- LEMBAR PENGESAHAN TANDA TANGAN RESMI KEDINASAN -->
                <tr height="25"><td colspan="${totalCols}"></td></tr>
                <tr>
                    <td colspan="${colLeft}" style="text-align: center; vertical-align: top; font-size: 9.5pt; line-height: 1.4;">
                        Mengetahui,<br/>
                        <strong>Kepala UPTD Kawasan Wisata Patemon</strong><br/>
                        Dinas Pariwisata dan Kebudayaan Kab. Jember
                    </td>
                    <td colspan="${colRight}" style="text-align: center; vertical-align: top; font-size: 9.5pt; line-height: 1.4;">
                        Tanggul, ${tanggalHariIniSingkat}<br/>
                        <strong>Bendahara Penerimaan Pembantu</strong><br/>
                        UPTD Kawasan Wisata Patemon
                    </td>
                </tr>
                <tr height="60">
                    <td colspan="${colLeft}"></td>
                    <td colspan="${colRight}"></td>
                </tr>
                <tr>
                    <td colspan="${colLeft}" style="text-align: center; vertical-align: bottom; font-size: 9.5pt;">
                        <strong><u>H. SLAMET RIYADI, S.Sos., M.Si.</u></strong><br/>
                        Pembina Tingkat I<br/>
                        NIP. 19740512 199803 1 004
                    </td>
                    <td colspan="${colRight}" style="text-align: center; vertical-align: bottom; font-size: 9.5pt;">
                        <strong><u>AHMAD FATHONI, S.E.</u></strong><br/>
                        Penata Muda Tk. I<br/>
                        NIP. 19820914 200801 1 009
                    </td>
                </tr>

                <tr height="20"><td colspan="${totalCols}"></td></tr>
                <tr>
                    <td colspan="${totalCols}" style="font-size: 8pt; font-style: italic; color: #94a3b8; text-align: left;">
                        * Dokumen ini digenerate secara resmi melalui Sistem Informasi Keuangan & Kasir Digital Wisata Pemandian Patemon - Pemerintah Kabupaten Jember.
                    </td>
                </tr>
            </table>
        </body>
        </html>
    `;

    // 7. Simpan Dokumen sebagai Berkas Excel (.xls) dengan UTF-8 BOM
    const filenameWithExt = (filename ? filename : 'Laporan_Retribusi_Patemon') + '.xls';
    const blob = new Blob(['\ufeff', excelTemplate], { 
        type: 'application/vnd.ms-excel;charset=utf-8;' 
    });

    if (window.navigator && window.navigator.msSaveOrOpenBlob) {
        window.navigator.msSaveOrOpenBlob(blob, filenameWithExt);
    } else {
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.href = url;
        link.download = filenameWithExt;
        document.body.appendChild(link);
        link.click();
        setTimeout(() => {
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }, 500);
    }
}