<?php

namespace App\Imports;

use App\Models\EmployeeLineAssignment;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Str;

class EmployeeLineAssignmentImport implements ToModel, WithHeadingRow
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function model(array $row)
    {
        return EmployeeLineAssignment::updateOrCreate(
            [
                'npk' => $row['npk'],
                'line_number' => $row['line_number'],
                'start_date' =>
                !empty($row['date'])
                    ? Date::excelToDateTimeObject($row['date'])
                    : null,
                'period_id'   => $this->periodId,
            ],
            [
                'name' => $row['name'],
                'role' => Str::lower($row['role']),
                'end_date' =>
                !empty($row['date'])
                    ? Date::excelToDateTimeObject($row['date'])
                    : null,
                'work_hours' => $row['work_hours'],
            ]
        );
    }
}
