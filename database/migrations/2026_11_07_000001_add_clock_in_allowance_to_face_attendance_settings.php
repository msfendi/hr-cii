<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN V31.2 (2026-11-03)
 *
 * Menambah dua kolom pada face_attendance_settings:
 *
 *  1. `clock_in_allowance_minutes`  -> kelonggaran BATAS AKHIR window Clock In
 *  2. `clock_out_allowance_minutes` -> kelonggaran BATAS AKHIR window Clock Out
 *
 * Contoh (shift masuk 08:00, pulang 17:00, before 15, after 15):
 *
 *   Tanpa allowance : In  07:45 - 08:00    Out 17:00 - 17:15
 *   In 5, Out 10    : In  07:45 - 08:05    Out 17:00 - 17:25
 *
 * Default 0 supaya perilaku lama TIDAK berubah sampai admin mengisinya.
 *
 * Catatan SQL Server: `after()` tidak didukung, jadi kolom hanya memakai
 * after() pada driver yang mendukungnya. Urutan kolom fisik tidak penting
 * karena semua akses memakai nama kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('face_attendance_settings')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $dukungAfter = in_array($driver, ['mysql', 'mariadb'], true);

        if (!Schema::hasColumn('face_attendance_settings', 'clock_in_allowance_minutes')) {
            Schema::table('face_attendance_settings', function (Blueprint $table) use ($dukungAfter) {
                // 0 = tidak ada kelonggaran (perilaku lama)
                $kolom = $table->integer('clock_in_allowance_minutes')
                    ->default(0)
                    ->nullable(false);
                if ($dukungAfter) {
                    $kolom->after('clock_in_before_minutes');
                }
            });
        }

        if (!Schema::hasColumn('face_attendance_settings', 'clock_out_allowance_minutes')) {
            Schema::table('face_attendance_settings', function (Blueprint $table) use ($dukungAfter) {
                $kolom = $table->integer('clock_out_allowance_minutes')
                    ->default(0)
                    ->nullable(false);
                if ($dukungAfter) {
                    $kolom->after('clock_out_after_minutes');
                }
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('face_attendance_settings')) {
            return;
        }

        foreach (['clock_in_allowance_minutes', 'clock_out_allowance_minutes'] as $kolom) {
            if (Schema::hasColumn('face_attendance_settings', $kolom)) {
                Schema::table('face_attendance_settings', function (Blueprint $table) use ($kolom) {
                    $table->dropColumn($kolom);
                });
            }
        }
    }
};
