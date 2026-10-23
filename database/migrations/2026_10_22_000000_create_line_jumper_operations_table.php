<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_jumper_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id')->index();
            $table->string('npk', 50)->index();
            $table->string('operation', 255);
            // versi ternormalisasi dari "operation" (tanpa nomor urut, uppercase)
            // dipakai supaya import ulang / penulisan beda tidak terhitung dobel
            $table->string('operation_key', 255);
            $table->string('machine', 100)->nullable();
            $table->timestamps();

            $table->unique(['period_id', 'npk', 'operation_key'], 'uq_line_jumper_ops');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_jumper_operations');
    }
};
