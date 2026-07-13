<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qc_inspectors', function ($table) {
            $table->dropUnique('qc_inspectors_pin_unique');
        });
    }

    public function down(): void
    {
        Schema::table('qc_inspectors', function ($table) {
            $table->unique('pin');
        });
    }
};
