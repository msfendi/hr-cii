<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PadEfficiency extends Model
{
    use HasFactory;

    protected $fillable = [
        'npk',
        'period_id',
        'role',
        'efficiency',
        'piece',
        'tim',
        'date'
    ];

    protected $casts = [
        'date' => 'date',
        'efficiency' => 'float'
    ];
}
