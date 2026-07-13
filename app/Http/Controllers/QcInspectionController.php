<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\Product;
use App\Models\QcInspection;
use App\Models\QcInspector;
use App\Models\QcNgType;
use App\Support\QcStatus;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Inti pencatatan QC: form input hasil cek, simpan inspeksi baru, dan
 * penyelesaian status Waiting menjadi keputusan final.
 */
class QcInspectionController extends Controller
{
    use LogsActivity;

    // Batas percobaan PIN yang salah sebelum dikunci sementara (anti brute-force).
    private const MAX_PIN_ATTEMPTS = 5;

    // Lama penguncian (detik) setelah batas percobaan PIN terlampaui.
    private const PIN_DECAY_SECONDS = 300;

    public function createWeb(Request $request)
    {
        $pendingInspection = $request->filled('resolve')
            ? $this->webInspectionQuery()
                ->where('result_status', QcStatus::WAITING)
                ->find($request->integer('resolve'))
            : null;

        return view('qc.input', [
            'machines' => Machine::with('department', 'users')
                ->whereHas('users', fn ($query) => $query->where('role', 'akun_mesin')->where('status', 'aktif'))
                ->where('status', 'aktif')
                ->orderBy('machine_code')
                ->get(),
            'products' => Product::where('status', 'aktif')->orderBy('name')->orderBy('product_code')->orderBy('process_name')->get(),
            'inspectors' => QcInspector::where('status', 'aktif')->orderBy('name')->get(),
            'ngTypes' => QcNgType::where('status', 'aktif')->orderBy('name')->get(),
            'todayInspections' => $this->todayQcInputHistory(),
            'pendingInspection' => $pendingInspection,
        ]);
    }

    public function storeWeb(Request $request)
    {
        $validated = $this->validateWebInput($request, true);

        $inspection = DB::transaction(function () use ($validated) {
            $inspection = QcInspection::create([
                'inspection_code' => $this->newInspectionCode(),
                'machine_id' => $validated['machine_id'],
                'product_id' => $validated['product_id'],
                'qc_inspector_id' => $validated['qc_inspector_id'],
                'quantity_inspected' => 1,
                ...$this->resultMetrics($validated['result_status']),
                'result_status' => $validated['result_status'],
                'ng_type' => null,
                'notes' => $validated['notes'] ?? null,
                'inspection_date' => Carbon::parse($validated['inspection_date'])->setTimeFromTimeString(now()->format('H:i:s')),
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
            ]);

            $this->syncNgTypes($inspection, $validated);

            return $inspection;
        });

        $inspection->load('machine', 'product', 'qcInspector');
        $this->logActivity(
            $request,
            'Input hasil QC',
            $inspection,
            sprintf(
                'Hasil QC %s untuk mesin %s, part %s, PIC %s.',
                QcStatus::label($inspection->result_status),
                $inspection->machineName(),
                $inspection->partName(),
                $inspection->inspectorName(),
            )
        );

        return redirect('/qc/input')->with([
            'success' => 'Hasil QC berhasil disimpan.',
            'savedInspectionId' => $inspection->id,
        ]);
    }

    public function resolvePendingWeb(Request $request, QcInspection $inspection)
    {
        abort_unless($inspection->result_status === QcStatus::WAITING, 404);

        $validated = $this->validateWebInput($request, true, false);

        DB::transaction(function () use ($inspection, $validated) {
            $inspection->fill([
                'machine_id' => $validated['machine_id'],
                'product_id' => $validated['product_id'],
                'qc_inspector_id' => $validated['qc_inspector_id'],
                ...$this->resultMetrics($validated['result_status']),
                'result_status' => $validated['result_status'],
                'ng_type' => null,
                'notes' => $validated['notes'] ?? null,
                'inspection_date' => Carbon::parse($validated['inspection_date'])->setTimeFromTimeString(now()->format('H:i:s')),
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
            ])->save();

            $this->syncNgTypes($inspection, $validated);
        });

        $inspection->load('machine', 'product', 'qcInspector');
        $this->logActivity(
            $request,
            'Selesaikan waiting QC',
            $inspection,
            sprintf(
                'Waiting QC diselesaikan menjadi %s untuk mesin %s, part %s, PIC %s.',
                QcStatus::label($inspection->result_status),
                $inspection->machineName(),
                $inspection->partName(),
                $inspection->inspectorName(),
            )
        );

        return redirect('/qc/input')->with([
            'success' => 'Waiting QC berhasil diselesaikan.',
            'savedInspectionId' => $inspection->id,
        ]);
    }

    private function webInspectionQuery()
    {
        return QcInspection::with([
            'machine.department',
            'product',
            'qcInspector',
            'ngTypes',
        ]);
    }

