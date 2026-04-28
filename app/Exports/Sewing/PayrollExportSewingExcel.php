<?php

namespace App\Exports\Sewing;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PayrollExportSewingExcel implements WithMultipleSheets
{
    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function sheets(): array
    {
        return [
            new PayrollDetailSewingSheet($this->run_id),
            new PayrollOutDetailSewingSheet($this->run_id),
            new PayrollSummarySewingSheet($this->run_id)
        ];
    }
}
