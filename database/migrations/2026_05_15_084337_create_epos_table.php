<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epos', function (Blueprint $table) {
            $table->id();

            $table->string('expat_name');
            $table->string('gender')->nullable();
            $table->string('place')->nullable();

            $table->date('date_of_birth')->nullable();

            $table->string('nationality')->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();

            $table->date('termination_date')->nullable();
            $table->date('must_leave_date')->nullable();

            $table->decimal('epo_cost', 15, 2)->default(0);
            $table->decimal('rptka_cancellation_cost', 15, 2)->default(0);

            $table->string('remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epos');
    }
};
