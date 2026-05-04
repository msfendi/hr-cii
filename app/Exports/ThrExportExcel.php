<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use App\Exports\NonSewing\ThrDetailNonSewingSheet;
use App\Exports\NonSewing\ThrOutDetailNonSewingSheet;
use App\Exports\Sewing\ThrDetailSewingSheet;
use App\Exports\Sewing\ThrOutDetailSewingSheet;
use App\Exports\Staff\ThrDetailStaffSheet;
use App\Exports\Staff\ThrOutDetailStaffSheet;

class ThrExportExcel
{
    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function export($filePath)
    {
        $spreadsheet = new Spreadsheet();

        $this->addSheet($spreadsheet, 0, new ThrDetailSheet($this->run_id));
        $this->addSheet($spreadsheet, 1, new ThrDetailStaffSheet($this->run_id));
        $this->addSheet($spreadsheet, 2, new ThrDetailSewingSheet($this->run_id));
        $this->addSheet($spreadsheet, 3, new ThrDetailNonSewingSheet($this->run_id));
        // $this->addSheet($spreadsheet, 8, new ThrSummarySheet($this->run_id));

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
