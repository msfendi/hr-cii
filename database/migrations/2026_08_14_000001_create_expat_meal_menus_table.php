<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expat_meal_menus', function (Blueprint $table) {
            $table->id();
            $table->string('makanan', 150);
            $table->string('kategori', 20); // Sarapan / Makan Siang
            $table->date('tanggal');
            $table->decimal('harga', 12, 2)->default(0);
            // shared = true -> harga makanan ini otomatis dihitung sebagai
            // biaya makan bersama untuk setiap expat yang hadir pada kategori
            // tsb (dipakai di laporan dashboard). shared = false -> hanya
            // referensi menu, tidak dihitung otomatis.
            $table->boolean('shared')->default(false);
            $table->timestamps();

            $table->unique(['makanan', 'kategori']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expat_meal_menus');
    }
};
