<?php

namespace App\Exports;

use App\Exports\NonSewing\PayrollDetailNonSewingSheet;
use App\Exports\NonSewing\PayrollOutDetailNonSewingSheet;
use App\Exports\Sewing\PayrollDetailSewingSheet;
use App\Exports\Sewing\PayrollOutDetailSewingSheet;
use App\Exports\Staff\PayrollDetailStaffSheet;
use App\Exports\Staff\PayrollOutDetailStaffSheet;
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
            new PayrollDetailStaffSheet($this->run_id), // sheet 2
            new PayrollDetailSewingSheet($this->run_id), // sheet 3
            new PayrollDetailNonSewingSheet($this->run_id), // sheet 4
            new PayrollOutDetailSheet($this->run_id), // sheet 5
            new PayrollOutDetailStaffSheet($this->run_id), // sheet 6
            new PayrollOutDetailSewingSheet($this->run_id), // sheet 7
            new PayrollOutDetailNonSewingSheet($this->run_id), // sheet 8
            new PayrollSummarySheet($this->run_id) // sheet 9

        ];
    }
}
