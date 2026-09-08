<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class QcEfficiencySheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'qc_efficiencies';
    }

    public function headings(): array
    {
        return [
            'line_number',
            'efficiency',
            'date',
            'days',
            'buyer',
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

        $sheet->getStyle('C:C')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        // contoh data
        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', '1.82');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('C2', $date);
        $sheet->setCellValue('D2', '1');
        $sheet->setCellValue('E2', 'MUJI');

        $sheet->setCellValue('A3', '2');
        $sheet->setCellValue('B3', '2.56');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('C3', $date);
        $sheet->setCellValue('D3', '1');
        $sheet->setCellValue('E3', 'UNIQLO');

        $sheet->setCellValue('A4', '3');
        $sheet->setCellValue('B4', '4.21');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('C4', $date);
        $sheet->setCellValue('D4', '1');
        $sheet->setCellValue('E4', 'MUJI');

        $sheet->setCellValue('A5', '1');
        $sheet->setCellValue('B5', '0.88');
        $date = Date::stringToExcel('2026-01-13');
        $sheet->setCellValue('C5', $date);
        $sheet->setCellValue('D5', '2');
        $sheet->setCellValue('E5', 'MUJI');

        $sheet->setCellValue('A6', '1');
        $sheet->setCellValue('B6', '1.51');
        $date = Date::stringToExcel('2026-01-14');
        $sheet->setCellValue('C6', $date);
        $sheet->setCellValue('D6', '3');
        $sheet->setCellValue('E6', 'MUJI');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
