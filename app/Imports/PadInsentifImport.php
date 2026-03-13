<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PadInsentifImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'pad_efficiencies' => new PadEfficiencyImport(),
            'employee_pad_assignments' => new EmployeePadAssignmentImport(),
        ];
    }
}
