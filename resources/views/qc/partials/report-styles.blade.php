{{--
    Gaya visual isi dokumen laporan QC — dipakai BERSAMA oleh:
      - PDF (dompdf)          : qc/exports/pdf.blade.php
      - Pratinjau di layar     : qc/exports/preview.blade.php
    Seluruh aturan diberi cakupan di bawah .report agar tidak bentrok dengan
    gaya halaman lain. Desain sengaja bersih & formal (hitam-putih, garis tipis).
--}}
<style>
    .report {
        font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; /* DejaVu = font bawaan dompdf */
        color: #000;
        font-size: 9px;
        line-height: 1.35;
        width: 100%;
    }
    .report table { border-collapse: collapse; }

    /* --- Kop / kepala dokumen --- */
    .report-head { width: 100%; border: 1px solid #000; }
    .report-head td { vertical-align: middle; padding: 8px 10px; border-right: 1px solid #000; }
    .report-head td:last-child { border-right: 0; }
    .head-brand { width: 30%; }
    .head-brand .brand-name { font-size: 13px; font-weight: bold; }
    .head-brand .brand-sub { font-size: 8px; color: #333; }
    .head-title { width: 40%; text-align: center; }
    .head-title .doc-title { font-size: 14px; font-weight: bold; letter-spacing: 0.03em; }
    .head-title .doc-subtitle { font-size: 8.5px; color: #333; text-transform: uppercase; }
    .head-doc { width: 30%; padding: 0 !important; }
    .doc-control { width: 100%; }
    .doc-control th, .doc-control td { border: 1px solid #000; padding: 4px 8px; font-size: 8.5px; text-align: left; }
    .doc-control th { width: 45%; font-weight: bold; }
    .doc-control tr:first-child th, .doc-control tr:first-child td { border-top: 0; }
    .doc-control th:first-child { border-left: 0; }

    /* --- Info filter + total --- */
    .report-info { width: 100%; border: 1px solid #000; border-top: 0; }
    .report-info td { padding: 5px 10px; font-size: 9px; }
    .report-info .info-total { text-align: right; }

    /* --- Rekap status (teks ringkas, tanpa warna) --- */
    .report-recap { width: 100%; border: 1px solid #000; border-top: 0; }
    .report-recap td { padding: 5px 8px; font-size: 8.5px; border-right: 1px solid #ccc; text-align: center; }
    .report-recap td:last-child { border-right: 0; }
    .report-recap .recap-title { font-weight: bold; text-align: left; width: 90px; }

    /* --- Tabel data --- */
    .data { width: 100%; table-layout: fixed; margin-top: 10px; }
    .data thead { display: table-header-group; }
    .data tr { page-break-inside: avoid; }
    .data th, .data td {
        border: 1px solid #999; padding: 3px 4px; font-size: 8px;
        vertical-align: top; word-wrap: break-word; overflow-wrap: break-word;
    }
    .data th {
        background: #e8e8e8; color: #000; font-weight: bold;
        text-align: center; text-transform: uppercase; border-color: #666;
    }
    .data td.c { text-align: center; }
    .data td.status { font-weight: bold; }
    /* Satu-satunya warna fungsional: status NG (cacat) merah agar langsung terlihat. */
    .data td.status-ng { color: #b71c1c; }
    .data td.empty { text-align: center; padding: 18px; color: #555; }

    /* --- Keterangan status --- */
    .status-note { margin-top: 6px; font-size: 8px; color: #333; }

    /* --- Tanda tangan --- */
    .signatures { width: 100%; margin-top: 20px; page-break-inside: avoid; }
    .signatures td { width: 33.33%; text-align: center; font-size: 9px; padding: 0 16px; vertical-align: bottom; }
    .signatures .sign-role { margin-bottom: 2px; }
    .signatures .sign-space { height: 46px; }
    .signatures .sign-name { border-top: 1px solid #000; padding-top: 3px; font-weight: bold; }

    .report-foot { margin-top: 16px; padding-top: 6px; text-align: center; font-size: 7.5px; color: #777; font-style: italic; }
</style>
