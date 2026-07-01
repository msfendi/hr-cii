<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_violations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id');
            $table->string('npk');
            $table->decimal('percentage', 5, 2)->default(0);
            $table->timestamps();

            $table->foreign('period_id')->references('id')->on('periods')->onDelete('cascade');
            // Kalau tabel employees pakai npk sebagai kolom (bukan primary key id), bisa tambahkan index saja:
            $table->index('npk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_violations');
    }
};
