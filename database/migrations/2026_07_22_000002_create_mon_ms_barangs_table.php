<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mon_ms_barangs', function (Blueprint $table) {
            $table->id();

            // ================================================================
            // Master data barang dari smartit (tabel ms_barang). Disinkron
            // penuh (upsert by barang_code) lewat SyncMsBarangFromSmartit,
            // dipakai untuk mengelompokkan material/produk berdasarkan
            // `barang_category` di MonitoringRekonsiliasiService, mis.:
            //  - Fabric    : Bahan Baku Lokal & Bahan Baku Import
            //  - Aksesoris : Bahan Penolong
            //  - Packing   : Packaging
            //  - Cutting (WIP)    : Bahan Setengah Jadi
            //  - Sewing/Packing/Warehouse/Shipment (produk jadi) : Barang Jadi
            // ================================================================
            $table->string('barang_code')->unique();
            $table->string('barang_name')->nullable();
            $table->string('satuan_code')->nullable();
            $table->string('barang_category')->nullable()->index();
            $table->string('header_code')->nullable();
            $table->string('hs_code')->nullable();
            $table->string('kode_sub_ap')->nullable();
            $table->string('kode_sub_ar')->nullable();
            $table->string('barang_status')->nullable()->index();
            $table->string('create_by')->nullable();
            $table->timestamp('create_date')->nullable();
            $table->string('modify_by')->nullable();
            $table->timestamp('modify_date')->nullable();
            $table->string('satuan_hs')->nullable();
            $table->decimal('konversi', 18, 4)->nullable();
            $table->string('kode')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_ms_barangs');
    }
};
