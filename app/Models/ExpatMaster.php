<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpatMaster extends Model
{
    protected $table = 'expat_master';

    protected $fillable = [
        'npk',
        'name',
        'position',
        'joining_date',
        'end_date',
        'passport_number',
        'passport_expiry',
        'kitas_expiry',
        'rptka_expiry',
        'merp_expiry',
        'house_address',
        'house_startdate',
        'lease_enddate'
    ];
}
