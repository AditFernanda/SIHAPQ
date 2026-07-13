<?php

namespace App\Http\Controllers;

use App\Exports\QcInspectionExcelExport;
use App\Models\Department;
use App\Models\Machine;
use App\Models\QcInspection;
use App\Support\QcStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan QC: tampilan terfilter di layar, ekspor PDF (dompdf), dan
 * ekspor Excel .xlsx asli (PhpSpreadsheet). Ketiganya memakai filter yang sama
 * (tanggal, mesin, bagian, pencarian) agar hasil ekspor konsisten dgn tampilan.
 */
class QcReportController extends Controller
{
    public function index(Request $request, string $role = 'qc')
    {
        $filters = $this->validatedFilters($request);
        $query = $this->inspectionQuery();
        $this->applyFilters($query, $filters);
        $statusCounts = (clone $query)->select('result_status', DB::raw('count(*) as total'))
            ->groupBy('result_status')
            ->pluck('total', 'result_status');
        $inspections = $this->applyReportOrder($query)->paginate(15)->withQueryString();

        return view('qc.report', [
            'role' => $role,
            'inspections' => $inspections,
            'statusCounts' => QcStatus::countsFrom($statusCounts),
            'filters' => $filters,
        ]);
    }

    /**
     * Laporan PDF dengan alur DUA LANGKAH:
     *   1. Tanpa parameter  -> tampilkan halaman PRATINJAU di layar (qc.exports.preview)
     *      supaya pengguna bisa memeriksa dulu sebelum menyimpan.
     *   2. Dengan ?download=1 -> hasilkan & unduh berkas PDF asli via dompdf.
     * Dibatasi 1000 baris agar proses render PDF tetap ringan.
     */
    public function exportPdf(Request $request, string $role = 'qc')
    {
        $filters = $this->validatedFilters($request);
        $query = $this->inspectionQuery();
        $this->applyFilters($query, $filters);
        $statusCounts = (clone $query)->select('result_status', DB::raw('count(*) as total'))
            ->groupBy('result_status')
            ->pluck('total', 'result_status');

        $documentData = [
            'printInspections' => $this->applyReportOrder(clone $query)->limit(1000)->get(),
            'filterSummary' => $this->filterSummary($filters),
            'totalRows' => (clone $query)->count(),
            'statusCounts' => QcStatus::countsFrom($statusCounts),
            'generatedAt' => now(),
        ];

        // Langkah 2: unduh berkas PDF asli.
        if ($request->boolean('download')) {
            return Pdf::loadView('qc.exports.pdf', $documentData)
                ->setPaper('a4', 'landscape')
                ->download('laporan-qc-'.now()->format('Ymd-His').'.pdf');
        }

        // Langkah 1: tampilkan pratinjau + tautan unduh/cetak (filter dipertahankan).
        $activeFilters = array_filter($filters, fn ($value) => filled($value));
        $prefix = $role === 'supervisor' ? '/supervisor' : '/qc';

        return view('qc.exports.preview', $documentData + [
            'downloadUrl' => url($prefix.'/report/pdf').'?'.http_build_query($activeFilters + ['download' => 1]),
            'excelUrl' => url($prefix.'/report/excel').'?'.http_build_query($activeFilters),
            'backUrl' => url($prefix.'/report').'?'.http_build_query($activeFilters),
        ]);
    }

