<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecruitmentPosition extends Model
{
    use HasFactory;

    protected $fillable = ['position', 'dept', 'is_aktif'];
}
