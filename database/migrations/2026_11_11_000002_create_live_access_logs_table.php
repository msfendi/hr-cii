<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel live_access_logs: jejak akses HTTP real-time untuk halaman
     * Live Access (/live-access). Ditulis oleh LiveAccessMiddleware di
     * setiap request web (kecuali aset & endpoint pengecualian).
     *
     * Idempotent: aman dijalankan ulang — tabel & index yang sudah ada
     * tidak dibuat ulang (SQL Server menolak nama index duplikat).
     */
    public function up(): void
    {
        if (! Schema::hasTable('live_access_logs')) {
            Schema::create('live_access_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name')->nullable();
                $table->string('npk', 50)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('method', 10);
                $table->string('path', 1000);
                $table->string('route_name', 255)->nullable();
                $table->unsignedSmallInteger('status')->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->timestamp('accessed_at');
                $table->timestamps();
            });
        }

        // Index satu per satu, hanya jika belum ada (idempotent).
        // Catatan: jangan deklarasikan ->index() inline pada kolom DAN
        // $table->index() pada kolom yang sama — nama index-nya sama,
        // SQL Server akan menolak saat create.
        foreach ([
            'user_id' => 'live_access_logs_user_id_index',
            'npk' => 'live_access_logs_npk_index',
            'ip_address' => 'live_access_logs_ip_address_index',
            'method' => 'live_access_logs_method_index',
            'path' => 'live_access_logs_path_index',
            'route_name' => 'live_access_logs_route_name_index',
            'status' => 'live_access_logs_status_index',
            'accessed_at' => 'live_access_logs_accessed_at_index',
        ] as $column => $indexName) {
            if (! Schema::hasColumn('live_access_logs', $column)) {
                continue;
            }
            if (! $this->indexExists('live_access_logs', $indexName)) {
                Schema::table('live_access_logs', function (Blueprint $table) use ($column) {
                    $table->index($column);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('live_access_logs');
    }

    /**
     * Cek apakah nama index sudah ada pada tabel (per driver).
     */
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

        // Driver lain: anggap sudah ada agar aman (tidak membuat duplikat).
        return true;
    }
};
