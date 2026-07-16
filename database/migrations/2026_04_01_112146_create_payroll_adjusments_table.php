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
        Schema::create('payroll_adjusments', function (Blueprint $table) {
            $table->id();
            $table->string('npk');
            $table->unsignedBigInteger('period_id');
            $table->decimal('adjusment', 15, 2)->default(0);
            $table->string('keterangan');
            $table->timestamps();

            // optional foreign key
            $table->foreign('period_id')
                ->references('id')
                ->on('payroll_periods')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_adjusments');
    }
};
