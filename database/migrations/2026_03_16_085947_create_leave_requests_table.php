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
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('NPK');
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->text('reason');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days');
            $table->string('approval_id');
            $table->integer('approval_level');
            $table->integer('approval_progress');
            $table->date('approval_date');
            $table->enum('status', ['pending','waiting','approved','rejected','cancelled'])->default('pending');
            $table->string('token');
            $table->string('void')->default('false');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
