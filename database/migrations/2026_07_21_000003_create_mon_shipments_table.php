<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mon_shipments', function (Blueprint $table) {
            $table->id();

            // Kunci unik untuk upsert dari smartit. Query get_pengeluaran_bc.txt
            // (UNION ALL 2 cabang: dokumen ekspor biasa + retur) tidak punya satu
            // kolom id tunggal yang unik per baris (hasilnya sudah di-GROUP BY
            // banyak kolom), jadi dibuat hash sintetis dari kombinasi kolom yang
            // membedakan tiap baris: doc_id + barang_code + dt_so_id + satuan_doc
            // + keterangan. Lihat SyncShipmentFromSmartit::rowKey().
            $table->string('row_key', 64)->unique();

            $table->string('hs_code')->nullable();
            $table->string('doc_id')->nullable()->index();
            $table->string('jenis_doc')->nullable()->index();
            $table->string('no_aju')->nullable();
            $table->string('no_doc')->nullable();
            $table->date('tgl_doc')->nullable()->index();
            $table->string('no_bukti')->nullable();
            $table->date('tgl_bukti')->nullable()->index();

            // 'Retur' atau value_order dari ms_order (jenis PS: SO/Subkon/dst)
            $table->string('jenis_ps')->nullable()->index();
            $table->string('no_ps')->nullable(); // no_so / no_po

            $table->string('no_invoice')->nullable();
            $table->string('file_number')->nullable();
            $table->string('supplier_name')->nullable();

            $table->string('barang_code')->nullable()->index();
            $table->string('barang_name')->nullable();
            $table->string('barang_category')->nullable();
            $table->string('satuan_doc')->nullable();

            $table->decimal('jumlah_doc', 18, 4)->default(0);
            $table->string('valas')->nullable();
            $table->decimal('nilai_barang', 18, 4)->default(0);
            $table->decimal('nilai_fob', 18, 4)->default(0);
            $table->decimal('jumlah_aktual', 18, 4)->default(0);

            $table->text('keterangan')->nullable();
            $table->string('ps_id')->nullable()->index(); // h.so_id

            $table->string('spesifikasi')->nullable();

            // Dipakai sebagai "CPO" -- sama seperti mon_orders / mon_boms /
            // mon_purchase_orders, supaya sheet SHIPMENT bisa di-scope oleh
            // filter CPO yang sama dengan sheet lain.
            $table->string('uraian')->nullable()->index();

            $table->decimal('berat', 18, 4)->nullable();
            $table->decimal('jumlah_barang', 18, 4)->default(0);
            $table->string('satuan_order')->nullable();
            $table->string('satuan_code')->nullable();

            // dt_so_id (cabang ekspor biasa) atau dt_po_id (cabang retur) --
            // di query aslinya dua-duanya keluar dengan nama kolom "dt_so_id"
            // karena UNION ALL memakai nama kolom dari SELECT pertama.
            $table->string('dt_so_id')->nullable()->index();

            $table->string('acc_bukti')->nullable();
            $table->dateTime('acc_tgl')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_shipments');
    }
};
