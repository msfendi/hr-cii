<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class HeatInsentifImport implements WithMultipleSheets
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function sheets(): array
    {
        return [
            'pad_efficiencies' => new HeatEfficiencyImport($this->periodId),
            'employee_pad_assignments' => new EmployeeHeatAssignmentImport($this->periodId),
        ];
    }
}
