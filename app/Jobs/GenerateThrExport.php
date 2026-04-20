<?php

namespace App\Jobs;

use App\Models\ThrExport;
use App\Exports\ThrExportExcel;
use App\Models\PayrollComponent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class GenerateThrExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 1;

    protected $export_id;
    protected $type;

    public function __construct($export_id, $type)
    {
        $this->export_id = $export_id;
        $this->type = $type;
    }

    public function handle()
    {
        ini_set('memory_limit', '4096M');
        set_time_limit(0);

        $export = ThrExport::find($this->export_id);
        if (!$export) return;

        $run_id = $export->run_id;


        $export->update([
            'status' => 'processing',
            'progress' => 0,
        ]);

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE UNION (LOAD ONCE)
        |--------------------------------------------------------------------------
        */
        $employeeUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'id_dept')
            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'id_dept')
            );

        $employees = DB::query()
            ->fromSub($employeeUnion, 'emp')
            ->get()
            ->keyBy('NPK');

        /*
        |--------------------------------------------------------------------------
        | SIGNATURE CACHE
        |--------------------------------------------------------------------------
        */
        $signatures = DB::table('users as u')
            ->leftJoin('signatures as s', 's.user_id', '=', 'u.id')
            ->select('u.npk', 's.signature_img')
            ->get()
            ->keyBy('npk');

        /*
        |--------------------------------------------------------------------------
        | PKWT LATEST (OPTIMIZED)
        |--------------------------------------------------------------------------
        */
        $pkwtLatest = DB::table('PKWT as p1')
            ->select('p1.NPK', 'p1.TKK')
            ->whereRaw('p1.TMK = (SELECT MAX(p2.TMK) FROM PKWT p2 WHERE p2.NPK = p1.NPK)');

        /*
        |--------------------------------------------------------------------------
        | PAYROLL QUERY
        |--------------------------------------------------------------------------
        */
        $data = DB::table('thr_run_details as trd')
            ->leftJoinSub(
                $employeeUnion,
                'emp',
                fn($j) => $j->on('emp.NPK', '=', 'trd.employee_npk')
            )
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

        if ($data->isEmpty()) return;

        $periodName = $data->first()->period_name ?? 'UNKNOWN';
        $periodNameFormatted = strtoupper(str_replace(' ', '_', $periodName));

        $folder = "public/thr/$periodNameFormatted";
        Storage::makeDirectory($folder, 0775, true);

        /*
        |--------------------------------------------------------------------------
        | EXCEL
        |--------------------------------------------------------------------------
        */
        if ($this->type === 'process') {

            $excelPath = "$folder/REKAP_$periodNameFormatted.xlsx";

            Excel::store(new ThrExportExcel($run_id), $excelPath);

            $export->update([
                'status' => 'processing',
                'progress' => 30,
                'file_excel' => str_replace('public/', '', $excelPath),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | COMPONENT MASTER
        |--------------------------------------------------------------------------
        */
        $componentKeys = collect($data)
            ->flatMap(fn($r) => array_keys(json_decode($r->components, true) ?? []))
            ->unique()
            ->reject(fn($c) => strtolower($c) === 'thr');

        $componentMasters = PayrollComponent::whereIn('code', $componentKeys)
            ->get()
            ->keyBy('code');

        $allComponents = $componentKeys->mapWithKeys(function ($code) use ($componentMasters) {

            $m = $componentMasters[$code] ?? null;

            return [$code => (object)[
                'code' => $code,
                'name' => $m->name ?? strtoupper(str_replace('_', ' ', $code)),
                'type' => $m->type ?? 'earning',
                'orders' => 0
            ]];
        });

        /*
        |--------------------------------------------------------------------------
        | DECODE COMPONENT (1 PASS ONLY)
        |--------------------------------------------------------------------------
        */
        foreach ($data as $item) {

            $components = json_decode($item->components, true) ?? [];

            $item->basic_salary   = $components['basic_salary'] ?? 0;
            $item->allowance      = $components['allowance'] ?? 0;
            $item->working_months = $components['working_months'] ?? 0;
            $item->thr            = $components['thr'] ?? 0;
        }

        /*
        |--------------------------------------------------------------------------
        | SPLIT EMPLOYEE
        |--------------------------------------------------------------------------
        */
        $activeEmployees = $data->filter(fn($r) => empty($r->TKK));

        $resignEmployees = $data->filter(
            fn($r) =>
            !empty($r->TKK)
                && $r->TKK >= $r->start_date
                && $r->TKK <= $r->end_date
        );

        $groupedActive = $activeEmployees->groupBy('DEPARTEMENT');

        /*
        |--------------------------------------------------------------------------
        | TOTAL ENGINE
        |--------------------------------------------------------------------------
        */
        $calcTotals = function ($rows) use ($allComponents) {

            $totals = array_fill_keys(array_keys($allComponents->toArray()), 0);

            foreach ($rows as $row) {
                foreach ($totals as $code => &$total) {
                    $total += $row->$code ?? 0;
                }
            }

            return $totals;
        };

        $activeTotals = $calcTotals($activeEmployees);

        /*
        |--------------------------------------------------------------------------
        | APPROVAL BUILDER
        |--------------------------------------------------------------------------
        */
        $approvals = [];

        $approve = DB::table('thr_approve')
            ->where('thr_run_id', $run_id)
            ->first();

        if ($approve && $approve->progress) {

            foreach (json_decode($approve->progress, true) as $row) {

                $npks = json_decode($row['npk'], true) ?? [];

                $statuses = json_decode($row['status'], true);
                if (!is_array($statuses)) {
                    $statuses = array_fill(0, count($npks), $row['status']);
                }

                foreach ($npks as $i => $npk) {

                    $approvals[] = [
                        'npk' => $npk,
                        'nama_karyawan' => $employees[$npk]->NAMA_KARYAWAN ?? '-',
                        'bagian' => $employees[$npk]->BAG ?? '-',
                        'status' => strtolower($statuses[$i] ?? 'waiting'),
                        'signature_img' => $signatures[$npk]->signature_img ?? null
                    ];
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | PDF GENERATION
        |--------------------------------------------------------------------------
        */
        $viewData = compact(
            'groupedActive',
            'allComponents',
            'periodName',
            'run_id',
            'approvals'
        );


        $pdf = Pdf::loadView('thr.rekap_pdf', $viewData)
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'sans-serif')
            ->setOption('isPhpEnabled', true);

        $pdfPeng = Pdf::loadView('thr.pengeluaran_pdf', $viewData)
            ->setPaper('a4');

        $suffix = $this->type === 'process' ? '' : '_APPROVED';

        $pdfPath = "$folder/REKAP_{$periodNameFormatted}{$suffix}.pdf";
        $pdfPengPath = "$folder/PENGELUARAN_{$periodNameFormatted}{$suffix}.pdf";

        $pdf->save(storage_path("app/$pdfPath"));
        $pdfPeng->save(storage_path("app/$pdfPengPath"));

        /*
        |--------------------------------------------------------------------------
        | FINAL UPDATE
        |--------------------------------------------------------------------------
        */
        $export->update([
            'status' => $this->type === 'process' ? 'finished' : 'approved',
            'progress' => 100,
            'file_pdf' => str_replace('public/', '', $pdfPath),
            'file_peng' => str_replace('public/', '', $pdfPengPath),
        ]);
    }
}
