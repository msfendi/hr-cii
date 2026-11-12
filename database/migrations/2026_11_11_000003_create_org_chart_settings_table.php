<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel org_chart_settings: setting MANUAL organization chart.
     * User mengisi: id_dept (DEPT.ID_DEPT) + level (company/division/
     * department/section/line) + parent (setting lain). Chart lalu
     * menampilkan karyawan BIODATA/PKWT aktif sesuai id_dept tiap node.
     *
     * Idempotent: aman dijalankan ulang (guard hasTable + index dicek
     * satu per satu lewat sys.indexes / information_schema / pg_indexes).
     */
    public function up(): void
    {
        if (! Schema::hasTable('org_chart_settings')) {
            Schema::create('org_chart_settings', function (Blueprint $table) {
                $table->id();
                $table->string('id_dept', 50)->nullable();  // DEPT.ID_DEPT (NULL untuk level Company)
                $table->string('level', 20);                   // company|division|department|section|line
                $table->unsignedBigInteger('parent_id')->nullable(); // org_chart_settings.id
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        foreach ([
            'id_dept' => 'org_chart_settings_id_dept_index',
            'parent_id' => 'org_chart_settings_parent_id_index',
        ] as $column => $indexName) {
            if (! Schema::hasColumn('org_chart_settings', $column)) {
                continue;
            }
            if (! $this->indexExists('org_chart_settings', $indexName)) {
                Schema::table('org_chart_settings', function (Blueprint $table) use ($column) {
                    $table->index($column);
                });
            }
        }

        if (Schema::hasColumn('org_chart_settings', 'id_dept') && Schema::hasColumn('org_chart_settings', 'level')) {
            // Unik per id_dept supaya satu dept tidak muncul dua kali di chart.
            if (! $this->indexExists('org_chart_settings', 'org_chart_settings_id_dept_unique')) {
                try {
                    Schema::table('org_chart_settings', function (Blueprint $table) {
                        $table->unique('id_dept');
                    });
                } catch (\Throwable $e) {
                    // Sudah ada data duplikat: unique index bisa gagal dibuat.
                    // Tidak fatal — setting tetap bisa dipakai.
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('org_chart_settings');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlsrv') {
            $row = DB::selectOne(
                "SELECT 1 AS x FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)",
                [$indexName, 'dbo.' . $table]
            );

            return $row !== null;
        }

        if ($driver === 'mysql') {
            $rows = DB::select(
                "SELECT 1 AS x FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
                [$table, $indexName]
            );

            return count($rows) > 0;
        }

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                "SELECT 1 AS x FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?",
                [$table, $indexName]
            );

            return $row !== null;
        }

        return true;
    }
};
