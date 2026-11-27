<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log pengisian kuesioner per PERANGKAT (anti-spam).
 *
 * Satu baris = satu kiriman yang BERHASIL. Tidak disimpan di
 * satisfaction_responses supaya tidak terduplikasi 35x dan tidak ikut
 * tampil di halaman jawaban.
 *
 * Kunci pemblokiran: device_hash (ID perangkat dari cookie pertama-pihak).
 * TIDAK ada kolom IP: IP/MAC tidak bisa dipakai sebagai identitas perangkat
 * (MAC mustahil dibaca dari HTTP; IP di belakang proxy = alamat server).
 *
 * unique(device_hash, period_key) => 1 kiriman per perangkat per bulan (WIB).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('satisfaction_submission_logs')) {
            return;
        }

        Schema::create('satisfaction_submission_logs', function (Blueprint $table) {
            $table->id();
            $table->char('device_hash', 64);
            $table->string('user_agent', 255)->nullable();
            $table->char('period_key', 7);
            $table->uuid('submission_id')->nullable();
            $table->timestamps();

            $table->index('created_at', 'idx_survey_log_created');
            $table->unique(['device_hash', 'period_key'], 'uq_survey_log_device_periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_submission_logs');
    }
};