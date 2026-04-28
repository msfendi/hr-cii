<?php

namespace App\Exports\NonSewing;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PayrollExportNonSewingExcel implements WithMultipleSheets
{
    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function sheets(): array
    {
        return [

            new PayrollDetailNonSewingSheet($this->run_id),
            new PayrollOutDetailNonSewingSheet($this->run_id),
            new PayrollSummaryNonSewingSheet($this->run_id)

        ];
    }
}
