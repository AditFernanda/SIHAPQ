<?php

namespace App\Console\Commands;

use App\Models\Machine;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Perintah artisan: menyelaraskan data mesin dengan akun login "akun_mesin".
 *
 * Latar belakang (inti novelty skripsi): setiap mesin produksi punya satu akun
 * khusus (role akun_mesin) agar layar area produksi bisa menampilkan hasil QC
 * tanpa operator harus datang ke area QC. Perintah ini menjaga agar:
 *   1) setiap mesin punya tepat satu akun,
 *   2) data akun (username/nama/status) selalu ikut perubahan data mesin,
 *   3) akun "yatim" (mesinnya sudah dihapus) ikut dibersihkan.
 *
 * Dipanggil manual (php artisan machines:sync-accounts), bukan fitur runtime.
 */
class SyncMachineAccounts extends Command
{
    protected $signature = 'machines:sync-accounts {--password= : Password default untuk akun baru}';

    protected $description = 'Buat akun_mesin untuk setiap mesin yang belum punya akun, sinkron status, dan hapus akun yatim.';

    public function handle(): int
    {
        $password = (string) ($this->option('password') ?: config('qc.machine_default_password', 'mesin1234'));
        $created = 0;
        $updated = 0;
        $orphans = 0;
        DB::transaction(function () use ($password, &$created, &$updated) {
            $machines = Machine::with(['users' => fn ($q) => $q->where('role', 'akun_mesin')])->get();

            foreach ($machines as $machine) {
                $existing = $machine->users->first();
                $username = $this->uniqueUsername($machine->machine_code, $existing?->id);
                $email = User::internalEmail($username);

                if ($existing) {
                    $existing->update([
                        'username' => $username,
                        'name' => $machine->name,
                        'email' => $email,
                        'status' => $machine->status,
                    ]);
                    $updated++;

                    continue;
                }

                $user = User::create([
                    'username' => $username,
                    'name' => $machine->name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => 'akun_mesin',
                    'status' => $machine->status,
                ]);
                $user->machines()->attach($machine->id);
                $created++;
            }
        });
        $orphans = $this->cleanOrphanAccounts();

        $this->info("Backfill selesai - dibuat: {$created}, diperbarui: {$updated}, akun yatim dihapus: {$orphans}.");
        if ($created > 0) {
            $this->line("Password default akun baru: {$password}");
        }

        return self::SUCCESS;
    }

    private function cleanOrphanAccounts(): int
    {
        $count = 0;

        // Akun mesin yatim muncul jika user ada, tapi master mesinnya sudah tidak ada.
        DB::transaction(function () use (&$count) {
            $orphanUsers = User::where('role', 'akun_mesin')
                ->whereNotExists(function ($query) {
                    $query
                        ->selectRaw('1')
                        ->from('user_machines')
                        ->join('machines', 'machines.id', '=', 'user_machines.machine_id')
                        ->whereColumn('user_machines.user_id', 'users.id')
                        ->whereNull('machines.deleted_at');
                })
                ->get();

            foreach ($orphanUsers as $user) {
                $user->machines()->detach();
                $user->delete();
                $count++;
            }
        });

        return $count;
    }

    private function uniqueUsername(string $machineCode, ?int $exceptUserId): string
    {
        $candidate = trim($machineCode);

        if (User::where('username', $candidate)
            ->when($exceptUserId, fn ($q) => $q->where('id', '!=', $exceptUserId))
            ->withTrashed()
            ->exists()
        ) {
            throw new \RuntimeException("Username {$candidate} sudah dipakai user lain. Kode mesin harus unik terhadap username.");
        }

        return $candidate;
    }
}
