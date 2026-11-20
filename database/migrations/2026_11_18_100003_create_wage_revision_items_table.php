<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wage_revision_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('wage_revision_batches')->cascadeOnDelete();
            $table->string('npk', 50);
            $table->string('segment');
            // Points at employees_contract.id on the `cii` connection (nvarchar(255), no FK possible
            // across connections).
            $table->string('contract_id', 64)->nullable();
            $table->boolean('is_expat')->default(false);
            $table->string('input_mode')->default('target');   // percent | delta | target
            $table->decimal('input_value', 18, 2)->nullable();
            $table->decimal('old_salary', 18, 2)->default(0);
            $table->decimal('old_daily_salary', 18, 2)->default(0);
            $table->decimal('old_allowance', 18, 2)->default(0);
            $table->decimal('new_salary', 18, 2)->default(0);
            $table->decimal('new_daily_salary', 18, 2)->default(0);
            $table->decimal('new_allowance', 18, 2)->default(0);
            $table->decimal('delta_monthly', 18, 2)->default(0);
            $table->boolean('warn_below_umr')->default(false);
            $table->boolean('warn_overtime_threshold')->default(false);
            $table->boolean('warn_column_mismatch')->default(false);
            $table->string('status')->default('draft');
            $table->string('message')->nullable();
            $table->string('source')->default('manual');       // generated | manual | import
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'npk']);
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wage_revision_items');
    }
};
