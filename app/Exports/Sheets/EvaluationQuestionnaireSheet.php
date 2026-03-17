<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;

class EvaluationQuestionnaireSheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'questionnaire';
    }

    public function headings(): array
    {
        return [
            'jobscope_id',
            'question',
            'optiona',
            'optionb',
            'optionc',
            'optiond',
            'correct_answer',
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
        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', 'Bagaimana saya ingin mempublish web application company yang berjalan di server lokal?');
        $sheet->setCellValue('C2', 'Upload ke CMS');
        $sheet->setCellValue('D2', 'Ubah IP Local VM menggunakan IP Public');
        $sheet->setCellValue('E2', 'Buat Port Forwarding dari IP Local ke IP Public');
        $sheet->setCellValue('F2', 'Berlangganan VPS dan deploy di VPS');
        $sheet->setCellValue('G2', 'C');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
