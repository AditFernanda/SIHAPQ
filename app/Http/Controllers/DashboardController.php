<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Machine;
use App\Models\Product;
use App\Models\QcInspection;
use App\Models\QcInspector;
use App\Models\QcNgType;
use App\Models\User;
use App\Support\QcStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menyediakan data dashboard untuk tiap peran (admin, QC, supervisor,
 * akun mesin), termasuk agregasi & tren berat yang di-cache.
 */
class DashboardController extends Controller
{
    /**
     * Dashboard ADMIN: ringkasan seluruh master data (user, mesin, produk,
     * inspector, jenis NG, departemen) beserta aktivitas terbaru sistem.
     */
    public function admin()
    {
        $machineStats = $this->statusSummary(Machine::query());
        $totalMachines = $machineStats['total'];
        $roleOrder = ['admin', 'supervisor', 'qc_inspector', 'akun_mesin'];
        $usersByRole = User::select('role', DB::raw('count(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role');

        return view('admin.dashboard', [
            'totalUsers' => $usersByRole->sum(),
            'usersByRole' => collect($roleOrder)->mapWithKeys(fn ($role) => [$role => (int) ($usersByRole[$role] ?? 0)]),
            'machineStats' => $machineStats,
            'totalMachines' => $totalMachines,
            'productStats' => $this->statusSummary(Product::query()),
            'inspectorStats' => $this->statusSummary(QcInspector::query()),
            'ngTypeStats' => $this->statusSummary(QcNgType::query()),
            'totalDepartments' => Department::count(),
            'departmentMachines' => Department::withCount('machines')->orderByDesc('machines_count')->orderBy('name')->get(),
            'customerProductRows' => Product::query()
                ->select('customer_name')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('customer_name')
                ->orderByDesc('total')
                ->orderBy('customer_name')
                ->limit(6)
                ->get(),
            'recentAdminActivities' => ActivityLog::with('user')->latest()->limit(6)->get(),
        ]);
    }

    /**
     * Dashboard QC INSPECTOR: fokus pada hari operasional berjalan —
     * jumlah per status hari ini, 10 inspeksi terbaru, dan daftar inspeksi
     * berstatus Waiting yang masih perlu diselesaikan menjadi keputusan final.
     */
    public function qc()
    {
        $operationalDate = QcInspection::currentOperationalDate();
        $statusCounts = $this->statusCounts(QcInspection::forOperationalDay($operationalDate));
        $totalToday = QcStatus::finalTotal($statusCounts);
        $recent = $this->inspectionQuery()
            ->forOperationalDay($operationalDate)
            ->latest('updated_at')
            ->limit(10)
            ->get();
        $pendingInspections = $this->inspectionQuery()
            ->where('result_status', QcStatus::WAITING)
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return view('qc.dashboard', [
            'totalToday' => $totalToday,
            'statusCounts' => $statusCounts,
            'recentInspections' => $recent,
            'pendingInspections' => $pendingInspections,
        ]);
    }

    /**
     * Dashboard SUPERVISOR: analisis mutu tingkat manajemen.
     * Menyediakan diagram Pareto jenis NG, mesin & part penyumbang NG terbanyak,
     * serta tren harian/bulanan tingkat NG. Bisa difilter per periode dan per
     * departemen. Karena agregasinya berat (puluhan query), hasilnya di-cache.
     */
    public function supervisor(Request $request)
    {
        $operationalDate = QcInspection::currentOperationalDate();
        $periodOptions = [
            'this_month' => 'Bulan ini',
            'last_3_months' => '3 bulan terakhir',
            'last_6_months' => '6 bulan terakhir',
            'this_year' => 'Tahun ini',
        ];
        $requestedPeriod = (string) $request->query('periode_ng', 'this_month');
        $selectedNgPeriod = array_key_exists($requestedPeriod, $periodOptions)
            ? $requestedPeriod
            : 'this_month';
        [$periodStart, $periodEnd] = $this->supervisorNgPeriodRange($selectedNgPeriod);

        // Filter opsional: batasi analisis NG ke satu bagian/departemen.
        $departments = Department::where('status', 'aktif')->orderBy('name')->get(['id', 'name']);
        $requestedDepartment = $request->integer('bagian_id');
        $selectedDepartmentId = $departments->contains('id', $requestedDepartment) ? $requestedDepartment : null;

        $todayStatusCounts = $this->statusCounts(QcInspection::forOperationalDay($operationalDate));
        $totalToday = QcStatus::finalTotal($todayStatusCounts);
        $dashboardCacheVersion = $this->dashboardCacheVersion();
        $periodNgFilter = fn ($query) => $query
            ->where('result_status', QcStatus::NG)
            ->forOperationalDateRange($periodStart->toDateString(), $periodEnd->toDateString());

        // Agregasi berat (puluhan query) di-cache agar dashboard tetap cepat saat data besar.
        // Lihat config qc.dashboard_cache_ttl. Set 0 untuk mengukur performa tanpa cache.
        $departmentCacheKey = $selectedDepartmentId ?? 'all';
        $topNg = $this->remember("qc:dash:topng-type-pareto:{$selectedNgPeriod}:dept-{$departmentCacheKey}:{$operationalDate}:{$dashboardCacheVersion}", function () use ($periodNgFilter, $periodStart, $periodEnd, $selectedDepartmentId) {
            $machines = Machine::query()
                ->when($selectedDepartmentId, fn ($query) => $query->where('department_id', $selectedDepartmentId))
                ->whereHas('qcInspections', $periodNgFilter)
                ->withCount(['qcInspections as ng_count' => $periodNgFilter])
                ->orderByDesc('ng_count')
                ->orderBy('machine_code')
                ->limit(10)
                ->get();

            $ngTypes = QcInspection::query()
                ->forOperationalDateRange($periodStart->toDateString(), $periodEnd->toDateString())
                ->join('qc_inspection_ng_type', 'qc_inspections.id', '=', 'qc_inspection_ng_type.qc_inspection_id')
                ->join('qc_ng_types', 'qc_ng_types.id', '=', 'qc_inspection_ng_type.qc_ng_type_id')
                ->whereNull('qc_inspections.deleted_at')
                ->where('qc_inspections.result_status', QcStatus::NG)
                ->when($selectedDepartmentId, fn ($query) => $query->whereHas('machine', fn ($m) => $m->where('department_id', $selectedDepartmentId)))
                ->selectRaw('qc_ng_types.name as ng_type_label')
                ->selectRaw('COUNT(*) as ng_count')
                ->groupBy('qc_ng_types.name')
                ->orderByDesc('ng_count')
                ->orderBy('ng_type_label')
                ->get();

            $topPartRows = $this->topPartNgRows($periodStart->toDateString(), $periodEnd->toDateString(), $selectedDepartmentId);

            $unclassifiedTotal = QcInspection::query()
                ->where('result_status', QcStatus::NG)
                ->forOperationalDateRange($periodStart->toDateString(), $periodEnd->toDateString())
                ->when($selectedDepartmentId, fn ($query) => $query->whereHas('machine', fn ($m) => $m->where('department_id', $selectedDepartmentId)))
                ->whereDoesntHave('ngTypes')
                ->count();

            if ($unclassifiedTotal > 0) {
                $ngTypes->push((object) [
                    'ng_type_label' => 'Belum diklasifikasi',
                    'ng_count' => $unclassifiedTotal,
                ]);
                $ngTypes = $ngTypes
                    ->sortBy([
                        ['ng_count', 'desc'],
                        ['ng_type_label', 'asc'],
                    ])
                    ->values();
            }

            // --- Susun data Diagram Pareto jenis NG ---
            // Prinsip Pareto (80/20): ambil 10 jenis NG terbanyak, sisanya
            // digabung jadi "Lainnya", lalu hitung persentase kumulatif agar
            // supervisor tahu sedikit jenis cacat yang menyumbang mayoritas NG.
            $paretoTypes = $ngTypes->take(10);
            $otherTotal = (int) $ngTypes->slice(10)->sum('ng_count');
            $ngOccurrenceTotal = (int) $ngTypes->sum('ng_count');
            $paretoTotal = max($ngOccurrenceTotal, 1);
            $runningTotal = 0;
            $paretoLabels = $paretoTypes->pluck('ng_type_label')->values();
            $paretoTotals = $paretoTypes->pluck('ng_count')->map(fn ($total) => (int) $total)->values();

            if ($otherTotal > 0) {
                $paretoLabels->push('Lainnya');
                $paretoTotals->push($otherTotal);
            }

            $paretoCumulative = $paretoTotals
                ->map(function ($total) use (&$runningTotal, $paretoTotal) {
                    $runningTotal += (int) $total;

                    return round(($runningTotal / $paretoTotal) * 100, 1);
                })
                ->values();

            $periodFinalTotal = QcInspection::query()
                ->whereIn('result_status', QcStatus::FINAL_STATUSES)
                ->forOperationalDateRange($periodStart->toDateString(), $periodEnd->toDateString())
                ->when($selectedDepartmentId, fn ($query) => $query->whereHas('machine', fn ($m) => $m->where('department_id', $selectedDepartmentId)))
                ->count();
            $periodNgTotal = QcInspection::query()
                ->where('result_status', QcStatus::NG)
                ->forOperationalDateRange($periodStart->toDateString(), $periodEnd->toDateString())
                ->when($selectedDepartmentId, fn ($query) => $query->whereHas('machine', fn ($m) => $m->where('department_id', $selectedDepartmentId)))
                ->count();

            return [
                'machineLabels' => $machines->map(fn ($machine) => $machine->displayName())->values(),
                'machineTotals' => $machines->pluck('ng_count')->values(),
                'ngTypeTotal' => $ngTypes->count(),
                'periodFinalTotal' => $periodFinalTotal,
                'periodNgTotal' => $periodNgTotal,
                'topPartRows' => $topPartRows,
                'ngTypeParetoLabels' => $paretoLabels,
                'ngTypeParetoTotals' => $paretoTotals,
                'ngTypeParetoCumulative' => $paretoCumulative,
            ];
        });

        $trendData = $this->remember("qc:dash:trend-daily:{$operationalDate}:{$dashboardCacheVersion}", function () {
            return collect(range(29, 0))->map(function ($days) {
                $date = now()->subDays($days);
                $total = QcInspection::forOperationalDay($date)->whereIn('result_status', QcStatus::FINAL_STATUSES)->count();
                $ab = QcInspection::forOperationalDay($date)->where('result_status', QcStatus::NG)->count();

                return $total > 0 ? round(($ab / $total) * 100, 1) : 0;
            })->values();
        });

        $monthlyTrendData = $this->remember('qc:dash:trend-monthly:'.now()->format('Y-m').":{$dashboardCacheVersion}", function () {
            return collect(range(11, 0))->map(function ($months) {
                $date = now()->subMonthsNoOverflow($months);
                $total = QcInspection::whereMonth('inspection_date', $date->month)
                    ->whereYear('inspection_date', $date->year)
                    ->whereIn('result_status', QcStatus::FINAL_STATUSES)
                    ->count();
                $ab = QcInspection::whereMonth('inspection_date', $date->month)
                    ->whereYear('inspection_date', $date->year)
                    ->where('result_status', QcStatus::NG)
                    ->count();

                return $total > 0 ? round(($ab / $total) * 100, 1) : 0;
            })->values();
        });

        return view('supervisor.dashboard', [
            'totalToday' => $totalToday,
            'todayStatusCounts' => $todayStatusCounts,
            'ngRateToday' => $totalToday > 0 ? round(($todayStatusCounts[QcStatus::NG] / $totalToday) * 100, 1) : 0,
            'periodOptions' => $periodOptions,
            'selectedNgPeriod' => $selectedNgPeriod,
            'selectedNgPeriodLabel' => $periodOptions[$selectedNgPeriod],
            'selectedNgPeriodRange' => $periodStart->translatedFormat('d M Y').' - '.$periodEnd->translatedFormat('d M Y'),
            'departments' => $departments,
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedDepartmentLabel' => $selectedDepartmentId
                ? ($departments->firstWhere('id', $selectedDepartmentId)?->name ?? 'Semua bagian')
                : 'Semua bagian',
            'latestAbInspections' => $this->inspectionQuery()->where('result_status', QcStatus::NG)->latest('inspection_date')->limit(15)->get(),
            'machineLabels' => $topNg['machineLabels'],
            'machineTotals' => $topNg['machineTotals'],
            'ngTypeTotal' => $topNg['ngTypeTotal'],
            'periodFinalTotal' => $topNg['periodFinalTotal'],
            'periodNgTotal' => $topNg['periodNgTotal'],
            'periodNgRate' => $topNg['periodFinalTotal'] > 0 ? round(($topNg['periodNgTotal'] / $topNg['periodFinalTotal']) * 100, 1) : 0,
            'topPartRows' => $topNg['topPartRows'],
            'ngTypeParetoLabels' => $topNg['ngTypeParetoLabels'],
            'ngTypeParetoTotals' => $topNg['ngTypeParetoTotals'],
            'ngTypeParetoCumulative' => $topNg['ngTypeParetoCumulative'],
            'trendLabels' => collect(range(29, 0))->map(fn ($days) => now()->subDays($days)->format('d'))->values(),
            'trendData' => $trendData,
            'monthlyTrendLabels' => collect(range(11, 0))->map(fn ($months) => now()->subMonthsNoOverflow($months)->translatedFormat('M Y'))->values(),
            'monthlyTrendData' => $monthlyTrendData,
        ]);
    }

    // Cache agregasi dashboard. TTL 0 berarti hitung langsung (tanpa cache).
    private function remember(string $key, callable $callback)
    {
        $ttl = (int) config('qc.dashboard_cache_ttl', 300);

        return $ttl > 0 ? Cache::remember($key, $ttl, $callback) : $callback();
    }

    private function dashboardCacheVersion(): string
    {
        $latestUpdate = QcInspection::query()->max('updated_at');
        $totalRows = QcInspection::query()->count();

        return $totalRows.'-'.($latestUpdate ? strtotime((string) $latestUpdate) : 0);
    }

    private function topPartNgRows(string $periodStart, string $periodEnd, ?int $departmentId = null)
    {
        return QcInspection::with('product')
            ->where('result_status', QcStatus::NG)
            ->whereNotNull('product_id')
            ->forOperationalDateRange($periodStart, $periodEnd)
            ->when($departmentId, fn ($query) => $query->whereHas('machine', fn ($m) => $m->where('department_id', $departmentId)))
            ->select('product_id')
            ->selectRaw('COUNT(*) as ng_count')
            ->groupBy('product_id')
            ->orderByDesc('ng_count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => (object) [
                'part_name' => $row->product?->name ?? '-',
                'part_number' => $row->product?->product_code ?? '-',
                'customer_name' => $row->product?->customer_name ?? '-',
                'ng_count' => (int) $row->ng_count,
                'dominant_ng_type' => $this->dominantPartNgType((int) $row->product_id, $periodStart, $periodEnd, $departmentId),
                'dominant_machine' => $this->dominantPartMachine((int) $row->product_id, $periodStart, $periodEnd, $departmentId),
            ])
            ->values();
    }

