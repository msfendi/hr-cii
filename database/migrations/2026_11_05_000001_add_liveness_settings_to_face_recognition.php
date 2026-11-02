<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN 25 -- Toggle Liveness Check (Global + Per Menu)
 * ========================================================
 * Menambah pengaturan liveness check pada tabel face_recognition_settings:
 *
 *   is_liveness_enabled      -> master switch. Jika OFF, SELURUH halaman
 *                               face recognition tidak meminta kedip/geleng.
 *   liveness_*_enabled       -> switch per menu, hanya berlaku bila master ON.
 *
 * Kenapa per menu? Karena tiap halaman punya risiko berbeda:
 *   - presensi  : butuh anti-titip absen (paling ketat)
 *   - login     : butuh anti-titip login
 *   - slip      : dokumen gaji, sebaiknya tetap ketat
 *   - cuti      : pengajuan, bisa lebih longgar
 *
 * Pola migrasi sengaja dibuat IDEMPOTEN (cek hasColumn dulu) supaya aman
 * dijalankan berulang di server yang mungkin sudah punya sebagian kolom.
 * Ini mengikuti pola 2026_10_30_000001_add_qr_login_settings_per_menu.php
 * yang sudah terbukti jalan di produksi.
 */
return new class extends Migration
{
    /**
     * Daftar kolom yang ditambahkan.
     * key = nama kolom, value = default.
     */
    private array $columns = [
        'is_liveness_enabled'          => true,
        'liveness_presensi_enabled'    => true,
        'liveness_login_enabled'       => true,
        'liveness_slip_enabled'        => true,
        'liveness_cuti_enabled'        => true,
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
