<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonRekonsiliasi extends Model
{
    protected $table = 'mon_rekonsiliasis';

    protected $guarded = ['id'];

    protected $casts = [
        'tgl_po'         => 'date',
        'tgl_pengiriman' => 'date',
        'jumlah_order'   => 'decimal:4',
        'jumlah_doc'     => 'decimal:4',
        'out_req'        => 'decimal:4',
        'out_prod'       => 'decimal:4',
        'sisa'           => 'decimal:4',
        'saldo_wip'      => 'decimal:4',
        'out_doc'        => 'decimal:4',
        'harga_total'    => 'decimal:4',
        'saldo_gudang'   => 'decimal:4',
    ];
}
