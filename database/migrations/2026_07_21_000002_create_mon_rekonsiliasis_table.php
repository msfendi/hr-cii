<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mon_rekonsiliasis', function (Blueprint $table) {
            $table->id();

            // Kunci unik untuk upsert dari smartit (prd_po_dt.dt_po_id)
            $table->string('dt_po_id')->unique();

            $table->string('valas')->nullable();
            $table->string('no_po')->nullable()->index();
            $table->string('jenis_po')->nullable()->index();
            $table->date('tgl_po')->nullable()->index();
            $table->string('termin')->nullable();
            $table->date('tgl_pengiriman')->nullable()->index();
            $table->string('supplier_name')->nullable();
            $table->string('barang_code')->nullable()->index();
            $table->string('barang_name')->nullable();
            $table->string('satuan_order')->nullable();
            $table->string('satuan_code')->nullable();
            $table->string('klaim_fsc')->nullable();
            $table->string('uraian')->nullable()->index(); // dipakai sebagai "CPO"
            $table->string('spesifikasi')->nullable();
            $table->string('ncv')->nullable();

            $table->decimal('jumlah_order', 18, 4)->default(0);
            $table->decimal('jumlah_doc', 18, 4)->default(0);
            $table->decimal('out_req', 18, 4)->default(0);
            $table->decimal('out_prod', 18, 4)->default(0);
            $table->decimal('sisa', 18, 4)->default(0);
            $table->decimal('saldo_wip', 18, 4)->default(0);
            $table->unsignedTinyInteger('digit')->default(2);
            $table->decimal('out_doc', 18, 4)->default(0);

            $table->string('create_by')->nullable();
            $table->string('create_date')->nullable(); // sudah diformat 'yyyy-MM-dd HH:mm' dari smartit

            $table->decimal('harga_total', 18, 4)->default(0);
            $table->decimal('saldo_gudang', 18, 4)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_rekonsiliasis');
    }
};
