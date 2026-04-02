<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class ThrDetailSheet implements
    FromQuery,
    WithMapping,
    WithHeadings,
    WithChunkReading,
    ShouldAutoSize,
    WithTitle
{
    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function title(): string
    {
        return 'THR';
    }

    public function query()
    {
        return DB::table('thr_run_details as trd')
            ->join('thr_runs as tr', 'tr.id', '=', 'trd.run_id')
            ->join('thr_periods as tp', 'tp.id', '=', 'tr.period_id')
            ->where('trd.run_id', $this->run_id)
            ->orderBy('trd.employee_npk') // ⭐ WAJIB
            ->select(
                'trd.employee_npk',
                'trd.employee_name',
                'trd.components',
                'trd.total_salary',
                'tp.name as period'
            );
    }

    public function map($row): array
    {
        $components = [];

        if (!empty($row->components)) {
            $components = json_decode($row->components, true) ?? [];
        }

        return [
            $row->employee_npk,
            $row->employee_name,
            $components['basic_salary'] ?? 0,
            $components['allowance'] ?? 0,
            $components['working_months'] ?? 0,
            $row->total_salary
        ];
    }

    public function headings(): array
    {
        return [
            'NPK',
            'Employee',
            'Salary',
            'Allowance',
            'Working Months',
            'THR'
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
