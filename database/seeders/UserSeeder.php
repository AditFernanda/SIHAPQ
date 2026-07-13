<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun pengguna aplikasi selain admin & akun mesin: supervisor dan QC Inspector.
 * Berisi akun demo dengan password contoh, hanya untuk pengembangan/pengujian.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Akun demo dengan password contoh; jangan dijalankan di produksi.
        if (app()->isProduction()) {
            throw new \RuntimeException('UserSeeder berisi akun demo dengan password contoh dan tidak boleh dijalankan di produksi. Buat akun asli lewat menu admin.');
        }

        $users = [
            ['username' => 'anggi', 'name' => 'Anggi', 'role' => 'qc_inspector', 'password' => 'Anggi123'],
            ['username' => 'dudi', 'name' => 'Dudi', 'role' => 'supervisor', 'password' => 'Dudi1234'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['username' => $user['username']],
                [
                    'name' => $user['name'],
                    'email' => User::internalEmail($user['username']),
                    'password' => Hash::make($user['password']),
                    'role' => $user['role'],
                    'status' => 'aktif',
                ],
            );
        }
    }
}
