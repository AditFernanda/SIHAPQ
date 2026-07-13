<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Master pengguna non-mesin (admin/supervisor/QC): CRUD + reset kata
 * sandi, dengan proteksi agar admin aktif terakhir tidak hilang.
 */
class AdminUserController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $roleOrder = ['admin' => 1, 'supervisor' => 2, 'qc_inspector' => 3, 'akun_mesin' => 4];
        $users = User::with('machines.department')
            ->where(function ($query) {
                $query
                    ->where('role', '!=', 'akun_mesin')
                    ->orWhereHas('machines');
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = (string) $request->search;
                $term = '%'.$search.'%';
                $roleMatches = $this->matchingRoleCodes($search);
                $query->where(function ($subQuery) use ($term, $roleMatches) {
                    $subQuery
                        ->where('username', 'like', $term)
                        ->orWhere('name', 'like', $term);

                    if ($roleMatches !== []) {
                        $subQuery->orWhereIn('role', $roleMatches);
                    }
                });
            })
            ->get()
            ->sortBy(fn ($user) => sprintf('%02d-%s', $roleOrder[$user->role] ?? 99, strtolower($user->username)))
            ->values();
        $editingUser = $request->filled('edit') ? User::where('role', '!=', 'akun_mesin')->find($request->integer('edit')) : null;
        $resettingUser = $request->filled('reset') ? User::where('role', '!=', 'akun_mesin')->find($request->integer('reset')) : null;
        $showForm = $request->boolean('create') || (bool) $editingUser;

        return view('admin.users', compact('users', 'editingUser', 'resettingUser', 'showForm'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', Rule::unique('users')->whereNull('deleted_at')],
            'name' => 'required|string',
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => 'required|in:admin,supervisor,qc_inspector',
            'status' => 'nullable|in:aktif,nonaktif',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['email'] = User::internalEmail($validated['username']);
        $validated['status'] = $validated['status'] ?? 'aktif';

        $user = User::create($validated);
        $this->logActivity($request, 'Tambah pengguna', $user, "Pengguna {$user->username} ditambahkan dengan peran {$user->role}.");

        return redirect('/admin/users')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if ($user->role === 'akun_mesin') {
            return back()->withErrors(['user' => 'Akun mesin dikelola dari menu Master Mesin.']);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', Rule::unique('users')->ignore($id)->whereNull('deleted_at')],
            'name' => 'required|string',
            'role' => 'required|in:admin,supervisor,qc_inspector',
            'status' => 'nullable|in:aktif,nonaktif',
        ]);

        $validated['status'] = $validated['status'] ?? $user->status;
        $validated['email'] = User::internalEmail($validated['username']);

        if ($this->wouldRemoveLastActiveAdmin($user, $validated)) {
            return back()->withErrors(['user' => 'Minimal harus ada satu akun admin aktif di sistem.']);
        }

        $user->update($validated);
        $user->machines()->detach();
        $this->logActivity($request, 'Ubah pengguna', $user, "Pengguna {$user->username} diperbarui.");

        return redirect('/admin/users')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if ($user->role === 'akun_mesin') {
            return back()->withErrors(['user' => 'Akun mesin dihapus dari menu Master Mesin.']);
        }

        if ((int) session('user_id') === (int) $user->id) {
            return back()->withErrors(['user' => 'Akun yang sedang digunakan tidak bisa dihapus.']);
        }

        if ($user->role === 'admin' && ! User::where('role', 'admin')->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['user' => 'Minimal harus ada satu akun admin di sistem.']);
        }

        $username = $user->username;

        DB::transaction(function () use ($user) {
            $user->machines()->detach();
            $user->qcInspector?->delete();
            // Baris tetap disimpan (soft delete) untuk menjaga riwayat aktivitas,
            // tetapi username & email dilepas agar bisa dipakai lagi. Penanda id
            // menjamin nilai baru unik terhadap index unik di database.
            $user->forceFill([
                'username' => $user->username.'__dihapus'.$user->id,
                'email' => $user->email.'.dihapus'.$user->id,
            ])->save();
            $user->delete();
        });
        $this->logActivity($request, 'Hapus pengguna', $user, "Pengguna {$username} dihapus.");

        return redirect('/admin/users')->with('success', 'Pengguna berhasil dihapus.');
    }

    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if ($user->role === 'akun_mesin') {
            return back()->withErrors(['user' => 'Kata sandi akun mesin dikelola dari menu Master Mesin.']);
        }

        if ((int) session('user_id') === (int) $user->id) {
            return back()->withErrors(['user' => 'Gunakan menu Ganti kata sandi untuk mengubah kata sandi akun sendiri.']);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);
        $this->logActivity($request, 'Atur ulang kata sandi pengguna', $user, "Kata sandi pengguna {$user->username} diatur ulang oleh admin.");

        return redirect('/admin/users')->with('success', "Kata sandi {$user->username} berhasil diatur ulang.");
    }

    private function wouldRemoveLastActiveAdmin(User $user, array $validated): bool
    {
        if ($user->role !== 'admin' || $user->status !== 'aktif') {
            return false;
        }

        if (($validated['role'] ?? $user->role) === 'admin' && ($validated['status'] ?? $user->status) === 'aktif') {
            return false;
        }

        return ! User::where('role', 'admin')
            ->where('status', 'aktif')
            ->where('id', '!=', $user->id)
            ->exists();
    }

    private function matchingRoleCodes(string $search): array
    {
        $term = str($search)->lower()->squish()->toString();
        $labels = [
            'admin' => ['admin', 'administrator'],
            'supervisor' => ['supervisor', 'spv'],
            'qc_inspector' => ['quality control', 'qc'],
            'akun_mesin' => ['akun mesin', 'mesin', 'operator mesin'],
        ];

        return collect($labels)
            ->filter(fn (array $aliases) => collect($aliases)->contains(fn (string $alias) => str_contains($alias, $term)))
            ->keys()
            ->all();
    }
}
