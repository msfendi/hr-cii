<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ExpatOnleaveSheet implements FromCollection, WithTitle, WithHeadings, ShouldAutoSize
{
    public function title(): string
    {
        return 'expat_onleave';
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

        // first decode
        $decoded = json_decode($value, true);

        // if still string → decode again
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function collection()
    {
        /*
        |--------------------------------------------------------------------------
        | COMPONENT MASTER
        |--------------------------------------------------------------------------
        */
        $components = DB::table('expat_cost_components')
            ->pluck('component', 'id');

        /*
        |--------------------------------------------------------------------------
        | GET DATA
        |--------------------------------------------------------------------------
        */
        $data = DB::table('expat_onleave as l')
            ->join('expat_master as m', 'm.npk', '=', 'l.npk')
            ->whereBetween('l.onleave_start', [$this->start, $this->end])
            ->select(
                'l.npk',
                'm.name',
                'l.onleave_start',
                'l.onleave_end',
                'l.leave_type',
                'l.component',
                'l.amount',
                'l.transactions_date',
                'l.remark'
            )
            ->orderByDesc('l.id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | EXPLODE JSON → MULTI ROW
        |--------------------------------------------------------------------------
        */
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
                    'leave_type' => $row->leave_type,
                    'component' => $components[$componentId] ?? $componentId,
                    'amount' => $amountArray[$i] ?? 0,
                    'transactions_date' => $dateArray[$i] ?? null,
                    'remark' => $row->remark,
                ]);
            }
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
            'Leave Type',
            'Component',
            'Amount',
            'Transaction Date',
            'Remark'
        ];
    }
}
