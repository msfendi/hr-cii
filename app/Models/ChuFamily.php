<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChuFamily extends Model
{
    protected $table = 'chu_family';

    protected $fillable = [
        'name',
        'gender',
        'place',
        'birth_date',
        'nationality',
        'passport_number',
        'passport_expiry',
        'visa_type',
        'visa_expiry',
        'kitas_expiry',
        'rptka_expiry',
    ];

    protected $dates = [
        'birth_date',
        'passport_expiry',
        'visa_expiry',
        'kitas_expiry',
        'rptka_expiry',
    ];
}
