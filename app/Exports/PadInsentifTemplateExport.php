<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PadInsentifTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            // new Sheets\PadSheet(),
            new Sheets\PadEfficiencySheet(),
            // new Sheets\EmployeePadAssignmentSheet(),
        ];
    }
}
