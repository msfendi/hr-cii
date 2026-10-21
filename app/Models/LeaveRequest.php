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
        'void',
        'attach_files',
        'custom_dates',
    ];

    protected $casts = [
        'attach_files' => 'array',
    ];

    /**
     * Daftar tanggal cuti efektif.
     *
     * - Bila pengajuan memakai tanggal bebas (custom_dates), mengembalikan daftar
     *   tanggal tersebut (tidak harus berurutan).
     * - Bila tidak, mengembalikan seluruh tanggal dalam rentang start_date..end_date.
     *
     * @return array<int,\Carbon\Carbon>
     */
    public function leaveDates(): array
    {
        if (!empty($this->custom_dates)) {
            $decoded = json_decode($this->custom_dates, true);
            if (is_array($decoded) && !empty($decoded)) {
                return array_map(fn ($d) => \Carbon\Carbon::parse($d), $decoded);
            }
        }

        $dates = [];
        $cursor = \Carbon\Carbon::parse($this->start_date);
        $end = \Carbon\Carbon::parse($this->end_date);

        while ($cursor->lte($end)) {
            $dates[] = $cursor->copy();
            $cursor->addDay();
        }

        return $dates;
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveTypes::class, 'leave_type_id');
    }

    public function employee()
    {
        return $this->belongsTo(Biodata::class, 'NPK');
    }
}
