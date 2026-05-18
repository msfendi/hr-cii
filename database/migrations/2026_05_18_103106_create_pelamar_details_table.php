<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pelamar_details', function (Blueprint $table) {
            $table->id();
            $table->string('id_pelamar')->nullable();
            $table->string('nomor_sim')->nullable();
            $table->string('warga_negara')->nullable();
            $table->boolean('ikut_kb')->nullable();
            $table->string('bakat_hobby')->nullable();
            $table->string('mode_transportasi')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('department')->nullable();
            $table->string('bpjs_tk')->nullable();
            $table->string('bpjs_kes')->nullable();
            $table->string('alamat_skrg')->nullable();
            $table->string('kabupaten_kota_skrg')->nullable();
            $table->string('status_domisili')->nullable();
            $table->string('nama_ktk_darurat')->nullable();
            $table->string('hubungan')->nullable();
            $table->string('no_telp_darurat')->nullable();
            $table->string('motivasi')->nullable();
            $table->string('kegiatan_ekstra')->nullable();
            $table->json('pengalaman_kerja')->nullable();
            $table->json('data_ayah')->nullable();
            $table->json('data_ibu')->nullable();
            $table->json('saudara_kandung')->nullable();
            $table->json('data_anak')->nullable();
            $table->json('riwayat_pendidikan')->nullable();
            $table->string('file_surat_lamaran')->nullable();
            $table->string('file_cv')->nullable();
            $table->string('file_ktp')->nullable();
            $table->string('file_kk')->nullable();
            $table->string('file_ijasah')->nullable();
            $table->string('file_akta_kelahiran')->nullable();
            $table->string('file_skck')->nullable();
            $table->string('file_surat_sehat')->nullable();
            $table->string('file_pas_foto')->nullable();
            $table->string('status_apply')->nullable();
            $table->string('is_test')->nullable();
            $table->date('tgl_test')->nullable();
            $table->string('is_kesehatan')->nullable();
            $table->date('tgl_kesehatan')->nullable();
            $table->string('is_interview')->nullable();
            $table->date('tgl_interview')->nullable();
            $table->date('tgl_diterima')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pelamar_details');
    }
};
