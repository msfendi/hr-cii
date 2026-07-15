<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doorprize_winners', function (Blueprint $table) {
            $table->id();
            $table->string('npk', 20);
            $table->string('name')->nullable();
            $table->string('department')->nullable();
            $table->string('photo')->nullable(); // path/url foto karyawan
            $table->string('batch_label')->nullable(); // contoh: "Undian Sesi 1 (5 Pemenang)"
            $table->unsignedBigInteger('drawn_by')->nullable();
            $table->timestamp('won_at')->useCurrent();
            $table->timestamps();

            $table->foreign('drawn_by')->references('id')->on('users')->onDelete('set null');
            $table->index('npk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doorprize_winners');
    }
};
