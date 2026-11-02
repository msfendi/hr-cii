<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN (2026-11-03): default kolom employee_faces.can_login.
 *
 * Latar belakang:
 *   Kolom can_login ditambahkan langsung di database produksi (tidak
 *   lewat migration), sehingga tidak punya DEFAULT. Kode lama menulis
 *   can_login = 1 untuk SETIAP pendaftaran wajah, akibatnya semua
 *   karyawan otomatis berhak login lewat wajah.
 *
 * Aturan yang diminta:
 *   - default 0  -> karyawan biasa TIDAK otomatis boleh login
 *   - menjadi 1  -> HANYA bila npk terdaftar di tabel users
 *   (logika penentuan ada di FaceRecognitionController::resolveDefaultCanLogin)
 *
 * Migration ini:
 *   1. mengisi DEFAULT 0 pada kolom can_login (level database),
 *   2. menormalkan baris lama yang masih NULL menjadi 0.
 *
 * Catatan: baris lama yang TERLANJUR bernilai 1 tidak diubah otomatis
 * di sini, karena bisa jadi memang sengaja diberikan admin. Gunakan
 * perintah opsional di bawah bila ingin menyelaraskan seluruh data:
 *
 *   UPDATE employee_faces SET can_login = 1
 *   WHERE UPPER(LTRIM(RTRIM(npk))) IN (
 *       SELECT UPPER(LTRIM(RTRIM(npk))) FROM users WHERE npk IS NOT NULL
 *   );
 *
 *   UPDATE employee_faces SET can_login = 0
 *   WHERE UPPER(LTRIM(RTRIM(npk))) NOT IN (
 *       SELECT UPPER(LTRIM(RTRIM(npk))) FROM users WHERE npk IS NOT NULL
 *   );
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_faces')) {
            return;
        }
        if (!Schema::hasColumn('employee_faces', 'can_login')) {
            // Kolom belum ada sama sekali: buat dengan default 0.
            Schema::table('employee_faces', function (Blueprint $table) {
                $table->boolean('can_login')->default(false);
            });

            return;
        }

        // Kolom sudah ada: normalkan NULL -> 0 lebih dulu, baru pasang DEFAULT.
        try {
            DB::table('employee_faces')->whereNull('can_login')->update(['can_login' => 0]);
        } catch (\Throwable $e) {
            // diamkan: kegagalan normalisasi tidak boleh menggagalkan migrasi
        }

        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlsrv') {
                // SQL Server: perlu cek constraint default yang sudah ada
                DB::statement(
                    "IF EXISTS (SELECT 1 FROM sys.default_constraints dc "
                    . "JOIN sys.columns c ON c.default_object_id = dc.object_id "
                    . "WHERE dc.parent_object_id = OBJECT_ID('employee_faces') "
                    . "AND c.name = 'can_login') "
                    . "BEGIN "
                    . "  DECLARE @cn sysname; "
                    . "  SELECT @cn = dc.name FROM sys.default_constraints dc "
                    . "  JOIN sys.columns c ON c.default_object_id = dc.object_id "
                    . "  WHERE dc.parent_object_id = OBJECT_ID('employee_faces') "
                    . "  AND c.name = 'can_login'; "
                    . "  EXEC('ALTER TABLE employee_faces DROP CONSTRAINT ' + @cn); "
                    . "END"
                );
                DB::statement(
                    "ALTER TABLE employee_faces ADD CONSTRAINT DF_employee_faces_can_login "
                    . "DEFAULT 0 FOR can_login"
                );
            } else {
                // MySQL/MariaDB/PostgreSQL: ubah default lewat raw SQL.
                if ($driver === 'pgsql') {
                    DB::statement('ALTER TABLE employee_faces ALTER COLUMN can_login SET DEFAULT 0');
                } else {
                    DB::statement('ALTER TABLE employee_faces ALTER COLUMN can_login SET DEFAULT 0');
                }
            }
        } catch (\Throwable $e) {
            // Bila gagal memasang DEFAULT, aplikasi tetap aman karena
            // controller selalu mengisi can_login secara eksplisit.
            \Illuminate\Support\Facades\Log::warning(
                'Gagal memasang DEFAULT 0 pada employee_faces.can_login: ' . $e->getMessage()
            );
        }
    }

    public function down(): void
    {
        // Tidak menghapus DEFAULT: kolom tetap dipakai aplikasi.
    }
};
