<?php

namespace App\Exports\Compensation;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CompensationExportExcel
{
    protected $cutoffDate;
    protected ?string $role;

    /**
     * @param string|\Carbon\Carbon $cutoffDate Tanggal cutoff (7 / 20) compensation yang mau diexport.
     * @param string|null $role Salah satu PayrollRoleFilterService::ROLE_* atau null (tidak difilter, untuk Payroll_ALL).
     */
    public function __construct($cutoffDate, ?string $role = null)
    {
        $this->cutoffDate = $cutoffDate;
        $this->role = $role;
    }

    public function export($filePath)
    {
        $spreadsheet = new Spreadsheet();

        $this->addSheet($spreadsheet, 0, new CompensationDetailSheet($this->cutoffDate, $this->role));

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
