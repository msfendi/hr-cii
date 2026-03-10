<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PayrollDetailSheet implements FromQuery, WithMapping, WithHeadings, WithChunkReading, ShouldAutoSize
{
    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function query()
    {
        return DB::table('payroll_run_details as prd')
            ->leftJoin('BIODATA as b', 'b.NPK', '=', 'prd.employee_npk')
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'b.id_dept')
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('prd.run_id', $this->run_id)
            ->select(
                'prd.*',
                'd.DEPARTEMENT as departement',
                'pp.name as period_name'
            )
            ->orderBy('d.DEPARTEMENT')
            ->orderBy('prd.employee_npk');
    }

    public function map($row): array
    {
        $components = json_decode($row->components, true);

        return [

            $row->employee_npk,
            $row->employee_name,
            $row->departement,

            $components['basic_salary'] ?? 0,
            $components['overtime_pay'] ?? 0,
            $components['special_overtime_pay'] ?? 0,
            $components['monthly_premi'] ?? 0,
            $components['long_service_allowance'] ?? 0,
            $components['allowance'] ?? 0,

            $components['bpjs_kesehatan'] ?? 0,
            $components['bpjs_ketenagakerjaan'] ?? 0,

            $components['pph_21'] ?? 0,
            $components['pph_21_deduction'] ?? 0,
            $components['absence_deduction'] ?? 0,

            $row->total_salary
        ];
    }

    public function headings(): array
    {
        return [

            'NPK',
            'Employee Name',
            'Departement',

            'Basic Salary',
            'Overtime Weekday',
            'Overtime Weekend',
            'Monthly Premi',
            'Long Service Allowance',
            'Allowance',

            'BPJS Kesehatan',
            'BPJS Ketenagakerjaan',

            'PPH21',
            'PPH21 Deduction',
            'Absence Deduction',

            'Total Salary'
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
