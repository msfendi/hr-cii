<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BpjsException extends Model
{
    protected $table = 'bpjs_exceptions';

    protected $fillable = [
        'npk',
        'component',
        'percentage',
    ];
}
