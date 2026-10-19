<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lock Data kini punya 2 mode: 'ocf' (per OCF) dan 'cpo' (per CPO / uraian).
 *
 * Kolom `ocf_no` tetap dipakai sebagai KUNCI lock (nilai OCF untuk mode 'ocf',
 * kode CPO untuk mode 'cpo'), jadi unique index lama
 * (ocf_no, lock_date, lock_type) tetap berlaku tanpa diubah. Untuk mode 'cpo'
 * kolom `uraian` juga diisi kode CPO yang sama.
 * Data lama otomatis menjadi mode 'ocf' lewat default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mon_rekon_locks', function (Blueprint $table) {
            $table->string('lock_mode', 10)->default('ocf');
            $table->index('lock_mode', 'ix_mon_rekon_locks_lock_mode');
        });
    }

    public function down(): void
    {
        Schema::table('mon_rekon_locks', function (Blueprint $table) {
            $table->dropIndex('ix_mon_rekon_locks_lock_mode');
        });

        Schema::table('mon_rekon_locks', function (Blueprint $table) {
            // SQL Server: default constraint harus di-drop dulu sebelum kolom.
            \Illuminate\Support\Facades\DB::statement(<<<'SQL'
DECLARE @c NVARCHAR(200);
SELECT @c = dc.name FROM sys.default_constraints dc
JOIN sys.columns col ON col.default_object_id = dc.object_id
WHERE dc.parent_object_id = OBJECT_ID('mon_rekon_locks') AND col.name = 'lock_mode';
IF @c IS NOT NULL EXEC('ALTER TABLE mon_rekon_locks DROP CONSTRAINT ' + @c);
SQL);
            $table->dropColumn('lock_mode');
        });
    }
};
