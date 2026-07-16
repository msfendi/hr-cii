<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pelamar extends Model
{
    use HasFactory;
    protected $connection = 'cii';
    protected $table = 'PELAMAR';
    public $timestamps = false;

    protected $fillable = [
        'NPK',
        'NAMA',
        'JENIS_KELAMIN',
        'TMPT_LAHIR',
        'TGL_LAHIR',
        'TMK',
        'UMUR',
        'ALAMAT_LENGKAP',
        'KABUPATEN',
        'ALAMAT_DOMISILI',
        'PENDIDIKAN',
        'NAMA_SEKOLAH',
        'KABUPATEN_SEKOLAH',
        'JURUSAN',
        'TINGGI_BADAN',
        'BERAT_BADAN',
        'HP',
        'AGAMA',
        'NIK',
        'NO_KK',
        'IBU',
        'STATUS',
        'TANGGUNGAN',
        'IS_KONTRAK'
    ];

    public function details()
    {
        return $this->hasOne(PelamarDetails::class, 'id_pelamar', 'id');
    }
}
