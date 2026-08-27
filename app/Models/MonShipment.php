<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonShipment extends Model
{
    protected $table = 'mon_shipments';

    protected $guarded = ['id'];

    protected $casts = [
        'tgl_doc'       => 'date',
        'tgl_bukti'     => 'date',
        'acc_tgl'       => 'datetime',
        'jumlah_doc'    => 'decimal:4',
        'nilai_barang'  => 'decimal:4',
        'nilai_fob'     => 'decimal:4',
        'jumlah_aktual' => 'decimal:4',
        'berat'         => 'decimal:4',
        'jumlah_barang' => 'decimal:4',
    ];
}
