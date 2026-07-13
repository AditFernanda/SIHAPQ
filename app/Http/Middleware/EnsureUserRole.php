<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware otorisasi: memastikan hanya peran tertentu yang boleh membuka route.
 *
 * Dipakai di routes/web.php, contoh: ->middleware('role:admin'). Argumen $roles
 * berisi daftar peran yang diizinkan untuk route tersebut. Pemeriksaan dilakukan
 * berlapis: (1) sesi valid, (2) user masih aktif & perannya cocok dengan sesi,
 * (3) peran termasuk yang diizinkan route.
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userId = $request->session()->get('user_id');
        $role = $request->session()->get('user_role');

        // Lapis 1: belum login (tidak ada data sesi) -> arahkan ke halaman login.
        if (! $userId || ! $role) {
            return redirect('/login');
        }

        // Lapis 2: cocokkan ulang ke database. Menangkap kasus akun dinonaktifkan
        // atau peran diubah admin saat sesi masih hidup -> paksa login ulang demi keamanan.
        $user = User::find($userId);
        if (! $user || $user->status !== 'aktif' || $user->role !== $role) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors(['username' => 'Sesi tidak berlaku. Silakan masuk kembali.']);
        }

        // Lapis 3: peran valid, tetapi tidak berhak atas route ini -> alihkan ke
        // dashboard sesuai perannya sendiri (bukan tampilkan error).
        if (! in_array($user->role, $roles, true)) {
            return redirect(match ($user->role) {
                'supervisor' => '/supervisor/dashboard',
                'qc_inspector' => '/qc/dashboard',
                'akun_mesin' => '/mesin/dashboard',
                default => '/admin/dashboard',
            });
        }

        return $next($request);
    }
}
