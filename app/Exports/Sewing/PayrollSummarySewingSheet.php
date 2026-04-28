<?php

namespace App\Exports\Sewing;

use Illuminate\Support\Facades\DB;
use App\Models\PayrollComponent;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PayrollSummarySewingSheet implements
    FromArray,
    WithTitle,
    WithStyles,
    ShouldAutoSize,
    WithStrictNullComparison,
    WithColumnFormatting
{
    protected $run_id;
    protected $componentCount;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
        $this->componentCount = PayrollComponent::count();
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
    BIODATA UNION
    =====================================================
    */

    private function baseBiodataQuery()
    {
        $aktif = DB::table('BIODATA as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select(
                'b.NPK',
                'b.NAMA_KARYAWAN',
                'b.id_dept',
                'p.TKK',
                'b.IS_STAFF'
            );

        $keluar = DB::table('BIODATA_KELUAR as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select(
                'b.NPK',
                'b.NAMA_KARYAWAN',
                'b.id_dept',
                'p.TKK',
                'b.IS_STAFF'
            );

        return $aktif->union($keluar);
    }

    /*
    =====================================================
    BUILD DATA
    =====================================================
    */

    public function array(): array
    {
        $biodataUnion = $this->baseBiodataQuery();

        $period = DB::table('payroll_runs as pr')
            ->join('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('pr.id', $this->run_id)
            ->select('pp.start_date', 'pp.end_date')
            ->first();

        /*
        =====================================================
        JOIN BIODATA + DEPT
        =====================================================
        */

        $data = DB::query()
            ->fromSub($biodataUnion, 'bio')
            ->join('payroll_run_details as prd', 'prd.employee_npk', '=', 'bio.NPK')
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'bio.id_dept')
            ->where('prd.run_id', $this->run_id)
            ->select(
                'prd.components',
                'bio.TKK',
                'bio.IS_STAFF',
                'd.IS_SEWING'
            )
            ->get();

        $components = PayrollComponent::orderBy('priority')->get();

        /*
        =====================================================
        GROUP CONTAINER
        =====================================================
        */

        $groups = [
            'active_sewing' => [],
            'resign_sewing' => [],
        ];

        $earning = array_fill_keys(array_keys($groups), 0);
        $deduction = array_fill_keys(array_keys($groups), 0);

        /*
        =====================================================
        HITUNG TOTAL
        =====================================================
        */

        foreach ($data as $row) {

            $items = json_decode($row->components, true) ?? [];

            $isResign = $row->TKK &&
                $row->TKK >= $period->start_date &&
                $row->TKK <= $period->end_date;

            /*
            ===============================
            CATEGORY RULE (UPDATED)
            ===============================
            */

            $isStaff = $row->IS_STAFF == 1;
            $isSewing = $row->IS_STAFF == 0 && $row->IS_SEWING == 0;
            $isNonSewing = $row->IS_STAFF == 0 && $row->IS_SEWING == 1;

            $targetGroups = [];

            if ($isResign) {
                if ($isSewing)
                    $targetGroups[] = 'resign_sewing';
            } else {
                if ($isSewing)
                    $targetGroups[] = 'active_sewing';
            }

            foreach ($components as $component) {

                $code = $component->code;

                $value = isset($items[$code])
                    ? (float)$items[$code]
                    : 0.0;

                foreach ($targetGroups as $grp) {
                    $groups[$grp][$code] =
                        ($groups[$grp][$code] ?? 0) + $value;
                }
            }
        }

        /*
        =====================================================
        BUILD EXCEL
        =====================================================
        */

        $header = [
            'Component',
            'Active Sewing',
            'Resign Sewing',
        ];

        $rows[] = $header;

        foreach ($components as $component) {

            $row = [$component->name];

            foreach ($groups as $key => $values) {

                $val = (float)($values[$component->code] ?? 0);

                if ($component->type === 'deduction') {
                    $val = -abs($val);
                    $deduction[$key] += $val;
                } else {
                    $earning[$key] += $val;
                }

                $row[] = $val;
            }

            $rows[] = $row;
        }

        $rows[] = array_fill(0, count($header), '');

        /*
        =====================================================
        TOTAL ROWS
        =====================================================
        */

        $totalEarning = ['Total Earning'];
        $totalDeduction = ['Total Deduction'];
        $net = ['Net Payroll'];

        foreach ($groups as $key => $v) {
            $totalEarning[] = $earning[$key];
            $totalDeduction[] = $deduction[$key];
            $net[] = $earning[$key] + $deduction[$key];
        }

        $rows[] = $totalEarning;
        $rows[] = $totalDeduction;
        $rows[] = $net;

        return $rows;
    }

    /*
    =====================================================
    STYLE
    =====================================================
    */

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('B2:Z500')
            ->getNumberFormat()
            ->setFormatCode('"Rp" #,##0');
    }
}
