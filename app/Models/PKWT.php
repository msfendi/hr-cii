<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PKWT extends Model
{
    use HasFactory;
    protected $connection = 'cii';
    protected $table = 'PKWT';
    protected $fillable = [
        'NPK',
        'NAMA',
        'JK',
        'TGLLAHIR',
        'PDDK',
        'AGAMA',
        'TMK',
        'USIA',
        'TKK',
        'BAGIAN',
        'ALAMAT',
        'KABUPATEN',
        'KTP',
        'NO_KK',
        'IBU',
        'HP',
        'STATUS',
        'TANGGUNGAN',
        'KETERANGAN',
        'TUTUPBUKU',
        'TMPTLAHIR',
        'NOREK',
        'JURUSAN',
        'FASKES'
    ];
}
