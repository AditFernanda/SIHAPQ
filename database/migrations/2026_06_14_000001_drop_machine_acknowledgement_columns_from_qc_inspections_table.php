<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('qc_inspections', 'machine_acknowledged_by')) {
            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->dropForeign(['machine_acknowledged_by']);
            });

            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->dropColumn('machine_acknowledged_by');
            });
        }

        if (Schema::hasColumn('qc_inspections', 'machine_acknowledged_at')) {
            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->dropColumn('machine_acknowledged_at');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasColumn('qc_inspections', 'machine_acknowledged_at')) {
            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->timestamp('machine_acknowledged_at')->nullable()->after('end_time');
            });
        }

        if (! Schema::hasColumn('qc_inspections', 'machine_acknowledged_by')) {
            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->foreignId('machine_acknowledged_by')
                    ->nullable()
                    ->after('machine_acknowledged_at')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }
};
