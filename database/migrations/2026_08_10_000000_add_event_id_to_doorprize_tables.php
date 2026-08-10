<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- doorprize_scans ---------------------------------------------
        // Migration aslinya mendefinisikan: $table->string('npk')->unique();
        // Laravel otomatis menamai index itu "doorprize_scans_npk_unique".
        // Index ini HARUS dihapus dulu, karena kalau tidak, unique lama
        // (npk sendirian) akan tetap mencegah NPK yang sama dipakai lagi
        // di event lain, walaupun sudah ditambah unique(event_id, npk).
        Schema::table('doorprize_scans', function (Blueprint $table) {
            $table->dropUnique('doorprize_scans_npk_unique');
        });

        Schema::table('doorprize_scans', function (Blueprint $table) {
            $table->foreignId('event_id')
                ->nullable()
                ->after('id')
                ->constrained('events')
                ->nullOnDelete();
        });

        Schema::table('doorprize_scans', function (Blueprint $table) {
            // Syarat unique sekarang jadi gabungan: 1 NPK hanya boleh 1x
            // scan PER event, tapi bebas scan lagi di event yang berbeda.
            $table->unique(['event_id', 'npk']);
            $table->index('event_id');
        });

        // --- doorprize_winners --------------------------------------------
        Schema::table('doorprize_winners', function (Blueprint $table) {
            $table->foreignId('event_id')
                ->nullable()
                ->after('id')
                ->constrained('events')
                ->nullOnDelete();

            $table->index('event_id');
            $table->index(['event_id', 'npk']);
        });
    }

    public function down(): void
    {
        Schema::table('doorprize_scans', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'npk']);
            $table->dropConstrainedForeignId('event_id');
        });

        Schema::table('doorprize_scans', function (Blueprint $table) {
            // Kembalikan unique tunggal seperti semula
            $table->unique('npk');
        });

        Schema::table('doorprize_winners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
