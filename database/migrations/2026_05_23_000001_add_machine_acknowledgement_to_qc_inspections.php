<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qc_inspections', function (Blueprint $table) {
            $table->timestamp('machine_acknowledged_at')->nullable()->after('end_time');
            $table->foreignId('machine_acknowledged_by')->nullable()->after('machine_acknowledged_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('qc_inspections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('machine_acknowledged_by');
            $table->dropColumn('machine_acknowledged_at');
        });
    }
};
