<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ExpatCostSheet implements FromCollection, WithTitle, WithHeadings, ShouldAutoSize
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

    public function collection()
    {
        return DB::table('expat_cost as c')
            ->join('expat_master as m', 'm.npk', '=', 'c.npk')
            ->whereBetween('c.transactions_date', [$this->start, $this->end])
            ->select(
                'c.npk',
                'm.name',
                'c.component',
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
}
