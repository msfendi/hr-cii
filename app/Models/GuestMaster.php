<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestMaster extends Model
{
    protected $table = 'guest_masters';

    protected $fillable = [
        'name',
        'gender',
        'place',
        'date_of_birth',
        'nationality',
        'passport_no',
        'remark',
        'issue_date',
        'must_used_date',
        'arrival_date',
        'visa_expiry',
        'status'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'issue_date' => 'date',
        'must_used_date' => 'date',
        'arrival_date' => 'date',
        'visa_expiry' => 'date',
    ];
}