<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;

class EmployeeCuttingAssignmentSheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'employee_cutting_assignments';
    }

    public function headings(): array
    {
        return [
            'npk',
            'role',
            'start_date',
            'end_date'
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

        // contoh data
        $sheet->setCellValue('A2', 'C-00827');
        $sheet->setCellValue('B2', 'Spreading Auto');
        $sheet->setCellValue('C2', '2026-01-01');
        $sheet->setCellValue('D2', '2026-01-31');

        $sheet->setCellValue('A3', 'C-00828');
        $sheet->setCellValue('B3', 'Spreading Auto');
        $sheet->setCellValue('C3', '2026-01-01');
        $sheet->setCellValue('D3', '2026-01-31');

        $sheet->setCellValue('A4', 'C-00829');
        $sheet->setCellValue('B4', 'Spreading Manual');
        $sheet->setCellValue('C4', '2026-01-01');
        $sheet->setCellValue('D4', '2026-01-31');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
