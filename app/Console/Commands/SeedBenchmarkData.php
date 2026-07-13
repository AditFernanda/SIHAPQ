<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Machine;
use App\Models\Product;
use App\Models\QcInspector;
use App\Models\QcNgType;
use App\Support\QcStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Perintah artisan (KHUSUS DEVELOPMENT — bukan fitur aplikasi).
 *
 * Mengisi database dengan data uji berjumlah besar (default 200.000 baris
 * inspeksi) agar pengujian performa/benchmark realistis. Dipakai berpasangan
 * dengan perintah qc:benchmark untuk kebutuhan Bab IV skripsi. Jangan dijalankan
 * di server produksi karena akan menambah data dummy dalam jumlah besar.
 */
class SeedBenchmarkData extends Command
{
    protected $signature = 'qc:seed-benchmark
        {--count=200000 : Jumlah baris qc_inspections yang dibuat}
        {--machines=50 : Minimal jumlah mesin}
        {--products=200 : Minimal jumlah part/produk}
        {--inspectors=20 : Minimal jumlah PIC QC}
        {--days=365 : Sebar tanggal inspeksi ke berapa hari ke belakang}
        {--fresh : Kosongkan dulu tabel qc_inspections}';

    protected $description = 'Membuat data dummy QC berskala besar untuk pengujian performa (skripsi).';

    public function handle(): int
    {
        $count = max(0, (int) $this->option('count'));
        $days = max(1, (int) $this->option('days'));

        if ($this->option('fresh')) {
            $this->warn('Mengosongkan tabel qc_inspections...');
            DB::table('qc_inspections')->delete();
        }

        $this->info('Menyiapkan data master pendukung...');
        $departmentIds = $this->ensureDepartments(5);
        $machines = $this->ensureMachines((int) $this->option('machines'), $departmentIds);
        $products = $this->ensureProducts((int) $this->option('products'));
        $inspectors = $this->ensureInspectors((int) $this->option('inspectors'));
        $ngTypes = QcNgType::query()
            ->where('status', 'aktif')
            ->pluck('id')
            ->all();

        $this->info("Membuat {$count} baris inspeksi (tersebar {$days} hari)...");
        DB::connection()->disableQueryLog();

        $statuses = $this->weightedStatuses();
        $startCode = (int) (DB::table('qc_inspections')->max('id') ?? 0) + 1;
        $chunkSize = 2000;
        $buffer = [];
        $pivotBuffer = [];
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $machine = $machines[array_rand($machines)];
            $product = $products[array_rand($products)];
            $inspector = $inspectors[array_rand($inspectors)];
            $status = $statuses[array_rand($statuses)];
            $passing = QcStatus::isPassing($status);

            $date = Carbon::now()->subDays(random_int(0, $days - 1))
                ->setTime(random_int(7, 22), random_int(0, 59), random_int(0, 59));
            $startTime = sprintf('%02d:%02d', random_int(7, 21), random_int(0, 59));
            $endTime = sprintf('%02d:%02d', (int) substr($startTime, 0, 2) + 1, random_int(0, 59));
            $code = 'QC-BMK-'.str_pad((string) ($startCode + $i), 9, '0', STR_PAD_LEFT);
            $notes = $this->randomNotes($machine, $product);
            $inspectionId = $startCode + $i;
            $selectedNgTypes = $status === QcStatus::NG && $ngTypes !== []
                ? $this->randomNgTypeIds($ngTypes)
                : [];
            $ngTypeLabel = $selectedNgTypes !== [] ? 'Multi jenis AB' : null;

            $buffer[] = [
                'inspection_code' => $code,
                'machine_id' => $machine['id'],
                'product_id' => $product['id'],
                'qc_inspector_id' => $inspector['id'],
                'quantity_inspected' => 1,
                'quantity_passed' => $passing ? 1 : 0,
                'quantity_failed' => $passing ? 0 : 1,
                'pass_percentage' => $passing ? 100 : 0,
                'status' => $passing ? 'pass' : 'fail',
                'result_status' => $status,
                'ng_type' => $ngTypeLabel,
                'notes' => $notes,
                'search_index' => trim(implode(' ', array_filter([
                    $code, $ngTypeLabel, $notes,
                    $product['name'], $product['product_code'], $product['process_name'], $product['customer_name'],
                    $machine['name'], $machine['machine_code'],
                    $inspector['name'],
                ]))),
                'inspection_date' => $date->format('Y-m-d H:i:s'),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'created_at' => $date->format('Y-m-d H:i:s'),
                'updated_at' => $date->format('Y-m-d H:i:s'),
            ];

            foreach ($selectedNgTypes as $ngTypeId) {
                $pivotBuffer[] = [
                    'qc_inspection_id' => $inspectionId,
                    'qc_ng_type_id' => $ngTypeId,
                    'created_at' => $date->format('Y-m-d H:i:s'),
                    'updated_at' => $date->format('Y-m-d H:i:s'),
                ];
            }

            if (count($buffer) >= $chunkSize) {
                DB::table('qc_inspections')->insert($buffer);
                if ($pivotBuffer !== []) {
                    DB::table('qc_inspection_ng_type')->insertOrIgnore($pivotBuffer);
                }
                $buffer = [];
                $pivotBuffer = [];
                $bar->advance($chunkSize);
            }
        }

