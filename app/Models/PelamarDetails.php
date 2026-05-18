<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PelamarDetails extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_pelamar',
        'nomor_sim',
        'warga_negara',
        'ikut_kb',
        'bakat_hobby',
        'mode_transportasi',
        'jabatan',
        'department',
        'bpjs_tk',
        'bpjs_kes',
        'alamat_skrg',
        'kabupaten_kota_skrg',
        'status_domisili',
        'nama_ktk_darurat',
        'hubungan',
        'no_telp_darurat',
        'pengalaman_kerja',
        'data_ayah',
        'data_ibu',
        'saudara_kandung',
        'data_anak',
        'riwayat_pendidikan',
        'motivasi',
        'kegiatan_ekstra',
        'file_surat_lamaran',
        'file_cv',
        'file_ktp',
        'file_kk',
        'file_ijasah',
        'file_akta_kelahiran',
        'file_skck',
        'file_surat_sehat',
        'file_pas_foto',
        'status_apply',
        'is_test',
        'tgl_test',
        'is_kesehatan',
        'tgl_kesehatan',
        'is_interview',
        'tgl_interview',
        'tgl_diterima',
    ];

    public function pelamar()
    {
        return $this->belongsTo(Pelamar::class, 'id_pelamar', 'id');
    }
}
