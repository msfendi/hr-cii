<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah kolom penanda kecurigaan pada `face_attendance_logs`.
 *
 * Tujuan: kalau presensi dikirim tanpa nonce yang sah (indikasi payload
 * dipalsukan / di-replay / Fake GPS lewat script), presensi TETAP DITERIMA
 * tetapi DITANDAI. Ini keputusan operasional (2026-11-04): jangan memblokir
 * karyawan yang sah karena bug, cukup beri jejak untuk audit.
 *
 * Kolom yang ditambahkan (semua NULLABLE, jadi data lama tidak terganggu):
 *   is_suspicious      1 = ditandai, 0 = normal
 *   suspicious_reason  alasan singkat, mis. 'nonce_kedaluwarsa_atau_terpakai'
 *   nonce_verified     1 = nonce sah, 0 = tidak sah / tidak dikirim
 *
 * AMAN dijalankan berulang: setiap kolom dicek dulu dengan hasColumn().
 * AMAN dijalankan pada database yang tabelnya belum ada: langsung return.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('face_attendance_logs')) {
            return;
        }

        $kolom = [
            'is_suspicious'     => fn (Blueprint $t) => $t->boolean('is_suspicious')->nullable(),
            'suspicious_reason' => fn (Blueprint $t) => $t->string('suspicious_reason', 100)->nullable(),
            'nonce_verified'    => fn (Blueprint $t) => $t->boolean('nonce_verified')->nullable(),
        ];

        foreach ($kolom as $nama => $definisi) {
            if (!Schema::hasColumn('face_attendance_logs', $nama)) {
                Schema::table('face_attendance_logs', function (Blueprint $t) use ($definisi) {
                    $definisi($t);
                });
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('face_attendance_logs')) {
            return;
        }

        foreach (['is_suspicious', 'suspicious_reason', 'nonce_verified'] as $nama) {
            if (Schema::hasColumn('face_attendance_logs', $nama)) {
                Schema::table('face_attendance_logs', function (Blueprint $t) use ($nama) {
                    $t->dropColumn($nama);
                });
            }
        }
    }
};
