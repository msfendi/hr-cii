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
        Schema::create('employee_line_assignments', function (Blueprint $table) {
            $table->id();

            $table->string('npk', 20);
            $table->unsignedBigInteger('line_number');

            $table->string('role');
            // operator, spv, chief, mekanik, mekanik_leader

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->timestamps();

            $table->index('npk');
            $table->index('line_number');
            $table->index(['npk', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_line_assignments');
    }
};
