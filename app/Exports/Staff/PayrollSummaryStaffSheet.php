<?php

namespace App\Exports\Staff;

use Illuminate\Support\Facades\DB;
use App\Models\PayrollComponent;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;

use Maatwebsite\Excel\Events\AfterSheet;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PayrollSummaryStaffSheet implements
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
        'active_staff' => [],
        'resign_staff' => [],
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
    QUERY (TIDAK DIUBAH)
    =====================================================
    */
    public function query()
    {
        $aktif = DB::table('BIODATA as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.NAMA_KARYAWAN', 'b.id_dept', 'p.TKK', 'b.IS_STAFF');

        $keluar = DB::table('BIODATA_KELUAR as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.NAMA_KARYAWAN', 'b.id_dept', 'p.TKK', 'b.IS_STAFF');

        $biodataUnion = $aktif->union($keluar);

        return DB::query()
            ->fromSub($biodataUnion, 'bio')
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
    MAP (LOGIC ASLI — TIDAK DIUBAH)
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

        $targetGroups = [];

        if ($isResign) {
            if ($isStaff) {
                $targetGroups[] = 'resign_staff';
            }
        } else {
            if ($isStaff) {
                $targetGroups[] = 'active_staff';
            }
        }

        foreach ($this->components as $component) {

            $code = $component->code;

            $value = isset($items[$code])
                ? (float)$items[$code]
                : 0.0;

            foreach ($targetGroups as $grp) {
                $this->groups[$grp][$code] =
                    ($this->groups[$grp][$code] ?? 0) + $value;
            }
        }

        // SUMMARY MODE → tidak output row
        return [];
    }

    /*
    =====================================================
    HEADINGS (TEMPLATE DISAMAKAN)
    =====================================================
    */
    public function headings(): array
    {
        $rows = [];

        $header = [
            'Component',
            'Active Staff',
            'Resign Staff',
        ];

        $rows[] = $header;

        foreach ($this->components as $component) {
            $rows[] = [$component->name, '', ''];
        }

        $rows[] = array_fill(0, count($header), '');

        $rows[] = ['Total Earning'];
        $rows[] = ['Total Deduction'];
        $rows[] = ['Net Payroll'];

        return $rows;
    }

    /*
    =====================================================
    AFTER SHEET — UNIVERSAL TEMPLATE
    =====================================================
    */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function ($event) {

                // UNIVERSAL SHEET DETECTOR
                $sheet = $event->sheet;

                if (method_exists($sheet, 'getDelegate')) {
                    $sheet = $sheet->getDelegate();
                }

                $rowStart = 2;

                foreach ($this->components as $i => $component) {

                    $row = $rowStart + $i;

                    $active =
                        $this->groups['active_staff'][$component->code] ?? 0;

                    $resign =
                        $this->groups['resign_staff'][$component->code] ?? 0;

                    if ($component->type === 'deduction') {

                        $active = -abs($active);
                        $resign = -abs($resign);

                        $this->deduction['active_staff'] += $active;
                        $this->deduction['resign_staff'] += $resign;
                    } else {

                        $this->earning['active_staff'] += $active;
                        $this->earning['resign_staff'] += $resign;
                    }

                    if (method_exists($sheet, 'setCellValue')) {
                        $sheet->setCellValue("B{$row}", $active);
                        $sheet->setCellValue("C{$row}", $resign);
                    }
                }

                $base = count($this->components) + 3;

                $sheet->setCellValue(
                    'B' . $base,
                    $this->earning['active_staff']
                );

                $sheet->setCellValue(
                    'C' . $base,
                    $this->earning['resign_staff']
                );

                $sheet->setCellValue(
                    'B' . ($base + 1),
                    $this->deduction['active_staff']
                );

                $sheet->setCellValue(
                    'C' . ($base + 1),
                    $this->deduction['resign_staff']
                );

                $sheet->setCellValue(
                    'B' . ($base + 2),
                    $this->earning['active_staff'] +
                        $this->deduction['active_staff']
                );

                $sheet->setCellValue(
                    'C' . ($base + 2),
                    $this->earning['resign_staff'] +
                        $this->deduction['resign_staff']
                );
            }
        ];
    }

    /*
    =====================================================
    STYLE (SAMA DENGAN SUMMARY)
    =====================================================
    */
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('B2:Z500')
            ->getNumberFormat()
            ->setFormatCode('"Rp" #,##0');
    }
}
