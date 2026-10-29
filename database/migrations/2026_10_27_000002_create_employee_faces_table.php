<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_faces', function (Blueprint $table) {
            $table->id();
            $table->string('npk', 20)->unique();
            $table->string('name')->nullable();

            // Foto wajah karyawan dalam format base64 (data:image/jpeg;base64,...)
            $table->longText('face_img');

            // Descriptor face-api.js (128 float) disimpan sebagai JSON agar
            // proses pencocokan tidak perlu menghitung ulang dari gambar.
            $table->longText('descriptor')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('registered_by')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('face_recognition_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->decimal('match_threshold', 4, 3)->default(0.500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_recognition_settings');
        Schema::dropIfExists('employee_faces');
    }
};
