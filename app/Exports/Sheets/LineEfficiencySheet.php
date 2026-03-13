<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;

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
            'date'
        ];
    }
    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->sheet->getDelegate();

        // bold header
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        // auto width
        foreach (range('A', 'C') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // contoh data
        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', '86');
        $sheet->setCellValue('C2', '2026-01-12');

        $sheet->setCellValue('A3', '2');
        $sheet->setCellValue('B3', '88');
        $sheet->setCellValue('C3', '2026-01-12');

        $sheet->setCellValue('A4', '3');
        $sheet->setCellValue('B4', '85');
        $sheet->setCellValue('C4', '2026-01-12');

        $sheet->setCellValue('A5', '1');
        $sheet->setCellValue('B5', '86');
        $sheet->setCellValue('C5', '2026-01-13');

        $sheet->setCellValue('A6', '1');
        $sheet->setCellValue('B6', '91');
        $sheet->setCellValue('C6', '2026-01-14');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
