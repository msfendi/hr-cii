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
        $sheet->setCellValue('A5', '4');
        $sheet->setCellValue('B5', 'Accescories');
        $sheet->setCellValue('A6', '5');
        $sheet->setCellValue('B6', 'Supermarket');
        $sheet->setCellValue('A7', '6');
        $sheet->setCellValue('B7', 'Loading to Sewing');
        $sheet->setCellValue('A8', '7');
        $sheet->setCellValue('B8', 'Waste');
        $sheet->setCellValue('A9', '8');
        $sheet->setCellValue('B9', 'Ganti BS');
        $sheet->setCellValue('A10', '9');
        $sheet->setCellValue('B10', 'Piping');
        $sheet->setCellValue('A11', '10');
        $sheet->setCellValue('B11', 'Cutting Admin');
        $sheet->setCellValue('A12', '11');
        $sheet->setCellValue('B12', 'Supermarket Admin');
        $sheet->setCellValue('A13', '12');
        $sheet->setCellValue('B13', 'Manual Cutter');
        $sheet->setCellValue('A14', '13');
        $sheet->setCellValue('B14', 'Auto Cutter');
        $sheet->setCellValue('A15', '14');
        $sheet->setCellValue('B15', 'Spreading Auto');
        $sheet->setCellValue('A16', '15');
        $sheet->setCellValue('B16', 'Spreading Manual');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
