<?php

namespace App\Exports\NonSewing;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use App\Exports\NonSewing\PayrollDetailNonSewingSheet;
use App\Exports\NonSewing\PayrollOutDetailNonSewingSheet;
use App\Exports\NonSewing\PayrollSummaryNonSewingSheet;

class PayrollExportNonSewingExcel
{
    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function export($filePath)
    {
        $spreadsheet = new Spreadsheet();

        $this->addSheet($spreadsheet, 0, new PayrollDetailNonSewingSheet($this->run_id));
        $this->addSheet($spreadsheet, 1, new PayrollOutDetailNonSewingSheet($this->run_id));
        $this->addSheet($spreadsheet, 2, new PayrollMADetailNonSewingSheet($this->run_id));
        $this->addSheet($spreadsheet, 3, new PayrollSummaryNonSewingSheet($this->run_id));

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
