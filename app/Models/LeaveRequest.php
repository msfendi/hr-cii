<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $table = 'leave_requests';

    protected $fillable = [
        'NPK',
        'leave_type_id',
        'reason',
        'start_date',
        'end_date',
        'total_days',
        'approval_id',
        'approval_level',
        'approval_progress',
        'approval_date',
        'status',
        'token',
    ];

    public function leaveType()
    {
        return $this->belongsTo(LeaveTypes::class, 'leave_type_id');
    }

    public function employee()
    {
        return $this->belongsTo(Biodata::class, 'NPK');
    }
}
