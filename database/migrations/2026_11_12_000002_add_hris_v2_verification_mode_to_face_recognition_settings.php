<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom mode verifikasi wajah untuk Portal Karyawan HRIS v2
 * (dipakai /v2/karyawan/login). Nilai: liveness | random_challenge | none.
 *
 * Idempotent: aman dijalankan berulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('face_recognition_settings')) {
            return;
        }

        if (! Schema::hasColumn('face_recognition_settings', 'hris_v2_verification_mode')) {
            Schema::table('face_recognition_settings', function (Blueprint $table) {
                $table->string('hris_v2_verification_mode', 30)->nullable()->after('canteen_verification_mode');
            });
        }

        // Isi default 'liveness' hanya untuk baris yang masih NULL/kosong,
        // supaya perilaku sebelum diset = fail-safe (wajib verifikasi).
        try {
            DB::table('face_recognition_settings')
                ->where(function ($q) {
                    $q->whereNull('hris_v2_verification_mode')->orWhere('hris_v2_verification_mode', '');
                })
                ->update(['hris_v2_verification_mode' => 'liveness']);
        } catch (\Throwable $e) {
            // Tabel ada tapi baris belum ada — biarkan default aplikasi yang bekerja.
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('face_recognition_settings') && Schema::hasColumn('face_recognition_settings', 'hris_v2_verification_mode')) {
            Schema::table('face_recognition_settings', function (Blueprint $table) {
                $table->dropColumn('hris_v2_verification_mode');
            });
        }
    }
};
