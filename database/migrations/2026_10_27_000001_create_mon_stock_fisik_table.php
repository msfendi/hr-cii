<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mon_stock_fisik', function (Blueprint $table) {
            $table->id();

            // 'dokumen'  : jenis_doc diawali "BC" (dokumen impor)
            // 'produksi' : jenis_doc selain itu (hasil produksi)
            $table->string('tipe', 20);

            $table->string('dt_doc_id', 100)->nullable();
            $table->string('barang_code', 50)->nullable();
            $table->string('barang_name', 255)->nullable();
            $table->string('satuan_code', 50)->nullable();
            $table->string('barang_category', 100)->nullable();
            $table->text('spesifikasi')->nullable();
            $table->string('jenis_doc', 500)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('jenis_doc1', 100)->nullable();
            $table->string('no_doc', 100)->nullable();
            $table->dateTime('tgl_doc')->nullable();
            $table->decimal('saldo', 24, 7)->default(0);
            $table->decimal('harga_satuan', 24, 7)->nullable();
            $table->decimal('harga_satuan_fob', 24, 7)->nullable();
            $table->text('uraian')->nullable();
            $table->decimal('nilai_harga', 28, 7)->nullable();
            $table->decimal('nilai_harga_fob', 28, 7)->nullable();
            $table->unsignedTinyInteger('digit')->default(2);

            $table->timestamps();

            $table->index('barang_code');
            $table->index(['tipe', 'satuan_code']);
            $table->index('tgl_doc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_stock_fisik');
    }
};
