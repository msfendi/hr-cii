<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    use HasFactory;
    protected $connection = 'cii';
    protected $table = 'AUDIT';
    protected $fillable = [
        'NPK',
        'NAMA_KARYAWAN',
        'TANGGAL',
        'SUBDIVISI',
        'KODE_BAGIAN',
        'JAM_PAGI',
        'JAM_SIANG',
        'JAM_MALAM',
        'STATUS'
    ];
}
