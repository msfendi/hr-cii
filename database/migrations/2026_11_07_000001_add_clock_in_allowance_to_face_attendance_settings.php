<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN V31.2 (2026-11-03)
 *
 * Menambah kolom `clock_in_allowance_minutes` pada face_attendance_settings.
 *
 * Gunanya: memberi kelonggaran (allowance) pada BATAS AKHIR window Clock In.
 *
 *   Tanpa allowance : Clock In 07:45 - 08:00 (untuk shift 08:00, before 15)
 *   Allowance 5 mnt : Clock In 07:45 - 08:05
 *
 * Default 0 supaya perilaku lama TIDAK berubah sampai admin mengisinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('face_attendance_settings')) {
            return;
        }

        if (!Schema::hasColumn('face_attendance_settings', 'clock_in_allowance_minutes')) {
            Schema::table('face_attendance_settings', function (Blueprint $table) {
                // 0 = tidak ada kelonggaran (perilaku lama)
                $table->integer('clock_in_allowance_minutes')
                    ->default(0)
                    ->nullable(false)
                    ->after('clock_in_before_minutes');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('face_attendance_settings')) {
            return;
        }

        if (Schema::hasColumn('face_attendance_settings', 'clock_in_allowance_minutes')) {
            Schema::table('face_attendance_settings', function (Blueprint $table) {
                $table->dropColumn('clock_in_allowance_minutes');
            });
        }
    }
};
