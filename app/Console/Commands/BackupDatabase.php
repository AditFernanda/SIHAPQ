<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Perintah artisan: membuat cadangan (backup) database aplikasi QC.
 *
 * Mendukung dua driver: MySQL/MariaDB (via mysqldump) dan SQLite (salin file).
 * File backup disimpan di folder qc.backup_path dan otomatis membuang backup
 * lama yang melewati batas hari (qc.backup_keep_days). Dijalankan manual atau
 * dijadwalkan (scheduler), bukan bagian dari alur aplikasi web.
 */
class BackupDatabase extends Command
{
    protected $signature = 'qc:backup-database {--connection= : Nama koneksi database} {--keep= : Jumlah hari backup disimpan}';

    protected $description = 'Membuat backup database aplikasi QC.';

    public function handle(): int
    {
        $connection = $this->option('connection') ?: config('database.default');
        $config = config("database.connections.{$connection}");

        if (! $config) {
            $this->error("Koneksi database {$connection} tidak ditemukan.");

            return self::FAILURE;
        }

        $backupDir = (string) config('qc.backup_path');
        File::ensureDirectoryExists($backupDir);

        $driver = $config['driver'] ?? null;
        $timestamp = now()->format('Ymd-His');
        $fileName = "{$connection}-{$timestamp}.".($driver === 'sqlite' ? 'sqlite' : 'sql');
        $target = $backupDir.DIRECTORY_SEPARATOR.$fileName;

        try {
            if ($driver === 'sqlite') {
                $this->backupSqlite($config, $target);
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $this->backupMysql($config, $target);
            } else {
                $this->error("Backup otomatis belum mendukung driver {$driver}.");

                return self::FAILURE;
            }
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->cleanOldBackups($backupDir, $connection);
        $this->info("Backup database berhasil dibuat: {$target}");

        return self::SUCCESS;
    }

    private function backupSqlite(array $config, string $target): void
    {
        $database = (string) ($config['database'] ?? '');
        $source = str_starts_with($database, DIRECTORY_SEPARATOR) ? $database : database_path($database);

        if (! is_file($source)) {
            throw new \RuntimeException("File database SQLite tidak ditemukan: {$source}");
        }

        File::copy($source, $target);
    }

    private function backupMysql(array $config, string $target): void
    {
        $command = [
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '-h',
            (string) ($config['host'] ?? '127.0.0.1'),
            '-P',
            (string) ($config['port'] ?? '3306'),
            '-u',
            (string) ($config['username'] ?? 'root'),
        ];

        if (! empty($config['unix_socket'])) {
            $command[] = '--socket='.$config['unix_socket'];
        }

        if (($config['password'] ?? '') !== '') {
            $command[] = '--password='.$config['password'];
        }

        $command[] = (string) $config['database'];

        $process = new Process($command);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump gagal dijalankan.');
        }

        File::put($target, $process->getOutput());
    }

    private function cleanOldBackups(string $backupDir, string $connection): void
    {
        $keepDays = (int) ($this->option('keep') ?: config('qc.backup_keep_days', 14));
        if ($keepDays <= 0) {
            return;
        }

        $limit = now()->subDays($keepDays)->getTimestamp();

        foreach (File::files($backupDir) as $file) {
            if (! str_starts_with($file->getFilename(), "{$connection}-")) {
                continue;
            }

            if ($file->getMTime() < $limit) {
                File::delete($file->getPathname());
            }
        }
    }
}
