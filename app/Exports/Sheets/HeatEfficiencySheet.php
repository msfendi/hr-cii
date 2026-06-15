<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class HeatEfficiencySheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'heat_efficiencies';
    }

    public function headings(): array
    {
        return [
            'npk',
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
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        // auto width
        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('E:E')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        // contoh data
        $sheet->setCellValue('A2', 'C-00827');
        $sheet->setCellValue('B2', 'operator');
        $sheet->setCellValue('C2', '85');
        $sheet->setCellValue('D2', '3517');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('E2', $date);

        $sheet->setCellValue('A3', 'C-00827');
        $sheet->setCellValue('B3', 'operator');
        $sheet->setCellValue('C3', '90');
        $sheet->setCellValue('D3', '3724');
        $date = Date::stringToExcel('2026-01-13');
        $sheet->setCellValue('E3', $date);

        $sheet->setCellValue('A4', 'C-00827');
        $sheet->setCellValue('B4', 'operator');
        $sheet->setCellValue('C4', '90');
        $sheet->setCellValue('D4', '3724');
        $date = Date::stringToExcel('2026-01-14');
        $sheet->setCellValue('E4', $date);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
