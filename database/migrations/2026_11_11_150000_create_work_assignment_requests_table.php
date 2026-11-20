<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WORK ASSIGNMENT REQUEST (Surat Ijin Dinas Luar — CII.FM.HR.02.08)
 *
 * Satu pengajuan = SATU ROW. Berbeda dengan leave_requests yang memakai N row
 * (satu per approver), tabel ini menyimpan jawaban approval sebagai array JSON
 * di kolom `answers`, dan chain approver disnapshot di `approval_chain`.
 *
 * Data karyawan (nama, jabatan, bagian, departemen, section) TIDAK disimpan —
 * hanya `npk`. Data lengkap diambil dari join master (BIODATA + DEPT + sections)
 * saat form/riwayat/PDF dibuka.
 *
 * `duty_dates` = JSON array tanggal dinas ("2026-11-12", ...). Bisa satu
 * tanggal atau beberapa (termasuk tanggal yang tidak berurutan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_assignment_requests')) {
            return;
        }

        Schema::create('work_assignment_requests', function (Blueprint $table) {
            $table->id();

            // Pengenal pengajuan (dipakai di URL & reply WA nanti)
            $table->string('token', 64)->unique();

            // Pemohon — satu-satunya identitas karyawan yang disimpan
            $table->string('npk', 50)->index();

            // ── Tanggal dinas ──
            // duty_dates  = JSON array ["2026-11-12","2026-11-13",...]
            // start/end   = min/max dari duty_dates (untuk sort & filter cepat)
            // total_days  = jumlah tanggal
            $table->json('duty_dates')->nullable();
            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('total_days')->nullable();

            // ── Isi pengajuan (sesuai form CII.FM.HR.02.08) ──
            $table->string('dinas_ke', 255)->nullable();   // 5. Dinas ke
            $table->text('keperluan')->nullable();         // 6. Keperluan
            $table->time('jam_keluar')->nullable();        // 7. Jam keluar
            $table->time('rencana_kembali')->nullable();   //    Rencana kembali
            $table->time('jam_kembali')->nullable();       //    Jam kembali (diisi Security)

            // ── Approval (struktur siap; flow belum diimplementasikan) ──
            // chain  = snapshot: [{level, role_key, role_label, npk, name}]
            // answers= array:     [{level, role_key, npk, decision, reason, at}]
            $table->json('approval_chain')->nullable();
            $table->json('answers')->nullable();

            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('current_level')->default(1);
            $table->string('void', 10)->default('false');

            // NPK yang men-submit (untuk kasus diisi HR, bukan self-service)
            $table->string('submitted_by', 50)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_assignment_requests');
    }
};
