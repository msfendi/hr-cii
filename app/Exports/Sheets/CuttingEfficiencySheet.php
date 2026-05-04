<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CuttingEfficiencySheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'cutting_efficiencies';
    }

    public function headings(): array
    {
        return [
            'efficiency',
            'date'
        ];
    }
    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->sheet->getDelegate();

        // bold header
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);

        // auto width
        foreach (range('A', 'B') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('B:B')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        // contoh data
        $sheet->setCellValue('A2', '86');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('B2', $date);

        $sheet->setCellValue('A3', '91');
        $date = Date::stringToExcel('2026-01-13');
        $sheet->setCellValue('B3', $date);

        $sheet->setCellValue('A4', '82');
        $date = Date::stringToExcel('2026-01-14');
        $sheet->setCellValue('B4', $date);

        $sheet->setCellValue('A5', '86');
        $date = Date::stringToExcel('2026-01-15');
        $sheet->setCellValue('B5', $date);

        $sheet->setCellValue('A6', '86');
        $date = Date::stringToExcel('2026-01-16');
        $sheet->setCellValue('B6', $date);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
