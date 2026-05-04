<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foreign_guests', function (Blueprint $table) {
            $table->id();

            $table->string('guest_name');
            $table->string('bank_account')->nullable();

            $table->string('photo')->nullable();
            $table->string('passport')->nullable();

            $table->string('visa_type')->nullable();
            $table->string('visa_application')->nullable();
            $table->string('visa_status')->nullable();

            $table->bigInteger('visa_invoice')->nullable();
            $table->bigInteger('rent_invoice')->nullable();

            $table->string('flight_detail')->nullable();
            $table->time('flight_eta')->nullable();

            $table->date('eta')->nullable();
            $table->date('return')->nullable();

            $table->string('hotel')->nullable();
            $table->string('hotel_file')->nullable();
            $table->bigInteger('hotel_invoice')->nullable();

            $table->string('status')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foreign_guests');
    }
};
