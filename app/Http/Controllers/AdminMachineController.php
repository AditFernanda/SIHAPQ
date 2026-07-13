<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Machine;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Master mesin sekaligus akun login mesin yang dibuat otomatis dari kode mesin.
 */
class AdminMachineController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $machines = Machine::with(['department', 'users' => fn ($q) => $q->where('role', 'akun_mesin')])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->search.'%';
                $query->where(function ($nested) use ($term) {
                    $nested
                        ->where('machine_code', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhereHas('department', fn ($department) => $department->where('name', 'like', $term));
                });
            })
            ->orderBy('machine_code')
            ->get();
        $departments = Department::where('status', 'aktif')->orderBy('name')->get();
        $editingMachine = $request->filled('edit') ? Machine::find($request->integer('edit')) : null;
        $resettingMachine = $request->filled('reset') ? Machine::with(['users' => fn ($q) => $q->where('role', 'akun_mesin')])->find($request->integer('reset')) : null;
        $showForm = $request->boolean('create') || (bool) $editingMachine;

        return view('admin.machines', compact('machines', 'departments', 'editingMachine', 'resettingMachine', 'showForm'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'machine_code' => 'required|string|unique:machines',
            'name' => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'status' => 'required|in:aktif,nonaktif',
        ]);

        $validated['machine_code'] = trim($validated['machine_code']);
        $validated['name'] = trim($validated['name']);
        $this->ensureMachineUsernameAvailable($validated['machine_code']);

        $finalUsername = null;

        // Setiap master mesin langsung dibuatkan akun masuk mesin.
        DB::transaction(function () use ($request, $validated, &$finalUsername) {
            $machine = Machine::create($validated);
            $finalUsername = $this->machineUsername($machine->machine_code);
            $user = User::create([
                'username' => $finalUsername,
                'name' => $machine->name,
                'email' => User::internalEmail($finalUsername),
                'password' => Hash::make($this->defaultPassword()),
                'role' => 'akun_mesin',
                'status' => $machine->status,
            ]);
            $user->machines()->attach($machine->id);
            $this->logActivity($request, 'Tambah mesin', $machine, "Mesin {$machine->machine_code} ditambahkan dan akun {$finalUsername} dibuat.");
        });

        return redirect('/admin/machines')->with('success', "Mesin berhasil ditambahkan. Akun mesin otomatis dibuat - nama pengguna: {$finalUsername}, kata sandi: ".$this->defaultPassword());
    }

    public function update(Request $request, $id)
    {
        $machine = Machine::findOrFail($id);

        $validated = $request->validate([
            'machine_code' => 'required|string|unique:machines,machine_code,'.$id,
            'name' => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'status' => 'required|in:aktif,nonaktif',
        ]);

        $validated['machine_code'] = trim($validated['machine_code']);
        $validated['name'] = trim($validated['name']);
        $existingUser = $machine->users()->where('role', 'akun_mesin')->first();
        $this->ensureMachineUsernameAvailable($validated['machine_code'], $existingUser?->id);

        // Kode mesin menjadi nama pengguna akun mesin.
        DB::transaction(function () use ($request, $machine, $validated, $existingUser) {
            $machine->update($validated);
            $newUsername = $this->machineUsername($machine->machine_code);

            if ($existingUser) {
                $existingUser->update([
                    'username' => $newUsername,
                    'name' => $machine->name,
                    'email' => User::internalEmail($newUsername),
                    'status' => $machine->status,
                ]);
                $this->logActivity($request, 'Ubah mesin', $machine, "Mesin {$machine->machine_code} diperbarui beserta akun mesinnya.");
            } else {
                $user = User::create([
                    'username' => $newUsername,
                    'name' => $machine->name,
                    'email' => User::internalEmail($newUsername),
                    'password' => Hash::make($this->defaultPassword()),
                    'role' => 'akun_mesin',
                    'status' => $machine->status,
                ]);
                $user->machines()->attach($machine->id);
                $this->logActivity($request, 'Buat akun mesin', $machine, "Akun mesin {$newUsername} dibuat untuk {$machine->machine_code}.");
            }
        });

        return redirect('/admin/machines')->with('success', 'Mesin berhasil diperbarui. Akun mesin turut diperbarui.');
    }

    public function resetPassword(Request $request, $id)
    {
        $machine = Machine::findOrFail($id);
        $machineUser = $machine->users()->where('role', 'akun_mesin')->first();

        if (! $machineUser) {
            return back()->withErrors(['machine' => 'Akun mesin belum tersedia. Simpan ulang data mesin untuk membuat akun otomatis.']);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $machineUser->update(['password' => Hash::make($validated['password'])]);
        $this->logActivity($request, 'Atur ulang kata sandi akun mesin', $machine, "Kata sandi akun mesin {$machineUser->username} diatur ulang oleh admin.");

        return redirect('/admin/machines')->with('success', "Kata sandi akun {$machineUser->username} berhasil diubah.");
    }

    public function destroy(Request $request, $id)
    {
        $machine = Machine::findOrFail($id);
        if ($machine->qcInspections()->exists()) {
            // Riwayat QC lama harus tetap utuh, jadi mesin cukup dinonaktifkan.
            DB::transaction(function () use ($request, $machine) {
                $machine->update(['status' => 'nonaktif']);
                $machine->users()->where('role', 'akun_mesin')->update(['status' => 'nonaktif']);
                $this->logActivity($request, 'Nonaktifkan mesin', $machine, "Mesin {$machine->machine_code} dinonaktifkan karena sudah memiliki riwayat QC.");
            });

            return redirect('/admin/machines')->with('success', 'Mesin sudah memiliki riwayat QC, sehingga statusnya dinonaktifkan.');
        }

        DB::transaction(function () use ($request, $machine) {
            $machineUser = $machine->users()->where('role', 'akun_mesin')->first();
            if ($machineUser) {
                $machineUser->machines()->detach();
                $machineUser->delete();
            }
            $machine->delete();
            $this->logActivity($request, 'Hapus mesin', $machine, "Mesin {$machine->machine_code} dan akun terkait dihapus.");
        });

        return redirect('/admin/machines')->with('success', 'Mesin dan akun mesin berhasil dihapus.');
    }

    private function machineUsername(string $machineCode): string
    {
        return trim($machineCode);
    }

    private function defaultPassword(): string
    {
        return (string) config('qc.machine_default_password', 'mesin1234');
    }

    private function ensureMachineUsernameAvailable(string $machineCode, ?int $exceptUserId = null): void
    {
        $username = $this->machineUsername($machineCode);

        $exists = User::where('username', $username)
            ->when($exceptUserId, fn ($q) => $q->where('id', '!=', $exceptUserId))
            ->withTrashed()
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'machine_code' => "Kode mesin {$username} tidak bisa dipakai karena sudah menjadi nama pengguna akun lain.",
            ]);
        }
    }
}
