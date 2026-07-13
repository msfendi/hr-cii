<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_orders', function (Blueprint $table) {
            $table->id();
            $table->string('npk');
            $table->unsignedBigInteger('food_menu_id');
            $table->unsignedBigInteger('canteen_id');
            $table->date('order_date'); // tanggal makanan akan diterima (hari-H)
            $table->string('status')->default('pending'); // pending | confirmed | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            // 1 karyawan hanya boleh 1 pesanan per tanggal
            $table->unique(['npk', 'order_date'], 'uniq_npk_order_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_orders');
    }
};
