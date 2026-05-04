<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineEfficiency extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_number',
        'period_id',
        'efficiency',
        'date',
        'days'
    ];

    protected $casts = [
        'date' => 'date',
        'efficiency' => 'float'
    ];
}
