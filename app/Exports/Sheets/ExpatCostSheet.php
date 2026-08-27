<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ExpatCostSheet implements
    FromCollection,
    WithTitle,
    WithHeadings,
    ShouldAutoSize,
    WithStyles
{
    protected $start;
    protected $end;

    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end   = $end;
    }

    public function title(): string
    {
        return 'expat_cost';
    }

    /*
    |--------------------------------------------------------------------------
    | DATA (LOGIC TIDAK DIUBAH)
    |--------------------------------------------------------------------------
    */

    public function collection()
    {

        return DB::table('expat_cost as c')
            ->join('expat_master as m', 'm.npk', '=', 'c.npk')
            ->join('expat_cost_components as cc', 'cc.id', '=', 'c.component')
            ->where('cc.component_type', '!=', 'meal')
            ->where('cc.component_type', '!=', 'transport')
            ->whereBetween('c.transactions_date', [$this->start, $this->end])
            ->select(
                'c.npk',
                'm.name',
                'cc.component',
                'c.amount',
                'c.transactions_date',
                'c.remark'
            )
            ->get();
    }

    public function headings(): array
    {
        return [
            'NPK',
            'Name',
            'Component',
            'Amount',
            'Transaction Date',
            'Remark'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | STYLING EXCEL
    |--------------------------------------------------------------------------
    */

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        /*
        |--------------------------------------------------------------------------
        | HEADER STYLE
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'D9E1F2',
                ],
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | FORMAT RUPIAH (COLUMN D)
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle("D2:D{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode(
                '"Rp" #,##0'
            );

        /*
        |--------------------------------------------------------------------------
        | ALIGNMENT
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle("A2:A{$highestRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("E2:E{$highestRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        /*
        |--------------------------------------------------------------------------
        | BORDER TABLE
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle("A1:F{$highestRow}")
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | FREEZE HEADER
        |--------------------------------------------------------------------------
        */

        $sheet->freezePane('A2');

        return [];
    }
}
