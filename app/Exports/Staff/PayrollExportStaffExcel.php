<?php

namespace App\Exports\Staff;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use App\Exports\Staff\PayrollDetailStaffSheet;
use App\Exports\Staff\PayrollOutDetailStaffSheet;
use App\Exports\Staff\PayrollSummaryStaffSheet;

class PayrollExportStaffExcel
{
    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function export($filePath)
    {
        $spreadsheet = new Spreadsheet();

        $this->addSheet($spreadsheet, 0, new PayrollDetailStaffSheet($this->run_id));
        $this->addSheet($spreadsheet, 1, new PayrollOutDetailStaffSheet($this->run_id));
        $this->addSheet($spreadsheet, 2, new PayrollMADetailStaffSheet($this->run_id));
        $this->addSheet($spreadsheet, 3, new PayrollSummaryStaffSheet($this->run_id));

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
