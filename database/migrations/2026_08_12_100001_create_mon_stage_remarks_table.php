<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel remark per tahap produksi (Cutting/Sewing/Packing/QC) untuk
     * satu OCF -- diisi manual lewat import Excel dari dashboard
     * Rekonsiliasi OCF (rekonsiliasi_ocf blade).
     *
     * `ocf_no` bukan foreign key ke tabel manapun -- nilainya berupa kode
     * OCF hasil ekstraksi dari mon_rekonsiliasis.code_prod (pola teks bebas
     * "... OCF <kode>", lihat MonStageDataService::extractOcfCode()), jadi
     * disimpan sebagai teks bebas juga, sama seperti kolom OCF di tabel
     * mon_* lain (mon_orders.ocf_no, dst).
     *
     * `department_id`, walau namanya diakhiri "_id", SENGAJA disimpan
     * sebagai string nilai tetap (Cutting/Sewing/Packing/QC) -- BUKAN
     * foreign key ke tabel departments -- karena tidak ada tabel master
     * department terpisah untuk 4 tahap ini, sesuai permintaan.
     */
    public function up(): void
    {
        Schema::create('mon_stage_remarks', function (Blueprint $table) {
            $table->id();
            $table->string('ocf_no', 100)->index();
            $table->string('department_id', 50)->index();
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_stage_remarks');
    }
};
