<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_efficiencies', function (Blueprint $table) {
            $table->id();
            $table->integer('line_number');
            $table->unsignedBigInteger('period_id');
            $table->float('efficiency')->nullable();
            $table->date('date');
            $table->integer('days')->nullable();
            $table->timestamps();

            $table->index(['period_id', 'line_number', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_efficiencies');
    }
};
