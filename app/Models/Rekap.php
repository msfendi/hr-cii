<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rekap extends Model
{
    use HasFactory;

    protected $connection = 'cii';
    protected $table = 'REKAP';

    protected $fillable = [
        'PKWT',
        'MAGANG',
        'BULAN',
        'TAHUN',
    ];
}
