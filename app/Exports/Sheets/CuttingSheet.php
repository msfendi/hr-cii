<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;

class CuttingSheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'cuttings';
    }

    public function headings(): array
    {
        return [
            'cutting_id',
            'cutting_name'
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

        // contoh data
        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', 'Bundling');

        $sheet->setCellValue('A3', '2');
        $sheet->setCellValue('B3', 'Rib');

        $sheet->setCellValue('A4', '3');
        $sheet->setCellValue('B4', 'Htl');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
