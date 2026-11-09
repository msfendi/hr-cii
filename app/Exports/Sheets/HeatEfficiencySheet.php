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

class HeatEfficiencySheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'heat_efficiencies';
    }

    public function headings(): array
    {
        return [
            'npk',
            'role',
            'efficiency',
            'piece',
            'date',
            'tim',
            'group'
        ];
    }
    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->sheet->getDelegate();
        $spreadsheet = $sheet->getParent();

        // bold header
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        // auto width
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('E:E')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        // contoh data
        $sheet->setCellValue('A2', 'C-00827');
        $sheet->setCellValue('B2', 'operator');
        $sheet->setCellValue('C2', '85');
        $sheet->setCellValue('D2', '3517');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('E2', $date);
        $sheet->setCellValue('F2', 1);
        $sheet->setCellValue('G2', 'sewing');

        $sheet->setCellValue('A3', 'C-00827');
        $sheet->setCellValue('B3', 'operator');
        $sheet->setCellValue('C3', '90');
        $sheet->setCellValue('D3', '3724');
        $date = Date::stringToExcel('2026-01-13');
        $sheet->setCellValue('E3', $date);
        $sheet->setCellValue('F3', 1);
        $sheet->setCellValue('G3', 'sewing');

        $sheet->setCellValue('A4', 'C-00827');
        $sheet->setCellValue('B4', 'operator');
        $sheet->setCellValue('C4', '90');
        $sheet->setCellValue('D4', '3724');
        $date = Date::stringToExcel('2026-01-14');
        $sheet->setCellValue('E4', $date);
        $sheet->setCellValue('F4', 2);
        $sheet->setCellValue('G4', 'cutting');

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

        // Apply dropdown (role) to B2:B5000
        for ($row = 2; $row <= 5000; $row++) {
            $validation = $sheet->getCell('B' . $row)->getDataValidation();

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

        // Apply dropdown (group: sewing / cutting) to G2:G5000
        for ($row = 2; $row <= 5000; $row++) {
            $validation = $sheet->getCell('G' . $row)->getDataValidation();

            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Input Salah');
            $validation->setError('Pilih group: sewing atau cutting.');
            $validation->setFormula1('"sewing,cutting"');
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}