<?php

namespace App\Imports;

use App\Models\EmployeePadAssignment;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeePadAssignmentImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return EmployeePadAssignment::updateOrCreate(
            [
                'npk' => $row['npk'],
                'dept' => $row['dept'],
                'start_date' => $row['start_date']
            ],
            [
                'role' => $row['role'],
                'end_date' => $row['end_date']
            ]
        );
    }
}
