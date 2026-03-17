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
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);

        // auto width
        foreach (range('A', 'D') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // contoh data
        $sheet->setCellValue('A2', 'it_programmer');
        $sheet->setCellValue('B2', 'IT Programmer');
        $sheet->setCellValue('A3', 'it_network');
        $sheet->setCellValue('B3', 'IT Networking');
        $sheet->setCellValue('A4', 'it_support');
        $sheet->setCellValue('B4', 'IT Support');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
