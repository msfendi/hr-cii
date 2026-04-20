<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PayrollDetailSheet implements FromQuery, WithMapping, WithHeadings, WithChunkReading, WithTitle, ShouldAutoSize, WithStrictNullComparison, WithColumnFormatting
{
    protected $run_id;
    protected $componentTypes = [];

    public function __construct($run_id)
    {
        $this->run_id = $run_id;

        // Ambil tipe komponen: earning / deduction
        $this->componentTypes = DB::table('payroll_components')
            ->pluck('type', 'code') // ['thr' => 'earning', 'pph_21' => 'deduction', ...]
            ->toArray();
    }

    public function title(): string
    {
        return 'Payroll_Active';
    }

    public function columnFormats(): array
    {
        return [
            'D:Z' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    private function baseBiodataQuery()
    {
        $biodataAktif = DB::table('BIODATA as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.NAMA_KARYAWAN', 'b.id_dept', 'p.TKK');

        $biodataKeluar = DB::table('BIODATA_KELUAR as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.NAMA_KARYAWAN', 'b.id_dept', 'p.TKK');

        return $biodataAktif->union($biodataKeluar);
    }

    public function query()
    {
        $biodataUnion = $this->baseBiodataQuery();

        return DB::table('payroll_run_details as prd')
            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('bio.NPK', '=', 'prd.employee_npk');
            })
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'bio.id_dept')
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('prd.run_id', $this->run_id)
            ->whereNull('bio.TKK')
            ->select('prd.*', 'bio.NAMA_KARYAWAN', 'd.DEPARTEMENT as departement', 'pp.name as period_name')
            ->orderBy('d.DEPARTEMENT')
            ->orderBy('prd.employee_npk');
    }

    public function map($row): array
    {
        $components = json_decode($row->components, true) ?? [];

        $fields = [
            'basic_salary',
            'overtime_pay',
            'special_overtime_pay',
            'monthly_premi',
            'long_service_allowance',
            'allowance',
            'sewing_insentif',
            'pad_insentif',
            'cutting_insentif',
            'adjusment',
            'bpjs_kesehatan',
            'bpjs_ketenagakerjaan',
            'pph_21',
            'pph_21_deduction',
            'absence_deduction',
            'late_deduction'
        ];

        $values = [];

        foreach ($fields as $field) {
            // Gunakan array_key_exists supaya 0 tetap muncul
            $value = array_key_exists($field, $components) ? (float)$components[$field] : 0;
            $type  = $this->componentTypes[$field] ?? 'earning';

            if ($type === 'deduction') {
                $value = -abs($value);
            }

            $values[] = $value;
        }

        return array_merge([
            $row->employee_npk,
            $row->employee_name,
            $row->departement
        ], $values, [
            array_key_exists('total_salary', (array)$row) ? (float)$row->total_salary : 0
        ]);
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
            'Late Deduction',
            'Total Salary'
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
