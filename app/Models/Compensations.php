<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compensations extends Model
{
    protected $table = 'compensations';

    protected $fillable = [
        'cutoff_date',
        'total_employee',
        'total_amount',
        'file_pdf',
        'file_csv',
        'progress',
        'status',
        'is_closed',
    ];

    protected $casts = [
        'cutoff_date' => 'date',
        'amount' => 'decimal:2',
    ];
}
