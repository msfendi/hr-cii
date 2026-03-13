<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;

class EmployeeLineAssignmentSheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'employee_line_assignments';
    }

    public function headings(): array
    {
        return [
            'npk',
            'line_number',
            'role',
            'start_date',
            'end_date'
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
        $sheet->setCellValue('B2', '1');
        $sheet->setCellValue('C2', 'operator');
        $sheet->setCellValue('D2', '2026-01-01');
        $sheet->setCellValue('E2', '2026-01-12');

        $sheet->setCellValue('A3', 'C-00828');
        $sheet->setCellValue('B3', '2');
        $sheet->setCellValue('C3', 'supervisor');
        $sheet->setCellValue('D3', '2026-01-01');
        $sheet->setCellValue('E3', '2026-01-12');

        $sheet->setCellValue('A4', 'C-00829');
        $sheet->setCellValue('B4', '3');
        $sheet->setCellValue('C4', 'operator');
        $sheet->setCellValue('D4', '2026-01-01');
        $sheet->setCellValue('E4', '2026-01-12');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
