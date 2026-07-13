<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Mengisi master mesin dari file database/seeders/data/data_mesin.csv.
 *
 * Kolom CSV: kode mesin, nama mesin, Bagian.
 * - Departemen (Bagian) dibuat otomatis bila belum ada.
 * - Tiap mesin dibuatkan akun login mesin (role akun_mesin, username = kode mesin),
 *   mengikuti perilaku AdminMachineController.
 */
class MachineSeeder extends Seeder
{
    public function run(): void
    {
        $path = __DIR__.'/data/data_mesin.csv';

        if (! is_readable($path)) {
            $this->command?->warn("File CSV mesin tidak ditemukan: {$path}");

            return;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->command?->warn("Gagal membuka file CSV mesin: {$path}");

            return;
        }

        // Lewati baris header.
        fgetcsv($handle);

        $defaultPassword = (string) config('qc.machine_default_password', 'mesin123');
        $departments = [];
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $machineCode = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));
            $departmentName = trim((string) ($row[2] ?? ''));

            if ($machineCode === '' || $departmentName === '') {
                continue;
            }

            // Nama mesin kosong di CSV di-fallback ke kode mesin.
            if ($name === '') {
                $name = $machineCode;
            }

            // Departemen di-cache agar tidak query berulang; dibuat bila belum ada.
            if (! isset($departments[$departmentName])) {
                $department = Department::withTrashed()->firstOrCreate(
                    ['name' => $departmentName],
                    ['status' => 'aktif'],
                );

                if ($department->trashed()) {
                    $department->restore();
                }

                $departments[$departmentName] = $department->id;
            }

            DB::transaction(function () use ($machineCode, $name, $departments, $departmentName, $defaultPassword) {
                $machine = Machine::withTrashed()->updateOrCreate(
                    ['machine_code' => $machineCode],
                    [
                        'name' => $name,
                        'department_id' => $departments[$departmentName],
                        'status' => 'aktif',
                    ],
                );

                if ($machine->trashed()) {
                    $machine->restore();
                }

                // Akun login mesin: username = kode mesin (lihat AdminMachineController).
                $user = User::withTrashed()->firstOrNew(['username' => $machineCode]);
                $user->name = $name;
                $user->email = User::internalEmail($machineCode);
                $user->role = 'akun_mesin';
                $user->status = 'aktif';

                // Kata sandi hanya diisi saat akun baru agar reset password admin tidak tertimpa.
                if (! $user->exists) {
                    $user->password = Hash::make($defaultPassword);
                }

                $user->save();

                if ($user->trashed()) {
                    $user->restore();
                }

                if (! $user->machines()->where('machines.id', $machine->id)->exists()) {
                    $user->machines()->attach($machine->id);
                }
            });

            $imported++;
        }

        fclose($handle);

        $this->command?->info("Mesin diimpor: {$imported} baris.");
    }
}
