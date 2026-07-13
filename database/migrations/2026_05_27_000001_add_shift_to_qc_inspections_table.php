<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qc_inspections', function (Blueprint $table) {
            $table->string('shift', 20)->nullable()->after('start_time');
        });

        DB::table('qc_inspections')
            ->whereNotNull('start_time')
            ->update([
                'shift' => DB::raw("CASE WHEN substr(start_time, 1, 5) >= '07:00' AND substr(start_time, 1, 5) < '19:00' THEN 'Shift 1' ELSE 'Shift 2' END"),
            ]);
    }

    public function down(): void
    {
        Schema::table('qc_inspections', function (Blueprint $table) {
            $table->dropColumn('shift');
        });
    }
};
