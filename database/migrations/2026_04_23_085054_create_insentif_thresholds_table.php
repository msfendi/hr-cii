<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insentif_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('insentif_type');
            $table->integer('days');
            $table->decimal('minimum', 5, 2);
            $table->string('type'); // sewing / cutting / etc
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insentif_thresholds');
    }
};
