<?php

namespace App\Jobs;

use App\Models\ThrExport;
use App\Exports\ThrExportExcel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class GenerateThrExport implements ShouldQueue
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
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        $export = ThrExport::find($this->export_id);
        if (!$export) return;

        $export->update([
            'status' => 'processing',
            'progress' => 5
        ]);

        $run_id = $export->run_id;

        /*
        |--------------------------------------------------------------------------
        | UNION BIODATA
        |--------------------------------------------------------------------------
        */

        $employeeUnion = DB::table('BIODATA')
            ->select('NPK', 'id_dept')
            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'id_dept')
            );

        /*
        |--------------------------------------------------------------------------
        | QUERY THR
        |--------------------------------------------------------------------------
        */

        $data = DB::table('thr_run_details as trd')

            ->leftJoinSub($employeeUnion, 'emp', function ($join) {
                $join->on('emp.NPK', '=', 'trd.employee_npk');
            })

            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'emp.id_dept')

            ->leftJoin('thr_runs as tr', 'tr.id', '=', 'trd.run_id')
            ->leftJoin('thr_periods as tp', 'tp.id', '=', 'tr.period_id')

            ->where('trd.run_id', $run_id)

            ->select(
                'trd.*',
                'd.DEPARTEMENT',
                'tp.name as period_name'
            )

            ->orderBy('d.DEPARTEMENT')
            ->orderBy('trd.employee_npk')

            ->get();

        if ($data->isEmpty()) {
            $export->update(['status' => 'failed']);
            return;
        }

        $periodName = strtoupper(
            str_replace(' ', '_', $data->first()->period_name ?? 'THR')
        );

        /*
        |--------------------------------------------------------------------------
        | EXCEL EXPORT
        |--------------------------------------------------------------------------
        */

        $export->update(['progress' => 30]);

        $excelPath = "public/thr/REKAP_$periodName.xlsx";
        $excelDB   = "thr/REKAP_$periodName.xlsx";

        Excel::store(
            new ThrExportExcel($run_id),
            $excelPath
        );

        /*
        |--------------------------------------------------------------------------
        | DECODE COMPONENT (AMBIL LANGSUNG)
        |--------------------------------------------------------------------------
        */

        $data = $data->map(function ($item) {

            $components = json_decode($item->components, true) ?? [];

            $item->basic_salary     = $components['basic_salary'] ?? 0;
            $item->allowance  = $components['allowance'] ?? 0;
            $item->working_months = $components['working_months'] ?? 0;
            $item->thr        = $components['thr'] ?? 0;

            return $item;
        });

        /*
        |--------------------------------------------------------------------------
        | GROUP BY DEPARTMENT
        |--------------------------------------------------------------------------
        */

        $groupedActive = $data->groupBy(
            fn($row) => $row->DEPARTEMENT ?? 'NO DEPARTMENT'
        );

        /*
        |--------------------------------------------------------------------------
        | PDF EXPORT
        |--------------------------------------------------------------------------
        */

        $export->update(['progress' => 70]);

        $pdf = Pdf::loadView('thr.rekap_pdf', [
            'groupedActive' => $groupedActive,
            'period_name' => $data->first()->period_name
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('isPhpEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        $pdfPath = "public/thr/REKAP_$periodName.pdf";
        $pdfDB   = "thr/REKAP_$periodName.pdf";

        $pdf->save(storage_path("app/$pdfPath"));

        /*
        |--------------------------------------------------------------------------
        | GENERATE PDF PENGELUARAN GAJI (STREAM SAVE)
        |--------------------------------------------------------------------------
        */

        $pdfPeng = Pdf::loadView('thr.pengeluaran_pdf', [
            'groupedActive' => $groupedActive,
            'period_name' => $data->first()->period_name
        ])
            ->setPaper('a4')
            ->setOption('defaultFont', 'sans-serif')
            ->setOption('isPhpEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $pdfPengPath = "public/thr/PENGELUARAN_$periodName.pdf";
        $pdfPengPathDB = "thr/PENGELUARAN_$periodName.pdf";

        $pdfPengPath = storage_path('app/' . $pdfPengPath);

        $pdfPeng->save($pdfPengPath);

        /*
        |--------------------------------------------------------------------------
        | FINISH
        |--------------------------------------------------------------------------
        */

        $export->update([
            'status' => 'finished',
            'progress' => 100,
            'file_excel' => $excelDB,
            'file_pdf' => $pdfDB,
            'file_peng' => $pdfPengPathDB,
        ]);
    }
}
