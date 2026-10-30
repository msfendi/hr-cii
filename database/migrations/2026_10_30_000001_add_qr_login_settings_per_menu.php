<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('face_recognition_settings')) {
            if (!Schema::hasColumn('face_recognition_settings', 'is_qr_login_enabled')) {
                Schema::table('face_recognition_settings', function (Blueprint $table) {
                    $table->boolean('is_qr_login_enabled')->default(true);
                });
            }
            if (!Schema::hasColumn('face_recognition_settings', 'is_qr_payroll_enabled')) {
                Schema::table('face_recognition_settings', function (Blueprint $table) {
                    $table->boolean('is_qr_payroll_enabled')->default(true);
                });
            }
            if (!Schema::hasColumn('face_recognition_settings', 'is_qr_thr_enabled')) {
                Schema::table('face_recognition_settings', function (Blueprint $table) {
                    $table->boolean('is_qr_thr_enabled')->default(true);
                });
            }
            if (!Schema::hasColumn('face_recognition_settings', 'is_qr_cuti_enabled')) {
                Schema::table('face_recognition_settings', function (Blueprint $table) {
                    $table->boolean('is_qr_cuti_enabled')->default(true);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('face_recognition_settings')) {
            $cols = ['is_qr_login_enabled', 'is_qr_payroll_enabled', 'is_qr_thr_enabled', 'is_qr_cuti_enabled'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('face_recognition_settings', $col)) {
                    Schema::table('face_recognition_settings', function (Blueprint $table) use ($col) {
                        $table->dropColumn($col);
                    });
                }
            }
        }
    }
};
