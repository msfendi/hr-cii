<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LineInsentifTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new Sheets\LineSheet(),
            new Sheets\LineEfficiencySheet(),
            new Sheets\EmployeeLineAssignmentSheet(),
        ];
    }
}
