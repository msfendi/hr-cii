<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * NOTE: The original `LineInsentifImport` class wasn't among the files you
 * shared, so this is inferred from LineInsentifMasterController::import()
 * (`Excel::import(new LineInsentifImport($request->period_id), $file)`)
 * and from LineInsentifTemplateExport's sheet order
 * (0 => Efficiency, 1 => Assignment). Please diff this against the real
 * LineInsentifImport if the sheet order/keys differ.
 */
class QcInsentifImport implements WithMultipleSheets
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function sheets(): array
    {
        return [
            0 => new QcEfficiencyImport($this->periodId),
            1 => new EmployeeQcAssignmentImport($this->periodId),
        ];
    }
}
