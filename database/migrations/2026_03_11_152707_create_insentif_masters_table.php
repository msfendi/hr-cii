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
        Schema::create('insentif_masters', function (Blueprint $table) {
            $table->id();
            $table->string('npk', 20);
            $table->date('date');
            $table->string('type', 50);
            $table->decimal('efficiency', 8, 2)->nullable();
            $table->decimal('piece', 12, 2)->nullable();
            $table->timestamps();

            $table->index('npk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insentif_masters');
    }
};
