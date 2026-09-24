function exportToPDF(tableId, title = 'Laporan') {
    const table = document.getElementById(tableId);
    if (!table) {
        console.error('Tabel dengan ID ' + tableId + ' tidak ditemukan.');
        return;
    }

    const jsPDFConstructor = window.jspdf ? window.jspdf.jsPDF : (typeof jsPDF !== 'undefined' ? jsPDF : null);
    if (!jsPDFConstructor) {
        alert('Library jsPDF tidak ditemukan. Silakan periksa koneksi internet Anda.');
        return;
    }

    const doc = new jsPDFConstructor('p', 'pt', 'a4');

    // Kloning tabel untuk menghapus kolom aksi sebelum diekstrak oleh autoTable
    const clone = table.cloneNode(true);
    clone.querySelectorAll('.action-column, .btn, .no-print').forEach(el => el.remove());

    clone.id = tableId + '_pdf_export_clone';
    clone.style.display = 'none';
    document.body.appendChild(clone);

    if (typeof doc.autoTable === 'function') {
        doc.setFontSize(14);
        doc.text(title, 40, 40);

        doc.autoTable({
            html: '#' + clone.id,
            theme: 'grid',
            startY: 55,
            styles: {
                fontSize: 8,
                cellPadding: 4,
                textColor: [33, 37, 41],
                overflow: 'linebreak'
            },
            headStyles: {
                fillColor: [52, 58, 64],
                textColor: [255, 255, 255],
                fontStyle: 'bold'
            }
        });

        doc.save((title || 'laporan').replace(/[^a-zA-Z0-9_\-]/g, '_') + '.pdf');
    } else {
        alert('Plugin jsPDF autoTable tidak ditemukan.');
    }

    document.body.removeChild(clone);
}