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
        Schema::create('compensations', function (Blueprint $table) {
            $table->id();

            $table->date('cutoff_date');
            $table->integer('total_employee')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0)->nullable();

            $table->string('file_pdf')->nullable();
            $table->string('file_csv')->nullable();
            $table->integer('progress')->nullable();
            $table->string('status')->nullable();
            $table->integer('is_closed')->nullable();

            $table->timestamps();

            // optional relation
            // $table->foreign('contract_id')
            //       ->references('id')
            //       ->on('contracts')
            //       ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compensations');
    }
};
