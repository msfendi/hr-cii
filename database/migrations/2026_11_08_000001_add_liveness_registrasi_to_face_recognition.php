<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN V31.3d -- Liveness untuk menu REGISTRASI MANDIRI
 * ===========================================================
 * Menambah kolom di face_recognition_settings:
 *
 *   liveness_registrasi_enabled -> switch liveness untuk halaman
 *                                  pendaftaran wajah mandiri
 *                                  (/self-register-face).
 *
 * Kenapa perlu kolom sendiri, bukan menumpang "presensi"?
 * ------------------------------------------------------
 * Halaman registrasi mandiri dipakai calon karyawan / karyawan baru
 * yang SEDANG MENDaftARKAN wajahnya. Risikonya berbeda dengan presensi
 * harian:
 *   - Di presensi, liveness mencegah titip absen.
 *   - Di registrasi, liveness mencegah orang mendaftarkan wajah ORANG
 *     LAIN (memakai foto/video) sehingga nanti absennya diwakilkan.
 *
 * Karena itu admin harus bisa mematikan liveness registrasi tanpa
 * ikut mematikan liveness presensi. Kalau hanya memakai satu kolom,
 * mematikan salah satu akan ikut mematikan yang lain.
 *
 * Pola IDEMPOTEN (hasColumn dulu) supaya aman dijalankan berulang di
 * server yang mungkin sudah punya kolomnya, mengikuti pola
 * 2026_11_05_000001_add_liveness_settings_to_face_recognition.php
 * yang sudah terbukti jalan di produksi.
 *
 * Catatan SQL Server:
 *   - Tidak memakai after() (tidak didukung konsisten).
 *   - Tidak memakai STRING_SPLIT / whereIn besar.
 *   - Tanpa foreign key, tanpa index baru.
 */
return new class extends Migration
{
    private const TABEL = 'face_recognition_settings';

    private string $kolom = 'liveness_registrasi_enabled';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABEL)) {
            return;
        }

        if (!Schema::hasColumn(self::TABEL, $this->kolom)) {
            Schema::table(self::TABEL, function (Blueprint $table) {
                // default true: perilaku aman (liveness menyala) bila admin
                // belum pernah menyentuh pengaturan ini.
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
