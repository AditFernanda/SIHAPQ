<?php

namespace Database\Seeders;

use App\Models\QcInspector;
use Illuminate\Database\Seeder;

/**
 * Mengisi master PIC QC dari file database/seeders/data/list_pic_qc.csv.
 *
 * Kolom CSV: NIK, Nama, PIN, Status.
 * PIN wajib 6 digit dan otomatis di-hash oleh model QcInspector.
 */
class PicQcSeeder extends Seeder
{
    public function run(): void
    {
        $path = __DIR__.'/data/list_pic_qc.csv';

        if (! is_readable($path)) {
            $this->command?->warn("File CSV PIC QC tidak ditemukan: {$path}");

            return;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->command?->warn("Gagal membuka file CSV PIC QC: {$path}");

            return;
        }

        // Lewati baris header.
        fgetcsv($handle);

        $imported = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            // Lewati baris kosong.
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $employeeId = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));
            $pin = trim((string) ($row[2] ?? ''));
            $status = strtolower(trim((string) ($row[3] ?? 'aktif'))) === 'nonaktif' ? 'nonaktif' : 'aktif';

            if ($employeeId === '' || $name === '') {
                continue;
            }

            // PIN wajib 6 digit; baris dengan PIN tidak valid dilewati agar verifikasi input tetap aman.
            if (! preg_match('/^\d{6}$/', $pin)) {
                $this->command?->warn("PIC QC {$employeeId} dilewati: PIN harus 6 digit angka.");
                $skipped++;

                continue;
            }

            $inspector = QcInspector::withTrashed()->updateOrCreate(
                ['employee_id' => $employeeId],
                [
                    'name' => $name,
                    'pin' => $pin,
                    'status' => $status,
                ],
            );

            if ($inspector->trashed()) {
                $inspector->restore();
            }

            $imported++;
        }

        fclose($handle);

        $this->command?->info("PIC QC diimpor: {$imported} baris.".($skipped > 0 ? " Dilewati: {$skipped}." : ''));
    }
}
