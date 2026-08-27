<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table sumber untuk sheet "BOM", disinkron dari database smartit (SQL Server)
 * lewat query get_bom_2026.txt. bom_id = natural key dari smartit (unik per baris BOM).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mon_boms', function (Blueprint $table) {
            $table->id();

            $table->string('bom_id')->unique(); // dari prd_bom.bom_id (smartit)
            $table->string('code_prod')->nullable();
            $table->date('tgl_prod')->nullable()->index();
            $table->string('barang_code')->index();
            $table->string('barang_name')->nullable();
            $table->string('satuan_code')->nullable();
            $table->string('uraian')->index(); // join key ke ORDER & PO
            $table->text('spesifikasi')->nullable();
            $table->decimal('cons', 18, 6)->default(0);
            $table->decimal('scrap_percent', 8, 4)->default(0);
            $table->string('departemen')->nullable();
            $table->string('komponen')->nullable();
            $table->string('barang_jadi')->nullable();

            $table->timestamps();

            $table->index(['uraian', 'barang_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_boms');
    }
};
