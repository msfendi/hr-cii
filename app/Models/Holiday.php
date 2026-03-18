<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = [
        'holiday_date',
        'name',
        'is_national'
    ];

    protected $casts = [
        'holiday_date' => 'date'
    ];
}