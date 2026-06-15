<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class HeatInsentifTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            // new Sheets\HeatSheet(),
            new Sheets\HeatEfficiencySheet(),
            // new Sheets\EmployeeHeatAssignmentSheet(),
        ];
    }
}
