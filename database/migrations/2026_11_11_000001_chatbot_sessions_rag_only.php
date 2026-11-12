<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chatbot kini HANYA mode RAG. Migrasi ini mengubah default kolom
     * chatbot_sessions.mode dari 'global' menjadi 'rag' dan mengubah
     * semua sesi lama yang masih 'global' menjadi 'rag', supaya tidak
     * ada sesi yang masih berjalan tanpa akses database.
     *
     * Idempotent: aman dijalankan ulang. Pada SQL Server, kolom tidak
     * boleh punya lebih dari satu DEFAULT constraint — constraint lama
     * (nama auto dari migrasi lama) di-drop dulu sebelum yang baru
     * dipasang.
     */
    public function up(): void
    {
        if (! Schema::hasTable('chatbot_sessions') || ! Schema::hasColumn('chatbot_sessions', 'mode')) {
            return;
        }

        DB::table('chatbot_sessions')->where('mode', 'global')->update(['mode' => 'rag']);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE chatbot_sessions MODIFY mode VARCHAR(255) NOT NULL DEFAULT 'rag'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE chatbot_sessions ALTER COLUMN mode SET DEFAULT 'rag'");
        } elseif ($driver === 'sqlsrv') {
            // 1) Drop SEMUA default constraint lama pada kolom mode
            //    (kecuali yang memang bernama DF_chatbot_sessions_mode).
            $oldConstraints = DB::select(
                "SELECT dc.name
                 FROM sys.default_constraints dc
                 JOIN sys.columns c
                   ON c.object_id = dc.parent_object_id
                  AND c.column_id = dc.parent_column_id
                 WHERE dc.parent_object_id = OBJECT_ID('dbo.chatbot_sessions')
                   AND c.name = 'mode'
                   AND dc.name <> 'DF_chatbot_sessions_mode'"
            );
            foreach ($oldConstraints as $c) {
                DB::statement('ALTER TABLE dbo.chatbot_sessions DROP CONSTRAINT [' . $c->name . ']');
            }

            // 2) Pasang DF_chatbot_sessions_mode hanya jika belum ada.
            $exists = DB::selectOne(
                "SELECT 1 AS x FROM sys.default_constraints
                 WHERE name = 'DF_chatbot_sessions_mode'
                   AND parent_object_id = OBJECT_ID('dbo.chatbot_sessions')"
            );
            if (! $exists) {
                DB::statement("ALTER TABLE dbo.chatbot_sessions ADD CONSTRAINT DF_chatbot_sessions_mode DEFAULT 'rag' FOR mode");
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('chatbot_sessions') || ! Schema::hasColumn('chatbot_sessions', 'mode')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE chatbot_sessions MODIFY mode VARCHAR(255) NOT NULL DEFAULT 'global'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE chatbot_sessions ALTER COLUMN mode SET DEFAULT 'global'");
        } elseif ($driver === 'sqlsrv') {
            $exists = DB::selectOne(
                "SELECT 1 AS x FROM sys.default_constraints
                 WHERE name = 'DF_chatbot_sessions_mode'
                   AND parent_object_id = OBJECT_ID('dbo.chatbot_sessions')"
            );
            if ($exists) {
                DB::statement('ALTER TABLE dbo.chatbot_sessions DROP CONSTRAINT DF_chatbot_sessions_mode');
            }
            DB::statement("ALTER TABLE dbo.chatbot_sessions ADD CONSTRAINT DF_chatbot_sessions_mode_legacy DEFAULT 'global' FOR mode");
        }
    }
};
