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
        Schema::create('payroll_run_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('payroll_runs');
            $table->string('employee_npk');
            $table->string('employee_name');

            $table->json('components'); // hasil komponen payroll
            $table->decimal('total_salary', 18, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_run_details');
    }
};
