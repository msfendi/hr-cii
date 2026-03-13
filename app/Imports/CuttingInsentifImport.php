<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CuttingInsentifImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'cutting_efficiencies' => new CuttingEfficiencyImport(),
            'employee_cutting_assignments' => new EmployeeCuttingAssignmentImport(),
        ];
    }
}
