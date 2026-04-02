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
        Schema::create('thr_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id');
            $table->dateTime('processed_at')->nullable();
            $table->decimal('total_thr', 18, 2)->default(0);
            $table->integer('employee_count')->default(0);
            $table->integer('progress')->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thr_runs');
    }
};
