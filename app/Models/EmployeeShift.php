<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeShift extends Model
{
    protected $fillable = [
        'npk',
        'shift_id',
        'shift_date'
    ];

    protected $casts = [
        'shift_date' => 'date'
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
