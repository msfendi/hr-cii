<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollPeriod extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_closed'
    ];

    public function runs()
    {
        return $this->hasMany(PayrollRun::class, 'period_id');
    }
}