    private function dominantPartNgType(int $productId, string $periodStart, string $periodEnd, ?int $departmentId = null): string
    {
        $dominantType = QcInspection::query()
            ->forOperationalDateRange($periodStart, $periodEnd)
            ->join('qc_inspection_ng_type', 'qc_inspections.id', '=', 'qc_inspection_ng_type.qc_inspection_id')
            ->join('qc_ng_types', 'qc_ng_types.id', '=', 'qc_inspection_ng_type.qc_ng_type_id')
            ->where('qc_inspections.result_status', QcStatus::NG)
            ->where('qc_inspections.product_id', $productId)
            ->when($departmentId, fn ($query) => $query->whereHas('machine', fn ($m) => $m->where('department_id', $departmentId)))
            ->selectRaw('qc_ng_types.name as label')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('qc_ng_types.name')
            ->orderByDesc('total')
            ->orderBy('label')
            ->value('label');

        if ($dominantType) {
            return (string) $dominantType;
        }

        return (string) (QcInspection::query()
            ->where('result_status', QcStatus::NG)
            ->where('product_id', $productId)
            ->whereNotNull('ng_type')
            ->where('ng_type', '<>', '')
            ->forOperationalDateRange($periodStart, $periodEnd)
            ->when($departmentId, fn ($query) => $query->whereHas('machine', fn ($m) => $m->where('department_id', $departmentId)))
            ->selectRaw('ng_type as label')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('ng_type')
            ->orderByDesc('total')
            ->orderBy('label')
            ->value('label') ?: '-');
    }

