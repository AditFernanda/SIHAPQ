<?php

namespace App\Exports;

use App\Support\QcStatus;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Membangun berkas Excel (.xlsx) ASLI untuk laporan hasil QC menggunakan
 * PhpSpreadsheet. Berbeda dengan ekspor lama yang hanya HTML ber-ekstensi .xls
 * (memicu peringatan "format tidak cocok" di Excel), berkas ini adalah format
 * spreadsheet resmi sehingga terbuka mulus tanpa peringatan.
 *
 * Gaya sengaja dibuat BERSIH & FORMAL: dominan hitam-putih dengan sedikit
 * abu-abu untuk baris judul, tanpa warna-warni, meniru form QC resmi di industri.
 */
class QcInspectionExcelExport
{
    /** Judul kolom tabel data. */
    private const HEADERS = [
        'No', 'Mesin', 'Bagian', 'Tanggal', 'Nama Part', 'No Part', 'Proses',
        'Customer', 'Mulai Cek', 'Selesai Cek', 'Status', 'Jenis NG', 'PIC QC', 'Keterangan',
    ];

    private const HEADER_FILL = 'E8E8E8'; // Abu-abu muda untuk baris judul kolom.

    private const LABEL_FILL = 'F2F2F2';  // Abu-abu sangat muda untuk sel label metadata.

    public function __construct(
        private Collection $inspections,
        private string $filterSummary,
        private int $totalRows,
        private array $statusCounts,
        private \DateTimeInterface $generatedAt,
    ) {}

    /**
     * Susun spreadsheet lalu kembalikan sebagai response unduhan .xlsx.
     */
    public function download(string $fileName): StreamedResponse
    {
        $spreadsheet = $this->build();

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Rakit seluruh isi & gaya spreadsheet.
     */
    private function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan QC');
        $sheet->setShowGridlines(false);

        $lastColumn = 'N'; // 14 kolom (A..N)

        $this->writeHeader($sheet, $lastColumn);
        $dataStartRow = $this->writeColumnTitles($sheet, 8);
        $this->writeRows($sheet, $dataStartRow);
        $this->finishLayout($sheet, $lastColumn);

        return $spreadsheet;
    }

    /**
     * Tulis kop dokumen, metadata (No. Dokumen/Revisi/Tgl), filter, total, & rekap status.
     */
    private function writeHeader(Worksheet $sheet, string $lastColumn): void
    {
        // Baris 1: judul utama (hitam tebal di atas putih, tanpa warna).
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', 'LAPORAN HASIL PENGECEKAN QUALITY');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Baris 2: subjudul (nama perusahaan).
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', config('qc.company_name').' - Sistem Informasi Hasil Pengecekan Quality');
        $sheet->getStyle('A2')->getFont()->setBold(true)->getColor()->setRGB('333333');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Baris 3: metadata dokumen dalam satu baris.
        $sheet->setCellValue('A3', 'No. Dokumen');
        $sheet->setCellValue('B3', 'FM-QC-01');
        $sheet->setCellValue('F3', 'No. Revisi');
        $sheet->setCellValue('G3', '00');
        $sheet->setCellValue('K3', 'Tgl. Cetak');
        $sheet->setCellValue('L3', $this->generatedAt->format('d/m/Y H:i'));

        // Baris 4-5: filter & total data.
        $sheet->setCellValue('A4', 'Filter');
        $sheet->mergeCells('B4:N4');
        $sheet->setCellValue('B4', $this->filterSummary ?: 'Periode: Semua tanggal');
        $sheet->setCellValue('A5', 'Total Data');
        $sheet->mergeCells('B5:N5');
        $sheet->setCellValue('B5', number_format($this->totalRows).' baris');

        // Baris 6: rekap jumlah per status sebagai teks ringkas (tanpa warna).
        $sheet->setCellValue('A6', 'Rekap Status');
        $sheet->mergeCells('B6:N6');
        $sheet->setCellValue('B6', $this->recapText());

        // Beri gaya label (abu muda, tebal) pada semua sel label metadata.
        foreach (['A3', 'F3', 'K3', 'A4', 'A5', 'A6'] as $labelCell) {
            $sheet->getStyle($labelCell)->getFont()->setBold(true);
            $sheet->getStyle($labelCell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::LABEL_FILL);
        }
    }

