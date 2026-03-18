<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LineInsentifImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'line_efficiencies' => new LineEfficiencyImport(),
            'employee_line_assignments' => new EmployeeLineAssignmentImport(),
        ];
    }
}
