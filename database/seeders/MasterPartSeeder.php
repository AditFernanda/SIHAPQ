<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Mengisi master part dari file database/seeders/data/list_part.csv.
 *
 * Kolom CSV: No Part, Nama Part, Proses, Customer, Status.
 */
class MasterPartSeeder extends Seeder
{
    public function run(): void
    {
        $path = __DIR__.'/data/list_part.csv';

        if (! is_readable($path)) {
            $this->command?->warn("File CSV master part tidak ditemukan: {$path}");

            return;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->command?->warn("Gagal membuka file CSV master part: {$path}");

            return;
        }

        // Lewati baris header (dan buang BOM bila ada).
        fgetcsv($handle);

        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            // Lewati baris kosong.
            if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $productCode = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));
            $processName = trim((string) ($row[2] ?? '')) ?: 'Proses 1';
            $customerName = trim((string) ($row[3] ?? '')) ?: null;
            $status = strtolower(trim((string) ($row[4] ?? 'aktif'))) === 'nonaktif' ? 'nonaktif' : 'aktif';

            if ($productCode === '' || $name === '') {
                continue;
            }

            $storedProduct = Product::withTrashed()->updateOrCreate(
                [
                    'product_code' => $productCode,
                    'name' => $name,
                    'process_name' => $processName,
                ],
                [
                    'customer_name' => $customerName,
                    'status' => $status,
                ],
            );

            if ($storedProduct->trashed()) {
                $storedProduct->restore();
            }

            $imported++;
        }

        fclose($handle);

        $this->command?->info("Master part diimpor: {$imported} baris.");
    }
}
