<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeatEfficiency extends Model
{
    use HasFactory;

    protected $fillable = [
        'npk',
        'period_id',
        'dept',
        'efficiency',
        'piece',
        'date'
    ];

    protected $casts = [
        'date' => 'date',
        'efficiency' => 'float'
    ];
}
