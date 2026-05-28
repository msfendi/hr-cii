<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DeptExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function collection()
    {
        return DB::connection('cii')
            ->table('DEPT')
            ->leftJoin('parent_dept', 'DEPT.id_parent_dept', '=', 'parent_dept.id')
            ->select('DEPT.ID_DEPT', 'DEPT.DEPARTEMENT', 'DEPT.id_parent_dept', 'parent_dept.parent_dept_name', 'DEPT.IS_SEWING', 'DEPT.SECTION')
            ->orderBy('DEPT.ID_DEPT')
            ->get();
    }

    public function map($dept): array
    {
        return [
            $dept->ID_DEPT,
            $dept->DEPARTEMENT,
            $dept->id_parent_dept,
            $dept->parent_dept_name,
            $dept->IS_SEWING == 0 ? 'Ya' : 'Tidak',
            $dept->SECTION,
        ];
    }

    public function headings(): array
    {
        return [
            'ID DEPT',
            'NAMA DEPARTEMEN',
            'ID PARENT DEPT',
            'PARENT DEPARTEMEN',
            'IS SEWING',
            'SECTION',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
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
