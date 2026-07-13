<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qc_inspections', function (Blueprint $table) {
            $table->dropColumn('shift');
        });
    }

    public function down(): void
    {
        Schema::table('qc_inspections', function (Blueprint $table) {
            $table->string('shift', 20)->nullable()->after('start_time');
        });
    }
};
