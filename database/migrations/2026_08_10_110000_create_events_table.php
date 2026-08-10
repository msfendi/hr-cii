<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * events
     *
     * Tabel master untuk event manapun yang butuh flow scan QR + RSVP
     * (HUT RI, gathering, dsb). Setiap baris = satu event dengan:
     *   - jadwal & lokasinya
     *   - blade folder mana yang dipakai untuk tampilannya
     *     (lihat resources/views/event_invitation/{view_folder}/...)
     *   - rekap jumlah hadir / tidak hadir (di-update otomatis oleh
     *     EventInvitationController setiap ada RSVP masuk/berubah)
     *   - flag is_active -> event mana yang sedang "live" dipakai kalau
     *     URL diakses tanpa id event secara eksplisit.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->string('nama_event');
            $table->date('tanggal_event');

            // teks bebas biar fleksibel, mis. "08.00 WIB - Selesai"
            $table->string('waktu_event');

            $table->string('lokasi_event');
            $table->string('dress_code')->nullable();
            $table->text('detail_event')->nullable();

            // nama folder di resources/views/event_invitation/{view_folder}/
            // yang wajib berisi scan.blade.php & form.blade.php
            $table->string('view_folder');

            $table->unsignedInteger('jumlah_hadir')->default(0);
            $table->unsignedInteger('jumlah_tidak_hadir')->default(0);

            // hanya 1 event yang boleh aktif di satu waktu -> dijaga di
            // EventController::activateOnly(), bukan lewat DB constraint
            $table->boolean('is_active')->default(false)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
