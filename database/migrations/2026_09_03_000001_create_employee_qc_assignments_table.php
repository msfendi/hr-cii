<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_qc_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('npk');
            $table->unsignedBigInteger('period_id');
            $table->integer('line_number')->nullable();
            $table->string('role')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('work_hours', 5, 2)->nullable();
            // Not present in EmployeeLineAssignment $fillable, but referenced
            // by EmployeeLineAssignmentImport::model() ('name', 'section_id') -
            // keep parity for the QC import.
            $table->string('name')->nullable();
            $table->integer('section_id')->nullable();
            $table->string('buyer')->nullable();
            $table->timestamps();

            $table->index(['period_id', 'line_number']);
            $table->index('npk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_qc_assignments');
    }
};
