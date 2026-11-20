<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_wage_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('npk', 50);
            $table->string('contract_id', 64)->nullable();
            $table->date('effective_date');
            $table->string('policy');                 // percent | nominal
            $table->decimal('percent', 6, 3)->nullable();
            $table->decimal('umr_reference', 18, 2)->nullable();
            $table->decimal('old_salary', 18, 2)->default(0);
            $table->decimal('new_salary', 18, 2)->default(0);
            $table->decimal('old_daily_salary', 18, 2)->default(0);
            $table->decimal('new_daily_salary', 18, 2)->default(0);
            $table->decimal('old_allowance', 18, 2)->default(0);
            $table->decimal('new_allowance', 18, 2)->default(0);
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            // Append-only audit trail and the source used by rollback.
            $table->unique(['npk', 'effective_date', 'batch_id']);
            $table->index(['npk', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_wage_revisions');
    }
};
