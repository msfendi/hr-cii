<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpatCost extends Model
{
    protected $table = 'expat_cost';

    protected $fillable = [
        'npk',
        'component',
        'amount',
        'transactions_date',
        'remark'
    ];
}
