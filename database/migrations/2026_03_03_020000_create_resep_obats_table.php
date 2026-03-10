<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resep_obats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kunjungan_id');
            $table->text('keterangan_obat');
            $table->timestamps();

            $table->foreign('kunjungan_id')->references('id')->on('kunjungans')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resep_obats');
    }
};
