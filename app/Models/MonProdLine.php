<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonProdLine extends Model
{
    protected $table = 'mon_prod_lines';

    protected $guarded = ['id'];

    protected $casts = [
        'tgl_produksi' => 'date',
        'jumlah'       => 'decimal:4',
        'total_nilai'  => 'decimal:4',
    ];
}
