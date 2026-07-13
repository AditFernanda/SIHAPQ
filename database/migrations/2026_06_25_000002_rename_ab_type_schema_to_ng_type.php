<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Menyeragamkan penamaan skema dari "ab_type" (AB) menjadi "ng_type" (NG):
    // tabel qc_ab_types -> qc_ng_types, pivot qc_inspection_ab_type -> qc_inspection_ng_type,
    // kolom qc_ab_type_id -> qc_ng_type_id, dan kolom qc_inspections.ab_type -> ng_type.
    public function up(): void
    {
        if (Schema::hasTable('qc_ab_types') && ! Schema::hasTable('qc_ng_types')) {
            Schema::rename('qc_ab_types', 'qc_ng_types');
        }

        if (Schema::hasTable('qc_inspection_ab_type') && ! Schema::hasTable('qc_inspection_ng_type')) {
            Schema::rename('qc_inspection_ab_type', 'qc_inspection_ng_type');
        }

        if (Schema::hasColumn('qc_inspection_ng_type', 'qc_ab_type_id')) {
            Schema::table('qc_inspection_ng_type', function (Blueprint $table) {
                $table->renameColumn('qc_ab_type_id', 'qc_ng_type_id');
            });
        }

        if (Schema::hasColumn('qc_inspections', 'ab_type')) {
            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->renameColumn('ab_type', 'ng_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('qc_inspections', 'ng_type')) {
            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->renameColumn('ng_type', 'ab_type');
            });
        }

        if (Schema::hasColumn('qc_inspection_ng_type', 'qc_ng_type_id')) {
            Schema::table('qc_inspection_ng_type', function (Blueprint $table) {
                $table->renameColumn('qc_ng_type_id', 'qc_ab_type_id');
            });
        }

        if (Schema::hasTable('qc_inspection_ng_type') && ! Schema::hasTable('qc_inspection_ab_type')) {
            Schema::rename('qc_inspection_ng_type', 'qc_inspection_ab_type');
        }

        if (Schema::hasTable('qc_ng_types') && ! Schema::hasTable('qc_ab_types')) {
            Schema::rename('qc_ng_types', 'qc_ab_types');
        }
    }
};
