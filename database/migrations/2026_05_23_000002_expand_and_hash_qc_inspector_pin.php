<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qc_inspectors', function (Blueprint $table) {
            $table->string('pin', 255)->change();
        });

        DB::table('qc_inspectors')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'pin'])
            ->each(function ($inspector) {
                if ($inspector->pin && Hash::needsRehash($inspector->pin)) {
                    DB::table('qc_inspectors')
                        ->where('id', $inspector->id)
                        ->update(['pin' => Hash::make($inspector->pin)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('qc_inspectors', function (Blueprint $table) {
            $table->string('pin', 6)->change();
        });
    }
};
