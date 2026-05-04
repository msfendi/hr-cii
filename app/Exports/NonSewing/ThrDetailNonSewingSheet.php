<?php

namespace App\Exports\NonSewing;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ThrDetailNonSewingSheet
{
    protected $run_id;
    protected $componentTypes = [];

    public function __construct($run_id)
    {
        $this->run_id = $run_id;

        // Ambil tipe komponen: earning / deduction
        $this->componentTypes = DB::table('payroll_components')
            ->pluck('type', 'code')
            ->toArray();
    }

    public function title(): string
    {
        return 'Thr_NonSewing_Active';
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
        foreach (range('D', 'Z') as $colLetter) {
            $sheet->getStyle("{$colLetter}2:{$colLetter}{$rowNum}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_NUMBER);
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

        return DB::table('thr_run_details as prd')
            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('bio.NPK', '=', 'prd.employee_npk');
            })
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'bio.id_dept')
            ->leftJoin('thr_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('thr_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('prd.run_id', $this->run_id)
            ->where('bio.IS_STAFF', 0)
            ->where('d.IS_SEWING', 1)
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
            'allowance',
            'working_months',
        ];

        $values = [];

        foreach ($fields as $field) {
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
            'Employee',
            'Department',
            'Salary',
            'Allowance',
            'Working Months',
            'THR'
        ];
    }
}
