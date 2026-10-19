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
        'role',
        'efficiency',
        'piece',
        'date',
        'tim'
    ];

    protected $casts = [
        'date' => 'date',
        'efficiency' => 'float',
        'tim' => 'integer'
    ];
}
