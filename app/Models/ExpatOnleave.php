<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpatOnleave extends Model
{
    protected $table = 'expat_onleave';

    protected $fillable = [
        'npk',
        'onleave_start',
        'onleave_end',
        'leave_type',
        'component',
        'amount',
        'transactions_date',
        'remark'
    ];

    protected $casts = [
        'component' => 'array',
        'amount' => 'array',
        'transactions_date' => 'array',
    ];
}
