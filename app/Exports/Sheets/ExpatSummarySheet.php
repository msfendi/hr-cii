<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ExpatSummarySheet implements
    FromCollection,
    WithTitle,
    WithHeadings,
    ShouldAutoSize,
    WithMapping,
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
        return 'expat_summary';
    }

    /*
    |--------------------------------------------------------------------------
    | COLLECTION (LOGIC TIDAK DIUBAH)
    |--------------------------------------------------------------------------
    */
    public function collection()
    {
        $today = \Carbon\Carbon::today();

        $data = DB::table('expat_master as m')

            ->leftJoin(DB::raw("
            (
                SELECT npk,
                       SUM(amount) total_living_cost
                FROM expat_cost
                WHERE transactions_date BETWEEN '{$this->start}' AND '{$this->end}'
                GROUP BY npk
            ) c
        "), 'm.npk', '=', 'c.npk')

            ->leftJoin(DB::raw("
            (
                SELECT npk,
                       COUNT(id) total_onleave
                FROM expat_onleave
                WHERE onleave_start BETWEEN '{$this->start}' AND '{$this->end}'
                GROUP BY npk
            ) l
        "), 'm.npk', '=', 'l.npk')

            ->select(
                'm.npk',
                'm.name',
                'm.position',
                'm.joining_date',
                'm.end_date',
                'm.passport_number',
                'm.passport_expiry',
                'm.kitas_expiry',
                'm.rptka_expiry',
                'm.lease_enddate',

                DB::raw('ISNULL(c.total_living_cost,0) total_living_cost'),
                DB::raw('ISNULL(l.total_onleave,0) total_onleave')
            )
            ->get();


        /*
        | TOTAL AMOUNT ONLEAVE
        */
        $onleaveAmounts = DB::table('expat_onleave')
            ->whereBetween('onleave_start', [$this->start, $this->end])
            ->get(['npk', 'amount']);

        $totalAmountPerNpk = [];

        foreach ($onleaveAmounts as $row) {

            $amountArray = json_decode($row->amount, true);

            if (is_string($amountArray)) {
                $amountArray = json_decode($amountArray, true);
            }

            $amountArray = $amountArray ?? [];

            $total = collect($amountArray)
                ->map(fn($v) => (float) $v)
                ->sum();

            $totalAmountPerNpk[$row->npk] =
                ($totalAmountPerNpk[$row->npk] ?? 0) + $total;
        }

        foreach ($data as $row) {

            $row->total_onleave_amount =
                $totalAmountPerNpk[$row->npk] ?? 0;

            if ($row->kitas_expiry) {

                $diff = $today->diffInDays(
                    \Carbon\Carbon::parse($row->kitas_expiry),
                    false
                );

                $row->kitas_status =
                    $diff < 0 ? 'EXPIRED' : $diff . ' Days';
            } else {
                $row->kitas_status = '-';
            }

            if ($row->rptka_expiry) {

                $diff = $today->diffInDays(
                    \Carbon\Carbon::parse($row->rptka_expiry),
                    false
                );

                $row->rptka_status =
                    $diff < 0 ? 'EXPIRED' : $diff . ' Days';
            } else {
                $row->rptka_status = '-';
            }
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'NPK',
            'Name',
            'Position',
            'Joining',
            'End',
            'Passport',
            'Passport Exp',
            'KITAS Exp',
            'KITAS Status',
            'RPTKA Exp',
            'RPTKA Status',
            'Lease End',
            'Total Living Cost',
            'On Leave Count',
            'On Leave Amount'
        ];
    }

    public function map($row): array
    {
        return [
            $row->npk,
            $row->name,
            $row->position,
            $row->joining_date,
            $row->end_date,
            $row->passport_number,
            $row->passport_expiry,
            $row->kitas_expiry,
            $row->kitas_status,
            $row->rptka_expiry,
            $row->rptka_status,
            $row->lease_enddate,
            $row->total_living_cost,
            $row->total_onleave,
            $row->total_onleave_amount,
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
        | HEADER
        */
        $sheet->getStyle('A1:O1')->applyFromArray([
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
        | FORMAT RUPIAH
        | M = Total Living Cost
        | O = On Leave Amount
        */
        $sheet->getStyle("M2:M{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('"Rp" #,##0');

        $sheet->getStyle("O2:O{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('"Rp" #,##0');

        /*
        | ALIGNMENT
        */
        $sheet->getStyle("A2:A{$highestRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("D2:E{$highestRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("G2:L{$highestRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("N2:N{$highestRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        /*
        | BORDER TABLE
        */
        $sheet->getStyle("A1:O{$highestRow}")
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
