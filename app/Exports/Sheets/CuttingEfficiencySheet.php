<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;

class CuttingEfficiencySheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'cutting_efficiencies';
    }

    public function headings(): array
    {
        return [
            'npk',
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
        $sheet->setCellValue('A2', 'C-00827');
        $sheet->setCellValue('B2', '86');
        $sheet->setCellValue('C2', '2026-01-12');

        $sheet->setCellValue('A3', 'C-00827');
        $sheet->setCellValue('B3', '91');
        $sheet->setCellValue('C3', '2026-01-13');

        $sheet->setCellValue('A4', 'C-00827');
        $sheet->setCellValue('B4', '82');
        $sheet->setCellValue('C4', '2026-01-14');

        $sheet->setCellValue('A5', 'C-00828');
        $sheet->setCellValue('B5', '86');
        $sheet->setCellValue('C5', '2026-01-11');

        $sheet->setCellValue('A5', 'C-00829');
        $sheet->setCellValue('B5', '86');
        $sheet->setCellValue('C5', '2026-01-12');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
