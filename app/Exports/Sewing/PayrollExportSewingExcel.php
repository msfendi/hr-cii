<?php

namespace App\Exports\Sewing;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use App\Exports\Sewing\PayrollDetailSewingSheet;
use App\Exports\Sewing\PayrollOutDetailSewingSheet;
use App\Exports\Sewing\PayrollSummarySewingSheet;

class PayrollExportSewingExcel
{
    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function export($filePath)
    {
        $spreadsheet = new Spreadsheet();

        $this->addSheet($spreadsheet, 0, new PayrollDetailSewingSheet($this->run_id));
        $this->addSheet($spreadsheet, 1, new PayrollOutDetailSewingSheet($this->run_id));
        $this->addSheet($spreadsheet, 2, new PayrollMADetailSewingSheet($this->run_id));
        $this->addSheet($spreadsheet, 3, new PayrollSummarySewingSheet($this->run_id));

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
