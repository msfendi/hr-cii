<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DeptTemplateExport implements FromArray, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        return [[
            'NAMA DEPARTEMEN',
            'ID PARENT DEPT',
            'IS SEWING (1=Ya, 0=Tidak)',
            'SECTION',
        ]];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => [
                'font' => ['bold' => true],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FFE2EFDA',
                    ]
                ]
            ],
        ];
    }
}
