<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonSubkon extends Model
{
    protected $table = 'mon_subkons';

    protected $guarded = ['id'];

    protected $casts = [
        'tgl_order'           => 'date',
        'qty_material_order'  => 'decimal:4',
        'qty_result_order'    => 'decimal:4',
        'qty_material_aktual' => 'decimal:4',
        'qty_result_aktual'   => 'decimal:4',
    ];
}
