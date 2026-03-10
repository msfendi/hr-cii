<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceFinger extends Model
{
    use HasFactory;
    protected $table = 'att_log';
    protected $connection = 'fingerspot';
    // protected $fillable = [
    //     'sn'
    // ]; 
}
