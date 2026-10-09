<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveReason extends Model
{
    use HasFactory;

    protected $table = 'leave_reasons';

    protected $fillable = [
        'leave_type_id',
        'reason',
    ];

    public function leaveType()
    {
        return $this->belongsTo(LeaveTypes::class, 'leave_type_id');
    }
}
