<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_masters', function (Blueprint $table) {
            $table->id();
            $table->string('npk')->unique();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->decimal('salary', 15, 2)->default(0);
            $table->decimal('allowance', 15, 2)->default(0);
            $table->decimal('pph21', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_masters');
    }
};
