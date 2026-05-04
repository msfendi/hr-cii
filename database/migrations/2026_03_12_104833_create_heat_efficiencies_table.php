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
        Schema::create('heat_efficiencies', function (Blueprint $table) {

            $table->id();
            $table->string('period_id');
            $table->string('npk');
            $table->string('dept');
            $table->decimal('efficiency', 5, 2);
            $table->decimal('piece', 10, 2);
            $table->date('date');

            $table->timestamps();

            $table->index(['date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heat_efficiencies');
    }
};
