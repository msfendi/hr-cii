<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chu_family', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('gender')->nullable();
            $table->string('place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('nationality')->nullable();

            $table->string('passport_number')->nullable();
            $table->date('passport_expiry')->nullable();

            $table->string('visa_type')->nullable();
            $table->date('visa_expiry')->nullable();

            $table->date('kitas_expiry')->nullable();
            $table->date('rptka_expiry')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chu_family');
    }
};