        if ($buffer !== []) {
            DB::table('qc_inspections')->insert($buffer);
            if ($pivotBuffer !== []) {
                DB::table('qc_inspection_ng_type')->insertOrIgnore($pivotBuffer);
            }
            $bar->advance(count($buffer));
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Selesai. Total baris qc_inspections: '.DB::table('qc_inspections')->count());
        $this->line('Jalankan pengujian: php artisan qc:benchmark');

        return self::SUCCESS;
    }

    /**
     * @param  array<int,int>  $ngTypes
     * @return array<int,int>
     */
    private function randomNgTypeIds(array $ngTypes): array
    {
        shuffle($ngTypes);
        $max = min(3, count($ngTypes));

        return array_slice($ngTypes, 0, random_int(1, $max));
    }

    /** @return array<int,int> */
    private function ensureDepartments(int $min): array
    {
        for ($i = Department::count() + 1; Department::count() < $min; $i++) {
            Department::create([
                'name' => 'Bagian '.$i,
                'description' => 'Data dummy benchmark',
                'status' => 'aktif',
            ]);
        }

        return Department::pluck('id')->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function ensureMachines(int $min, array $departmentIds): array
    {
        for ($i = Machine::count() + 1; Machine::count() < $min; $i++) {
            Machine::create([
                'machine_code' => 'BMK-MCH-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'name' => 'Mesin Benchmark '.$i,
                'department_id' => $departmentIds[array_rand($departmentIds)],
                'status' => 'aktif',
            ]);
        }

        return Machine::select('id', 'machine_code', 'name')->get()->map(fn ($m) => $m->only(['id', 'machine_code', 'name']))->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function ensureProducts(int $min): array
    {
        $customers = ['Astra', 'Denso', 'Toyota', 'Aisin', 'Yamaha', 'Honda', 'Mitsubishi'];
        $processes = ['Stamping', 'Welding', 'Machining', 'Assembly', 'Painting', 'Injection'];

        for ($i = Product::count() + 1; Product::count() < $min; $i++) {
            Product::create([
                'product_code' => 'BMK-PRD-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'name' => 'Part Benchmark '.$i,
                'process_name' => $processes[array_rand($processes)],
                'customer_name' => $customers[array_rand($customers)],
                'status' => 'aktif',
            ]);
        }

        return Product::select('id', 'product_code', 'name', 'process_name', 'customer_name')
            ->get()
            ->map(fn ($p) => $p->only(['id', 'product_code', 'name', 'process_name', 'customer_name']))
            ->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function ensureInspectors(int $min): array
    {
        for ($i = QcInspector::count() + 1; QcInspector::count() < $min; $i++) {
            QcInspector::create([
                'employee_id' => 'BMK-QC-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'name' => 'PIC QC Benchmark '.$i,
                'pin' => '123456',
                'status' => 'aktif',
            ]);
        }

        return QcInspector::select('id', 'name')->get()->map(fn ($q) => $q->only(['id', 'name']))->all();
    }

    /** @return array<int,string> */
    private function weightedStatuses(): array
    {
        // Distribusi mendekati kondisi nyata: mayoritas lolos, sebagian abnormal.
        return array_merge(
            array_fill(0, 70, QcStatus::RP),
            array_fill(0, 15, QcStatus::NG),
            array_fill(0, 10, QcStatus::SR),
            array_fill(0, 5, QcStatus::SC),
        );
    }

    private function randomNotes(array $machine, array $product): string
    {
        $templates = [
            "Pengecekan rutin {$machine['name']} untuk part {$product['name']}.",
            "Hasil cek dimensi {$product['product_code']} sesuai standar.",
            "Catatan inspeksi {$machine['machine_code']} customer {$product['customer_name']}.",
            "Verifikasi proses {$product['process_name']} pada {$machine['name']}.",
        ];

        return $templates[array_rand($templates)];
    }
}
