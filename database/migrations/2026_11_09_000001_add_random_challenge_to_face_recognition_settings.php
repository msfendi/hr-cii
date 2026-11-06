<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RANDOM CHALLENGE (ANTI-KECURANGAN FACE ATTENDANCE)
 * =====================================================
 *
 * Menambah pengaturan random challenge pada tabel face_recognition_settings.
 *
 * Random challenge adalah tantangan RANDOM yang diminta ke pengguna sebelum
 * wajah boleh dicocokkan. Contoh tantangan:
 *   - "Hadap ke kiri"   -> user harus memutar kepala ke kiri
 *   - "Hadap ke kanan"  -> user harus memutar kepala ke kanan
 *   - "Buka mulut"     -> user harus membuka mulut
 *
 * Tujuannya mencegah titip absen / penggunaan foto/video: karena tantangan
 * dipilih ACak dan tidak diketahui orang lain, foto statis atau video yang
 * sudah direkam sebelumnya tidak bisa memenuhi semuanya.
 *
 * STRUKTUR (mengikuti pola liveness yang sudah ada):
 *   is_random_challenge_enabled            -> master switch
 *   random_challenge_presensi_enabled      -> /face-attendance
 *   random_challenge_login_enabled         -> /face-attendance/login
 *   random_challenge_slip_enabled          -> /employee-payroll
 *   random_challenge_cuti_enabled          -> /pengajuan-cuti
 *   random_challenge_registrasi_enabled    -> /self-register-face (Langkah 4)
 *
 * PENTING: fitur ini ORTogonal terhadap liveness check. Mengaktifkan random
 * challenge TIDAK mengubah logika liveness sama sekali, dan mematikan liveness
 * TIDAK mematikan random challenge. Keduanya bisa aktif bersamaan.
 *
 * Idempotent: cek hasColumn dulu supaya aman dijalankan berulang.
 */
return new class extends Migration
{
    /**
     * Daftar kolom yang ditambahkan.
     * key = nama kolom, value = default.
     */
    private array $columns = [
        'is_random_challenge_enabled'         => false,
        'random_challenge_presensi_enabled'   => true,
        'random_challenge_login_enabled'      => true,
        'random_challenge_slip_enabled'       => true,
        'random_challenge_cuti_enabled'       => true,
        'random_challenge_registrasi_enabled' => true,
    ];

    public function up(): void
    {
        if (!Schema::hasTable('face_recognition_settings')) {
            return;
        }

        foreach ($this->columns as $column => $default) {
            if (!Schema::hasColumn('face_recognition_settings', $column)) {
                Schema::table('face_recognition_settings', function (Blueprint $table) use ($column, $default) {
                    $table->boolean($column)->default($default);
                });
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('face_recognition_settings')) {
            return;
        }

        foreach (array_keys($this->columns) as $column) {
            if (Schema::hasColumn('face_recognition_settings', $column)) {
                Schema::table('face_recognition_settings', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
