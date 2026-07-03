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
        Schema::create('employee_lates', function (Blueprint $table) {
            $table->id();
            $table->string('npk');
            $table->date('date');
            $table->time('arrival_time');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('npk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_lates');
    }
};
