<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use App\Exports\NonSewing\PayrollDetailNonSewingSheet;
use App\Exports\NonSewing\PayrollMADetailNonSewingSheet;
use App\Exports\NonSewing\PayrollOutDetailNonSewingSheet;
use App\Exports\Sewing\PayrollDetailSewingSheet;
use App\Exports\Sewing\PayrollMADetailSewingSheet;
use App\Exports\Sewing\PayrollOutDetailSewingSheet;
use App\Exports\Staff\PayrollDetailStaffSheet;
use App\Exports\Staff\PayrollMADetailStaffSheet;
use App\Exports\Staff\PayrollOutDetailStaffSheet;

class PayrollExportExcel
{
    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function export($filePath)
    {
        $spreadsheet = new Spreadsheet();

        $this->addSheet($spreadsheet, 0, new PayrollDetailSheet($this->run_id));
        $this->addSheet($spreadsheet, 1, new PayrollDetailStaffSheet($this->run_id));
        $this->addSheet($spreadsheet, 2, new PayrollDetailSewingSheet($this->run_id));
        $this->addSheet($spreadsheet, 3, new PayrollDetailNonSewingSheet($this->run_id));
        $this->addSheet($spreadsheet, 4, new PayrollOutDetailSheet($this->run_id));
        $this->addSheet($spreadsheet, 5, new PayrollOutDetailStaffSheet($this->run_id));
        $this->addSheet($spreadsheet, 6, new PayrollOutDetailSewingSheet($this->run_id));
        $this->addSheet($spreadsheet, 7, new PayrollOutDetailNonSewingSheet($this->run_id));
        $this->addSheet($spreadsheet, 8, new PayrollMADetailSheet($this->run_id));
        $this->addSheet($spreadsheet, 9, new PayrollMADetailStaffSheet($this->run_id));
        $this->addSheet($spreadsheet, 10, new PayrollMADetailSewingSheet($this->run_id));
        $this->addSheet($spreadsheet, 11, new PayrollMADetailNonSewingSheet($this->run_id));
        $this->addSheet($spreadsheet, 12, new PayrollSummarySheet($this->run_id));

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
