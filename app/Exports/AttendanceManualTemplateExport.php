<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Models\Dept;
use App\Exports\Sheets\AttendanceManualSheet;

class AttendanceManualTemplateExport implements WithMultipleSheets
{
    use Exportable;

    protected $month;
    protected $year;
    protected $is_sewing;

    public function __construct($month, $year, $is_sewing)
    {
        $this->month = $month;
        $this->year = $year;
        $this->is_sewing = $is_sewing;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Ambil semua dept yang sesuai dengan is_sewing
        $departments = Dept::where('IS_SEWING', $this->is_sewing)
            ->whereNotNull('DEPARTEMENT')
            ->orderBy('DEPARTEMENT', 'asc')
            ->get();

        foreach ($departments as $dept) {
            $sheets[] = new AttendanceManualSheet($this->month, $this->year, $dept->ID_DEPT, $dept->DEPARTEMENT);
        }

        return $sheets;
    }
}
