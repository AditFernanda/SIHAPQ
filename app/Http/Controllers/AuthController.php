<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Menangani autentikasi: menampilkan form login, memproses login
 * (dengan throttle anti brute-force), dan logout.
 */
class AuthController extends Controller
{
    // Maksimal percobaan gagal sebelum login dikunci sementara.
    private const MAX_ATTEMPTS = 5;

    // Lama penguncian (detik) setelah batas percobaan terlampaui.
    private const DECAY_SECONDS = 60;

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $throttleKey = $this->throttleKey($request, $credentials['username']);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withErrors(['username' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik."])
                ->withInput($request->only('username'));
        }

        $user = User::where('username', $credentials['username'])
            ->where('status', 'aktif')
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            return back()
                ->withErrors(['username' => 'Nama pengguna atau kata sandi tidak sesuai.'])
                ->withInput($request->only('username'));
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        session([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
        ]);

        return redirect($this->homeForRole($user->role));
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function throttleKey(Request $request, string $username): string
    {
        return Str::transliterate(Str::lower($username).'|'.$request->ip());
    }

    private function homeForRole(string $role): string
    {
        return match ($role) {
            'supervisor' => '/supervisor/dashboard',
            'qc_inspector' => '/qc/dashboard',
            'akun_mesin' => '/mesin/dashboard',
            default => '/admin/dashboard',
        };
    }
}
