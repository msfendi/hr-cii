<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LineJumperInsentifTemplateExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function headings(): array
    {
        return ['npk', 'operation', 'machine'];
    }

    public function array(): array
    {
        // contoh pengisian (hapus / timpa dengan data asli)
        return [
            ['C-00105', '1. ATTACH SLEEVE TO BODY', '4TOL'],
            ['C-00105', '2. CLOSING SIDE SEAM',     '4TOL'],
            ['C-00105', '3. STITCH FRONT',          'SNCS'],
            ['C-00105', '4. TACK BACK NECK TAPE',   'SN'],
        ];
    }

    public function title(): string
    {
        return 'LineJumperOperation';
    }

    public function styles(Worksheet $sheet)
    {
        // NPK sebagai teks supaya tidak diubah Excel
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