    private function validateWebInput(Request $request, bool $useCurrentEndTime = false, bool $allowPending = true): array
    {
        $request->merge([
            'start_time' => $this->normalizeTimeInput($request->input('start_time')),
            'end_time' => $useCurrentEndTime
                ? now()->format('H:i')
                : $this->normalizeTimeInput($request->input('end_time')),
        ]);

        $validated = $request->validate([
            'machine_id' => ['required', Rule::exists('machines', 'id')->where('status', 'aktif')],
            'product_id' => ['required', Rule::exists('products', 'id')->where('status', 'aktif')],
            'qc_inspector_id' => ['required', Rule::exists('qc_inspectors', 'id')->where('status', 'aktif')],
            'inspection_date' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:'.now()->subDays(7)->format('Y-m-d'),
            'pin' => 'required|digits:6',
            'result_status' => ['required', Rule::in($allowPending ? QcStatus::ALL : QcStatus::FINAL_STATUSES)],
            'ng_type_ids' => ['nullable', 'array', Rule::requiredIf(fn () => $request->input('result_status') === QcStatus::NG), 'min:1'],
            'ng_type_ids.*' => ['integer', Rule::exists('qc_ng_types', 'id')->where('status', 'aktif')],
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'notes' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn () => $request->input('result_status') === QcStatus::WAITING)],
        ], [
            'inspection_date.before_or_equal' => 'Tanggal cek tidak boleh melebihi hari ini.',
            'inspection_date.after_or_equal' => 'Tanggal cek maksimal 7 hari ke belakang.',
            'result_status.required' => 'Hasil cek wajib dipilih.',
            'result_status.in' => $allowPending ? 'Hasil cek tidak valid.' : 'Waiting harus diselesaikan dengan keputusan final.',
            'ng_type_ids.required' => 'Jenis NG wajib dipilih saat hasil cek NG.',
            'ng_type_ids.min' => 'Pilih minimal satu jenis NG.',
            'notes.required' => 'Keterangan wajib diisi saat hasil cek Waiting.',
        ]);

        if ($validated['result_status'] === QcStatus::NG && empty($validated['ng_type_ids'])) {
            throw ValidationException::withMessages([
                'ng_type_ids' => 'Jenis NG wajib dipilih saat hasil cek NG.',
            ]);
        }

        // Crossing midnight diperbolehkan, tapi durasi cek dibatasi maksimal 12 jam.
        $duration = $this->inspectionDurationMinutes($validated['start_time'], $validated['end_time']);
        if (($duration <= 0 && ! ($useCurrentEndTime && $duration === 0)) || $duration > 720) {
            throw ValidationException::withMessages([
                'end_time' => 'Waktu selesai cek harus setelah waktu mulai cek dan maksimal dalam rentang 12 jam.',
            ]);
        }

        // Mesin tanpa akun aktif tidak bisa menerima notifikasi hasil QC.
        $machineHasAccount = Machine::whereKey($validated['machine_id'])
            ->whereHas('users', fn ($query) => $query->where('role', 'akun_mesin')->where('status', 'aktif'))
            ->exists();

        if (! $machineHasAccount) {
            throw ValidationException::withMessages([
                'machine_id' => 'Mesin wajib memiliki akun mesin aktif sebelum bisa menerima hasil QC.',
            ]);
        }

        // PIN memverifikasi PIC QC yang dipilih, bukan pengguna yang masuk.
        // PIN hanya 6 digit (1 juta kombinasi), jadi verifikasinya di-throttle
        // per inspector+IP agar tidak bisa ditebak paksa lewat form input.
        $pinKey = 'pin-verify:'.$validated['qc_inspector_id'].'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($pinKey, self::MAX_PIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($pinKey);

            throw ValidationException::withMessages([
                'pin' => "Terlalu banyak percobaan PIN. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $inspector = QcInspector::findOrFail($validated['qc_inspector_id']);
        if (! $inspector->verifyPin($validated['pin'])) {
            RateLimiter::hit($pinKey, self::PIN_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'pin' => 'PIN anggota QC tidak sesuai.',
            ]);
        }

        RateLimiter::clear($pinKey);

        return $validated;
    }

    private function normalizeTimeInput($value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return substr($value, 0, 5);
    }

    private function resultMetrics(string $status): array
    {
        $isPassing = QcStatus::isPassing($status);

        return [
            'quantity_passed' => $isPassing ? 1 : 0,
            'quantity_failed' => $isPassing ? 0 : 1,
            'pass_percentage' => $isPassing ? 100 : 0,
            'status' => $isPassing ? 'pass' : 'fail',
        ];
    }

    private function syncNgTypes(QcInspection $inspection, array $validated): void
    {
        if ($validated['result_status'] !== QcStatus::NG) {
            $inspection->ngTypes()->detach();
            $inspection->forceFill([
                'ng_type' => null,
                'search_index' => $inspection->buildSearchIndex(),
            ])->saveQuietly();

            return;
        }

        $inspection->ngTypes()->sync($validated['ng_type_ids']);
        $inspection->load('ngTypes');
        $inspection->forceFill([
            'ng_type' => $inspection->ngTypes->pluck('name')->implode(', '),
            'search_index' => $inspection->buildSearchIndex(),
        ])->saveQuietly();
    }

    private function inspectionDurationMinutes(string $startTime, string $endTime): int
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', substr($startTime, 0, 5)));
        [$endHour, $endMinute] = array_map('intval', explode(':', substr($endTime, 0, 5)));

        $start = ($startHour * 60) + $startMinute;
        $end = ($endHour * 60) + $endMinute;

        if ($end < $start) {
            $end += 1440;
        }

        return $end - $start;
    }

    private function newInspectionCode(): string
    {
        do {
            $code = 'QC-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5));
        } while (QcInspection::where('inspection_code', $code)->exists());

        return $code;
    }

    private function todayQcInputHistory()
    {
        return $this->webInspectionQuery()
            ->forOperationalDay()
            ->latest('updated_at')
            ->limit(8)
            ->get();
    }
}
