<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class InsentifMasterTemplateExport implements WithHeadings, WithEvents
{
    public function headings(): array
    {
        return [
            'npk',
            'type',
            'date',
            'efficiency',
            'piece'
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function ($event) {

                $sheet = $event->sheet->getDelegate();

                // bold header
                $sheet->getStyle('A1:E1')->getFont()->setBold(true);

                // auto width
                foreach (range('A', 'E') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // contoh data
                $sheet->setCellValue('A2', 'C-00827');
                $sheet->setCellValue('B2', 'efficiency');
                $sheet->setCellValue('C2', '2026-01-12');
                $sheet->setCellValue('D2', '65');
                $sheet->setCellValue('E2', '');

                $sheet->setCellValue('A3', 'C-00828');
                $sheet->setCellValue('B3', 'piece');
                $sheet->setCellValue('C3', '2026-01-12');
                $sheet->setCellValue('D3', '');
                $sheet->setCellValue('E3', '1326');
            }
        ];
    }
}
