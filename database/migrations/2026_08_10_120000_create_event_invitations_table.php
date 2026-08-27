<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * event_invitations
     *
     * Menyimpan konfirmasi kehadiran karyawan untuk sebuah event
     * (mis. Perayaan HUT Kemerdekaan RI). Flow-nya:
     *   1. Karyawan scan QR NPK di ID card -> disimpan sementara di session.
     *   2. Karyawan mengisi form "Bisa Hadir / Tidak Bisa Hadir" + ucapan.
     *   3. Baris di tabel ini di-upsert (updateOrCreate) berdasarkan
     *      kombinasi event_id + npk, jadi 1 NPK hanya bisa punya 1 jawaban
     *      per event (tapi jawaban itu boleh diubah/di-submit ulang).
     */
    public function up(): void
    {
        Schema::create('event_invitations', function (Blueprint $table) {
            $table->id();

            // FK ke tabel master events (lihat 2026_08_10_110000_create_events_table.php)
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->string('npk', 20)->index();
            $table->string('nama')->nullable();
            $table->string('departemen')->nullable();

            // null = belum menjawab, 'hadir' / 'tidak_hadir' = sudah menjawab
            $table->enum('status', ['hadir', 'tidak_hadir'])->nullable()->index();

            $table->text('ucapan')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->unique(['event_id', 'npk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_invitations');
    }
};
