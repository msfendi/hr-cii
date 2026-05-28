<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResepObat extends Model
{
    use HasFactory;

    protected $connection = 'cii';
    protected $table = 'resep_obats';
    protected $fillable = [
        'kunjungan_id',
        'keterangan_obat',
        'qty',
    ];
}
