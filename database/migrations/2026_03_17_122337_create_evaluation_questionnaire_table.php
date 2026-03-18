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
        Schema::create('evaluation_questionnaire', function (Blueprint $table) {
            $table->id();
            $table->string('jobscope_id');
            $table->text('question');
            $table->text('optiona');
            $table->text('optionb');
            $table->text('optionc');
            $table->text('optiond');
            $table->text('correct_answer');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_questionnaire');
    }
};
