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
            $this->upSqlite();

            return;
        }

        Schema::table('qc_inspections', function (Blueprint $table) {
            $table->unsignedBigInteger('production_order_id')->nullable()->change();
            $table->unsignedBigInteger('machine_id')->nullable()->after('production_order_id');
            $table->unsignedBigInteger('product_id')->nullable()->after('machine_id');

            $table->foreign('machine_id')->references('id')->on('machines')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            UPDATE qc_inspections qi
            LEFT JOIN production_orders po ON po.id = qi.production_order_id
            SET qi.machine_id = po.machine_id,
                qi.product_id = po.product_id
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->downSqlite();

            return;
        }

        Schema::table('qc_inspections', function (Blueprint $table) {
            $table->dropForeign(['machine_id']);
            $table->dropForeign(['product_id']);
            $table->dropColumn(['machine_id', 'product_id']);
            $table->unsignedBigInteger('production_order_id')->nullable(false)->change();
        });
    }

    private function upSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('ALTER TABLE qc_inspections RENAME TO qc_inspections_old');
        DB::statement('DROP INDEX IF EXISTS qc_inspections_inspection_code_unique');
        DB::statement(<<<'SQL'
            CREATE TABLE qc_inspections (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                inspection_code VARCHAR NOT NULL,
                production_order_id INTEGER NULL,
                machine_id INTEGER NULL,
                product_id INTEGER NULL,
                qc_inspector_id INTEGER NOT NULL,
                quantity_inspected INTEGER NOT NULL DEFAULT 1,
                quantity_passed INTEGER NOT NULL DEFAULT 1,
                quantity_failed INTEGER NOT NULL DEFAULT 0,
                pass_percentage NUMERIC NOT NULL DEFAULT 100,
                status VARCHAR CHECK (status IN ('pass', 'fail')) NOT NULL DEFAULT 'pass',
                result_status VARCHAR NOT NULL DEFAULT 'RP',
                defects TEXT NULL,
                notes TEXT NULL,
                inspection_date DATETIME NOT NULL,
                start_time TIME NULL,
                end_time TIME NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                deleted_at DATETIME NULL,
                FOREIGN KEY(production_order_id) REFERENCES production_orders(id) ON DELETE SET NULL,
                FOREIGN KEY(machine_id) REFERENCES machines(id) ON DELETE SET NULL,
                FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
                FOREIGN KEY(qc_inspector_id) REFERENCES qc_inspectors(id) ON DELETE CASCADE
            )
        SQL);
        DB::statement(<<<'SQL'
            INSERT INTO qc_inspections (
                id, inspection_code, production_order_id, machine_id, product_id, qc_inspector_id,
                quantity_inspected, quantity_passed, quantity_failed, pass_percentage, status,
                result_status, defects, notes, inspection_date, start_time, end_time, created_at, updated_at, deleted_at
            )
            SELECT
                qi.id, qi.inspection_code, qi.production_order_id, po.machine_id, po.product_id, qi.qc_inspector_id,
                qi.quantity_inspected, qi.quantity_passed, qi.quantity_failed, qi.pass_percentage, qi.status,
                qi.result_status, qi.defects, qi.notes, qi.inspection_date, qi.start_time, qi.end_time,
                qi.created_at, qi.updated_at, qi.deleted_at
            FROM qc_inspections_old qi
            LEFT JOIN production_orders po ON po.id = qi.production_order_id
        SQL);
        DB::statement('CREATE UNIQUE INDEX qc_inspections_inspection_code_unique ON qc_inspections (inspection_code)');
        DB::statement('DROP TABLE qc_inspections_old');
        DB::statement('PRAGMA foreign_keys=ON');
    }

    private function downSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('ALTER TABLE qc_inspections RENAME TO qc_inspections_old');
        DB::statement(<<<'SQL'
            CREATE TABLE qc_inspections (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                inspection_code VARCHAR NOT NULL,
                production_order_id INTEGER NOT NULL,
                qc_inspector_id INTEGER NOT NULL,
                quantity_inspected INTEGER NOT NULL,
                quantity_passed INTEGER NOT NULL,
                quantity_failed INTEGER NOT NULL,
                pass_percentage NUMERIC NOT NULL,
                status VARCHAR CHECK (status IN ('pass', 'fail')) NOT NULL DEFAULT 'pass',
                defects TEXT NULL,
                notes TEXT NULL,
                inspection_date DATETIME NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                deleted_at DATETIME NULL,
                result_status VARCHAR NOT NULL DEFAULT 'RP',
                start_time TIME NULL,
                end_time TIME NULL,
                FOREIGN KEY(production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
                FOREIGN KEY(qc_inspector_id) REFERENCES qc_inspectors(id) ON DELETE CASCADE
            )
        SQL);
        DB::statement(<<<'SQL'
            INSERT INTO qc_inspections (
                id, inspection_code, production_order_id, qc_inspector_id, quantity_inspected,
                quantity_passed, quantity_failed, pass_percentage, status, defects, notes,
                inspection_date, created_at, updated_at, deleted_at, result_status, start_time, end_time
            )
            SELECT
                id, inspection_code, COALESCE(production_order_id, 1), qc_inspector_id, quantity_inspected,
                quantity_passed, quantity_failed, pass_percentage, status, defects, notes,
                inspection_date, created_at, updated_at, deleted_at, result_status, start_time, end_time
            FROM qc_inspections_old
        SQL);
        DB::statement('CREATE UNIQUE INDEX qc_inspections_inspection_code_unique ON qc_inspections (inspection_code)');
        DB::statement('DROP TABLE qc_inspections_old');
        DB::statement('PRAGMA foreign_keys=ON');
    }
};
