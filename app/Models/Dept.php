<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dept extends Model
{
    use HasFactory;

    protected $connection = 'cii';
    protected $table = 'DEPT';
    protected $primaryKey = 'ID_DEPT';
    public $timestamps = false;

    protected $fillable = [
        'DEPARTEMENT',
        'IS_SEWING',
        'SECTION',
    ];
}
