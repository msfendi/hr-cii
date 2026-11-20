<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wage_revision_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            // Reference value only: used to display warnings (below new minimum wage).
            // It never recalculates a non-staff raise, that comes from the percent rule.
            $table->decimal('umr_new', 18, 2)->nullable();
            $table->date('effective_date');
            $table->string('status')->default('draft');
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique('year');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wage_revision_batches');
    }
};
