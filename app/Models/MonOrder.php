<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonOrder extends Model
{
    protected $table = 'mon_orders';

    protected $guarded = ['id'];

    protected $casts = [
        'qty_ord' => 'decimal:2',
        'planned_qty' => 'decimal:2',
        'production_delivery' => 'date',
        'buyer_delivery' => 'date',
        'prod_start' => 'date',
        'prod_end' => 'date',
        'sewing_start_date' => 'date',
    ];
}
