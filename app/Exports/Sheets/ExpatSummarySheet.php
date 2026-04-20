<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ExpatSummarySheet implements FromCollection, WithTitle, WithHeadings, ShouldAutoSize
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

    public function collection()
    {
        return DB::table('expat_master as m')

            ->leftJoin(DB::raw("
                (
                    SELECT npk,SUM(amount) total_living_cost
                    FROM expat_cost
                    WHERE transactions_date BETWEEN '{$this->start}' AND '{$this->end}'
                    GROUP BY npk
                ) c
            "), 'm.npk', '=', 'c.npk')

            ->leftJoin(DB::raw("
                (
                    SELECT npk,COUNT(id) total_onleave
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
                'm.passport_expiry',
                'm.kitas_expiry',
                'm.lease_enddate',
                DB::raw('ISNULL(c.total_living_cost,0) total_living_cost'),
                DB::raw('ISNULL(l.total_onleave,0) total_onleave')
            )
            ->get();
    }

    public function headings(): array
    {
        return [
            'NPK',
            'Name',
            'Position',
            'Joining',
            'End',
            'Passport Exp',
            'KITAS Exp',
            'Lease End',
            'Total Living Cost',
            'On Leave Count'
        ];
    }
}
