<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PadEfficiency extends Model
{
    use HasFactory;

    protected $fillable = [
        'npk',
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
