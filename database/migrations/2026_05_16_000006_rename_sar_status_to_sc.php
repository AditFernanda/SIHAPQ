<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('qc_inspections')
            ->where('result_status', 'SAR')
            ->update(['result_status' => 'SC']);
    }

    public function down(): void
    {
        DB::table('qc_inspections')
            ->where('result_status', 'SC')
            ->update(['result_status' => 'SAR']);
    }
};
