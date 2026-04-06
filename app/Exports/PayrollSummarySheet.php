<?php

namespace App\Exports;

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
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PayrollSummarySheet implements
    FromArray,
    WithTitle,
    WithCharts,
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

    /*
    =====================================================
    FORMAT ANGKA
    =====================================================
    */

    public function columnFormats(): array
    {
        return [
            'B:Z' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    /*
    =====================================================
    BUILD DATA
    =====================================================
    */

    public function array(): array
    {
        $period = DB::table('payroll_runs as pr')
            ->join('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('pr.id', $this->run_id)
            ->select('pp.start_date', 'pp.end_date')
            ->first();

        $data = DB::table('payroll_run_details as prd')
            ->leftJoin('PKWT as p', 'p.NPK', '=', 'prd.employee_npk')
            ->where('prd.run_id', $this->run_id)
            ->select('prd.components', 'p.TKK')
            ->get();

        $components = PayrollComponent::orderBy('priority')->get();

        $totalsActive = [];
        $totalsResign = [];

        $earningActive = 0;
        $deductionActive = 0;
        $earningResign = 0;
        $deductionResign = 0;

        /*
        =====================================================
        HITUNG TOTAL
        =====================================================
        */

        foreach ($data as $row) {

            // ⭐ FORCE ARRAY
            $items = json_decode($row->components, true) ?? [];

            $isResign = $row->TKK &&
                $row->TKK >= $period->start_date &&
                $row->TKK <= $period->end_date;

            foreach ($components as $component) {

                $code = $component->code;

                // ⭐ FORCE ZERO
                $value = isset($items[$code])
                    ? (float)$items[$code]
                    : 0.0;

                if ($isResign) {
                    $totalsResign[$code] =
                        (float)($totalsResign[$code] ?? 0) + $value;
                } else {
                    $totalsActive[$code] =
                        (float)($totalsActive[$code] ?? 0) + $value;
                }
            }
        }

        /*
        =====================================================
        BUILD EXCEL ROW
        =====================================================
        */

        $rows = [];
        $rows[] = ['Component', 'Active Payroll', 'Resigned Payroll', 'Total'];

        foreach ($components as $component) {

            $activeValue = (float)($totalsActive[$component->code] ?? 0);
            $resignValue = (float)($totalsResign[$component->code] ?? 0);

            if ($component->type === 'deduction') {

                $activeValue = -abs($activeValue);
                $resignValue = -abs($resignValue);

                $deductionActive += $activeValue;
                $deductionResign += $resignValue;
            } else {

                $earningActive += $activeValue;
                $earningResign += $resignValue;
            }

            // ⭐ FORCE NUMERIC ZERO
            $rows[] = [
                $component->name,
                (float)$activeValue,
                (float)$resignValue,
                (float)($activeValue + $resignValue),
            ];
        }

        $rows[] = ['', '', '', ''];

        $rows[] = [
            'Total Earning',
            (float)$earningActive,
            (float)$earningResign,
            (float)($earningActive + $earningResign),
        ];

        $rows[] = [
            'Total Deduction',
            (float)$deductionActive,
            (float)$deductionResign,
            (float)($deductionActive + $deductionResign),
        ];

        $rows[] = [
            'Net Payroll',
            (float)($earningActive + $deductionActive),
            (float)($earningResign + $deductionResign),
            (float)(
                ($earningActive + $deductionActive) +
                ($earningResign + $deductionResign)
            ),
        ];

        return $rows;
    }

    /*
    =====================================================
    STYLE
    =====================================================
    */

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('B2:D500')
            ->getNumberFormat()
            ->setFormatCode('"Rp" #,##0');
    }

    /*
    =====================================================
    CHART
    =====================================================
    */

    public function charts()
    {
        $startRow = 2;
        $endRow = $this->componentCount + 1;

        $labelRange  = "Payroll_Summary!\$A\${$startRow}:\$A\${$endRow}";
        $activeRange = "Payroll_Summary!\$B\${$startRow}:\$B\${$endRow}";
        $resignRange = "Payroll_Summary!\$C\${$startRow}:\$C\${$endRow}";
        $totalRange  = "Payroll_Summary!\$D\${$startRow}:\$D\${$endRow}";

        $labels = [
            new DataSeriesValues('String', $labelRange, null, $this->componentCount),
        ];

        $seriesLabels = [
            new DataSeriesValues('String', 'Payroll_Summary!$B$1', null, 1),
            new DataSeriesValues('String', 'Payroll_Summary!$C$1', null, 1),
            new DataSeriesValues('String', 'Payroll_Summary!$D$1', null, 1),
        ];

        $values = [
            new DataSeriesValues('Number', $activeRange, null, $this->componentCount),
            new DataSeriesValues('Number', $resignRange, null, $this->componentCount),
            new DataSeriesValues('Number', $totalRange, null, $this->componentCount),
        ];

        $layout = new Layout();
        $layout->setShowVal(false);

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($values) - 1),
            $seriesLabels,
            $labels,
            $values
        );

        $series->setPlotDirection(DataSeries::DIRECTION_COL);

        $plot = new PlotArea($layout, [$series]);

        $chart = new Chart(
            'Payroll Chart',
            new Title('Payroll Component Summary'),
            new Legend(Legend::POSITION_RIGHT, null, false),
            $plot
        );

        $chart->setTopLeftPosition('F2');
        $chart->setBottomRightPosition('T28');

        return $chart;
    }
}
