<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jumlah hasil QC per department (Cutting/Sewing/Packing/QC) untuk satu
     * code_prod -- diisi manual lewat import Excel dari dashboard
     * Rekonsiliasi OCF (rekonsiliasi_ocf blade).
     *
     * `code_prod` di sini nilainya SAMA formatnya dengan `ocf_no` di
     * mon_stage_remarks (hasil ekstraksi dari mon_rekonsiliasis.code_prod,
     * pola "... OCF <kode>" -> ambil <kode>-nya saja), disimpan sebagai
     * teks bebas.
     *
     * `department_id` sama seperti mon_stage_remarks: string nilai tetap
     * (Cutting/Sewing/Packing/QC), bukan foreign key.
     */
    public function up(): void
    {
        Schema::create('mon_prod_qc', function (Blueprint $table) {
            $table->id();
            $table->string('code_prod', 100)->index();
            $table->string('department_id', 50)->index();
            $table->integer('jumlah')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_prod_qc');
    }
};
