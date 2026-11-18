<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IZIN MENINGGALKAN TUGAS / PEKERJAAN (CII.FM.HR.02.06)
 *
 * Satu pengajuan = SATU ROW, mengikuti pola ijin dinas: rantai approver
 * disimpan sebagai snapshot JSON (`approval_chain`) dan keputusan per level di
 * `answers`. Bukan satu row per level seperti `leave_requests`.
 *
 * PENTING — hubungan dengan tabel lama `ijin_meninggalkan_pekerjaans`:
 * tabel itu sudah dipakai CRUD admin dan dibaca payroll, dan TIDAK diubah.
 * Tabel ini adalah tempat permohonannya; barisnya baru disalin ke tabel lama
 * setelah security mencatat karyawan kembali (`jam_kembali` terisi).
 * `legacy_ijin_id` menyimpan id hasil salinan itu supaya penyalinan tidak
 * pernah terjadi dua kali.
 *
 * Data karyawan (nama, bagian, departemen) tidak disimpan — hanya `npk`.
 * Sisanya dibaca dari master saat halaman dibuka.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ijin_meninggalkan_requests')) {
            return;
        }

        Schema::create('ijin_meninggalkan_requests', function (Blueprint $table) {
            $table->id();

            // Pengenal pengajuan (deep link & QR)
            $table->string('token', 64)->unique();

            // Pemohon
            $table->string('npk', 50)->index();

            // ── Isi surat (form CII.FM.HR.02.06) ──
            // Form hanya menyediakan SATU tanggal.
            $table->date('tanggal')->index();

            // No. 4 form: "jam meninggalkan tugas / keadaan kembali (S/D)"
            $table->time('jam_keluar');
            $table->time('rencana_kembali')->nullable();

            // No. 5 form: jam kembali aktual — diisi gate security.
            $table->time('jam_kembali')->nullable();

            // No. 6 form
            $table->text('alasan')->nullable();

            // ── Approval ──
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('current_level')->default(1);
            $table->json('approval_chain')->nullable();
            $table->json('answers')->nullable();

            // ── Gate security (dipakai pada fase berikutnya) ──
            $table->json('security_check')->nullable();

            // ── Jembatan ke tabel lama (diisi fase sinkronisasi) ──
            $table->unsignedBigInteger('legacy_ijin_id')->nullable();

            $table->string('void', 10)->default('false');
            $table->string('submitted_by', 50)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ijin_meninggalkan_requests');
    }
};