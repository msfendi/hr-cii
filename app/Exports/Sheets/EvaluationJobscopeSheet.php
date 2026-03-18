<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;

class EvaluationJobscopeSheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'jobscope';
    }

    public function headings(): array
    {
        return [
            'id',
            'job_code',
            'job_name',
            'description',
            'qr_code',
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
        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', 'it_programmer');
        $sheet->setCellValue('C2', 'IT Programmer');
        $sheet->setCellValue('A3', '2');
        $sheet->setCellValue('B3', 'it_network');
        $sheet->setCellValue('C3', 'IT Networking');
        $sheet->setCellValue('A4', '3');
        $sheet->setCellValue('B4', 'it_support');
        $sheet->setCellValue('C4', 'IT Support');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
