{{--
    Kerangka HTML untuk PDF laporan QC (dirender oleh dompdf, BUKAN browser).
    Gaya isi dokumen diambil dari partial bersama qc.partials.report-styles
    (dipakai juga oleh halaman pratinjau), sehingga PDF & pratinjau selalu sama.
--}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan QC</title>
    <style>
        @page { margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; }
    </style>
    @include('qc.partials.report-styles')
</head>
<body>
    @include('qc.partials.report-document', [
        'printInspections' => $printInspections,
        'filterSummary' => $filterSummary,
        'totalRows' => $totalRows,
        'statusCounts' => $statusCounts,
        'generatedAt' => $generatedAt,
    ])
</body>
</html>
