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
        if (Schema::hasTable('employee_faces')) {
            Schema::table('employee_faces', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_faces', 'can_login')) {
                    $table->boolean('can_login')->default(false)->after('is_active');
                }
            });
        }

        if (Schema::hasTable('face_recognition_settings')) {
            Schema::table('face_recognition_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('face_recognition_settings', 'is_qr_enabled')) {
                    $table->boolean('is_qr_enabled')->default(true)->after('is_enabled');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('employee_faces')) {
            Schema::table('employee_faces', function (Blueprint $table) {
                if (Schema::hasColumn('employee_faces', 'can_login')) {
                    $table->dropColumn('can_login');
                }
            });
        }

        if (Schema::hasTable('face_recognition_settings')) {
            Schema::table('face_recognition_settings', function (Blueprint $table) {
                if (Schema::hasColumn('face_recognition_settings', 'is_qr_enabled')) {
                    $table->dropColumn('is_qr_enabled');
                }
            });
        }
    }
};
