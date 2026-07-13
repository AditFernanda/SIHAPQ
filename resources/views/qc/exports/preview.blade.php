{{--
    Halaman PRATINJAU laporan QC di layar (sebelum diunduh).

    Menampilkan dokumen persis seperti hasil PDF (memakai partial & gaya yang
    sama), dilengkapi toolbar untuk mengunduh PDF/Excel atau mencetak. Toolbar
    otomatis disembunyikan saat dokumen dicetak.

    Variabel: $printInspections, $filterSummary, $totalRows, $statusCounts,
    $generatedAt, $downloadUrl (PDF), $excelUrl, $backUrl.
--}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pratinjau Laporan QC</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f4; font-family: Arial, Helvetica, sans-serif; color: #111; }

        /* Toolbar tindakan (tidak ikut tercetak) */
        .toolbar {
            position: sticky; top: 0; z-index: 10;
            display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
            padding: 12px 18px; background: #fff; border-bottom: 1px solid #d4dde6;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
        }
        .toolbar .hint { margin: 0; margin-right: auto; font-size: 12px; color: #5f6b76; }
        .toolbar a, .toolbar button {
            display: inline-flex; align-items: center; gap: 6px;
            border: 1px solid #cfd6dd; border-radius: 8px; background: #fff;
            padding: 8px 14px; font-size: 13px; font-weight: 600; color: #1f2937;
            cursor: pointer; text-decoration: none;
        }
        .toolbar a.primary { background: #1f3a5f; border-color: #1f3a5f; color: #fff; }
        .toolbar a:focus-visible, .toolbar button:focus-visible { outline: 2px solid #1f3a5f; outline-offset: 2px; }

        /* Area kertas */
        .sheet-wrap { padding: 22px 16px 48px; }
        .sheet {
            width: 277mm; max-width: 100%; margin: 0 auto; background: #fff;
            padding: 16px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.14); overflow-x: auto;
        }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet-wrap { padding: 0; }
            .sheet { width: auto; max-width: none; box-shadow: none; padding: 0; }
        }
    </style>
    @include('qc.partials.report-styles')
</head>
<body>
    <div class="toolbar">
        <p class="hint">Pratinjau laporan. Periksa dulu, lalu unduh atau cetak.</p>
        <a href="{{ $backUrl }}">&larr; Kembali</a>
        <button type="button" onclick="window.print()">Cetak</button>
        <a href="{{ $excelUrl }}">Unduh Excel</a>
        <a href="{{ $downloadUrl }}" class="primary">Unduh PDF</a>
    </div>

    <div class="sheet-wrap">
        <div class="sheet">
            @include('qc.partials.report-document', [
                'printInspections' => $printInspections,
                'filterSummary' => $filterSummary,
                'totalRows' => $totalRows,
                'statusCounts' => $statusCounts,
                'generatedAt' => $generatedAt,
            ])
        </div>
    </div>
</body>
</html>
