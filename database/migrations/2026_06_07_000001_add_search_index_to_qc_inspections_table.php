<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Kolom gabungan untuk pencarian: satu MATCH AGAINST (FULLTEXT) menggantikan
    // LIKE '%kata%' lintas tabel yang lambat saat data besar.
    public function up(): void
    {
        if (! Schema::hasColumn('qc_inspections', 'search_index')) {
            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->text('search_index')->nullable()->after('notes');
            });
        }

        // Backfill data lama. Tabel kosong (mis. saat test sqlite) cukup dilewati.
        if ($this->usesMySql()) {
            DB::statement(<<<'SQL'
                UPDATE qc_inspections qi
                LEFT JOIN products p ON p.id = qi.product_id
                LEFT JOIN machines m ON m.id = qi.machine_id
                LEFT JOIN qc_inspectors i ON i.id = qi.qc_inspector_id
                SET qi.search_index = TRIM(CONCAT_WS(' ',
                    qi.inspection_code, qi.notes,
                    p.name, p.product_code, p.process_name, p.customer_name,
                    m.name, m.machine_code,
                    i.name
                ))
            SQL);

            if (! Schema::hasIndex('qc_inspections', 'qc_inspections_search_fulltext')) {
                DB::statement('ALTER TABLE qc_inspections ADD FULLTEXT qc_inspections_search_fulltext (search_index)');
            }
        }
    }

    public function down(): void
    {
        if ($this->usesMySql() && Schema::hasIndex('qc_inspections', 'qc_inspections_search_fulltext')) {
            DB::statement('ALTER TABLE qc_inspections DROP INDEX qc_inspections_search_fulltext');
        }

        if (Schema::hasColumn('qc_inspections', 'search_index')) {
            Schema::table('qc_inspections', function (Blueprint $table) {
                $table->dropColumn('search_index');
            });
        }
    }

    private function usesMySql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
