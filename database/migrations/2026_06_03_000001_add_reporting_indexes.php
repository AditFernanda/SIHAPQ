<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Index gabungan ini mengikuti filter laporan dan dashboard harian.
        $this->addIndexIfMissing('qc_inspections', 'qc_inspections_date_start_idx', function () {
            Schema::table('qc_inspections', fn (Blueprint $table) => $table->index(['inspection_date', 'start_time'], 'qc_inspections_date_start_idx'));
        });
        $this->addIndexIfMissing('qc_inspections', 'qc_inspections_status_date_idx', function () {
            Schema::table('qc_inspections', fn (Blueprint $table) => $table->index(['result_status', 'inspection_date'], 'qc_inspections_status_date_idx'));
        });
        $this->addIndexIfMissing('qc_inspections', 'qc_inspections_machine_date_idx', function () {
            Schema::table('qc_inspections', fn (Blueprint $table) => $table->index(['machine_id', 'inspection_date'], 'qc_inspections_machine_date_idx'));
        });
        $this->addIndexIfMissing('qc_inspections', 'qc_inspections_product_date_idx', function () {
            Schema::table('qc_inspections', fn (Blueprint $table) => $table->index(['product_id', 'inspection_date'], 'qc_inspections_product_date_idx'));
        });
        $this->addIndexIfMissing('qc_inspections', 'qc_inspections_inspector_date_idx', function () {
            Schema::table('qc_inspections', fn (Blueprint $table) => $table->index(['qc_inspector_id', 'inspection_date'], 'qc_inspections_inspector_date_idx'));
        });

        if ($this->usesMySql()) {
            $this->addMysqlPrefixIndex('products', 'products_status_name_idx', '`status`, `name`(100)');
            $this->addMysqlPrefixIndex('products', 'products_customer_name_idx', '`customer_name`(100), `name`(100)');
            $this->addMysqlPrefixIndex('products', 'products_code_process_idx', '`product_code`(100), `process_name`(100)');
            $this->addMysqlPrefixIndex('machines', 'machines_status_code_idx', '`status`, `machine_code`(100)');
        } else {
            $this->addIndexIfMissing('products', 'products_status_name_idx', function () {
                Schema::table('products', fn (Blueprint $table) => $table->index(['status', 'name'], 'products_status_name_idx'));
            });
            $this->addIndexIfMissing('products', 'products_customer_name_idx', function () {
                Schema::table('products', fn (Blueprint $table) => $table->index(['customer_name', 'name'], 'products_customer_name_idx'));
            });
            $this->addIndexIfMissing('products', 'products_code_process_idx', function () {
                Schema::table('products', fn (Blueprint $table) => $table->index(['product_code', 'process_name'], 'products_code_process_idx'));
            });
            $this->addIndexIfMissing('machines', 'machines_status_code_idx', function () {
                Schema::table('machines', fn (Blueprint $table) => $table->index(['status', 'machine_code'], 'machines_status_code_idx'));
            });
        }

        $this->addIndexIfMissing('machines', 'machines_department_status_idx', function () {
            Schema::table('machines', fn (Blueprint $table) => $table->index(['department_id', 'status'], 'machines_department_status_idx'));
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('machines', 'machines_status_code_idx');
        $this->dropIndexIfExists('machines', 'machines_department_status_idx');
        $this->dropIndexIfExists('products', 'products_status_name_idx');
        $this->dropIndexIfExists('products', 'products_customer_name_idx');
        $this->dropIndexIfExists('products', 'products_code_process_idx');
        $this->dropIndexIfExists('qc_inspections', 'qc_inspections_date_start_idx');
        $this->dropIndexIfExists('qc_inspections', 'qc_inspections_status_date_idx');
        $this->dropIndexIfExists('qc_inspections', 'qc_inspections_machine_date_idx');
        $this->dropIndexIfExists('qc_inspections', 'qc_inspections_product_date_idx');
        $this->dropIndexIfExists('qc_inspections', 'qc_inspections_inspector_date_idx');
    }

    private function addMysqlPrefixIndex(string $table, string $index, string $columns): void
    {
        $this->addIndexIfMissing($table, $index, function () use ($table, $index, $columns) {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columns})");
        });
    }

    private function addIndexIfMissing(string $table, string $index, Closure $callback): void
    {
        if (! Schema::hasIndex($table, $index)) {
            $callback();
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (Schema::hasIndex($table, $index)) {
            Schema::table($table, fn (Blueprint $schema) => $schema->dropIndex($index));
        }
    }

    private function usesMySql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
