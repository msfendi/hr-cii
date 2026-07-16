<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ijin_meninggalkan_pekerjaans', function (Blueprint $table) {
            $table->id();

            $table->string('npk', 20);
            $table->date('tanggal');

            $table->time('jam_keluar');
            $table->time('rencana_kembali')->nullable();
            $table->time('jam_kembali')->nullable();

            $table->text('reason')->nullable();

            $table->timestamps();

            $table->index('npk');
            $table->index('tanggal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ijin_meninggalkan_pekerjaans');
    }
};