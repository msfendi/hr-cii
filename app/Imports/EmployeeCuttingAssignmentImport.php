<?php

namespace App\Imports;

use App\Models\EmployeeCuttingAssignment;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeeCuttingAssignmentImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return EmployeeCuttingAssignment::updateOrCreate(
            [
                'npk' => $row['npk'],
                'start_date' => $row['start_date']
            ],
            [
                'role' => $row['role'],
                'end_date' => $row['end_date']
            ]
        );
    }
}
