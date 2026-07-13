<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Sebelumnya file_pdf / file_excel / file_csv hanya menyimpan 1 nama file
 * (nvarchar). Sekarang 1 baris compensations (1 cutoff_date) bisa punya
 * beberapa file sekaligus -> 1 file per role_payroll (ALL, STAFF,
 * NON_STAFF, SEWING, NON_SEWING). Kolom diubah jadi nvarchar(max) supaya
 * cukup untuk menyimpan JSON, misal:
 * {"ALL":"REKAP_COMPENSATION_July_2026_ALL.pdf","STAFF":"REKAP_COMPENSATION_July_2026_STAFF.pdf"}
 */
return new class extends Migration
{
    public function up(): void
    {
        // Untuk SQL Server: ubah ke nvarchar(max). Kalau pakai MySQL/Postgres,
        // ganti bagian raw SQL ini dengan $table->text('file_pdf')->change();
        // (butuh doctrine/dbal untuk MySQL).
        DB::statement('ALTER TABLE compensations ALTER COLUMN file_pdf NVARCHAR(MAX) NULL');
        DB::statement('ALTER TABLE compensations ALTER COLUMN file_excel NVARCHAR(MAX) NULL');
        DB::statement('ALTER TABLE compensations ALTER COLUMN file_csv NVARCHAR(MAX) NULL');

        // Migrasikan data lama (1 file polos) menjadi format JSON {"ALL": "<nama file lama>"}
        // supaya baris lama tetap bisa dibaca oleh blade & kode baru.
        DB::table('compensations')
            ->whereNotNull('file_pdf')
            ->where('file_pdf', 'not like', '{%')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('compensations')
                        ->where('id', $row->id)
                        ->update([
                            'file_pdf' => json_encode(['ALL' => $row->file_pdf]),
                        ]);
                }
            });

        DB::table('compensations')
            ->whereNotNull('file_csv')
            ->where('file_csv', 'not like', '{%')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('compensations')
                        ->where('id', $row->id)
                        ->update([
                            'file_csv' => json_encode(['ALL' => $row->file_csv]),
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE compensations ALTER COLUMN file_pdf NVARCHAR(255) NULL');
        DB::statement('ALTER TABLE compensations ALTER COLUMN file_excel NVARCHAR(255) NULL');
        DB::statement('ALTER TABLE compensations ALTER COLUMN file_csv NVARCHAR(255) NULL');
    }
};
