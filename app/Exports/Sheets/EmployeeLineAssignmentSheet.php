<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use Illuminate\Support\Facades\DB;

class EmployeeLineAssignmentSheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'employee_line_assignments';
    }

    public function headings(): array
    {
        return [
            'npk',
            'name',
            'line_number',
            'role',
            'date',
            'work_hours',
        ];
    }

    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->sheet->getDelegate();
        $spreadsheet = $sheet->getParent();

        // bold header
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        // auto width
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('E:E')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        // contoh data
        $sheet->setCellValue('A2', 'C-00827');
        $sheet->setCellValue('B2', 'Dimas Galang Ramadhan');
        $sheet->setCellValue('C2', '1');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('D2', 'operator');
        $sheet->setCellValue('E2', $date);
        $sheet->setCellValue('F2', '8');

        $sheet->setCellValue('A3', 'C-00827');
        $sheet->setCellValue('B3', 'Dimas Galang Ramadhan');
        $sheet->setCellValue('C3', '1');
        $date = Date::stringToExcel('2026-01-13');
        $sheet->setCellValue('D3', 'operator');
        $sheet->setCellValue('E3', $date);
        $sheet->setCellValue('F3', '10');

        $sheet->setCellValue('A4', 'C-00827');
        $sheet->setCellValue('B4', 'Dimas Galang Ramadhan');
        $sheet->setCellValue('C4', '1');
        $date = Date::stringToExcel('2026-01-14');
        $sheet->setCellValue('D4', 'operator');
        $sheet->setCellValue('E4', $date);
        $sheet->setCellValue('F4', '8');

        $sheet->setCellValue('A5', 'C-00827');
        $sheet->setCellValue('B5', 'Dimas Galang Ramadhan');
        $sheet->setCellValue('C5', '1');
        $date = Date::stringToExcel('2026-01-15');
        $sheet->setCellValue('D5', 'operator');
        $sheet->setCellValue('E5', $date);
        $sheet->setCellValue('F5', '4');

        $sheet->setCellValue('A6', 'C-00827');
        $sheet->setCellValue('B6', 'Dimas Galang Ramadhan');
        $sheet->setCellValue('C6', '1');
        $date = Date::stringToExcel('2026-01-16');
        $sheet->setCellValue('D6', 'operator');
        $sheet->setCellValue('E6', $date);
        $sheet->setCellValue('F6', '8');

        $sheet->setCellValue('A7', 'C-00825');
        $sheet->setCellValue('B7', 'Christantie Imanuela');
        $sheet->setCellValue('C7', '1');
        $date = Date::stringToExcel('2026-01-14');
        $sheet->setCellValue('D7', 'operator');
        $sheet->setCellValue('E7', $date);
        $sheet->setCellValue('F7', '10');

        $roles = DB::table('insentif_role_formulas')
            ->where('dept', 'sewing')
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
            $validation = $sheet->getCell('D' . $row)->getDataValidation();

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
