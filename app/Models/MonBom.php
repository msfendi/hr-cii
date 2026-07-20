<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonBom extends Model
{
    protected $table = 'mon_boms';

    protected $guarded = ['id'];

    protected $casts = [
        'tgl_prod' => 'date',
        'cons' => 'decimal:6',
        'scrap_percent' => 'decimal:4',
    ];
}
