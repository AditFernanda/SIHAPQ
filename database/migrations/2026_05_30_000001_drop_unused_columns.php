<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus kolom yang ada di skema tapi tidak dipakai code:
 * - qc_inspections.defects   : tidak ada di $fillable, tidak pernah dibaca/ditulis
 * - users.phone              : tidak pernah diisi, tidak ada di $fillable
 * - users.remember_token     : sisa default Laravel auth (kita pakai custom session)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('qc_inspections', 'defects')) {
            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->dropColumn('defects');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('users', 'phone')) {
                $columnsToDrop[] = 'phone';
            }
            if (Schema::hasColumn('users', 'remember_token')) {
                $columnsToDrop[] = 'remember_token';
            }
            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    public function down(): void
    {
        // Restore agar bisa rollback. Tipe disamakan dengan migration awal.
        Schema::table('qc_inspections', function (Blueprint $table) {
            $table->json('defects')->nullable()->after('result_status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->rememberToken();
        });
    }
};
