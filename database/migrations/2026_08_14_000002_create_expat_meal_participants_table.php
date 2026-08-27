<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expat_meal_participants', function (Blueprint $table) {
            $table->id();
            $table->string('npk', 20);
            $table->string('nama_expat', 100);
            $table->date('tanggal');
            $table->string('kategori', 20); // Sarapan / Makan Siang
            $table->timestamps();

            // Satu NPK hanya boleh tercatat sekali per tanggal per kategori.
            // Import ulang untuk kombinasi yang sama akan meng-update baris ini
            // (updateOrCreate), bukan membuat duplikat.
            $table->unique(['npk', 'tanggal', 'kategori']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expat_meal_participants');
    }
};
