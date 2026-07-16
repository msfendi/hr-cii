<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_tests', function (Blueprint $table) {
            $table->id();

            $table->string('nik');

            $table->boolean('cacat')->default(0);
            $table->boolean('buta_warna')->default(0);
            $table->string('visus_mata_od')->nullable();
            $table->string('visus_mata_os')->nullable();
            $table->integer('tinggi')->nullable();
            $table->integer('berat')->nullable();

            $table->string('abdoment')->nullable();
            $table->string('gigi')->nullable();
            $table->string('cor_pulmo')->nullable();
            $table->string('tht')->nullable();
            $table->string('extreme')->nullable();

            $table->string('tekanan_darah')->nullable();
            $table->integer('respirasi')->nullable();
            $table->integer('denyut')->nullable();
            $table->integer('suhu')->nullable();

            $table->boolean('paru')->default(0);
            $table->boolean('hepatitis')->default(0);
            $table->boolean('jantung')->default(0);
            $table->boolean('thypoid')->default(0);
            $table->boolean('alergi')->default(0);
            $table->boolean('ashma')->default(0);

            $table->string('lain')->nullable();

            $table->boolean('kesimpulan')->default(1); // sehat
            $table->string('remark')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_tests');
    }
};
