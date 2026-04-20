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

class GeneratePayrollExport implements ShouldQueue
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

        $export = PayrollExport::find($this->export_id);
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
        $data = DB::table('payroll_run_details as prd')
            ->leftJoinSub(
                $employeeUnion,
                'emp',
                fn($j) =>
                $j->on('emp.NPK', '=', 'prd.employee_npk')
            )
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'emp.id_dept')
            ->leftJoinSub(
                $pkwtLatest,
                'p',
                fn($j) =>
                $j->on('p.NPK', '=', 'prd.employee_npk')
            )
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

        $folder = "public/payroll/$periodNameFormatted";
        Storage::makeDirectory($folder, 0775, true);

        /*
        |--------------------------------------------------------------------------
        | EXCEL
        |--------------------------------------------------------------------------
        */
        if ($this->type === 'process') {

            $excelPath = "$folder/REKAP_$periodNameFormatted.xlsx";

            Excel::store(new PayrollExportExcel($run_id), $excelPath);

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
            foreach (json_decode($item->components, true) ?? [] as $k => $v) {
                $item->$k = $v;
            }
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
        $groupedResign = $resignEmployees->groupBy('DEPARTEMENT');

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
        $resignTotals = $calcTotals($resignEmployees);

        /*
        |--------------------------------------------------------------------------
        | APPROVAL BUILDER
        |--------------------------------------------------------------------------
        */
        $approvals = [];

        $approve = DB::table('payroll_approve')
            ->where('payroll_run_id', $run_id)
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
            'groupedResign',
            'allComponents',
            'activeTotals',
            'resignTotals',
            'run_id',
            'approvals'
        );

        $pdf = Pdf::loadView('payroll.rekap_pdf', $viewData)
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'sans-serif')
            ->setOption('isPhpEnabled', true);

        $pdfPeng = Pdf::loadView('payroll.pengeluaran_pdf', $viewData)
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