    /**
     * Rangkai teks rekap jumlah tiap status dalam satu baris.
     */
    private function recapText(): string
    {
        $parts = [];
        foreach (['RP', 'NG', 'SR', 'SC', 'WAITING'] as $code) {
            $parts[] = QcStatus::label($code).': '.($this->statusCounts[$code] ?? 0);
        }

        return implode('   |   ', $parts);
    }

    /**
     * Tulis baris judul kolom. Kembalikan nomor baris awal data.
     */
    private function writeColumnTitles(Worksheet $sheet, int $row): int
    {
        foreach (array_values(self::HEADERS) as $index => $title) {
            // Ubah indeks 0,1,2,... menjadi huruf kolom A,B,C,... (hindari increment string).
            $cell = Coordinate::stringFromColumnIndex($index + 1).$row;
            $sheet->setCellValue($cell, $title);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_FILL);
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        return $row + 1;
    }

    /**
     * Tulis seluruh baris data inspeksi mulai dari $startRow.
     */
    private function writeRows(Worksheet $sheet, int $startRow): void
    {
        $row = $startRow;

        foreach ($this->inspections as $index => $inspection) {
            $sheet->setCellValueExplicit("A{$row}", $index + 1, DataType::TYPE_NUMERIC);
            $sheet->setCellValue("B{$row}", $inspection->machineName());
            $sheet->setCellValue("C{$row}", $inspection->sectionName());
            // No Part, tanggal, & jam ditulis sebagai teks agar tidak diubah format otomatis Excel.
            $sheet->setCellValueExplicit("D{$row}", $inspection->inspection_date?->format('Y-m-d') ?? '', DataType::TYPE_STRING);
            $sheet->setCellValue("E{$row}", $inspection->partName());
            $sheet->setCellValueExplicit("F{$row}", $inspection->partNumber(), DataType::TYPE_STRING);
            $sheet->setCellValue("G{$row}", $inspection->processName());
            $sheet->setCellValue("H{$row}", $inspection->customerName());
            $sheet->setCellValueExplicit("I{$row}", $inspection->start_time ?: '-', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("J{$row}", $inspection->end_time ?: '-', DataType::TYPE_STRING);
            $sheet->setCellValue("K{$row}", QcStatus::label($inspection->result_status));
            $sheet->setCellValue("L{$row}", $inspection->ngTypeNames());
            $sheet->setCellValue("M{$row}", $inspection->inspectorName());
            // Rapikan keterangan multi-baris jadi satu baris.
            $sheet->setCellValue("N{$row}", $inspection->notes ? preg_replace('/\s*\R+\s*/u', ' ', $inspection->notes) : '-');

            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            // Kolom status ditebalkan; khusus NG (cacat) diberi teks merah agar langsung terlihat.
            $statusFont = $sheet->getStyle("K{$row}")->getFont()->setBold(true);
            if ($inspection->result_status === QcStatus::NG) {
                $statusFont->getColor()->setRGB('B71C1C');
            }
            $sheet->getStyle("K{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
        }

        if ($this->inspections->isEmpty()) {
            $sheet->mergeCells("A{$startRow}:N{$startRow}");
            $sheet->setCellValue("A{$startRow}", 'Tidak ada data sesuai filter.');
            $sheet->getStyle("A{$startRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    /**
     * Terapkan lebar kolom, garis tabel, dan freeze header agar rapi & mudah dibaca.
     */
    private function finishLayout(Worksheet $sheet, string $lastColumn): void
    {
        $widths = [
            'A' => 5, 'B' => 16, 'C' => 16, 'D' => 12, 'E' => 26, 'F' => 16, 'G' => 18,
            'H' => 18, 'I' => 11, 'J' => 11, 'K' => 10, 'L' => 22, 'M' => 18, 'N' => 34,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        // Garis tipis untuk seluruh area tabel data (judul kolom + isi).
        $lastRow = max(8, $sheet->getHighestRow());
        $sheet->getStyle("A8:{$lastColumn}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('999999');

        // Bekukan baris judul kolom supaya tetap terlihat saat di-scroll.
        $sheet->freezePane('A9');

        // Cetak dalam orientasi landscape, muat selebar 1 halaman.
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
    }
}
