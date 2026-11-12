<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jadikan kolom id_dept BOLEH NULL pada org_chart_settings.
 *
 * Alasan: node level Company tidak terikat ke master DEPT, sehingga
 * id_dept memang harus NULL. Sebelumnya kolom NOT NULL -> error
 * "Cannot insert the value NULL into column 'id_dept'".
 *
 * Idempotent: aman dijalankan berulang (cek is_nullable dulu per driver).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('org_chart_settings') || ! Schema::hasColumn('org_chart_settings', 'id_dept')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            $row = DB::selectOne(
                "SELECT c.is_nullable AS v FROM sys.columns c
                 WHERE c.object_id = OBJECT_ID(?) AND c.name = 'id_dept'",
                ['dbo.org_chart_settings']
            );
            if ($row && (int) $row->v === 1) {
                return; // sudah nullable
            }
            DB::statement('ALTER TABLE [dbo].[org_chart_settings] ALTER COLUMN [id_dept] NVARCHAR(50) NULL');
            return;
        }

        if ($driver === 'mysql') {
            $row = DB::selectOne(
                "SELECT IS_NULLABLE AS v FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'org_chart_settings' AND column_name = 'id_dept'"
            );
            if ($row && strtoupper((string) $row->v) === 'YES') {
                return;
            }
            DB::statement('ALTER TABLE `org_chart_settings` MODIFY `id_dept` VARCHAR(50) NULL');
            return;
        }

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                "SELECT is_nullable AS v FROM information_schema.columns
                 WHERE table_schema = current_schema() AND table_name = 'org_chart_settings' AND column_name = 'id_dept'"
            );
            if ($row && strtoupper((string) $row->v) === 'YES') {
                return;
            }
            DB::statement('ALTER TABLE org_chart_settings ALTER COLUMN id_dept DROP NOT NULL');
            return;
        }

        // Driver lain (mis. sqlite untuk pengujian): pakai change() Laravel.
        try {
            Schema::table('org_chart_settings', function (Blueprint $table) {
                $table->string('id_dept', 50)->nullable()->change();
            });
        } catch (\Throwable $e) {
            // Kolom mungkin sudah nullable — tidak fatal.
            \Illuminate\Support\Facades\Log::warning('OrgChart migrasi nullable id_dept dilewati: ' . $e->getMessage());
        }

        // Verifikasi hasil change(); bila belum berubah (sqlite lama tanpa
        // dukungan native), rebuild tabel agar kolom benar-benar NULLABLE.
        try {
            $stillNotNull = false;
            if ($driver === 'sqlite') {
                $col = collect(DB::select("PRAGMA table_info(org_chart_settings)"))->firstWhere('name', 'id_dept');
                $stillNotNull = $col && (int) $col->notnull === 1;
            }
            if ($stillNotNull) {
                DB::statement('ALTER TABLE org_chart_settings RENAME TO org_chart_settings__old');
                DB::statement('CREATE TABLE org_chart_settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    id_dept TEXT NULL, level TEXT NOT NULL, parent_id INTEGER NULL,
                    sort_order INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1,
                    created_at TEXT NULL, updated_at TEXT NULL, label TEXT NULL)');
                DB::statement('INSERT INTO org_chart_settings (id, id_dept, level, parent_id, sort_order, is_active, created_at, updated_at, label)
                    SELECT id, id_dept, level, parent_id, sort_order, is_active, created_at, updated_at, label FROM org_chart_settings__old');
                DB::statement('DROP TABLE org_chart_settings__old');
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('OrgChart migrasi nullable id_dept (rebuild sqlite) gagal: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan ke NOT NULL: node Company memang memakai NULL.
    }
};
