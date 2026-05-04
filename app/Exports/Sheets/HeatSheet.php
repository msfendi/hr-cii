<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;

class HeatSheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'heats';
    }

    public function headings(): array
    {
        return [
            'column',
            'remark',
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
        $sheet->setCellValue('A2', 'role');
        $sheet->setCellValue('B2', 'Role yang dapat diisi oleh komponen ini : supervisor, leader, inkmaking, helper. Pastikan role yang diisi sesuai, perhatikan huruf besar dan kecilnya.');
        $sheet->setCellValue('A3', 'dept');
        $sheet->setCellValue('B3', 'Wajib diisi dengan : heat');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
