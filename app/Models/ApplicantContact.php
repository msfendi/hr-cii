<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantContact extends Model
{
    protected $fillable = [
        'phone',
        'name',
        'position'
    ];
}
