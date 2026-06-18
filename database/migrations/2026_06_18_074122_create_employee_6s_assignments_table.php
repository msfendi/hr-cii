<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_6s_assignments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('period_id');
            $table->integer('section_id');
            $table->string('inspector');

            $table->date('inspection_date');

            $table->decimal('total_score', 10, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->string('file_path')->nullable();

            $table->timestamps();

            $table->foreign('period_id')
                ->references('id')
                ->on('payroll_periods')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_6s_assignments');
    }
};
