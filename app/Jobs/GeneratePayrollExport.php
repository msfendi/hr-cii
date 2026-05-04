<?php

namespace App\Jobs;

use App\Exports\NonSewing\PayrollExportNonSewingExcel;
use App\Exports\PayrollExportExcel;
use App\Exports\Sewing\PayrollExportSewingExcel;
use App\Exports\Staff\PayrollExportStaffExcel;
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
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use App\Services\FastExcelExport;
use App\Helpers\PdfPassword;
use App\Services\ExcelProtectService;
use App\Services\PdfService;
use App\Services\ExcelZipEncryptService;

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
            'status' => 'Start Processing',
            'progress' => 0,
        ]);

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE UNION (LOAD ONCE)
        |--------------------------------------------------------------------------
        */

        $export->update([
            'progress' => 15,
            'status' => 'Processing Employee Data'
        ]);

        $employeeUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'id_dept', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'id_dept', 'IS_STAFF')
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
        | PKWT LATEST
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
                fn($j) => $j->on('emp.NPK', '=', 'prd.employee_npk')
            )
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'emp.id_dept')
            ->leftJoinSub(
                $pkwtLatest,
                'p',
                fn($j) => $j->on('p.NPK', '=', 'prd.employee_npk')
            )
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('prd.run_id', $run_id)
            ->select(
                'prd.*',
                'd.DEPARTEMENT',
                'pp.id as period_id',
                'pp.name as period_name',
                'pp.start_date',
                'pp.end_date',
                'p.TKK',
                'emp.IS_STAFF',
                'd.IS_SEWING'
            )
            ->orderBy('d.DEPARTEMENT')
            ->orderBy('prd.employee_npk')
            ->get();

        if ($data->isEmpty()) return;


        $periodName = $data->first()->period_name ?? 'UNKNOWN';
        $periodNameFormatted = strtoupper(str_replace(' ', '_', $periodName));

        /*
        |--------------------------------------------------------------------------
        | FOLDER
        |--------------------------------------------------------------------------
        */

        $export->update([
            'progress' => 20,
            'status' => 'Creating Export Folders'
        ]);

        $folder = "public/payroll/$periodNameFormatted";
        $folderStaff = "$folder/STAFF";
        $folderSewing = "$folder/SEWING";
        $folderNonSewing = "$folder/NON_SEWING";

        Storage::makeDirectory($folder, 0777, true);
        Storage::makeDirectory($folderStaff, 0777, true);
        Storage::makeDirectory($folderSewing, 0777, true);
        Storage::makeDirectory($folderNonSewing, 0777, true);

        /*
        |--------------------------------------------------------------------------
        | EXCEL (UNCHANGED)
        |--------------------------------------------------------------------------
        */
        if ($this->type === 'process') {

            $zipService = app(\App\Services\ExcelZipEncryptService::class);

            $export->update([
                'progress' => 30,
                'status' => 'Processing Excel Files for ALL'
            ]);
            (new PayrollExportExcel($run_id))
                ->export(storage_path("app/$folder/REKAP_$periodNameFormatted.xlsx"));

            $export->update([
                'progress' => 35,
                'status' => 'Processing Excel Files for STAFF'
            ]);
            (new PayrollExportStaffExcel($run_id))
                ->export(storage_path("app/$folderStaff/REKAP_$periodNameFormatted.xlsx"));

            $export->update([
                'progress' => 40,
                'status' => 'Processing Excel Files for SEWING'
            ]);
            (new PayrollExportSewingExcel($run_id))
                ->export(storage_path("app/$folderSewing/REKAP_$periodNameFormatted.xlsx"));

            $export->update([
                'progress' => 45,
                'status' => 'Processing Excel Files for NON_SEWING'
            ]);
            (new PayrollExportNonSewingExcel($run_id))
                ->export(storage_path("app/$folderNonSewing/REKAP_$periodNameFormatted.xlsx"));
        }

        /*
        |--------------------------------------------------------------------------
        | COMPONENT MASTER (UNCHANGED)
        |--------------------------------------------------------------------------
        */

        $export->update([
            'progress' => 50,
            'status' => 'Collecting Payroll Data'
        ]);

        $componentKeys = collect($data)
            ->flatMap(fn($r) => array_keys(json_decode($r->components, true) ?? []))
            ->unique()
            ->reject(fn($c) => strtolower($c) === 'thr')
            ->reject(fn($c) => strtolower($c) === 'late_minutes');

        $componentMasters = PayrollComponent::whereIn('code', $componentKeys)
            ->get()->keyBy('code');

        $allComponents = $componentKeys->mapWithKeys(function ($code) use ($componentMasters) {

            $m = $componentMasters[$code] ?? null;

            return [$code => (object)[
                'code' => $code,
                'name' => $m->name ?? strtoupper(str_replace('_', ' ', $code)),
                'type' => $m->type ?? 'earning',
                'orders' => 0
            ]];
        });

        foreach ($data as $item) {
            foreach (json_decode($item->components, true) ?? [] as $k => $v) {
                $item->$k = $v;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SPLIT EMPLOYEE BASE
        |--------------------------------------------------------------------------
        */
        $activeEmployees = $data->filter(fn($r) => empty($r->TKK));

        $resignEmployees = $data->filter(
            fn($r) => !empty($r->TKK)
                && $r->TKK >= $r->start_date
                && $r->TKK <= $r->end_date
        );

        /*
        |--------------------------------------------------------------------------
        | CATEGORY SPLIT (NEW ADDITION)
        |--------------------------------------------------------------------------
        */
        $categories = [

            'ALL' => fn($r) => true,

            'STAFF' => fn($r) => $r->IS_STAFF == 1,

            'SEWING' => fn($r) => $r->IS_SEWING == 0 && $r->IS_STAFF == 0,

            'NON_SEWING' => fn($r) => $r->IS_SEWING == 1 && $r->IS_STAFF == 0,
        ];

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


        /*
        |--------------------------------------------------------------------------
        | APPROVAL BUILDER (UNCHANGED)
        |--------------------------------------------------------------------------
        */

        $export->update([
            'progress' => 55,
            'status' => 'Approval Checking'
        ]);
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
        | PDF GENERATION SPLIT (NEW)
        |--------------------------------------------------------------------------
        */

        $suffix = $this->type === 'process' ? '' : '_APPROVED';

        foreach ($categories as $key => $filter) {

            $export->update([
                'progress' => 75,
                'file_excel' => "REKAP_$periodNameFormatted.zip",
                'status' => "Encrypting Excel Files for $key"
            ]);

            $folderTarget = match ($key) {
                'STAFF' => $folderStaff,
                'SEWING' => $folderSewing,
                'NON_SEWING' => $folderNonSewing,
                default => $folder
            };

            $password = match ($key) {
                'STAFF' => PdfPassword::generate('staff', $data->first()->start_date),
                'SEWING' => PdfPassword::generate('sewing', $data->first()->start_date),
                'NON_SEWING' => PdfPassword::generate('nonsewing', $data->first()->start_date),
                default => PdfPassword::generate('all', $data->first()->start_date)
            };

            if ($this->type === 'process') {
                $zipService->encrypt(
                    storage_path("app/$folderTarget/REKAP_$periodNameFormatted.xlsx"),
                    $password ?? $password
                );
            }

            $active = $activeEmployees->filter($filter);
            $resign = $resignEmployees->filter($filter);

            if ($active->isEmpty() && $resign->isEmpty()) continue;

            $viewData = [
                'groupedActive' => $active->groupBy('DEPARTEMENT'),
                'groupedResign' => $resign->groupBy('DEPARTEMENT'),
                'allComponents' => $allComponents,
                'activeTotals' => $calcTotals($active),
                'resignTotals' => $calcTotals($resign),
                'run_id' => $run_id,
                'approvals' => $approvals
            ];

            /*
            |--------------------------------------------------------------------------
            | RENDER HTML MANUAL
            |--------------------------------------------------------------------------
            */

            $htmlRekap = View::make('payroll.rekap_pdf', $viewData)->render();
            $htmlPeng  = View::make('payroll.pengeluaran_pdf', $viewData)->render();

            $pdfPath = "$folderTarget/REKAP_{$periodNameFormatted}{$suffix}.pdf";
            $pdfPengPath = "$folderTarget/PENGELUARAN_{$periodNameFormatted}{$suffix}.pdf";
            $pdfPathTemp = "$folderTarget/REKAP_{$periodNameFormatted}{$suffix}_temp.pdf";
            $pdfPengPathTemp = "$folderTarget/PENGELUARAN_{$periodNameFormatted}{$suffix}_temp.pdf";

            /*
            |--------------------------------------------------------------------------
            | GENERATE PDF
            |--------------------------------------------------------------------------
            */

            $export->update([
                'progress' => 75,
                'status' => "Generating PDF Files for $key"
            ]);

            $pdf = App::make('snappy.pdf.wrapper');
            $pdf->loadHTML($htmlRekap)
                ->setPaper('a4')
                ->setOrientation('landscape')
                ->setOption('enable-local-file-access', true)
                ->setOption('encoding', 'UTF-8');

            $pdfPeng = App::make('snappy.pdf.wrapper');
            $pdfPeng->loadHTML($htmlPeng)
                ->setPaper('a4')
                ->setOption('enable-local-file-access', true);

            /*
            |--------------------------------------------------------------------------
            | DELETE OLD FILE
            |--------------------------------------------------------------------------
            */

            $fullPdfPathTemp = storage_path("app/$pdfPathTemp");
            $fullPdfPath = storage_path("app/$pdfPath");
            $fullPdfPengPathTemp = storage_path("app/$pdfPengPathTemp");
            $fullPdfPengPath = storage_path("app/$pdfPengPath");

            if (File::exists($fullPdfPath)) File::delete($fullPdfPath);
            if (File::exists($fullPdfPengPath)) File::delete($fullPdfPengPath);
            if (File::exists($fullPdfPathTemp)) File::delete($fullPdfPathTemp);
            if (File::exists($fullPdfPengPathTemp)) File::delete($fullPdfPengPathTemp);

            /*
            |--------------------------------------------------------------------------
            | SAVE
            |--------------------------------------------------------------------------
            */
            $pdf->save($fullPdfPathTemp);
            $pdfPeng->save($fullPdfPengPathTemp);

            $export->update([
                'progress' => 75,
                'status' => "Encrypting PDF Files for $key"
            ]);

            PdfService::protect($fullPdfPathTemp, $fullPdfPath, $password);
            PdfService::protect($fullPdfPengPathTemp, $fullPdfPengPath, $password);

            $export->update([
                'progress' => 75,
                'status' => "Deleting Temporary PDF Files for $key"
            ]);

            if (File::exists($fullPdfPathTemp)) File::delete($fullPdfPathTemp);
            if (File::exists($fullPdfPengPathTemp)) File::delete($fullPdfPengPathTemp);

            // $pdf->save($fullPdfPath);
            // $pdfPeng->save($fullPdfPengPath);

            if ($key === 'ALL') {
                $export->update([
                    'file_pdf' => str_replace('public/', '', "REKAP_{$periodNameFormatted}{$suffix}.pdf"),
                    'file_peng' => str_replace('public/', '', "PENGELUARAN_{$periodNameFormatted}{$suffix}.pdf"),
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | FINAL UPDATE
        |--------------------------------------------------------------------------
        */
        $export->update([
            'status' => $this->type === 'process' ? 'finished' : 'approved',
            'progress' => 100
        ]);
    }
}
