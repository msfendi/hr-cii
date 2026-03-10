<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunjungans', function (Blueprint $table) {
            $table->id();
            $table->string('NPK')->index(); // references biodatas.NPK
            $table->date('tanggal_kunjungan');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->text('keluhan');
            $table->text('diagnosa')->nullable();
            $table->text('catatan_dokter')->nullable();
            $table->text('tindakan')->nullable();
            $table->enum('status', ['menunggu', 'diperiksa', 'selesai'])->default('menunggu');
            $table->unsignedBigInteger('dokter_id')->nullable();
            $table->integer('no_antrian');
            $table->timestamps();

            $table->foreign('dokter_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunjungans');
    }
};
