<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Mengelola ubah kata sandi akun sendiri (admin, supervisor, QC).
 */
class PasswordController extends Controller
{
    public function edit()
    {
        $user = User::findOrFail(session('user_id'));

        return view('auth.change-password', compact('user'));
    }

    public function update(Request $request)
    {
        $user = User::findOrFail(session('user_id'));

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ], [
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi lama.',
        ]);
        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Kata sandi lama tidak sesuai.']);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }
}
