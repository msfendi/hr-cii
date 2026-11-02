<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('face_attendance_logs')) {
            return;
        }

        Schema::create('face_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('npk', 50)->index();
            $table->string('barcode', 100)->nullable()->index();
            $table->string('name')->nullable();
            $table->dateTime('scan_date')->index();
            $table->string('action_type', 50)->nullable(); // Clock In / Clock Out
            $table->tinyInteger('inoutmode')->nullable(); // 0 = in, 1 = out

            // Koordinat lokasi saat absensi
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy', 10, 2)->nullable(); // radius akurasi GPS (meter)

            // Nama lokasi polygon yang cocok
            $table->string('location_name')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();

            // Wajah hasil capture (base64, tanpa prefix data:image/...)
            $table->longText('face_image')->nullable();

            $table->timestamps();

            $table->index(['npk', 'scan_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_attendance_logs');
    }
};
