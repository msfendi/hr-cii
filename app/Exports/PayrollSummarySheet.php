<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use App\Models\PayrollComponent;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use Maatwebsite\Excel\Concerns\WithCharts;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PayrollSummarySheet implements FromArray, WithTitle, WithCharts, WithStyles, ShouldAutoSize
{

    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function title(): string
    {
        return 'Payroll_Summary';
    }

    public function array(): array
    {

        $data = DB::table('payroll_run_details')
            ->where('run_id', $this->run_id)
            ->get();

        $components = PayrollComponent::orderBy('priority')->get();

        $totals = [];
        $earning = 0;
        $deduction = 0;

        foreach ($data as $row) {

            $items = json_decode($row->components, true);

            foreach ($items as $code => $value) {

                $totals[$code] = ($totals[$code] ?? 0) + $value;
            }
        }

        $rows[] = ['Component', 'Type', 'Total'];

        foreach ($components as $component) {

            $value = $totals[$component->code] ?? 0;

            if ($component->type == 'deduction') {
                $value = -$value;
                $deduction += $value;
            } else {
                $earning += $value;
            }

            $rows[] = [
                $component->name,
                strtoupper($component->type),
                $value
            ];
        }

        $rows[] = ['', '', ''];
        $rows[] = ['Total Earning', '', $earning];
        $rows[] = ['Total Deduction', '', $deduction];
        $rows[] = ['Net Payroll', '', $earning + $deduction];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('C2:C100')
            ->getNumberFormat()
            ->setFormatCode('"Rp" #,##0');
    }

    public function charts()
    {

        $labels = [
            new DataSeriesValues('String', 'Payroll_Summary!$A$2:$A$12', null, 11),
        ];

        $values = [
            new DataSeriesValues('Number', 'Payroll_Summary!$C$2:$C$12', null, 11),
        ];

        $layout = new Layout();
        $layout->setShowVal(true);
        $layout->setNumFmtCode('"Rp" #,##0');

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($values) - 1),
            [],
            $labels,
            $values
        );

        $plot = new PlotArea(null, [$series]);

        $chart = new Chart(
            'Payroll Chart',
            new Title('Payroll Component Summary'),
            new Legend(),
            $plot
        );

        $chart->setTopLeftPosition('E2');
        $chart->setBottomRightPosition('L20');

        return $chart;
    }
}
