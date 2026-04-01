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
        Schema::create('payroll_exports', function (Blueprint $table) {
            $table->id();
            $table->integer('run_id');
            $table->integer('progress')->default(0);
            $table->string('status')->default('processing');
            $table->string('file_excel')->nullable();
            $table->string('file_pdf')->nullable();
            $table->string('file_bank_active')->nullable();
            $table->string('file_bank_resign')->nullable();
            $table->string('file_peng')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_exports');
    }
};
