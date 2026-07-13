<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Perintah artisan: memeriksa kesiapan konfigurasi sebelum deploy.
 *
 * Bertindak sebagai "checklist otomatis" yang memverifikasi hal-hal yang sering
 * terlupa saat naik ke server: APP_KEY, mode debug/production, koneksi database,
 * folder yang harus writable, hasil build frontend, serta password bawaan yang
 * belum diganti. Dipakai di akhir skrip deploy.sh sebagai pengaman.
 */
class CheckDeploymentReadiness extends Command
{
    protected $signature = 'qc:deployment-check {--strict : Gagal bila rekomendasi production belum terpenuhi}';

    protected $description = 'Memeriksa kesiapan konfigurasi sebelum deploy lokal/perusahaan.';

    public function handle(): int
    {
        $strict = (bool) $this->option('strict');
        $errors = [];
        $warnings = [];

        $this->info('Memeriksa kesiapan deploy SIHAPQ...');

        $this->checkAppConfig($errors, $warnings);
        $this->checkDatabase($errors);
        $this->checkWritablePaths($errors);
        $this->checkFrontendBuild($errors);
        $this->checkQcConfig($warnings);

        $this->newLine();
        $this->renderRows('Wajib diperbaiki', $errors, 'error');
        $this->renderRows('Rekomendasi', $warnings, 'warn');

        if ($errors !== [] || ($strict && $warnings !== [])) {
            $this->newLine();
            $this->error($strict
                ? 'Deployment check belum lolos strict mode.'
                : 'Deployment check belum lolos karena ada item wajib diperbaiki.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Deployment check lolos.');

        return self::SUCCESS;
    }

    private function checkAppConfig(array &$errors, array &$warnings): void
    {
        if (! config('app.key')) {
            $errors[] = 'APP_KEY belum dibuat. Jalankan php artisan key:generate.';
        }

        if (app()->environment('production') === false) {
            $warnings[] = 'APP_ENV belum production. Untuk server perusahaan gunakan APP_ENV=production.';
        }

        if ((bool) config('app.debug')) {
            $warnings[] = 'APP_DEBUG masih aktif. Untuk deploy gunakan APP_DEBUG=false.';
        }

        $appUrl = (string) config('app.url');
        if ($appUrl === '' || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
            $warnings[] = 'APP_URL masih localhost. Untuk LAN gunakan IP/hostname server, contoh http://192.168.1.10.';
        }
    }

    private function checkDatabase(array &$errors): void
    {
        try {
            DB::connection()->getPdo();
            DB::table('users')->limit(1)->exists();
        } catch (\Throwable $exception) {
            $errors[] = 'Koneksi database gagal: '.$exception->getMessage();
        }
    }

    private function checkWritablePaths(array &$errors): void
    {
        foreach ([storage_path(), base_path('bootstrap/cache')] as $path) {
            if (! File::isDirectory($path) || ! is_writable($path)) {
                $errors[] = "Folder wajib writable: {$path}";
            }
        }
    }

    private function checkFrontendBuild(array &$errors): void
    {
        if (! File::exists(public_path('build/manifest.json'))) {
            $errors[] = 'Asset frontend belum dibuild. Jalankan npm run build.';
        }
    }

    private function checkQcConfig(array &$warnings): void
    {
        $unsafeValues = [
            'admin_default_password' => ['admin123', 'password', 'change-this-admin-password'],
            'qc_inspector_default_pin' => ['123456', '000000', '111111'],
            'machine_default_password' => ['mesin1234', 'password', 'change-this-machine-password'],
        ];

        foreach ($unsafeValues as $key => $values) {
            $value = (string) config("qc.{$key}", '');
            if (in_array($value, $values, true) || str_starts_with($value, 'change-this')) {
                $warnings[] = "Konfigurasi qc.{$key} masih memakai nilai bawaan/tidak aman.";
            }
        }

        $backupPath = (string) config('qc.backup_path');
        if (! File::isDirectory($backupPath)) {
            File::ensureDirectoryExists($backupPath);
        }

        if (! is_writable($backupPath)) {
            $warnings[] = "Folder backup database tidak writable: {$backupPath}";
        }

        $dashboardCacheTtl = (int) config('qc.dashboard_cache_ttl', 60);
        if ($dashboardCacheTtl > 60) {
            $warnings[] = 'QC_DASHBOARD_CACHE_TTL lebih dari 60 detik. Dashboard supervisor bisa terasa lambat update.';
        }
    }

    private function renderRows(string $title, array $rows, string $type): void
    {
        if ($rows === []) {
            $this->line("<info>{$title}: tidak ada.</info>");

            return;
        }

        $this->line($type === 'error' ? "<error>{$title}:</error>" : "<comment>{$title}:</comment>");
        foreach ($rows as $row) {
            $this->line('- '.$row);
        }
    }
}
