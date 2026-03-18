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
        Schema::create('line_efficiencies', function (Blueprint $table) {

            $table->id();
            $table->unsignedBigInteger('line_number');
            $table->decimal('efficiency', 5, 2);
            $table->date('date');

            $table->timestamps();

            $table->index(['line_number', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_efficiencies');
    }
};
