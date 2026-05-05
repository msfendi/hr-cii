<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CuttingInsentifImport implements WithMultipleSheets
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function sheets(): array
    {
        return [
            'cutting_efficiencies' => new CuttingEfficiencyImport($this->periodId),
            // 'employee_cutting_assignments' => new EmployeeCuttingAssignmentImport($this->periodId),
        ];
    }
}
