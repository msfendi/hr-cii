<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use Illuminate\Support\Facades\DB;

class EmployeeHeatAssignmentSheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'employee_heat_assignments';
    }

    public function headings(): array
    {
        return [
            'npk',
            'dept',
            'role',
            'start_date',
            'end_date'
        ];
    }

    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->sheet->getDelegate();
        $spreadsheet = $sheet->getParent();

        // bold header
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        // auto width
        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // contoh data
        $sheet->setCellValue('A2', 'C-00827');
        $sheet->setCellValue('B2', 'heat');
        $sheet->setCellValue('C2', 'operator');
        $sheet->setCellValue('D2', '2026-01-01');
        $sheet->setCellValue('E2', '2026-01-12');

        $sheet->setCellValue('A3', 'C-00828');
        $sheet->setCellValue('B3', 'heat');
        $sheet->setCellValue('C3', 'supervisor');
        $sheet->setCellValue('D3', '2026-01-01');
        $sheet->setCellValue('E3', '2026-01-12');

        $sheet->setCellValue('A4', 'C-00829');
        $sheet->setCellValue('B4', 'heat');
        $sheet->setCellValue('C4', 'helper');
        $sheet->setCellValue('D4', '2026-01-01');
        $sheet->setCellValue('E4', '2026-01-12');


        $roles = DB::table('insentif_role_formulas')
            ->where('dept', 'heat')
            ->orderBy('role')
            ->pluck('role')
            ->unique()
            ->values()
            ->toArray();

        // Hidden Sheet
        $hiddenSheet = $spreadsheet->createSheet();
        $hiddenSheet->setTitle('role_master');

        foreach ($roles as $index => $role) {
            $hiddenSheet->setCellValue(
                'A' . ($index + 1),
                $role
            );
        }

        $hiddenSheet->setSheetState(
            \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN
        );

        $lastRow = count($roles);

        // Apply dropdown to C2:C5000
        for ($row = 2; $row <= 5000; $row++) {
            $validation = $sheet->getCell('C' . $row)->getDataValidation();

            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Input Salah');
            $validation->setError('Pilih role dari daftar.');
            $validation->setFormula1("=role_master!\$A\$1:\$A\${$lastRow}");
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
