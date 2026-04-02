<?php

namespace App\Jobs;

use App\Exports\PayrollExportExcel;
use App\Models\PayrollComponent;
use App\Models\PayrollExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class GeneratePayrollExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 1;

    protected $export_id;

    public function __construct($export_id)
    {
        $this->export_id = $export_id;
    }

    public function handle()
    {
        ini_set('memory_limit', '4096M');
        set_time_limit(0);

        $export = PayrollExport::find($this->export_id);
        if (!$export) return;

        $run_id = $export->run_id;

        /*
        |---------------------------------------------------------------------- 
        | UNION BIODATA + BIODATA_KELUAR
        |---------------------------------------------------------------------- 
        */
        $employeeUnion = DB::table('BIODATA')->select('NPK', 'id_dept')
            ->unionAll(DB::table('BIODATA_KELUAR')->select('NPK', 'id_dept'));

        /*
        |---------------------------------------------------------------------- 
        | PKWT TERAKHIR (BERDASARKAN TMK)
        |---------------------------------------------------------------------- 
        */
        $pkwtLatest = DB::raw("
            (
                SELECT p1.NPK, p1.TKK
                FROM PKWT p1
                WHERE p1.TMK = (
                    SELECT MAX(p2.TMK)
                    FROM PKWT p2
                    WHERE p2.NPK = p1.NPK
                )
            ) as p
        ");

        /*
        |---------------------------------------------------------------------- 
        | QUERY PAYROLL
        |---------------------------------------------------------------------- 
        */
        $data = DB::table('payroll_run_details as prd')
            ->leftJoinSub($employeeUnion, 'emp', fn($join) => $join->on('emp.NPK', '=', 'prd.employee_npk'))
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'emp.id_dept')
            ->leftJoin($pkwtLatest, 'p.NPK', '=', 'prd.employee_npk')
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('prd.run_id', $run_id)
            ->select(
                'prd.*',
                'd.DEPARTEMENT',
                'pp.name as period_name',
                'pp.start_date',
                'pp.end_date',
                'p.TKK'
            )
            ->orderBy('d.DEPARTEMENT')
            ->orderBy('prd.employee_npk')
            ->get();

        if ($data->isEmpty()) return;

        $periodName = $data->first()->period_name ?? 'UNKNOWN';
        $periodNameFormatted = strtoupper(str_replace(' ', '_', $periodName));

        /*
        |---------------------------------------------------------------------- 
        | GENERATE EXCEL
        |---------------------------------------------------------------------- 
        */
        $excelPath = 'public/payroll/REKAP_' . $periodNameFormatted . '.xlsx';
        $excelPathDB = 'payroll/REKAP_' . $periodNameFormatted . '.xlsx';
        Excel::store(new PayrollExportExcel($run_id), $excelPath, null, \Maatwebsite\Excel\Excel::XLSX);

        /*
        |---------------------------------------------------------------------- 
        | COMPONENT STRUCTURE DARI JSON + TYPE MASTER
        |---------------------------------------------------------------------- 
        */
        $componentKeys = collect($data)
            ->flatMap(fn($row) => array_keys(json_decode($row->components, true) ?? []))
            ->unique()
            ->filter(fn($code) => strtolower($code) !== 'thr'); // exclude THR

        // Ambil type & name dari master table payroll_components
        $componentMasters = PayrollComponent::whereIn('code', $componentKeys)
            ->get()
            ->keyBy('code');

        $allComponents = $componentKeys->map(function ($code) use ($componentMasters) {
            $master = $componentMasters[$code] ?? null;
            return (object)[
                'code' => $code,
                'name' => $master->name ?? strtoupper(str_replace('_', ' ', $code)), // ambil name dari master
                'type' => $master->type ?? 'earning',
                'orders' => 0
            ];
        })->keyBy('code');

        /*
        |---------------------------------------------------------------------- 
        | DECODE COMPONENT JSON
        |---------------------------------------------------------------------- 
        */
        $data = $data->map(function ($item) {
            $components = json_decode($item->components, true);
            if ($components) {
                foreach ($components as $key => $value) {
                    $item->$key = $value;
                }
            }
            return $item;
        });

        /*
        |---------------------------------------------------------------------- 
        | PISAH AKTIF VS RESIGN
        |---------------------------------------------------------------------- 
        */
        $activeEmployees = $data->filter(fn($row) => empty($row->TKK));
        $resignEmployees = $data->filter(fn($row) => !empty($row->TKK) && $row->TKK >= $row->start_date && $row->TKK <= $row->end_date);

        /*
        |---------------------------------------------------------------------- 
        | GROUP BY DEPARTMENT
        |---------------------------------------------------------------------- 
        */
        $groupedActive = $activeEmployees->groupBy('DEPARTEMENT');
        $groupedResign = $resignEmployees->groupBy('DEPARTEMENT');

        /*
        |---------------------------------------------------------------------- 
        | HITUNG TOTAL KOMPONEN
        |---------------------------------------------------------------------- 
        */
        $activeTotals = [];
        foreach ($activeEmployees as $row) {
            foreach ($allComponents as $code => $component) {
                $value = $row->$code ?? 0;
                $activeTotals[$code] = ($activeTotals[$code] ?? 0) + $value;
            }
        }

        $resignTotals = [];
        foreach ($resignEmployees as $row) {
            foreach ($allComponents as $code => $component) {
                $value = $row->$code ?? 0;
                $resignTotals[$code] = ($resignTotals[$code] ?? 0) + $value;
            }
        }

        /*
        |---------------------------------------------------------------------- 
        | GENERATE PDF REKAP
        |---------------------------------------------------------------------- 
        */
        $pdf = Pdf::loadView('payroll.rekap_pdf', [
            'groupedActive' => $groupedActive,
            'groupedResign' => $groupedResign,
            'allComponents' => $allComponents,
            'activeTotals' => $activeTotals,
            'resignTotals' => $resignTotals,
            'run_id' => $run_id
        ])->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'sans-serif')
            ->setOption('isPhpEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $pdfPath = 'public/payroll/REKAP_' . $periodNameFormatted . '.pdf';
        $pdfPathDB = 'payroll/REKAP_' . $periodNameFormatted . '.pdf';
        $pdf->save(storage_path('app/' . $pdfPath));

        /*
        |---------------------------------------------------------------------- 
        | GENERATE PDF PENGELUARAN
        |---------------------------------------------------------------------- 
        */
        $pdfPeng = Pdf::loadView('payroll.pengeluaran_pdf', [
            'groupedActive' => $groupedActive,
            'groupedResign' => $groupedResign,
            'allComponents' => $allComponents,
            'activeTotals' => $activeTotals,
            'resignTotals' => $resignTotals,
            'run_id' => $run_id
        ])->setPaper('a4')
            ->setOption('defaultFont', 'sans-serif')
            ->setOption('isPhpEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $pdfPengPath = 'public/payroll/PENGELUARAN_' . $periodNameFormatted . '.pdf';
        $pdfPengPathDB = 'payroll/PENGELUARAN_' . $periodNameFormatted . '.pdf';
        $pdfPeng->save(storage_path('app/' . $pdfPengPath));

        /*
        |---------------------------------------------------------------------- 
        | UPDATE STATUS
        |---------------------------------------------------------------------- 
        */
        PayrollExport::where('id', $export->id)->update([
            'status' => 'finished',
            'progress' => 100,
            'file_excel' => $excelPathDB,
            'file_pdf' => $pdfPathDB,
            'file_peng' => $pdfPengPathDB
        ]);
    }
}
