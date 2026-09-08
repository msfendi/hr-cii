<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyimpan setiap baris pesan (user & assistant) di dalam satu sesi.
     * Kolom `meta` dipakai untuk menyimpan info tambahan khusus mode RTG,
     * misalnya query SQL yang dijalankan dan jumlah baris hasilnya —
     * berguna untuk audit / transparansi ke user, dan untuk debugging.
     */
    public function up(): void
    {
        Schema::create('chatbot_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('chatbot_session_id')
                ->constrained('chatbot_sessions')
                ->cascadeOnDelete();

            // 'user' | 'assistant' | 'system'
            $table->string('role');

            $table->longText('content');

            // Contoh isi meta untuk mode RTG:
            // { "sql": "SELECT TOP 50 ...", "row_count": 12 }
            // Contoh isi meta kalau terjadi error:
            // { "error": "Query yang dihasilkan AI ditolak karena menyentuh tabel di luar whitelist." }
            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_messages');
    }
};
