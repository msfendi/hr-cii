<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Outsource extends Model
{
    // Menggunakan koneksi default aplikasi (bukan cii/canteen).
    // Ganti $connection jika ternyata tabel outsources ada di database lain.

    protected $connection = 'canteen';

    protected $table = 'outsources';

    public $timestamps = true;

    protected $fillable = [
        'NPK',
        'NAMA',
        'VENDOR',
        'void',
    ];
}
