<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompensationDetails extends Model
{
    protected $table = 'compensation_details';

    protected $fillable = [
        'npk',
        'id_dept',
        'contract_id',
        'cutoff_date',
        'amount',
        'status',
        'is_active',
    ];

    protected $casts = [
        'cutoff_date' => 'date',
        'amount' => 'decimal:2',
    ];

    // public function employee()
    // {
    //     return $this->belongsTo(EmployeeContract::class, 'contract_id');
    // }
}
