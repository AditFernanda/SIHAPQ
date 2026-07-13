<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

/**
 * Service Provider utama aplikasi.
 * Tempat mendaftarkan konfigurasi global yang berlaku sekali saat aplikasi
 * dijalankan (bootstrapping). Saat ini: menetapkan kebijakan kata sandi terpusat.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Dijalankan setelah semua service provider terdaftar.
     * Cocok untuk mengatur aturan/binding yang berlaku di seluruh aplikasi.
     */
    public function boot(): void
    {
        // Aturan kata sandi terpusat: minimal 8 karakter, mengandung huruf dan angka.
        // Cukup ubah di sini bila kebijakan keamanan perusahaan berubah.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }
}
