<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lock mode CPO: kolom `ocf_no` berisi NOMOR OCF (daftar OCF milik CPO, dipisah
 * koma), sedangkan kode CPO disimpan di kolom baru `cpo_no`.
 *
 *  - ocf_no dilebarkan ke NVARCHAR(450) (muat daftar OCF; 450 x 2 byte = 900 byte,
 *    masih di bawah batas key index).
 *  - Unique index lama (ocf_no, lock_date, lock_type) diganti 2 filtered unique index:
 *      mode 'ocf' -> (ocf_no, lock_date, lock_type)   [nama index lama dipertahankan]
 *      mode 'cpo' -> (cpo_no, lock_date, lock_type)
 *  - Data lock CPO hasil versi sebelumnya (ocf_no berisi kode CPO) dipindah ke cpo_no.
 *    ocf_no-nya tidak bisa direkonstruksi otomatis -> hapus lock CPO uji coba lalu lock ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mon_rekon_locks', function (Blueprint $table) {
            $table->string('cpo_no', 150)->nullable();
        });

        // Backfill: lock CPO versi lama menyimpan kode CPO di ocf_no.
        DB::statement("UPDATE mon_rekon_locks SET cpo_no = ocf_no WHERE lock_mode = 'cpo' AND cpo_no IS NULL");

        // Drop semua index non-PK yang memuat kolom ocf_no (wajib sebelum ALTER COLUMN).
        DB::unprepared(<<<'SQL'
DECLARE @sql NVARCHAR(MAX) = N'';
SELECT @sql = @sql + N'DROP INDEX ' + QUOTENAME(i.name) + N' ON mon_rekon_locks;'
FROM sys.indexes i
JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
WHERE i.object_id = OBJECT_ID('mon_rekon_locks')
  AND i.is_primary_key = 0 AND i.is_unique_constraint = 0 AND i.type > 0
  AND c.name = 'ocf_no'
GROUP BY i.name;
EXEC sp_executesql @sql;
SQL);

        DB::statement('ALTER TABLE mon_rekon_locks ALTER COLUMN ocf_no NVARCHAR(450) NOT NULL');

        DB::statement("CREATE UNIQUE INDEX uq_mon_rekon_locks_ocf_date_type ON mon_rekon_locks (ocf_no, lock_date, lock_type) WHERE lock_mode = 'ocf'");
        DB::statement("CREATE UNIQUE INDEX uq_mon_rekon_locks_cpo_date_type ON mon_rekon_locks (cpo_no, lock_date, lock_type) WHERE lock_mode = 'cpo'");
        DB::statement('CREATE INDEX ix_mon_rekon_locks_ocf_no ON mon_rekon_locks (ocf_no)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_mon_rekon_locks_cpo_date_type ON mon_rekon_locks');
        DB::statement('DROP INDEX IF EXISTS uq_mon_rekon_locks_ocf_date_type ON mon_rekon_locks');
        DB::statement('DROP INDEX IF EXISTS ix_mon_rekon_locks_ocf_no ON mon_rekon_locks');
        DB::statement("DELETE FROM mon_rekon_locks WHERE lock_mode = 'cpo'");
        DB::statement('CREATE UNIQUE INDEX uq_mon_rekon_locks_ocf_date_type ON mon_rekon_locks (ocf_no, lock_date, lock_type)');

        Schema::table('mon_rekon_locks', function (Blueprint $table) {
            $table->dropColumn('cpo_no');
        });
    }
};
