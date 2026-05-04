<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CuttingInsentifTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new Sheets\CuttingSheet(),
            new Sheets\CuttingEfficiencySheet(),
            // new Sheets\EmployeeCuttingAssignmentSheet(),
        ];
    }
}
