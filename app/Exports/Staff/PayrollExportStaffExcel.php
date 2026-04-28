<?php

namespace App\Exports\Staff;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PayrollExportStaffExcel implements WithMultipleSheets
{
    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function sheets(): array
    {
        return [
            new PayrollDetailStaffSheet($this->run_id),
            new PayrollOutDetailStaffSheet($this->run_id),
            new PayrollSummaryStaffSheet($this->run_id)
        ];
    }
}
