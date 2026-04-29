<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use App\Models\PayrollComponent;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

use Maatwebsite\Excel\Events\AfterSheet;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PayrollSummarySheet implements
    FromQuery,
    WithMapping,
    WithHeadings,
    WithEvents,
    WithTitle,
    WithStyles,
    ShouldAutoSize,
    WithStrictNullComparison,
    WithColumnFormatting
{
    protected $run_id;
    protected $components;
    protected $period;

    protected $groups = [
        'active_all' => [],
        'active_staff' => [],
        'active_sewing' => [],
        'active_non_sewing' => [],
        'resign_all' => [],
        'resign_staff' => [],
        'resign_sewing' => [],
        'resign_non_sewing' => [],
    ];

    protected $earning = [];
    protected $deduction = [];

    public function __construct($run_id)
    {
        $this->run_id = $run_id;

        $this->components = PayrollComponent::orderBy('priority')->get();

        foreach ($this->groups as $k => $v) {
            $this->earning[$k] = 0;
            $this->deduction[$k] = 0;
        }

        $this->period = DB::table('payroll_runs as pr')
            ->join('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('pr.id', $this->run_id)
            ->select('pp.start_date', 'pp.end_date')
            ->first();
    }

    public function title(): string
    {
        return 'Payroll_Summary';
    }

    public function columnFormats(): array
    {
        return [
            'B:Z' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    /*
    =====================================================
    QUERY
    =====================================================
    */
    public function query()
    {
        $aktif = DB::table('BIODATA as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.id_dept', 'p.TKK', 'b.IS_STAFF');

        $keluar = DB::table('BIODATA_KELUAR as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.id_dept', 'p.TKK', 'b.IS_STAFF');

        $union = $aktif->union($keluar);

        return DB::query()
            ->fromSub($union, 'bio')
            ->join('payroll_run_details as prd', 'prd.employee_npk', '=', 'bio.NPK')
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'bio.id_dept')
            ->where('prd.run_id', $this->run_id)
            ->select(
                'bio.NPK',
                'prd.components',
                'bio.TKK',
                'bio.IS_STAFF',
                'd.IS_SEWING'
            );
    }

    /*
    =====================================================
    MAP (LOGIC ORIGINAL — TIDAK DIUBAH)
    =====================================================
    */
    public function map($row): array
    {
        $items = json_decode($row->components, true) ?? [];

        $isResign =
            $row->TKK &&
            $row->TKK >= $this->period->start_date &&
            $row->TKK <= $this->period->end_date;

        $isStaff = $row->IS_STAFF == 1;
        $isSewing = $row->IS_STAFF == 0 && $row->IS_SEWING == 0;
        $isNonSewing = $row->IS_STAFF == 0 && $row->IS_SEWING == 1;

        $targets = [];

        if ($isResign) {
            $targets[] = 'resign_all';
            if ($isStaff) $targets[] = 'resign_staff';
            if ($isSewing) $targets[] = 'resign_sewing';
            if ($isNonSewing) $targets[] = 'resign_non_sewing';
        } else {
            $targets[] = 'active_all';
            if ($isStaff) $targets[] = 'active_staff';
            if ($isSewing) $targets[] = 'active_sewing';
            if ($isNonSewing) $targets[] = 'active_non_sewing';
        }

        foreach ($this->components as $component) {

            $code = $component->code;
            $value = (float)($items[$code] ?? 0);

            foreach ($targets as $grp) {
                $this->groups[$grp][$code] =
                    ($this->groups[$grp][$code] ?? 0) + $value;
            }
        }

        return [];
    }

    /*
    =====================================================
    HEADINGS
    =====================================================
    */
    public function headings(): array
    {
        $rows = [];

        $header = [
            'Component',
            'Active All',
            'Active Staff',
            'Active Sewing',
            'Active Non Sewing',
            'Resign All',
            'Resign Staff',
            'Resign Sewing',
            'Resign Non Sewing',
        ];

        $rows[] = $header;

        foreach ($this->components as $c) {
            $rows[] = [$c->name, '', '', '', '', '', '', '', ''];
        }

        $rows[] = array_fill(0, count($header), '');
        $rows[] = ['Total Earning'];
        $rows[] = ['Total Deduction'];
        $rows[] = ['Net Payroll'];

        return $rows;
    }

    /*
    =====================================================
    AFTER SHEET — UNIVERSAL VERSION
    =====================================================
    */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function ($event) {

                // 🔥 UNIVERSAL SHEET DETECTOR
                $sheet = $event->sheet;

                if (method_exists($sheet, 'getDelegate')) {
                    $sheet = $sheet->getDelegate();
                }

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

                        if (method_exists($sheet, 'setCellValue')) {
                            $sheet->setCellValue($col . $row, $val);
                        }
                    }
                }

                $base = count($this->components) + 3;

                foreach (array_keys($this->groups) as $grp) {

                    $col = $this->getColumnIndex($grp);

                    $sheet->setCellValue(
                        $col . $base,
                        $this->earning[$grp]
                    );

                    $sheet->setCellValue(
                        $col . ($base + 1),
                        $this->deduction[$grp]
                    );

                    $sheet->setCellValue(
                        $col . ($base + 2),
                        $this->earning[$grp] + $this->deduction[$grp]
                    );
                }
            }
        ];
    }

    private function getColumnIndex($group)
    {
        return [
            'active_all' => 'B',
            'active_staff' => 'C',
            'active_sewing' => 'D',
            'active_non_sewing' => 'E',
            'resign_all' => 'F',
            'resign_staff' => 'G',
            'resign_sewing' => 'H',
            'resign_non_sewing' => 'I',
        ][$group] ?? 'B';
    }

    /*
    =====================================================
    STYLE (Laravel Excel ONLY)
    =====================================================
    */
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('B2:Z500')
            ->getNumberFormat()
            ->setFormatCode('"Rp" #,##0');
    }
}
