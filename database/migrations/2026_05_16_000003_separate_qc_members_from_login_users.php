<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('qc_inspectors', function ($table) {
                $table->string('name')->nullable()->after('employee_id');
                $table->foreignId('user_id')->nullable()->change();
            });

            return;
        }

        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('ALTER TABLE qc_inspectors RENAME TO qc_inspectors_old');
        DB::statement('DROP INDEX IF EXISTS qc_inspectors_employee_id_unique');
        DB::statement(<<<'SQL'
            CREATE TABLE qc_inspectors (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER NULL,
                employee_id VARCHAR NOT NULL,
                name VARCHAR NULL,
                pin VARCHAR(6) NOT NULL,
                status VARCHAR CHECK (status IN ('aktif', 'nonaktif')) NOT NULL DEFAULT 'aktif',
                notes TEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                deleted_at DATETIME NULL,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        SQL);
        DB::statement(<<<'SQL'
            INSERT INTO qc_inspectors (id, user_id, employee_id, name, pin, status, notes, created_at, updated_at, deleted_at)
            SELECT qi.id, qi.user_id, qi.employee_id, users.name, qi.pin, qi.status, qi.notes, qi.created_at, qi.updated_at, qi.deleted_at
            FROM qc_inspectors_old qi
            LEFT JOIN users ON users.id = qi.user_id
        SQL);
        DB::statement('CREATE UNIQUE INDEX qc_inspectors_employee_id_unique ON qc_inspectors (employee_id)');
        DB::statement('DROP TABLE qc_inspectors_old');
        DB::statement('PRAGMA foreign_keys=ON');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('qc_inspectors', function ($table) {
                $table->dropColumn('name');
                $table->foreignId('user_id')->nullable(false)->change();
            });

            return;
        }

        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('ALTER TABLE qc_inspectors RENAME TO qc_inspectors_old');
        DB::statement(<<<'SQL'
            CREATE TABLE qc_inspectors (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER NOT NULL,
                employee_id VARCHAR NOT NULL,
                pin VARCHAR(6) NOT NULL,
                status VARCHAR CHECK (status IN ('aktif', 'nonaktif')) NOT NULL DEFAULT 'aktif',
                notes TEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                deleted_at DATETIME NULL,
                FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        SQL);
        DB::statement(<<<'SQL'
            INSERT INTO qc_inspectors (id, user_id, employee_id, pin, status, notes, created_at, updated_at, deleted_at)
            SELECT id, COALESCE(user_id, 1), employee_id, pin, status, notes, created_at, updated_at, deleted_at
            FROM qc_inspectors_old
        SQL);
        DB::statement('CREATE UNIQUE INDEX qc_inspectors_employee_id_unique ON qc_inspectors (employee_id)');
        DB::statement('DROP TABLE qc_inspectors_old');
        DB::statement('PRAGMA foreign_keys=ON');
    }
};
