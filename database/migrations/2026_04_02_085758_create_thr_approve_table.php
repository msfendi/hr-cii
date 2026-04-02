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
        Schema::create('thr_approve', function (Blueprint $table) {
            $table->id();
            $table->integer('thr_run_id');
            $table->json('approval'); // array NPK dari payroll_settings
            $table->json('progress')->nullable(); // progress approval
            $table->json('approved_at')->nullable();
            $table->string('status')->default('pending'); // pending / finish
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thr_approve');
    }
};
