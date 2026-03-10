<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PayrollExportExcel implements WithMultipleSheets
{
    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function sheets(): array
    {
        return [

            new PayrollDetailSheet($this->run_id), // sheet 1
            new PayrollSummarySheet($this->run_id) // sheet 2

        ];
    }
}
