<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN V33 (2026-11-05)
 *
 * Menambah kolom `is_npk_required_enabled` pada `face_attendance_settings`.
 *
 * TUJUAN:
 *   Pada route /face-attendance/login, user WAJIB memasukkan NPK terlebih
 *   dahulu sebelum kamera dibuka. Descriptor wajah lalu dicocokkan HANYA
 *   terhadap data wajah karyawan dengan NPK tersebut, sehingga tidak ada
 *   kemungkinan wajah orang lain ikut terbaca.
 *
 * DEFAULT: TRUE (aktif).
 *   Alasan: dari observations user, "jika tanpa npk maka ada kemungkinan
 *   salah mengambil wajah orang lain". Jadi mode yang aman adalah default.
 *   Admin bisa mematikan lewat halaman Pengaturan Presensi Wajah.
 *
 * Catatan SQL Server: `after()` tidak didukung, jadi hanya dipakai pada
 * driver mysql/mariadb. Urutan kolom fisik tidak penting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('face_attendance_settings')) {
            return;
        }

        if (Schema::hasColumn('face_attendance_settings', 'is_npk_required_enabled')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $dukungAfter = in_array($driver, ['mysql', 'mariadb'], true);

        Schema::table('face_attendance_settings', function (Blueprint $table) use ($dukungAfter) {
            $kolom = $table->boolean('is_npk_required_enabled')
                ->default(true)
                ->nullable(false);
            if ($dukungAfter) {
                $kolom->after('is_holiday_enabled');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('face_attendance_settings')) {
            return;
        }

        if (Schema::hasColumn('face_attendance_settings', 'is_npk_required_enabled')) {
            Schema::table('face_attendance_settings', function (Blueprint $table) {
                $table->dropColumn('is_npk_required_enabled');
            });
        }
    }
};
