<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN V14 (2026-11-06) -- Kolom "Input NPK Dulu" pindah ke
 * face_recognition_settings
 * ============================================================
 * Menambah kolom di face_recognition_settings:
 *
 *   is_npk_required_enabled -> switch "input NPK dulu" sebelum kamera
 *                              dibuka, untuk halaman-halaman face login
 *                              (/face-attendance/login, /employee-payroll,
 *                              /pengajuan-cuti/login).
 *
 * Kenapa pindah dari face_attendance_settings?
 * ---------------------------------------------
 * Flag ini bukan pengaturan window presensi, tapi pengaturan
 * PENGENALAN Wajah / alur login. Mengelompokkannya bersama liveness
 * dan Recognition di face_recognition_settings supaya:
 *   1. Satu sumber kebenaran (tidak ada duplikasi antar tabel).
 *   2. Sakelar tidak tercampur dengan pengaturan clock in/out.
 *
 * Pola IDEMPOTEN (hasColumn dulu) supaya aman dijalankan berulang di
 * server yang mungkin sudah punya kolomnya, mengikuti pola
 * 2026_11_05_000001_add_liveness_settings_to_face_recognition.php
 * yang sudah terbukti jalan di produksi.
 *
 * Catatan SQL Server:
 *   - Tidak memakai after() (tidak didukung konsisten).
 *   - Tanpa foreign key, tanpa index baru.
 */
return new class extends Migration
{
    private const TABEL = 'face_recognition_settings';

    private string $kolom = 'is_npk_required_enabled';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABEL)) {
            return;
        }

        if (!Schema::hasColumn(self::TABEL, $this->kolom)) {
            Schema::table(self::TABEL, function (Blueprint $table) {
                // default true: alur aman (NPK wajib) bila admin belum
                // pernah menyentuh pengaturan ini.
                $table->boolean($this->kolom)->default(true);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABEL)) {
            return;
        }

        if (Schema::hasColumn(self::TABEL, $this->kolom)) {
            Schema::table(self::TABEL, function (Blueprint $table) {
                $table->dropColumn($this->kolom);
            });
        }
    }
};
