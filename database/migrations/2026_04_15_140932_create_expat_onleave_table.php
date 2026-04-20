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
        Schema::create('expat_onleave', function (Blueprint $table) {
            $table->id();

            $table->string('npk');

            $table->date('onleave_start')->nullable();
            $table->date('onleave_end')->nullable();

            $table->string('leave_type')->nullable();

            $table->string('component')->nullable();

            $table->decimal('amount', 15, 2)->nullable();

            $table->date('transactions_date')->nullable();

            $table->text('remark')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expat_onleave');
    }
};
