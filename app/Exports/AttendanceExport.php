<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Sheets\AttendanceFingerExport;
use App\Exports\Sheets\AttendanceLateExport;
use App\Exports\Sheets\AttendanceNotFingerExport;

class AttendanceExport implements WithMultipleSheets
{
    protected $date;

    public function __construct($date)
    {
        $this->date = $date;
    }

    public function sheets(): array
    {
        return [
            new AttendanceFingerExport($this->date),
            new AttendanceLateExport($this->date),
            new AttendanceNotFingerExport($this->date),
        ];
    }
}
