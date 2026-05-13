<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;

class PayrollMasterTemplateExport implements WithHeadings, WithEvents
{

    public function headings(): array
    {
        return [
            'npk',
            'bank_name',
            'bank_account',
        ];
    }

    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->sheet->getDelegate();

        // bold header
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        // auto width
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // contoh data
        $sheet->setCellValue('A2', 'C-00827');
        $sheet->setCellValue('B2', 'Permata Bank');
        $sheet->setCellValue('C2', '999123456789');

        $sheet->setCellValue('A3', 'C-00828');
        $sheet->setCellValue('B3', 'Permata Bank');
        $sheet->setCellValue('C3', '999123456789');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
