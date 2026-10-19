<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class QcInsentifTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new Sheets\QcEfficiencySheet(),
            new Sheets\EmployeeQcAssignmentSheet(),
        ];
    }
}
