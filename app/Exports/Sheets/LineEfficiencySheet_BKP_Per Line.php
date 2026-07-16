<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class LineEfficiencySheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'line_efficiencies';
    }

    public function headings(): array
    {
        return [
            'line_number',
            'efficiency',
            'date',
            'days'
        ];
    }
    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->sheet->getDelegate();

        // bold header
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);

        // auto width
        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('C:C')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        // contoh data
        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', '86');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('C2', $date);
        $sheet->setCellValue('D2', '1');

        $sheet->setCellValue('A3', '2');
        $sheet->setCellValue('B3', '88');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('C3', $date);
        $sheet->setCellValue('D3', '1');

        $sheet->setCellValue('A4', '3');
        $sheet->setCellValue('B4', '85');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('C4', $date);
        $sheet->setCellValue('D4', '1');

        $sheet->setCellValue('A5', '1');
        $sheet->setCellValue('B5', '86');
        $date = Date::stringToExcel('2026-01-13');
        $sheet->setCellValue('C5', $date);
        $sheet->setCellValue('D5', '2');

        $sheet->setCellValue('A6', '1');
        $sheet->setCellValue('B6', '91');
        $date = Date::stringToExcel('2026-01-14');
        $sheet->setCellValue('C6', $date);
        $sheet->setCellValue('D6', '3');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
