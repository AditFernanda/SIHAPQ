<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Menyeragamkan nilai status di database dengan istilah baru:
    // AB (Abnormal) -> NG (No Good), PENDING -> WAITING.
    public function up(): void
    {
        DB::table('qc_inspections')
            ->where('result_status', 'AB')
            ->update(['result_status' => 'NG']);

        DB::table('qc_inspections')
            ->where('result_status', 'PENDING')
            ->update(['result_status' => 'WAITING']);
    }

    public function down(): void
    {
        DB::table('qc_inspections')
            ->where('result_status', 'NG')
            ->update(['result_status' => 'AB']);

        DB::table('qc_inspections')
            ->where('result_status', 'WAITING')
            ->update(['result_status' => 'PENDING']);
    }
};
