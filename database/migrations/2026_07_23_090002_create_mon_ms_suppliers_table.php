<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master data supplier, sync dari smartit.ms_supplier (lihat perintah
     * `monitoring:sync-ms-supplier`). `negara_id` menyimpan kode negara
     * (mis. "ID", "VN", "HK") yang cocok ke mon_ms_negaras.negara_code --
     * dipakai untuk mapping mon_shipments.supplier_name -> negara pada
     * filter "per negara" di dashboard Rekonsiliasi.
     */
    public function up(): void
    {
        Schema::create('mon_ms_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code', 30)->unique();
            $table->string('supplier_name', 200)->nullable();
            $table->string('npwp', 50)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('pic', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('rekening', 100)->nullable();
            $table->string('category', 50)->nullable();
            $table->string('kode_sub_ap', 20)->nullable();
            $table->string('kode_sub_ar', 20)->nullable();
            $table->string('negara_id', 10)->nullable();
            $table->string('supplier_status', 30)->nullable();
            $table->string('create_by', 100)->nullable();
            $table->dateTime('create_date')->nullable();
            $table->string('modify_by', 100)->nullable();
            $table->dateTime('modify_date')->nullable();
            $table->tinyInteger('ppb')->nullable()->default(0);
            $table->string('tpb', 100)->nullable();
            $table->string('nib', 100)->nullable();
            $table->date('tgl_tpb')->nullable();
            $table->timestamps();

            $table->index('supplier_name');
            $table->index('negara_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_ms_suppliers');
    }
};
