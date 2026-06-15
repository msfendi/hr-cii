<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

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
            'efficiency',
            'piece',
            'date'
        ];
    }
    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->sheet->getDelegate();

        // bold header
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        // auto width
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->getStyle('F:F')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        // contoh data
        $sheet->setCellValue('A2', 'C-00827');
        $sheet->setCellValue('B2', 'DIMAS GALANG RAMADHAN');
        $sheet->setCellValue('C2', 'operator');
        $sheet->setCellValue('D2', '85');
        $sheet->setCellValue('E2', '3517');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('F2', $date);

        $sheet->setCellValue('A3', 'C-00827');
        $sheet->setCellValue('B3', 'DIMAS GALANG RAMADHAN');
        $sheet->setCellValue('C3', 'operator');
        $sheet->setCellValue('D3', '90');
        $sheet->setCellValue('E3', '3724');
        $date = Date::stringToExcel('2026-01-13');
        $sheet->setCellValue('F3', $date);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
