<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table sumber untuk sheet "PO", disinkron dari database smartit (SQL Server)
 * lewat query get_po_2026.txt. dt_po_id = natural key baris detail PO (unik).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mon_purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->string('klaim_fsc')->nullable();
            $table->string('dt_po_id')->unique(); // key baris detail PO
            $table->string('po_id')->nullable()->index();
            $table->string('jenis_po')->nullable()->index(); // PO / Material Supply / dll
            $table->string('no_po')->nullable();
            $table->date('tgl_po')->nullable()->index();
            $table->string('supplier_name')->nullable();
            $table->string('barang_code')->index();
            $table->string('barang_name')->nullable();
            $table->string('satuan_order')->nullable();
            $table->string('uraian')->index(); // join key ke ORDER & BOM
            $table->text('spesifikasi')->nullable();
            $table->decimal('jumlah_order', 18, 4)->default(0);
            $table->decimal('harga_satuan', 18, 4)->default(0);
            $table->decimal('harga_total', 18, 4)->default(0);
            $table->decimal('harga_fob', 18, 4)->default(0);
            $table->decimal('total_fob', 18, 4)->default(0);
            $table->string('ppn')->nullable();
            $table->string('pph')->nullable();
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('biaya', 18, 4)->default(0);
            $table->string('valas')->nullable();
            $table->text('note')->nullable();
            $table->string('create_by')->nullable();
            $table->string('create_date')->nullable();
            $table->string('ncv')->nullable();
            $table->decimal('jumlah_doc', 18, 4)->default(0);   // qty diterima (import doc)
            $table->decimal('total_in', 18, 4)->default(0);
            $table->decimal('out_req', 18, 4)->default(0);
            $table->decimal('total_req', 18, 4)->default(0);
            $table->decimal('out_prod', 18, 4)->default(0);
            $table->decimal('total_prod', 18, 4)->default(0);
            $table->decimal('out_doc', 18, 4)->default(0);
            $table->decimal('total_doc', 18, 4)->default(0);
            $table->integer('digit')->default(2);
            $table->decimal('total_order', 18, 4)->default(0);
            $table->decimal('total_harga', 18, 4)->default(0);
            $table->decimal('sisa', 18, 4)->default(0);
            $table->decimal('total_sisa', 18, 4)->default(0);
            $table->decimal('total_gudang', 18, 4)->default(0);
            $table->decimal('total_wip', 18, 4)->default(0);

            $table->timestamps();

            $table->index(['uraian', 'barang_code']);
            $table->index(['jenis_po', 'tgl_po']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_purchase_orders');
    }
};
