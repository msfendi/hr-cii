<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsentifMaster extends Model
{
    use HasFactory;

    protected $table = 'insentif_masters';

    protected $fillable = [
        'npk',
        'type',
        'date',
        'efficiency',
        'piece'
    ];
}
