<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('canteen_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('photo')->nullable();
            $table->decimal('price', 12, 2)->default(0);

            // Availability rules
            $table->date('available_start')->nullable();   // rentang tanggal mulai
            $table->date('available_end')->nullable();      // rentang tanggal sampai
            $table->string('available_days')->nullable();   // json: ["monday","tuesday"]
            $table->string('available_weeks')->nullable();  // json: [1,3] -> minggu ke-1 & ke-3 dalam bulan

            $table->integer('quota')->nullable();            // null = unlimited
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('canteen_id')->references('id')->on('canteens')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_menus');
    }
};