    /**
     * Ekspor laporan ke berkas Excel .xlsx ASLI (PhpSpreadsheet) untuk diunduh.
     * Dibatasi 10.000 baris untuk menjaga penggunaan memori tetap aman.
     */
    public function exportExcel(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $query = $this->inspectionQuery();
        $this->applyFilters($query, $filters);
        $statusCounts = (clone $query)->select('result_status', DB::raw('count(*) as total'))
            ->groupBy('result_status')
            ->pluck('total', 'result_status');
        $totalRows = (clone $query)->count();

        $export = new QcInspectionExcelExport(
            inspections: $this->applyReportOrder($query)->limit(10000)->get(),
            filterSummary: $this->filterSummary($filters),
            totalRows: $totalRows,
            statusCounts: QcStatus::countsFrom($statusCounts),
            generatedAt: now(),
        );

        return $export->download('laporan-qc-'.now()->format('Ymd-His').'.xlsx');
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'machine_id' => 'nullable|integer|exists:machines,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'search' => 'nullable|string|max:100',
        ]);
    }

    private function inspectionQuery()
    {
        return QcInspection::with([
            'machine.department',
            'product',
            'qcInspector',
            'ngTypes',
        ]);
    }

    private function applyFilters($query, array $filters): void
    {
        $query
            ->forOperationalDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->when(! empty($filters['machine_id']), fn ($q) => $q->where('machine_id', $filters['machine_id']))
            ->when(! empty($filters['department_id']), fn ($q) => $q->whereHas('machine', fn ($machine) => $machine->where('department_id', $filters['department_id'])))
            ->when(! empty($filters['search']), fn ($q) => $this->applySearch($q, $filters['search']));
    }

    // Pencarian gabungan:
    //   - FULLTEXT (MySQL/MariaDB) untuk kata panjang, cepat lewat search_index.
    //   - LIKE substring pada kolom BERMAKNA (kode mesin, part, customer, NG,
    //     inspector, catatan, bagian) agar istilah pendek/angka spt "60" cocok ke
    //     kode mesin. Sengaja TIDAK menyaring blob search_index dengan LIKE karena
    //     blob memuat nomor laporan bertanggal (mis. "2026..."), sehingga "60"
    //     akan salah cocok ke angka tanggal.
    private function applySearch($query, string $term): void
    {
        $like = '%'.$term.'%';
        $useFullText = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);

        $query->where(function ($searchQuery) use ($term, $like, $useFullText) {
            if ($useFullText) {
                $searchQuery->whereFullText('search_index', $term);
            }

            $searchQuery
                ->orWhere('ng_type', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhereHas('machine', fn ($machine) => $machine->where('machine_code', 'like', $like)->orWhere('name', 'like', $like))
                ->orWhereHas('machine.department', fn ($department) => $department->where('name', 'like', $like))
                ->orWhereHas('product', fn ($product) => $product->where('name', 'like', $like)->orWhere('product_code', 'like', $like)->orWhere('process_name', 'like', $like)->orWhere('customer_name', 'like', $like))
                ->orWhereHas('ngTypes', fn ($ngType) => $ngType->where('name', 'like', $like))
                ->orWhereHas('qcInspector', fn ($inspector) => $inspector->where('name', 'like', $like));
        });
    }

    private function applyReportOrder($query)
    {
        return $query
            ->orderByDesc('inspection_date')
            ->orderByDesc('start_time')
            ->orderBy('machine_id')
            ->orderBy('product_id');
    }

    private function filterSummary(array $filters): string
    {
        $parts = [];

        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $parts[] = 'Periode: '.Carbon::parse($filters['date_from'])->format('d/m/Y').' - '.Carbon::parse($filters['date_to'])->format('d/m/Y');
        } elseif (! empty($filters['date_from'])) {
            $parts[] = 'Periode: sejak '.Carbon::parse($filters['date_from'])->format('d/m/Y');
        } elseif (! empty($filters['date_to'])) {
            $parts[] = 'Periode: sampai '.Carbon::parse($filters['date_to'])->format('d/m/Y');
        } else {
            $parts[] = 'Periode: Semua tanggal';
        }

        if (! empty($filters['machine_id'])) {
            $machine = Machine::find((int) $filters['machine_id']);
            if ($machine) {
                $parts[] = 'Mesin: '.$machine->detailName();
            }
        }

        if (! empty($filters['department_id'])) {
            $department = Department::find((int) $filters['department_id']);
            if ($department) {
                $parts[] = 'Bagian: '.$department->name;
            }
        }

        if (! empty($filters['search'])) {
            $parts[] = 'Pencarian: "'.$filters['search'].'"';
        }

        return implode(' | ', $parts);
    }
}
