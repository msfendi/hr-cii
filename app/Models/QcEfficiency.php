<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QcEfficiency extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_number',
        'period_id',
        'efficiency',
        'date',
        'days',
        'buyer',
    ];

    protected $casts = [
        'date' => 'date',
        'efficiency' => 'float'
    ];
}
