<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder utama: data pokok yang selalu dibutuhkan (akun admin) plus impor
 * master data dari CSV (part, PIC QC, mesin).
 *
 * Master data CSV sengaja TIDAK dijalankan saat testing agar test tetap cepat
 * dan tidak bentrok dengan data fixture yang dibuat tiap test.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Akun admin awal; password diambil dari config qc.admin_default_password.
        $adminDefaultPassword = $this->requiredSeedValue('qc.admin_default_password', 'admin123');
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin',
                'email' => User::internalEmail('admin'),
                'password' => Hash::make($adminDefaultPassword),
                'role' => 'admin',
                'status' => 'aktif',
            ],
        );

        // Impor master data hanya di luar testing (lihat catatan di docblock kelas).
        if (! app()->runningUnitTests()) {
            $this->call([
                UserSeeder::class,
                MasterPartSeeder::class,
                PicQcSeeder::class,
                MachineSeeder::class,
            ]);
        }
    }

    /**
     * Ambil nilai config wajib; cegah pemakaian default tidak aman di produksi.
     */
    private function requiredSeedValue(string $configKey, string $unsafeDefault): string
    {
        $value = (string) config($configKey, '');

        if (app()->isProduction() && $value === $unsafeDefault) {
            throw new \RuntimeException("Ubah {$configKey} di environment produksi sebelum menjalankan seeder.");
        }

        return $value;
    }
}
