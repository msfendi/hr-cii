<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonProdQc extends Model
{
    protected $table = 'mon_prod_qc';

    protected $fillable = [
        'code_prod',
        'department_id',
        'jumlah',
    ];

    protected $casts = [
        'jumlah' => 'integer',
    ];
}
