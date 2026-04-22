<?php

namespace App\Imports;

use App\Models\EmployeeLineAssignment;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

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
                !empty($row['start_date'])
                    ? Date::excelToDateTimeObject($row['start_date'])
                    : null,
                'period_id'   => $this->periodId,
            ],
            [
                'role' => $row['role'],
                'end_date' =>
                !empty($row['end_date'])
                    ? Date::excelToDateTimeObject($row['end_date'])
                    : null,
            ]
        );
    }
}
