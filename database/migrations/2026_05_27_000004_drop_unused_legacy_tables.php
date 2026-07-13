<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('inspection_details');
        Schema::dropIfExists('qc_reports');
        Schema::dropIfExists('machine_maintenance');
        Schema::dropIfExists('qc_defects');
    }

    public function down(): void
    {
        // Legacy tables are intentionally not restored. They are no longer used
        // by the QC web workflow and keeping them out makes fresh installs cleaner.
    }
};
