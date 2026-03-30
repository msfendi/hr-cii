<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuttingEfficiency extends Model
{
    use HasFactory;

    protected $fillable = [
        'npk',
        'period_id',
        'efficiency',
        'date'
    ];

    protected $casts = [
        'date' => 'date',
        'efficiency' => 'float'
    ];
}
