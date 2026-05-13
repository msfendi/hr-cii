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
        Schema::create('compensation_details', function (Blueprint $table) {
            $table->id();

            $table->string('npk', 50);
            $table->string('id_dept')->nullable();
            $table->unsignedBigInteger('contract_id');

            $table->date('cutoff_date');

            $table->decimal('amount', 15, 0)->default(0);

            $table->string('status');
            $table->string('is_active');

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
