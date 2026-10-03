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
            'third_party',
            'dept',
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

        $sheet->getStyle('C:C')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        // contoh data (third_party opsional: kosongkan jika tidak ada; dept: qc / qa)
        $sheet->setCellValue('A2', '1');
        $sheet->setCellValue('B2', '1.82');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('C2', $date);
        $sheet->setCellValue('D2', '1');
        $sheet->setCellValue('E2', 'MUJI');
        $sheet->setCellValue('F2', 'PQC');
        $sheet->setCellValue('G2', 'qc');

        $sheet->setCellValue('A3', '2');
        $sheet->setCellValue('B3', '2.56');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('C3', $date);
        $sheet->setCellValue('D3', '1');
        $sheet->setCellValue('E3', 'UNIQLO');
        $sheet->setCellValue('F3', '');
        $sheet->setCellValue('G3', 'qc');

        $sheet->setCellValue('A4', '3');
        $sheet->setCellValue('B4', '4.21');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('C4', $date);
        $sheet->setCellValue('D4', '1');
        $sheet->setCellValue('E4', 'MUJI');
        $sheet->setCellValue('F4', 'TENTAC');
        $sheet->setCellValue('G4', 'qc');

        $sheet->setCellValue('A5', '1');
        $sheet->setCellValue('B5', '0.88');
        $date = Date::stringToExcel('2026-01-13');
        $sheet->setCellValue('C5', $date);
        $sheet->setCellValue('D5', '2');
        $sheet->setCellValue('E5', 'MUJI');
        $sheet->setCellValue('F5', '');
        $sheet->setCellValue('G5', 'qc');

        $sheet->setCellValue('A6', '1');
        $sheet->setCellValue('B6', '1.51');
        $date = Date::stringToExcel('2026-01-14');
        $sheet->setCellValue('C6', $date);
        $sheet->setCellValue('D6', '3');
        $sheet->setCellValue('E6', 'MUJI');
        $sheet->setCellValue('F6', '');
        $sheet->setCellValue('G6', 'qc');

        // contoh baris QA (dept = qa)
        $sheet->setCellValue('A7', '');
        $sheet->setCellValue('B7', '1.20');
        $sheet->setCellValue('C7', Date::stringToExcel('2026-01-12'));
        $sheet->setCellValue('D7', '1');
        $sheet->setCellValue('E7', 'MUJI');
        $sheet->setCellValue('F7', 'PQC');
        $sheet->setCellValue('G7', 'qa');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
