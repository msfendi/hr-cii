<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LineInsentifImport implements WithMultipleSheets
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function sheets(): array
    {
        return [
            'line_efficiencies' =>
            new LineEfficiencyImport($this->periodId),

            // 'employee_line_assignments' =>
            // new EmployeeLineAssignmentImport($this->periodId),
        ];
    }
}
