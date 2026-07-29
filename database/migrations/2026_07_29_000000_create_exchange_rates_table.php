<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('rate_date');
            $table->string('currency_from', 3)->default('USD');
            $table->string('currency_to', 3)->default('IDR');
            $table->decimal('kurs_jual', 15, 2)->nullable();
            $table->decimal('kurs_beli', 15, 2)->nullable();
            $table->decimal('kurs_tengah', 15, 2)->nullable();
            $table->string('source')->default('bi.go.id');
            $table->json('raw_response')->nullable();
            $table->timestamps();

            // 1 baris per hari per pasangan mata uang
            $table->unique(['rate_date', 'currency_from', 'currency_to'], 'exchange_rates_unique_per_day');
            $table->index('rate_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
