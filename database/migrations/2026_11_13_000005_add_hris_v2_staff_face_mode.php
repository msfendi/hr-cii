<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode verifikasi wajah untuk login STAF di /v2/login.
 *
 * Permintaan user: "/v2/login bisa dicontroll memakai liveness atau random
 * challenge atau tanpa verifikasi keduanya dan bisa dimanage di
 * /face-attendance/settings".
 *
 * Kolom TERPISAH dari hris_v2_verification_mode (yang mengatur login
 * karyawan di /v2/karyawan/login) supaya admin bisa menyetel keduanya
 * secara independen.
 *
 * Nilai: 'liveness' | 'random_challenge' | 'none'.
 * NULL = belum diset -> aplikasi memakai default fail-safe 'liveness'.
 *
 * Idempotent: aman dijalankan berulang.
 */
return new class extends Migration
{
    private const TABLE = 'face_recognition_settings';
    private const COLUMN = 'hris_v2_staff_face_mode';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string(self::COLUMN, 30)->nullable();
        });

        \App\Models\FaceRecognitionSetting::forgetSchemaCache();
        \App\Models\FaceRecognitionSetting::forgetCurrent();
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (!Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(self::COLUMN);
        });

        \App\Models\FaceRecognitionSetting::forgetSchemaCache();
        \App\Models\FaceRecognitionSetting::forgetCurrent();
    }
};