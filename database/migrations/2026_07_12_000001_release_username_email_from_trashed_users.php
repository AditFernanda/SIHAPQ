<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membebaskan username & email pada pengguna yang sudah dihapus (soft delete)
 * sebelum perilaku "lepas username saat hapus" diterapkan. Tanpa ini, index
 * unik di database masih menahan nilai lama sehingga nama tak bisa dipakai lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $trashed = DB::table('users')
            ->whereNotNull('deleted_at')
            ->where('username', 'not like', '%\_\_dihapus%')
            ->get(['id', 'username', 'email']);

        foreach ($trashed as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'username' => $user->username.'__dihapus'.$user->id,
                'email' => $user->email.'.dihapus'.$user->id,
            ]);
        }
    }

    public function down(): void
    {
        // Tidak dapat dibalik dengan aman: nilai asli sudah dilepas.
    }
};
