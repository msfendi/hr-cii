<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollAdjusment extends Model
{
    protected $table = 'payroll_adjusments';

    protected $fillable = [
        'npk',
        'period_id',
        'adjusment'
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATION
    |--------------------------------------------------------------------------
    */

    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }
}
