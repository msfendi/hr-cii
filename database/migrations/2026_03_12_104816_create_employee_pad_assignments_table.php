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
        Schema::create('employee_pad_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('period_id');

            $table->string('npk', 20);
            $table->string('dept');
            $table->string('role');
            // operator, supervisor, inkmaking, helper

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->timestamps();

            $table->index('npk');
            $table->index(['npk', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_pad_assignments');
    }
};
