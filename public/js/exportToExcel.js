function exportToExcel(tableId, filename = 'Laporan') {
    const tableSelect = document.getElementById(tableId);
    if (!tableSelect) {
        console.error('Tabel dengan ID ' + tableId + ' tidak ditemukan.');
        return;
    }

    // Kloning tabel agar kolom action dapat dihapus dengan aman tanpa merusak DOM
    const clone = tableSelect.cloneNode(true);
    clone.querySelectorAll('.action-column, .btn, .no-print').forEach(el => el.remove());

    const filenameWithExt = (filename ? filename : 'excel_data') + '.xls';
    const html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Sheet1</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
            <meta http-equiv="content-type" content="text/plain; charset=UTF-8"/>
            <style>
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 0.5pt solid #cccccc; padding: 6px; }
                th { background-color: #f2f2f2; font-weight: bold; }
            </style>
        </head>
        <body>
            ${clone.outerHTML}
        </body>
        </html>
    `;

    const blob = new Blob(['\ufeff', html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    if (navigator.msSaveOrOpenBlob) {
        navigator.msSaveOrOpenBlob(blob, filenameWithExt);
    } else {
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.href = url;
        link.download = filenameWithExt;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
}