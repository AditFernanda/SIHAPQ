<?php

namespace App\Console\Commands;

use App\Models\QcInspection;
use App\Support\QcStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Perintah artisan (KHUSUS DEVELOPMENT — bukan fitur aplikasi).
 *
 * Mengukur waktu respons pencarian laporan dan pemuatan dashboard untuk
 * membandingkan performa sebelum vs sesudah optimasi (indeks DB & cache).
 * Hasilnya dipakai sebagai bukti pengujian performa di Bab IV skripsi.
 * Tidak dipanggil oleh alur aplikasi manapun; jalankan manual saat menguji.
 */
class BenchmarkPerformance extends Command
{
    protected $signature = 'qc:benchmark
        {--term=Toyota : Kata kunci pencarian yang diuji}
        {--repeat=5 : Jumlah pengulangan tiap skenario untuk rata-rata}';

    protected $description = 'Mengukur waktu respons sebelum vs sesudah optimasi (pencarian & dashboard) untuk bab pengujian skripsi.';

    public function handle(): int
    {
        $term = (string) $this->option('term');
        $repeat = max(1, (int) $this->option('repeat'));
        $driver = DB::connection()->getDriverName();
        $totalRows = DB::table('qc_inspections')->count();

        $this->info('=== Pengujian Performa SIHAPQ ===');
        $this->line("Driver database : {$driver}");
        $this->line('Total baris qc_inspections : '.number_format($totalRows));
        $this->line("Kata kunci uji  : \"{$term}\"");
        $this->line("Pengulangan     : {$repeat}x (diambil rata-rata)");
        $this->newLine();

        $rows = [];

        // Pencarian: LIKE multi-tabel (lama) vs FULLTEXT (baru).
        [$likeMs, $likeCount] = $this->measure($repeat, fn () => $this->searchWithLike($term));
        $rows[] = ['Pencarian (LIKE multi-tabel)', $this->ms($likeMs), $likeCount];

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            [$ftMs, $ftCount] = $this->measure($repeat, fn () => $this->searchWithFullText($term));
            $rows[] = ['Pencarian (FULLTEXT)', $this->ms($ftMs), $ftCount];
            $rows[] = ['Peningkatan pencarian', $this->speedup($likeMs, $ftMs), '-'];
        } else {
            $rows[] = ['Pencarian (FULLTEXT)', 'butuh MySQL', '-'];
        }

        // Dashboard tren harian 30 hari: tanpa cache vs dengan cache.
        $trendKey = 'qc:benchmark:trend-daily';
        Cache::forget($trendKey);
        [$noCacheMs] = $this->measure($repeat, fn () => $this->dailyTrend());
        Cache::forget($trendKey);
        Cache::remember($trendKey, 300, fn () => $this->dailyTrend());
        [$cacheMs] = $this->measure($repeat, fn () => Cache::remember($trendKey, 300, fn () => $this->dailyTrend()));
        Cache::forget($trendKey);
        $rows[] = ['Dashboard tren (tanpa cache)', $this->ms($noCacheMs), '-'];
        $rows[] = ['Dashboard tren (dengan cache)', $this->ms($cacheMs), '-'];
        $rows[] = ['Peningkatan dashboard', $this->speedup($noCacheMs, $cacheMs), '-'];

        // Pagination laporan, halaman pertama (15 baris).
        [$pageMs, $pageCount] = $this->measure($repeat, function () {
            $page = QcInspection::with(['machine.department', 'product', 'qcInspector'])
                ->orderByDesc('inspection_date')
                ->limit(15)
                ->get();

            return $page->count();
        });
        $rows[] = ['Laporan (1 halaman, 15 baris)', $this->ms($pageMs), $pageCount];

        $this->table(['Skenario', 'Rata-rata waktu', 'Jumlah hasil'], $rows);
        $this->newLine();
        $this->line('Catatan: angka waktu dalam milidetik (ms). Salin tabel ini untuk bab Pengujian.');

        return self::SUCCESS;
    }

    /**
     * Jalankan callback berulang, kembalikan [rata-rata ms, hasil terakhir].
     *
     * @return array{0: float, 1: mixed}
     */
    private function measure(int $repeat, callable $callback): array
    {
        $durations = [];
        $result = null;

        for ($i = 0; $i < $repeat; $i++) {
            $start = hrtime(true);
            $result = $callback();
            $durations[] = (hrtime(true) - $start) / 1_000_000; // ns -> ms
        }

        return [array_sum($durations) / count($durations), $result];
    }

    private function searchWithLike(string $term): int
    {
        $like = '%'.$term.'%';

        return QcInspection::query()
            ->where(function ($nested) use ($like) {
                $nested->where('inspection_code', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', $like)->orWhere('product_code', 'like', $like)->orWhere('process_name', 'like', $like)->orWhere('customer_name', 'like', $like))
                    ->orWhereHas('machine', fn ($machine) => $machine->where('name', 'like', $like)->orWhere('machine_code', 'like', $like));
            })
            ->count();
    }

    private function searchWithFullText(string $term): int
    {
        return QcInspection::query()->whereFullText('search_index', $term)->count();
    }

    /** @return Collection<int, float> */
    private function dailyTrend()
    {
        return collect(range(29, 0))->map(function ($days) {
            $date = Carbon::now()->subDays($days);
            $total = QcInspection::forOperationalDay($date)->whereIn('result_status', QcStatus::FINAL_STATUSES)->count();
            $ab = QcInspection::forOperationalDay($date)->where('result_status', QcStatus::NG)->count();

            return $total > 0 ? round(($ab / $total) * 100, 1) : 0;
        })->values();
    }

    private function ms(float $value): string
    {
        return number_format($value, 2).' ms';
    }

    private function speedup(float $before, float $after): string
    {
        if ($after <= 0) {
            return '-';
        }

        return number_format($before / $after, 1).'x lebih cepat';
    }
}
