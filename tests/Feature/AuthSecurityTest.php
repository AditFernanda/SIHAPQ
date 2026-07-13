<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_too_many_failed_attempts(): void
    {
        User::create([
            'username' => 'throttle_user',
            'name' => 'Throttle User',
            'email' => 'throttle-user@pt-rmi.local',
            'password' => Hash::make('rahasia-kuat1'),
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'throttle_user',
                'password' => 'salah-password',
            ])->assertSessionHasErrors('username');
        }

        $this->post('/login', [
            'username' => 'throttle_user',
            'password' => 'salah-password',
        ])->assertSessionHasErrors('username');

        $this->assertStringContainsString(
            'Terlalu banyak percobaan masuk',
            session('errors')->first('username')
        );
    }

    public function test_successful_login_clears_the_throttle_counter(): void
    {
        User::create([
            'username' => 'clear_user',
            'name' => 'Clear User',
            'email' => 'clear-user@pt-rmi.local',
            'password' => Hash::make('rahasia-kuat1'),
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', [
                'username' => 'clear_user',
                'password' => 'salah-password',
            ])->assertSessionHasErrors('username');
        }

        $this->post('/login', [
            'username' => 'clear_user',
            'password' => 'rahasia-kuat1',
        ])->assertRedirect('/admin/dashboard')->assertSessionHasNoErrors();
    }

    public function test_change_password_rejects_weak_password(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->withSession([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => 'admin',
        ]);

        // Terlalu pendek dan tanpa angka -> ditolak.
        $this->from('/password')->put('/password', [
            'current_password' => config('qc.admin_default_password'),
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors('password');

        // Tanpa angka -> tetap ditolak meski cukup panjang.
        $this->from('/password')->put('/password', [
            'current_password' => config('qc.admin_default_password'),
            'password' => 'tanpaangka',
            'password_confirmation' => 'tanpaangka',
        ])->assertSessionHasErrors('password');
    }

    public function test_change_password_rejects_reusing_current_password(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->withSession([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => 'admin',
        ]);

        $current = config('qc.admin_default_password');

        $this->from('/password')->put('/password', [
            'current_password' => $current,
            'password' => $current,
            'password_confirmation' => $current,
        ])->assertSessionHasErrors('password');
    }
}
