<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalances extends Model
{
    use HasFactory;

    protected $table = 'leave_balances';

    protected $fillable = [
        'NPK',
        'leave_type_id',
        'year',
        'remained_days',
        'used_days',
    ];
}