    private function dominantPartMachine(int $productId, string $periodStart, string $periodEnd, ?int $departmentId = null): string
    {
        $machine = QcInspection::query()
            ->forOperationalDateRange($periodStart, $periodEnd)
            ->join('machines', 'machines.id', '=', 'qc_inspections.machine_id')
            ->where('qc_inspections.result_status', QcStatus::NG)
            ->where('qc_inspections.product_id', $productId)
            ->when($departmentId, fn ($query) => $query->where('machines.department_id', $departmentId))
            ->selectRaw('machines.machine_code as machine_code')
            ->selectRaw('machines.name as machine_name')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('machines.id', 'machines.machine_code', 'machines.name')
            ->orderByDesc('total')
            ->orderBy('machines.machine_code')
            ->first();

        return $machine?->machine_code ?: ($machine?->machine_name ?: '-');
    }

    /**
     * Dashboard AKUN MESIN (inti kebaruan/novelty skripsi): layar yang dipasang
     * di area produksi. Menampilkan hasil QC untuk mesin milik akun yang login
     * saja, sehingga operator tidak perlu datang ke area QC. Layar ini di-refresh
     * otomatis tiap 30 detik dari sisi tampilan (lihat komponen auto-refresh).
     */
    public function mesin()
    {
        $userId = session('user_id');
        $user = $userId ? User::with(['machines.department'])->find($userId) : null;
        $machine = $user?->machines->first();

        $history = $this->inspectionQuery()
            ->when($machine, fn ($query) => $query->where('machine_id', $machine->id))
            ->when(! $machine, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('inspection_date')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('mesin.dashboard', [
            'machine' => $machine,
            'latestInspection' => $history->first(),
            'history' => $history,
            'statusCounts' => $this->statusCounts(
                QcInspection::query()
                    ->when($machine, fn ($query) => $query->where('machine_id', $machine->id))
                    ->when(! $machine, fn ($query) => $query->whereRaw('1 = 0'))
                    ->forOperationalDay()
            ),
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

    private function supervisorNgPeriodRange(string $period): array
    {
        return match ($period) {
            'last_3_months' => [now()->subMonthsNoOverflow(2)->startOfMonth(), now()->endOfDay()],
            'last_6_months' => [now()->subMonthsNoOverflow(5)->startOfMonth(), now()->endOfDay()],
            'this_year' => [now()->startOfYear(), now()->endOfDay()],
            default => [now()->startOfMonth(), now()->endOfDay()],
        };
    }

    // Ringkasan total dan jumlah yang berstatus aktif untuk satu master data.
    private function statusSummary($query): array
    {
        $total = (clone $query)->count();

        return [
            'total' => $total,
            'active' => (clone $query)->where('status', 'aktif')->count(),
        ];
    }

    private function statusCounts($query): array
    {
        $counts = $query->select('result_status', DB::raw('count(*) as total'))
            ->groupBy('result_status')
            ->pluck('total', 'result_status');

        return QcStatus::countsFrom($counts);
    }
}
