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
        Schema::create('AUDIT', function (Blueprint $table) {
            $table->id();
            $table->string('NPK');
            $table->string('NAMA_KARYAWAN');
            $table->date('TANGGAL');
            $table->string('SUBDIVISI');
            $table->string('KODE_BAGIAN');
            $table->string('JAM_PAGI')->nullable();
            $table->string('JAM_SIANG')->nullable();
            $table->string('JAM_MALAM')->nullable();
            $table->string('STATUS')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('AUDIT');
    }
};
