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
        Schema::create('employees_contract', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('npk');
            $table->string('contract_ke');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('month_duration'); //1, 3, 6, 9, atau 12
            $table->string('status_contract'); // AKTIF, HABIS, DIPERPANJANG, DIAKHIRI
            $table->decimal('salary', 15, 2)->default(0);
            $table->decimal('allowance', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees_contract');
    }
};
