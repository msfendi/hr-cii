<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExpatTransportSheet implements
    FromCollection,
    WithTitle,
    WithHeadings,
    ShouldAutoSize,
    WithStyles
{
    public function title(): string
    {
        return 'expat_transport';
    }

    protected $start;
    protected $end;

    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end   = $end;
    }

    /*
    |--------------------------------------------------------------------------
    | SAFE JSON DECODE (ANTI DOUBLE ENCODE)
    |--------------------------------------------------------------------------
    */
    private function decodeJson($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /*
    |--------------------------------------------------------------------------
    | COLLECTION (LOGIC LAMA TETAP + TAMBAHAN DARI expat_cost)
    |--------------------------------------------------------------------------
    */
    public function collection()
    {
        $components = DB::table('expat_cost_components')
            ->pluck('component', 'id');

        /*
        |----------------------------------------------------------------
        | 1) DATA DARI expat_onleave (LOGIC LAMA, TIDAK DIUBAH)
        |----------------------------------------------------------------
        */
        $data = DB::table('expat_onleave as l')
            ->join('expat_master as m', 'm.npk', '=', 'l.npk')
            ->whereBetween('l.onleave_start', [$this->start, $this->end])
            ->select(
                'l.npk',
                'm.name',
                'l.onleave_start',
                'l.onleave_end',
                'l.component',
                'l.amount',
                'l.transactions_date',
                'l.remark'
            )
            ->orderByDesc('l.id')
            ->get();

        $rows = collect();

        foreach ($data as $row) {

            $componentArray = $this->decodeJson($row->component);
            $amountArray    = $this->decodeJson($row->amount);
            $dateArray      = $this->decodeJson($row->transactions_date);

            foreach ($componentArray as $i => $componentId) {

                $rows->push([
                    'npk' => $row->npk,
                    'name' => $row->name,
                    'leave_start' => $row->onleave_start,
                    'leave_end' => $row->onleave_end,
                    'component' => $components[$componentId] ?? $componentId,
                    'amount' => $amountArray[$i] ?? 0,
                    'transactions_date' => $row->transactions_date ? $dateArray[$i] ?? null : null,
                    'remark' => $row->remark,
                ]);
            }
        }

        /*
        |----------------------------------------------------------------
        | 2) DATA TAMBAHAN DARI expat_cost DENGAN component_type = 'transport'
        |----------------------------------------------------------------
        */
        $costData = DB::table('expat_cost as c')
            ->join('expat_master as m', 'm.npk', '=', 'c.npk')
            ->join('expat_cost_components as cc', 'cc.id', '=', 'c.component')
            ->leftJoin('expat_onleave as l', function ($join) {
                $join->on('l.npk', '=', 'c.npk')
                    ->whereColumn('c.transactions_date', '>=', 'l.onleave_start')
                    ->whereColumn('c.transactions_date', '<=', 'l.onleave_end');
            })
            ->where('cc.component_type', '=', 'transport')
            ->whereBetween('c.transactions_date', [$this->start, $this->end])
            ->select(
                'c.npk',
                'm.name',
                'l.onleave_start',
                'l.onleave_end',
                'cc.component',
                'c.amount',
                'c.transactions_date',
                'c.remark'
            )
            ->get();

        foreach ($costData as $row) {
            $rows->push([
                'npk' => $row->npk,
                'name' => $row->name,
                'leave_start' => $row->onleave_start,
                'leave_end' => $row->onleave_end,
                'component' => $row->component,
                'amount' => $row->amount,
                'transactions_date' => $row->transactions_date,
                'remark' => $row->remark,
            ]);
        }

        return new Collection($rows);
    }

    public function headings(): array
    {
        return [
            'NPK',
            'Name',
            'Leave Start',
            'Leave End',
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
        | HEADER STYLE
        */
        $sheet->getStyle('A1:H1')->applyFromArray([
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
        | FORMAT RUPIAH (COLUMN G)
        */
        $sheet->getStyle("G2:G{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('"Rp" #,##0');

        /*
        | ALIGNMENT
        */
        $sheet->getStyle("A2:A{$highestRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("C2:D{$highestRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("H2:H{$highestRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        /*
        | BORDER TABLE
        */
        $sheet->getStyle("A1:H{$highestRow}")
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ]);

        /*
        | FREEZE HEADER
        */
        $sheet->freezePane('A2');

        return [];
    }
}
