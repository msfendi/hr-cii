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
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use RealRashid\SweetAlert\Facades\Alert;

class GeneratePayrollExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $export_id;

    public function __construct($export_id)
    {
        $this->export_id = $export_id;
    }

    public function handle()
    {
        $export = PayrollExport::find($this->export_id);

        if (!$export) {
            return;
        }

        $run_id = $export->run_id;

        $query = DB::table('payroll_run_details as prd')
            ->leftJoin('BIODATA as b', 'b.NPK', '=', 'prd.employee_npk')
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'b.id_dept')
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('prd.run_id', $run_id)
            ->select(
                'prd.*',
                'd.DEPARTEMENT',
                'pp.name as period_name'
            )
            ->orderBy('d.DEPARTEMENT')
            ->orderBy('prd.employee_npk');

        // dd($query->get());

        $total = $query->count();
        $periodName = $query->get()->value('period_name');
        $periodNameFormatted = strtoupper(str_replace(' ', '_', $periodName ?? 'UNKNOWN'));
        $processed = 0;

        /*
        |--------------------------------------------------------------------------
        | Progress Process
        |--------------------------------------------------------------------------
        */

        $query->chunk(1000, function ($rows) use (&$processed, $total, $export) {

            foreach ($rows as $row) {

                $components = json_decode($row->components, true);

                // proses data payroll jika perlu

                $processed++;
            }

            $progress = intval(($processed / $total) * 100);

            PayrollExport::where('id', $export->id)
                ->update(['progress' => $progress]);
        }, 'id');

        /*
        |--------------------------------------------------------------------------
        | Generate Excel
        |--------------------------------------------------------------------------
        */

        $excelPath = 'public/payroll/REKAP_' . $periodNameFormatted . '.xlsx';
        $excelPathDB = 'payroll/REKAP_' . $periodNameFormatted . '.xlsx';

        Excel::store(
            new PayrollExportExcel($run_id),
            $excelPath,
            null,
            \Maatwebsite\Excel\Excel::XLSX
        );

        /*
        |--------------------------------------------------------------------------
        | Generate PDF
        |--------------------------------------------------------------------------
        */

        $allComponents = PayrollComponent::orderBy('priority')
            ->get()
            ->keyBy('code');

        $data = DB::table('payroll_run_details as prd')
            ->leftJoin('BIODATA as b', 'b.NPK', '=', 'prd.employee_npk')
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'b.id_dept')
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('prd.run_id', $run_id)
            ->select(
                'prd.*',
                'd.DEPARTEMENT',
                'pp.name as period_name'
            )
            ->orderBy('d.DEPARTEMENT')
            ->orderBy('prd.employee_npk')
            ->get();

        $data->transform(function ($item) {

            $components = json_decode($item->components, true);

            foreach ($components as $key => $value) {
                $item->$key = $value;
            }

            return $item;
        });

        $grouped = $data->groupBy('DEPARTEMENT');

        ini_set('memory_limit', '1024M');
        set_time_limit(0);


        $pdf = Pdf::loadView('payroll.rekap_pdf', [
            'grouped' => $grouped,
            'allComponents' => $allComponents,
            'run_id' => $run_id
        ])->setPaper('a4', 'landscape');

        $pdf->setOption('isPhpEnabled', true);

        $pdfPath = 'public/payroll/REKAP_' . $periodNameFormatted . '.pdf';
        $pdfPathDB = 'payroll/REKAP_' . $periodNameFormatted . '.pdf';

        Storage::put($pdfPath, $pdf->output());

        /*
        |--------------------------------------------------------------------------
        | Update Export Status
        |--------------------------------------------------------------------------
        */

        PayrollExport::where('id', $export->id)
            ->update([
                'status' => 'finished',
                'progress' => 100,
                'file_excel' => $excelPathDB,
                'file_pdf' => $pdfPathDB
            ]);

        Alert::success('Sukses', 'Export payroll selesai diproses!');
    }
}
