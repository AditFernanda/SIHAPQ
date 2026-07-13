<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('qc_inspections', 'ab_type')) {
            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->string('ab_type', 80)->nullable()->after('result_status');
                $table->index(['result_status', 'ab_type'], 'qc_inspections_status_ab_type_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('qc_inspections', 'ab_type')) {
            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->dropIndex('qc_inspections_status_ab_type_idx');
                $table->dropColumn('ab_type');
            });
        }
    }
};
