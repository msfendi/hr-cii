<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class PKWTCountGenderExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $data = DB::connection('cii')->table('dept as d')
            ->leftJoin('biodata as b', 'b.ID_DEPT', '=', 'd.ID_DEPT')
            ->select(
                'd.DEPARTEMENT',

                DB::raw('COUNT(b.NPK) as total'),

                DB::raw("SUM(CASE WHEN b.JENIS_KEL = 'L' THEN 1 ELSE 0 END) as laki_laki"),

                DB::raw("SUM(CASE WHEN b.JENIS_KEL = 'P' THEN 1 ELSE 0 END) as perempuan")
            )
            ->where('d.DEPARTEMENT', 'not like', '%HOD%')
            ->where('d.DEPARTEMENT', 'not like', '%MANAGER%')
            ->groupBy('d.DEPARTEMENT')
            ->orderBy('d.DEPARTEMENT', 'ASC')
            ->get();

        return $data;
    }

    public function headings(): array
    {
        return [
            'DEPARTEMENT',
            'TOTAL',
            'LAKI-LAKI',
            'PEREMPUAN'
        ];
    }

    public function title(): string
    {
        return 'Rekap Per Dept';
    }

    /**
     * Apply styles to the header row
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style for header row (row 1)
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => Color::COLOR_WHITE],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4472C4'], // Blue color
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
