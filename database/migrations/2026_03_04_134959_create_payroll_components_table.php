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
        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // upah, lembur, mangkir
            $table->string('code')->unique(); // upah, lembur, mangkir
            $table->enum('type', ['earning', 'deduction']); // jenis komponen: earning (pendapatan) atau deduction (potongan)
            $table->enum('calculation_method', ['fixed', 'formula']); // metode perhitungan: fixed (nilai tetap) atau formula (berdasarkan rumus)
            $table->decimal('value', 15, 2)->nullable(); // nilai tetap jika calculation_method = fixed
            $table->text('formula')->nullable(); // rumus jika calculation_method = formula, contoh: "upah * 0.1" untuk tunjangan kesehatan
            $table->string('description')->nullable(); // deskripsi tambahan tentang komponen
            $table->string('category')->nullable(); // kategori komponen, misalnya: "gaji pokok", "tunjangan", "potongan"
            $table->integer('priority')->default(0); // prioritas perhitungan, semakin tinggi semakin dulu dihitung
            $table->boolean('is_taxable')->default(false); // apakah komponen ini dikenakan pajak
            $table->boolean('is_active')->default(true); // status aktif komponen
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_components');
    }
};
