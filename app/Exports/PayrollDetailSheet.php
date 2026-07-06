<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PayrollDetailSheet
{
    protected $run_id;
    protected $componentTypes = [];

    public function __construct($run_id)
    {
        $this->run_id = $run_id;

        $this->componentTypes = DB::table('payroll_components')
            ->pluck('type', 'code')
            ->toArray();
    }

    public function title(): string
    {
        return 'Payroll_Active';
    }

    public function exportToSheet(Spreadsheet $spreadsheet, int $sheetIndex = 0)
    {
        $sheet = $sheetIndex === 0
            ? $spreadsheet->getActiveSheet()
            : $spreadsheet->createSheet($sheetIndex);

        $sheet->setTitle($this->title());

        // ======================
        // HEADER
        // ======================
        $headings = $this->headings();

        $col = 1;
        foreach ($headings as $heading) {
            $sheet->setCellValueByColumnAndRow($col, 1, $heading);
            $col++;
        }

        // STYLE HEADER
        $lastCol = chr(64 + count($headings));

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // ======================
        // DATA
        // ======================
        $rows = $this->query()->get();

        $rowNum = 2;

        foreach ($rows as $row) {
            $data = $this->map($row);

            $col = 1;
            foreach ($data as $value) {
                $sheet->setCellValueByColumnAndRow($col, $rowNum, $value);
                $col++;
            }

            $rowNum++;
        }

        // ======================
        // BORDER TABLE
        // ======================
        $sheet->getStyle("A1:{$lastCol}" . ($rowNum - 1))
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);

        // ======================
        // NUMBER FORMAT
        // ======================
        foreach (range('D', $lastCol) as $colLetter) {
            $sheet->getStyle("{$colLetter}2:{$colLetter}{$rowNum}")
                ->getNumberFormat()
                ->setFormatCode('"Rp" #,##0;[Red]-"Rp" #,##0');
        }

        // ======================
        // AUTO SIZE COLUMN
        // ======================
        foreach (range('A', $lastCol) as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
    }

    private function baseBiodataQuery()
    {
        $biodataAktif = DB::table('BIODATA as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.NAMA_KARYAWAN', 'b.id_dept', 'p.TKK', 'b.IS_STAFF');

        $biodataKeluar = DB::table('BIODATA_KELUAR as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.NAMA_KARYAWAN', 'b.id_dept', 'p.TKK', 'b.IS_STAFF');

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
            ->where(function ($query) {
            $query->whereNull('bio.TKK')
                  ->orWhereColumn('bio.TKK', '>', 'pp.end_date');
        })
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
            'heat_insentif',
            'adjusment',
            'bpjs_kesehatan',
            'bpjs_ketenagakerjaan',
            'pph_21',
            'pph_21_deduction',
            'absence_deduction',
            'late_deduction',
            'work_leave_deduction'
        ];

        $values = [];

        foreach ($fields as $field) {
            $component = $components[$field] ?? null;

            if (is_array($component)) {
                // Format baru: {"amount": ..., "type": "earning|deduction"}
                $value = (float)($component['amount'] ?? 0);
                $type  = $component['type'] ?? ($this->componentTypes[$field] ?? 'earning');
            } else {
                // Fallback untuk format lama: nilai langsung berupa angka
                $value = (float)($component ?? 0);
                $type  = $this->componentTypes[$field] ?? 'earning';
            }

            if ($type === 'deduction') {
                $value = -abs($value);
            }
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
            'Heat Seal Insentif',
            'Adjusment',
            'BPJS Kesehatan',
            'BPJS Ketenagakerjaan',
            'PPH21',
            'PPH21 Deduction',
            'Absence Deduction',
            'Late Deduction',
            'Work Leave Deduction',
            'Total Salary'
        ];
    }
}
