<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class PayrollOutDetailSheet implements FromQuery, WithMapping, WithHeadings, WithChunkReading,  WithTitle, ShouldAutoSize
{
    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }
    public function title(): string
    {
        return 'Payroll_Out';
    }

    private function baseBiodataQuery()
    {
        $biodataAktif = DB::table('BIODATA as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select(
                'b.NPK',
                'b.NAMA_KARYAWAN',
                'b.id_dept',
                'p.TKK'
            );

        $biodataKeluar = DB::table('BIODATA_KELUAR as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select(
                'b.NPK',
                'b.NAMA_KARYAWAN',
                'b.id_dept',
                'p.TKK'
            );

        return $biodataAktif->union($biodataKeluar);
    }

    public function query()
    {
        $period = DB::table('payroll_runs as pr')
            ->join('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('pr.id', $this->run_id)
            ->select('pp.start_date', 'pp.end_date')
            ->first();

        $biodataUnion = $this->baseBiodataQuery();

        return DB::table('payroll_run_details as prd')

            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('bio.NPK', '=', 'prd.employee_npk');
            })

            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'bio.id_dept')
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')

            ->where('prd.run_id', $this->run_id)
            ->whereBetween('bio.TKK', [$period->start_date, $period->end_date])

            ->select(
                'prd.*',
                'bio.NAMA_KARYAWAN',
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
            $components['sewing_insentif'] ?? 0,
            $components['pad_insentif'] ?? 0,
            $components['cutting_insentif'] ?? 0,
            $components['adjusment'] ?? 0,

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
            'Sewing Insentif',
            'Pad Print Insentif',
            'Cutting Insentif',
            'Adjusment',

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
