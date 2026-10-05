<?php

namespace App\Exports\Staff;

use Illuminate\Support\Facades\DB;
use App\Models\PayrollComponent;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class PayrollSummaryStaffSheet
{
    protected $run_id;
    protected $components;
    protected $period;

    protected $groups = [
        'active_staff' => [],
        'resign_staff' => [],
        'mangkir_staff' => [],
    ];

    protected $earning = [];
    protected $deduction = [];
    protected $adjustment = []; // selisih staff expat, ditambahkan ke Net Payroll
    protected $countDays = 0;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;

        $this->components = PayrollComponent::orderBy('priority')->get();

        foreach ($this->groups as $k => $v) {
            $this->earning[$k] = 0;
            $this->deduction[$k] = 0;
            $this->adjustment[$k] = 0;
        }

        $this->period = DB::table('payroll_runs as pr')
            ->join('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('pr.id', $this->run_id)
            ->select('pp.start_date', 'pp.end_date')
            ->first();

        $this->countDays = \Carbon\Carbon::parse($this->period->start_date)
            ->diffInDays(\Carbon\Carbon::parse($this->period->end_date)) + 1;
    }

    /**
     * Subquery kontrak terbaru yang berlaku di periode (sama seperti GeneratePayrollProcess).
     * Dipakai untuk mengambil salary & daily_salary karyawan.
     */
    protected function latestContractSub()
    {
        $periodStart = $this->period->start_date;
        $periodEnd   = $this->period->end_date;

        return DB::table('employees_contract as ec1')
            ->select('ec1.npk', 'ec1.salary', 'ec1.daily_salary', 'ec1.type')
            ->whereDate('ec1.start_date', '<=', $periodEnd)
            ->whereDate('ec1.end_date', '>=', $periodStart)
            ->whereRaw("
                ec1.id = (
                    SELECT TOP 1 ec2.id
                    FROM employees_contract ec2
                    WHERE ec2.npk = ec1.npk
                      AND ec2.start_date <= ?
                      AND ec2.end_date >= ?
                    ORDER BY ec2.contract_ke DESC,
                             ec2.start_date DESC
                )
            ", [$periodEnd, $periodStart]);
    }

    /**
     * Selisih khusus STAFF + EXPAT bertipe Daily:
     * (daily_salary * count_days) - salary
     * Expat bertipe Contract tidak kena (daily_salary = 0).
     */
    protected function expatStaffDiff($row): float
    {
        if ($row->IS_STAFF_PRD != 1 || $row->IS_EXPAT_PRD != 1) {
            return 0.0;
        }

        if (strtolower(trim($row->contract_type ?? '')) !== 'daily' || (float) $row->daily_salary <= 0) {
            return 0.0;
        }

        return ((float) $row->daily_salary * (float) $this->countDays) - (float) $row->salary;
    }


    /**
     * Tentukan tabel payroll_run_details yang dipakai berdasarkan route saat ini.
     * - payroll.exportaudit.export -> payroll_run_details_audit
     * - payroll.export.export (default) -> payroll_run_details
     */
    protected function detailsTable(): string
    {
        $routeName = optional(request()->route())->getName();

        return $routeName === 'payroll.exportaudit.export'
            ? 'payroll_run_details_audit'
            : 'payroll_run_details';
    }

    public function title(): string
    {
        return 'Payroll_Summary';
    }

    public function exportToSheet(Spreadsheet $spreadsheet, int $sheetIndex)
    {
        $sheet = $sheetIndex === 0
            ? $spreadsheet->getActiveSheet()
            : $spreadsheet->createSheet($sheetIndex);

        $sheet->setTitle($this->title());

        // =========================
        // HEADER
        // =========================
        $headingRows = $this->headings();

        $rowNum = 1;
        foreach ($headingRows as $row) {
            $col = 1;
            foreach ($row as $value) {
                $sheet->setCellValueByColumnAndRow($col, $rowNum, $value);
                $col++;
            }
            $rowNum++;
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headingRows[0]));

        // =========================
        // HEADER STYLE
        // =========================
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E79']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // =========================
        // QUERY + MAP (TETAP)
        // =========================
        $dataRows = $this->query()->get();

        foreach ($dataRows as $row) {
            $this->map($row);
        }

        // =========================
        // AFTER SHEET LOGIC (TETAP)
        // =========================
        $rowStart = 2;

        foreach ($this->components as $i => $component) {

            $row = $rowStart + $i;

            foreach ($this->groups as $group => $v) {

                $val = $this->groups[$group][$component->code] ?? 0;

                if ($component->type === 'deduction') {
                    $val = -abs($val);
                    $this->deduction[$group] += $val;
                } else {
                    $this->earning[$group] += $val;
                }

                $col = $this->getColumnIndex($group);

                $sheet->setCellValue($col . $row, $val);
            }
        }

        $base = count($this->components) + 3;

        foreach (array_keys($this->groups) as $grp) {

            $col = $this->getColumnIndex($grp);

            $sheet->setCellValue($col . $base, $this->earning[$grp]);
            $sheet->setCellValue($col . ($base + 1), $this->deduction[$grp]);
            $sheet->setCellValue($col . ($base + 2), $this->earning[$grp] + $this->deduction[$grp] + $this->adjustment[$grp]);
        }

        // =========================
        // BORDER TABLE
        // =========================
        $sheet->getStyle("A1:{$lastCol}" . ($rowNum - 1))
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);

        // =========================
        // NUMBER FORMAT
        // =========================
        foreach (range('B', $lastCol) as $colLetter) {
            $sheet->getStyle("{$colLetter}2:{$colLetter}{$rowNum}")
                ->getNumberFormat()
                ->setFormatCode('"Rp" #,##0;[Red]-"Rp" #,##0');
        }

        // =========================
        // AUTO SIZE COLUMN
        // =========================
        for ($i = 1; $i <= count($headingRows[0]); $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // =========================
        // FREEZE HEADER
        // =========================
        $sheet->freezePane('A2');
    }

    /*
    =====================================================
    QUERY (TIDAK DIUBAH)
    =====================================================
    */
    public function query()
    {
        $aktif = DB::table('BIODATA as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.ID_DEPT', 'p.TKK', 'p.TMK', 'b.IS_STAFF', 'p.KETERANGAN');

        $keluar = DB::table('BIODATA_KELUAR as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.ID_DEPT', 'p.TKK', 'p.TMK', 'b.IS_STAFF', 'p.KETERANGAN');

        $union = $aktif->union($keluar);

        return DB::table($this->detailsTable() . ' as prd')
            ->leftJoinSub($union, 'bio', function ($join) {
                $join->on('bio.NPK', '=', 'prd.employee_npk');
            })
            ->leftJoinSub($this->latestContractSub(), 'ec', function ($join) {
                $join->on('ec.npk', '=', 'prd.employee_npk');
            })
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'prd.employee_dept')
            ->where('prd.run_id', $this->run_id)
            ->where('prd.employee_staff', 1)
            ->select(
                'bio.NPK',
                'prd.components',
                'bio.TKK',
                'bio.TMK',
                'bio.IS_STAFF',
                'd.IS_SEWING',
                'bio.KETERANGAN',
                'prd.employee_staff as IS_STAFF_PRD',
                'prd.employee_expat as IS_EXPAT_PRD',
                'ec.salary',
                'ec.daily_salary',
                'ec.type as contract_type',
            );
    }

    /*
    =====================================================
    MAP (LOGIC ASLI — TIDAK DIUBAH)
    =====================================================
    */
    public function map($row): array
    {
        $items = json_decode($row->components, true) ?? [];

        $keterangan = strtoupper(trim($row->KETERANGAN ?? ''));
        $tkk = $row->TKK ? \Carbon\Carbon::parse($row->TKK) : null;
        $tmk = !empty($row->TMK) ? \Carbon\Carbon::parse($row->TMK) : null;

        $periodStart = \Carbon\Carbon::parse($this->period->start_date);
        $periodEnd   = \Carbon\Carbon::parse($this->period->end_date);

        $isTMKInPeriod = $tmk && $tmk->betweenIncluded($periodStart, $periodEnd);

        $isMangkir =
            !is_null($tkk) &&
            $keterangan === 'MA' &&
            $tkk->betweenIncluded($periodStart, $periodEnd);

        $isResign =
            !is_null($tkk) &&
            $keterangan !== 'MA' &&
            $tkk->betweenIncluded($periodStart, $periodEnd);

        $isActive =
            is_null($tkk) || $tkk->greaterThan($periodEnd);

        $isStaff = $row->IS_STAFF == 1;

        $groups = [];

        if ($isMangkir) {
            if ($isStaff) {
                $groups[] = 'mangkir_staff';
            }
        } elseif ($isResign) {
            if ($isStaff) {
                $groups[] = 'resign_staff';
            }
        } elseif ($isActive) {
            if ($isStaff) {
                $groups[] = 'active_staff';
            }
        }

        // Selisih staff expat -> masuk ke Net Payroll tiap group yang cocok
        $diff = $this->expatStaffDiff($row);
        if ($diff != 0) {
            foreach ($groups as $grp) {
                $this->adjustment[$grp] += $diff;
            }
        }

        foreach ($this->components as $component) {

            $code = $component->code;

            $item = $items[$code] ?? null;

            if (is_array($item)) {
                // Format baru: {"amount": ..., "type": "earning|deduction"}
                $value = (float)($item['amount'] ?? 0);
            } else {
                // Fallback untuk format lama: nilai langsung berupa angka
                $value = (float)($item ?? 0);
            }

            foreach ($groups as $grp) {
                $this->groups[$grp][$code] =
                    ($this->groups[$grp][$code] ?? 0) + $value;
            }
        }

        return [];
    }

    /*
    =====================================================
    HEADINGS (TEMPLATE SAMA)
    =====================================================
    */
    public function headings(): array
    {
        $rows = [];

        $header = [
            'Component',
            'Active Staff',
            'Resign Staff',
            'Mangkir Staff',
        ];

        $rows[] = $header;

        foreach ($this->components as $c) {
            $rows[] = [
                $c->name,
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ];
        }

        $rows[] = array_fill(0, count($header), '');

        $rows[] = ['Total Earning'];
        $rows[] = ['Total Deduction'];
        $rows[] = ['Net Payroll'];

        return $rows;
    }

    private function getColumnIndex($group)
    {
        return [
            'active_staff' => 'B',
            'resign_staff' => 'C',
            'mangkir_staff' => 'D',
        ][$group] ?? 'B';
    }
}
