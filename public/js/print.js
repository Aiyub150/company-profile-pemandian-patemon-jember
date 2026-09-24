function printTable(tableId, title = 'Laporan') {
    const table = document.getElementById(tableId);
    if (!table) {
        console.error('Tabel dengan ID ' + tableId + ' tidak ditemukan.');
        return;
    }

    // Kloning tabel agar tidak merusak DOM halaman aktif
    const clone = table.cloneNode(true);
    clone.querySelectorAll('.action-column, .btn, .no-print').forEach(el => el.remove());

    const printWindow = window.open('', '_blank', 'width=900,height=650');
    if (!printWindow) {
        window.print();
        return;
    }

    printWindow.document.write(`
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <title>${title}</title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; 
                    padding: 24px; 
                    color: #212529; 
                }
                h2 { 
                    text-align: center; 
                    margin-bottom: 20px; 
                    font-size: 20px;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-bottom: 20px; 
                }
                th, td { 
                    border: 1px solid #dee2e6; 
                    padding: 8px 12px; 
                    font-size: 13px;
                }
                th { 
                    background-color: #f8f9fa; 
                    font-weight: 600; 
                    text-align: left;
                }
                tr:nth-child(even) { 
                    background-color: #fcfcfc; 
                }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                @media print {
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            <h2>${title}</h2>
            ${clone.outerHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 400);
}