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
        Schema::create('expat_master', function (Blueprint $table) {
            $table->id();

            $table->string('npk')->unique();
            $table->string('name')->nullable();
            $table->string('position')->nullable();
            $table->string('place')->nullable();
            $table->string('nationality')->nullable();
            $table->string('direct_report')->nullable();
            $table->string('npwp')->nullable();

            $table->date('joining_date')->nullable();
            $table->date('end_date')->nullable();

            $table->string('passport_number')->nullable();
            $table->date('passport_expiry')->nullable();
            $table->date('kitas_expiry')->nullable();
            $table->date('rptka_expiry')->nullable();
            $table->date('merp_expiry')->nullable();

            $table->text('house_address')->nullable();
            $table->date('house_startdate')->nullable();
            $table->date('lease_enddate')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expat_master');
    }
};
