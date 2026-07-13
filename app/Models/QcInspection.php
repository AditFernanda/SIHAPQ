<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Catatan satu hasil pengecekan QC; pusat data operasional
 * (status, waktu, jenis NG, dan indeks pencarian).
 */
class QcInspection extends Model
{
    use HasFactory, SoftDeletes;

    // Hari QC mengikuti ritme pabrik, default 07:00 sampai 07:00 hari berikutnya.
    private const DEFAULT_OPERATIONAL_DAY_START = '07:00';

    protected $fillable = [
        'inspection_code',
        'machine_id',
        'product_id',
        'qc_inspector_id',
        'quantity_inspected',
        'quantity_passed',
        'quantity_failed',
        'pass_percentage',
        'status',
        'result_status',
        'ng_type',
        'notes',
        'inspection_date',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'inspection_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Jaga kolom denormalisasi pencarian selalu sinkron setiap simpan/ubah.
        static::saving(function (self $inspection) {
            $inspection->search_index = $inspection->buildSearchIndex();
        });
    }

    public function buildSearchIndex(): string
    {
        $product = $this->relationLoaded('product')
            ? $this->product
            : ($this->product_id ? Product::find($this->product_id) : null);
        $machine = $this->relationLoaded('machine')
            ? $this->machine
            : ($this->machine_id ? Machine::find($this->machine_id) : null);
        $inspector = $this->relationLoaded('qcInspector')
            ? $this->qcInspector
            : ($this->qc_inspector_id ? QcInspector::find($this->qc_inspector_id) : null);
        $ngTypeNames = $this->relationLoaded('ngTypes')
            ? $this->ngTypes->pluck('name')->implode(' ')
            : '';

        return collect([
            $this->inspection_code,
            $this->ng_type,
            $ngTypeNames,
            $this->notes,
            $product?->name,
            $product?->product_code,
            $product?->process_name,
            $product?->customer_name,
            $machine?->name,
            $machine?->machine_code,
            $machine?->department?->name,
            $inspector?->name,
        ])->filter()->implode(' ');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function qcInspector()
    {
        return $this->belongsTo(QcInspector::class);
    }

    public function ngTypes()
    {
        return $this->belongsToMany(QcNgType::class, 'qc_inspection_ng_type')
            ->withTimestamps()
            ->orderBy('qc_ng_types.name');
    }

    public function machineName(): string
    {
        return $this->machine?->displayName() ?? '-';
    }

    public function sectionName(): string
    {
        return $this->machine?->department?->name ?? '-';
    }

    public function partName(): string
    {
        return $this->product?->name ?? '-';
    }

    public function partNumber(): string
    {
        return $this->product?->product_code ?? '-';
    }

    public function processName(): string
    {
        return $this->product?->processName() ?? '-';
    }

    public function customerName(): string
    {
        return $this->product?->customer_name ?? '-';
    }

    public function inspectorName(): string
    {
        return $this->qcInspector?->name ?? '-';
    }

    public function ngTypeNames(): string
    {
        if ($this->relationLoaded('ngTypes') && $this->ngTypes->isNotEmpty()) {
            return $this->ngTypes->pluck('name')->implode(', ');
        }

        return $this->ng_type ?: '-';
    }

    public static function currentOperationalDate($now = null): string
    {
        $now = $now ? Carbon::parse($now) : now();

        return $now->format('H:i') < self::operationalDayStart()
            ? $now->copy()->subDay()->toDateString()
            : $now->toDateString();
    }

    public function operationalDate(): string
    {
        $date = $this->inspection_date?->copy() ?? now();
        $time = substr((string) $this->start_time, 0, 5);

        return $time && $time < self::operationalDayStart()
            ? $date->subDay()->toDateString()
            : $date->toDateString();
    }

    public function scopeForOperationalDay($query, $date = null)
    {
        $date = $date
            ? Carbon::parse($date)->toDateString()
            : self::currentOperationalDate();

        return $query->forOperationalDateRange($date, $date);
    }

    public function scopeForOperationalDateRange($query, $dateFrom = null, $dateTo = null)
    {
        $dayStart = self::operationalDayStart();

        // Filter laporan memakai hari QC, bukan tanggal kalender murni.
        if ($dateFrom) {
            $fromStart = Carbon::parse($dateFrom)->startOfDay();
            $fromNextDayStart = $fromStart->copy()->addDay();

            $query->where(function ($nested) use ($fromStart, $fromNextDayStart, $dayStart) {
                $nested->where('inspection_date', '>=', $fromNextDayStart)
                    ->orWhere(function ($sameDay) use ($fromStart, $fromNextDayStart, $dayStart) {
                        $sameDay->where('inspection_date', '>=', $fromStart)
                            ->where('inspection_date', '<', $fromNextDayStart)
                            ->where('start_time', '>=', $dayStart);
                    });
            });
        }

        if ($dateTo) {
            $endStart = Carbon::parse($dateTo)->addDay()->startOfDay();
            $endNextDayStart = $endStart->copy()->addDay();

            $query->where(function ($nested) use ($endStart, $endNextDayStart, $dayStart) {
                $nested->where('inspection_date', '<', $endStart)
                    ->orWhere(function ($nextMorning) use ($endStart, $endNextDayStart, $dayStart) {
                        $nextMorning->where('inspection_date', '>=', $endStart)
                            ->where('inspection_date', '<', $endNextDayStart)
                            ->where('start_time', '<', $dayStart);
                    });
            });
        }

        return $query;
    }

    public static function operationalDayStart(): string
    {
        $value = (string) config('qc.operational_day_start', self::DEFAULT_OPERATIONAL_DAY_START);

        return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : self::DEFAULT_OPERATIONAL_DAY_START;
    }
}
