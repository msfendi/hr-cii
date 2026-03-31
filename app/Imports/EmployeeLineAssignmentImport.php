<?php

namespace App\Imports;

use App\Models\EmployeeLineAssignment;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

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
                'start_date' => $row['start_date'],
                'period_id'   => $this->periodId,
            ],
            [
                'role' => $row['role'],
                'end_date' => $row['end_date']
            ]
        );
    }
}
