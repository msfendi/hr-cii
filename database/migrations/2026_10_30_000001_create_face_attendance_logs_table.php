<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel detail absensi wajah + GPS.
 *
 * - Tabel `att_log` TIDAK diubah sama sekali (sn, scan_date, pin, verifymode,
 *   inoutmode, reserved, work_code, att_id).
 * - Data yang tidak muat di att_log (GPS, akurasi, confidence, liveness,
 *   status, alasan penolakan, dll) disimpan di sini.
 * - Penghubung ke att_log = kolom `att_id` (logical link, bukan FK keras,
 *   karena att_id di att_log belum tentu punya unique index).
 * - Percobaan yang DITOLAK tidak masuk att_log, hanya tercatat di sini
 *   (att_id = NULL), supaya rekap/payroll yang membaca att_log tidak tercemar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_attendance_logs', function (Blueprint $table) {
            $table->id();

            // Link ke att_log.att_id (NULL bila percobaan ditolak)
            $table->string('att_id', 64)->nullable()->index();

            $table->string('npk', 30);
            $table->string('pin', 50);
            $table->string('action', 10);              // clock_in | clock_out
            $table->dateTime('scan_date');

            // GPS
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->float('accuracy')->nullable();     // meter
            $table->boolean('inside_polygon')->default(false);
            $table->unsignedBigInteger('work_location_id')->nullable();

            // Wajah
            $table->boolean('face_match')->default(false);
            $table->decimal('confidence', 5, 4)->nullable();
            $table->boolean('liveness_passed')->default(false);

            // Hasil
            $table->string('status', 10);              // PRESENT | REJECTED
            $table->string('rejection_reason', 255)->nullable();

            // Jejak audit
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['npk', 'scan_date']);
            $table->index(['pin', 'scan_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_attendance_logs');
    }
};
