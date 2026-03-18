<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;

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
            'dept',
            'efficiency',
            'piece',
            'date'
        ];
    }
    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->sheet->getDelegate();

        // bold header
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        // auto width
        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // contoh data
        $sheet->setCellValue('A2', 'C-00827');
        $sheet->setCellValue('B2', 'padprint');
        $sheet->setCellValue('C2', '85');
        $sheet->setCellValue('D2', '3517');
        $sheet->setCellValue('E2', '2026-01-12');

        $sheet->setCellValue('A3', 'C-00828');
        $sheet->setCellValue('B3', 'padprint');
        $sheet->setCellValue('C3', '90');
        $sheet->setCellValue('D3', '3724');
        $sheet->setCellValue('E3', '2026-01-12');

        $sheet->setCellValue('A4', 'C-00827');
        $sheet->setCellValue('B4', 'padprint');
        $sheet->setCellValue('C4', '90');
        $sheet->setCellValue('D4', '3724');
        $sheet->setCellValue('E4', '2026-01-13');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
