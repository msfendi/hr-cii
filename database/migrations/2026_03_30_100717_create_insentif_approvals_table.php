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
        Schema::create('insentif_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('period_id')
                ->constrained('payroll_periods')
                ->cascadeOnDelete();

            $table->string('payroll_component');

            $table->longText('approval')->nullable();   // structure dari payroll_settings
            $table->longText('progress')->nullable();   // progress approval
            $table->timestamp('approved_at')->nullable();

            $table->string('status')->default('pending');

            $table->timestamps();

            $table->unique(['period_id', 'payroll_component']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insentif_approvals');
    }
};
