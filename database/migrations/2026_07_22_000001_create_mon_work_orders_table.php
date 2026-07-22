<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mon_work_orders', function (Blueprint $table) {
            $table->id();

            // ================================================================
            // Kunci unik untuk upsert. get_ppic_bom.txt menghasilkan satu baris
            // per komponen BOM (join prd_plan_hd + prd_bom + ms_barang), dan
            // m.bom_id SUDAH unik per baris itu -- jadi dipakai langsung sebagai
            // wo_id, tidak perlu lagi dirakit manual (prod_id+barang_code+dept)
            // ataupun pakai DISTINCT untuk menghindari duplikat.
            // Lihat SyncWorkOrderFromSmartit::handle().
            // ================================================================
            $table->string('wo_id'); // = m.bom_id dari smartit

            // ============================================================
            // dari prd_plan_hd (h) -- get_ppic_bom.txt: h.*
            // ============================================================
            $table->string('prod_id')->nullable()->index();
            $table->string('code_prod')->nullable();             // h.code_prod (deskripsi CPO/style)
            $table->string('product_code')->nullable()->index(); // h.barang_code (kode produk/style)
            $table->decimal('jumlah_prod', 18, 4)->default(0);   // h.jumlah_prod
            $table->date('tgl_prod')->nullable();                 // h.tgl_prod
            $table->date('tgl_doc')->nullable();                  // h.tgl_doc
            $table->decimal('target_hari', 18, 2)->nullable();   // h.target_hari
            $table->text('note')->nullable();                     // h.note
            $table->string('warehouse_id')->nullable();          // h.warehouse_id
            $table->string('status')->nullable()->index();       // h.status ('Unfinish' dst)
            $table->string('plan_create_by')->nullable();        // h.create_by
            $table->timestamp('plan_create_date')->nullable();   // h.create_date
            $table->string('plan_modify_by')->nullable();        // h.modify_by
            $table->timestamp('plan_modify_date')->nullable();   // h.modify_date
            $table->string('flag_type')->nullable();             // h.flag_type

            // pb.barang_name AS product_name
            $table->string('product_name')->nullable();

            // ============================================================
            // dari prd_bom (m) -- get_ppic_bom.txt: m.* (komponen/material)
            // ============================================================
            $table->string('bom_prod_id')->nullable();           // m.prod_id (sama dgn h.prod_id)
            $table->string('barang_code')->nullable()->index();  // m.barang_code (kode material)
            $table->string('uraian')->nullable();                 // m.uraian
            $table->string('spesifikasi')->nullable();           // m.spesifikasi
            $table->string('departemen')->nullable();            // m.departemen
            $table->decimal('cons', 18, 6)->default(0);          // m.cons
            $table->decimal('scrap_percent', 18, 4)->default(0); // m.scrap_percent
            $table->string('bom_create_by')->nullable();         // m.create_by
            $table->timestamp('bom_create_date')->nullable();    // m.create_date
            $table->string('bom_modify_by')->nullable();         // m.modify_by
            $table->timestamp('bom_modify_date')->nullable();    // m.modify_date
            $table->string('auto_create')->nullable();           // m.auto_create ('yes'/lainnya)
            $table->string('komponen')->nullable();              // m.komponen
            $table->decimal('bom_jumlah_prod', 18, 4)->default(0); // m.jumlah_prod

            // ============================================================
            // dari ms_barang (b) -- data material
            // ============================================================
            $table->string('barang_name')->nullable();           // b.barang_name
            $table->string('satuan_code')->nullable()->index();  // b.satuan_code

            // ============================================================
            // hasil perhitungan di query get_ppic_bom.txt
            // ============================================================
            // request     : SUM(prd_request_dt.jumlah) per prod_id+departemen+barang_code (NEED qty)
            // total       : cons * jumlah_prod (auto_create='yes') atau request (selain itu)
            // actual_cons : request / jumlah_prod
            $table->decimal('request', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->decimal('actual_cons', 18, 6)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_work_orders');
    }
};
