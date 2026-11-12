<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * V44 HOTFIX (2026-11-11): Pastikan kolom deprecated di canteen_settings
 * benar-benar ter-drop dan bersihkan cache schema aplikasi.
 *
 * Latar belakang:
 *   - Error produksi: SQLSTATE[42S22] Invalid column name
 *     'liveness_canteen_enabled' saat INSERT ke canteen_settings.
 *   - Akar masalah: cache schema (schema_columns_canteen_settings,
 *     TTL 1 jam) bisa stale setelah migrasi drop kolom, sehingga
 *     CanteenSetting::sanitizeAttributes() mengira kolom deprecated
 *     masih ada padahal sudah di-drop.
 *   - Migrasi 2026_11_09_143000 memang sudah drop kolom, tapi bila
 *     migrasi itu gagal/terlewat di server produksi, kolom masih ada.
 *
 * Migrasi ini idempotent (cek hasColumn dulu) dan fail-safe (try/catch).
 * Setelah up(), cache schema dibersihkan supaya request berikutnya
 * membaca skema fisik terbaru.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('canteen_settings')) {
            return;
        }

        $deprecated = [
            'liveness_canteen_enabled',
            'is_random_challenge_enabled',
            'random_challenge_canteen_enabled',
        ];

        foreach ($deprecated as $column) {
            if (Schema::hasColumn('canteen_settings', $column)) {
                try {
                    Schema::table('canteen_settings', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                } catch (\Throwable $e) {
                    \Log::warning('V44 hotfix: gagal drop kolom canteen_settings.' . $column . ': ' . $e->getMessage());
                }
            }
        }

        // Bersihkan cache schema supaya request berikutnya membaca
        // skema fisik terbaru (kolom deprecated sudah tidak ada).
        try {
            \App\Models\FaceRecognitionSetting::forgetSchemaCache();
        } catch (\Throwable $e) {
            // Model mungkin belum ter-load saat migrasi; abaikan.
        }
        try {
            \Illuminate\Support\Facades\Cache::forget('schema_columns_canteen_settings');
            \Illuminate\Support\Facades\Cache::forget('schema_columns_face_recognition_settings');
            \Illuminate\Support\Facades\Cache::forget('schema_tables');
        } catch (\Throwable $e) {
            // Abaikan.
        }
    }

    public function down(): void
    {
        // Tidak perlu mengembalikan kolom deprecated; mode verifikasi
        // kini dikelola di face_recognition_settings.canteen_verification_mode.
    }
};
