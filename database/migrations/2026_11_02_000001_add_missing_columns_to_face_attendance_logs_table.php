<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyelarasan struktur table `face_attendance_logs`.
 *
 * Dua skema pernah dipakai bersamaan:
 *  A. Kode aplikasi (FaceAttendanceController::saveFaceAttendanceDetail) menulis:
 *     npk, barcode, name, scan_date, action_type, inoutmode, latitude, longitude,
 *     accuracy, location_name, location_id, face_image
 *  B. Skema versi lain (sudah ada di server):
 *     npk, pin, action, scan_date, latitude, longitude, accuracy, inside_polygon,
 *     work_location_id, face_match, confidence, liveness_passed, status,
 *     rejection_reason, ip_address, user_agent
 *
 * Migration ini BERSIFAT ADITIF: tidak menghapus / me-rename kolom apa pun,
 * hanya menambahkan kolom yang belum ada. Hasilnya kedua kode bisa jalan:
 *  - INSERT dari kode A  -> kolom J ikut terisi
 *  - INSERT dari kode B  -> kolom A ikut terisi (nullable)
 *
 * Aman dijalankan berulang (idempotent): setiap kolom dicek dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('face_attendance_logs')) {
            return;
        }

        // ---- Kelompok A: kolom yang ditulis kode aplikasi (bila belum ada) ----
        $groupA = [
            'barcode'       => fn (Blueprint $t) => $t->string('barcode', 100)->nullable(),
            'name'          => fn (Blueprint $t) => $t->string('name', 255)->nullable(),
            'action_type'   => fn (Blueprint $t) => $t->string('action_type', 50)->nullable(),
            'inoutmode'     => fn (Blueprint $t) => $t->tinyInteger('inoutmode')->nullable(),
            'location_name' => fn (Blueprint $t) => $t->string('location_name', 255)->nullable(),
            'location_id'   => fn (Blueprint $t) => $t->unsignedBigInteger('location_id')->nullable(),
            'face_image'    => fn (Blueprint $t) => $t->longText('face_image')->nullable(),
        ];

        // ---- Kelompok B: kolom skema alternatif (bila belum ada) ----
        $groupB = [
            'pin'              => fn (Blueprint $t) => $t->string('pin', 50)->nullable(),
            'action'           => fn (Blueprint $t) => $t->string('action', 50)->nullable(),
            'inside_polygon'   => fn (Blueprint $t) => $t->boolean('inside_polygon')->nullable(),
            'work_location_id' => fn (Blueprint $t) => $t->unsignedBigInteger('work_location_id')->nullable(),
            'face_match'       => fn (Blueprint $t) => $t->boolean('face_match')->nullable(),
            'confidence'       => fn (Blueprint $t) => $t->decimal('confidence', 8, 5)->nullable(),
            'liveness_passed'  => fn (Blueprint $t) => $t->boolean('liveness_passed')->nullable(),
            'status'           => fn (Blueprint $t) => $t->string('status', 30)->nullable(),
            'rejection_reason' => fn (Blueprint $t) => $t->string('rejection_reason', 255)->nullable(),
            'ip_address'       => fn (Blueprint $t) => $t->string('ip_address', 45)->nullable(),
            'user_agent'       => fn (Blueprint $t) => $t->text('user_agent')->nullable(),
        ];

        foreach ([$groupA, $groupB] as $group) {
            foreach ($group as $column => $definition) {
                if (Schema::hasColumn('face_attendance_logs', $column)) {
                    continue;
                }

                Schema::table('face_attendance_logs', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        // Sengaja TIDAK menghapus kolom: data presensi bersifat historis dan
        // kolom tambahan tidak mengganggu. Rollback = no-op yang aman.
    }
};
