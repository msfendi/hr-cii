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
        Schema::create('evaluation_employee', function (Blueprint $table) {
            $table->id();
            $table->string('npk');
            $table->string('jobscope_id');
            $table->integer('score')->default(0);
            $table->timestamp('evaluation_date')->nullable();
            $table->string('employee_question')->nullable();
            $table->string('employee_answer')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_employee');
    }
};
