<?php

namespace App\Exports\NonStaff;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use App\Exports\NonStaff\PayrollDetailNonStaffSheet;
use App\Exports\NonStaff\PayrollOutDetailNonStaffSheet;
use App\Exports\NonStaff\PayrollSummaryNonStaffSheet;

class PayrollExportNonStaffExcel
{
    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function export($filePath)
    {
        $spreadsheet = new Spreadsheet();

        $this->addSheet($spreadsheet, 0, new PayrollDetailNonStaffSheet($this->run_id));
        $this->addSheet($spreadsheet, 1, new PayrollOutDetailNonStaffSheet($this->run_id));
        $this->addSheet($spreadsheet, 2, new PayrollMADetailNonStaffSheet($this->run_id));
        $this->addSheet($spreadsheet, 3, new PayrollSummaryNonStaffSheet($this->run_id));

        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
    }

    private function addSheet(Spreadsheet $spreadsheet, int $index, $sheetClass)
    {
        $sheetClass->exportToSheet($spreadsheet, $index);
    }
}
