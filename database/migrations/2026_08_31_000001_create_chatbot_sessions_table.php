<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel ini menyimpan setiap "percakapan" (sesi) chatbot.
     * Satu sesi = satu pilihan AI provider + satu mode (global / rtg)
     * yang dipilih user saat memulai chat baru.
     */
    public function up(): void
    {
        Schema::create('chatbot_sessions', function (Blueprint $table) {
            $table->id();

            // Nullable dulu supaya kompatibel walau aplikasi belum pakai auth user.
            // Kalau sistem sudah punya tabel users, boleh diaktifkan foreign key-nya.
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('title')->nullable();

            // 'global'  -> chatbot ngobrol bebas, tidak menyentuh database.
            // 'rtg'     -> Read To Generate: chatbot boleh baca (SELECT saja)
            //              dari database lewat koneksi read-only untuk menjawab.
            $table->string('mode')->default('global');

            // Key provider, harus salah satu key di config('services.ai_providers').
            $table->string('ai_provider');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_sessions');
    }
};
