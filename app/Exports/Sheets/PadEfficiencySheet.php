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

class PadEfficiencySheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'pad_efficiencies';
    }

    public function headings(): array
    {
        return [
            'npk',
            'name',
            'role',
            'tim',
            'efficiency',
            'piece',
            'date'
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
        $sheet->getStyle('G:G')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        // contoh data
        $sheet->setCellValue('A2', 'C-00827');
        $sheet->setCellValue('B2', 'DIMAS GALANG RAMADHAN');
        $sheet->setCellValue('C2', 'operator');
        $sheet->setCellValue('D2', '1');
        $sheet->setCellValue('E2', '85');
        $sheet->setCellValue('F2', '3517');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('G2', $date);

        $sheet->setCellValue('A3', 'C-00827');
        $sheet->setCellValue('B3', 'DIMAS GALANG RAMADHAN');
        $sheet->setCellValue('C3', 'operator');
        $sheet->setCellValue('D3', '1');
        $sheet->setCellValue('E3', '90');
        $sheet->setCellValue('F3', '3724');
        $date = Date::stringToExcel('2026-01-13');
        $sheet->setCellValue('G3', $date);


        $roles = DB::table('insentif_role_formulas')
            ->where('dept', 'pad')
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
