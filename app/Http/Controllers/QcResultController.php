<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\QcInspection;
use Illuminate\Http\Request;

/**
 * Daftar hasil QC terbaru per mesin (dipakai role QC, supervisor, dan akun mesin).
 */
class QcResultController extends Controller
{
    public function index(Request $request, string $role = 'qc')
    {
        $filters = $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'machine_id' => 'nullable|integer|exists:machines,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'search' => 'nullable|string|max:100',
        ]);

        $hasDateFilters = ! empty($filters['date_from']) || ! empty($filters['date_to']);
        $inspectionDateScope = fn ($inspection) => $inspection->forOperationalDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        $query = Machine::with([
            'department',
            'latestQcInspection.product',
            'latestQcInspection.qcInspector',
            'latestQcInspection.ngTypes',
        ]);

        $query
            ->when(! empty($filters['machine_id']), fn ($q) => $q->whereKey($filters['machine_id']))
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('department_id', $filters['department_id']))
            ->when($hasDateFilters, fn ($q) => $q->whereHas('qcInspections', $inspectionDateScope))
            ->when(! empty($filters['search']), function ($q) use ($filters, $hasDateFilters, $inspectionDateScope) {
                $term = '%'.$filters['search'].'%';
                $q->where(function ($nested) use ($term, $hasDateFilters, $inspectionDateScope) {
                    $nested->where('name', 'like', $term)
                        ->orWhere('machine_code', 'like', $term)
                        ->orWhereHas('department', fn ($department) => $department->where('name', 'like', $term))
                        ->orWhereHas('qcInspections', function ($inspection) use ($term, $hasDateFilters, $inspectionDateScope) {
                            if ($hasDateFilters) {
                                $inspectionDateScope($inspection);
                            }

                            $inspection->where(function ($inspectionSearch) use ($term) {
                                $inspectionSearch
                                    ->where('ng_type', 'like', $term)
                                    ->orWhere('notes', 'like', $term)
                                    ->orWhereHas('ngTypes', fn ($ngType) => $ngType->where('name', 'like', $term))
                                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', $term)->orWhere('product_code', 'like', $term)->orWhere('process_name', 'like', $term)->orWhere('customer_name', 'like', $term))
                                    ->orWhereHas('qcInspector', fn ($inspector) => $inspector->where('name', 'like', $term));
                            });
                        });
                });
            });

        $machineRows = $query->orderBy('department_id')->orderBy('machine_code')->paginate(15)->withQueryString();
        if ($hasDateFilters) {
            $this->attachLatestInspectionsForDateFilter($machineRows, $filters);
        }

        return view('qc.results', [
            'role' => $role,
            'machineRows' => $machineRows,
            'filters' => $filters,
        ]);
    }

    private function attachLatestInspectionsForDateFilter($machineRows, array $filters): void
    {
        $machineIds = $machineRows->getCollection()->pluck('id')->all();
        if ($machineIds === []) {
            return;
        }

        $latestInspections = QcInspection::with(['product', 'qcInspector', 'ngTypes'])
            ->whereIn('machine_id', $machineIds)
            ->forOperationalDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->orderByDesc('inspection_date')
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->get()
            ->unique('machine_id')
            ->keyBy('machine_id');

        $machineRows->getCollection()->each(function ($machine) use ($latestInspections) {
            $machine->setRelation('latestQcInspection', $latestInspections->get($machine->id));
        });
    }
}
