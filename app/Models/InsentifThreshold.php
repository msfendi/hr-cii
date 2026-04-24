<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsentifThreshold extends Model
{
    protected $table = 'insentif_thresholds';

    protected $fillable = [
        'insentif_type',
        'days',
        'minimum',
        'type'
    ];
}
