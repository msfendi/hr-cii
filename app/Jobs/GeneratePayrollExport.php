<?php

namespace App\Jobs;

use App\Exports\NonSewing\PayrollExportNonSewingExcel;
use App\Exports\NonStaff\PayrollExportNonStaffExcel;
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
use App\Models\PayrollPeriod;
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

        $period = DB::table('payroll_runs')
            ->leftJoin('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.period_id')
            ->select('payroll_periods.start_date', 'payroll_periods.end_date')
            ->where('payroll_runs.id', $run_id)
            ->first();

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
            ->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'ID_DEPT', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'ID_DEPT', 'IS_STAFF')
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
            ->select('p1.NPK', 'p1.TMK', 'p1.TKK', 'p1.KETERANGAN') // tambah TMK
            ->whereRaw('p1.TMK = (SELECT MAX(p2.TMK) FROM PKWT p2 WHERE p2.NPK = p1.NPK)');

        /*
        |--------------------------------------------------------------------------
        | PAYROLL QUERY
        |--------------------------------------------------------------------------
        */

        $overtimeAgg = DB::table('overtimes')
            ->whereBetween('OVERTIME_DATE', [$period->start_date, $period->end_date])
            ->selectRaw("
        NPK,
        SUM(CASE WHEN JUMLAH_JAM_LEMBUR = 'MA' THEN 1 ELSE 0 END) as MA,
        SUM(CASE WHEN JUMLAH_JAM_LEMBUR = 'P1' THEN 1 ELSE 0 END) as P1,
        SUM(CASE WHEN JUMLAH_JAM_LEMBUR = 'CT' THEN 1 ELSE 0 END) as CT,
        SUM(CASE WHEN JUMLAH_JAM_LEMBUR = 'SD' THEN 1 ELSE 0 END) as SD,
        SUM(CASE WHEN JUMLAH_JAM_LEMBUR = 'BR' THEN 1 ELSE 0 END) as BR,
        SUM(CASE WHEN JUMLAH_JAM_LEMBUR = 'OUT' THEN 1 ELSE 0 END) as [OUT]
    ")
            ->groupBy('NPK');

        $ijinSummary = DB::table('ijin_meninggalkan_pekerjaans')
            ->selectRaw("
        npk,
        SUM(
            CASE 
                WHEN jam_kembali IS NOT NULL 
                THEN DATEDIFF(MINUTE, jam_keluar, jam_kembali)
                ELSE 0 
            END
        ) as total_ijin_minutes
    ")
            ->whereBetween('tanggal', [$period->start_date, $period->end_date])
            ->groupBy('npk');

        // dd($overtimeAgg);

        $data = DB::table('payroll_run_details as prd')
            ->leftJoinSub(
                $employeeUnion,
                'emp',
                fn($j) => $j->on('emp.NPK', '=', 'prd.employee_npk')
            )
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'prd.employee_dept')
            ->leftJoinSub(
                $pkwtLatest,
                'p',
                fn($j) => $j->on('p.NPK', '=', 'prd.employee_npk')
            )
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')

            // 🔥 JOIN overtime aggregate
            ->leftJoinSub(
                $overtimeAgg,
                'ot',
                fn($j) => $j->on('ot.NPK', '=', 'prd.employee_npk')
            )
            ->leftJoinSub($ijinSummary, 'ij', function ($join) {
                $join->on('emp.NPK', '=', 'ij.npk');
            })

            ->where('prd.run_id', $run_id)
            // ->where('prd.employee_npk', '=', 'C-00741')
            ->select(
                'prd.*',
                'd.DEPARTEMENT',
                'pp.id as period_id',
                'pp.name as period_name',
                'pp.start_date',
                'pp.end_date',
                'p.TKK',
                'p.TMK',
                'emp.IS_STAFF',
                'd.IS_SEWING',
                'p.KETERANGAN',

                // overtime hasil agregasi
                'ot.MA',
                'ot.P1',
                'ot.CT',
                'ot.SD',
                'ot.BR',
                'ot.OUT',
                'ij.total_ijin_minutes'
            )
            ->orderBy('d.DEPARTEMENT')
            ->orderBy('prd.employee_npk')
            ->get();

        // dd($data);

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
        $folderNonStaff = "$folder/NON_STAFF";
        $folderNonStaff = "$folder/NON_STAFF";
        $folderSewing = "$folder/SEWING";
        $folderNonSewing = "$folder/NON_SEWING";

        Storage::makeDirectory($folder, 0777, true);
        Storage::makeDirectory($folderStaff, 0777, true);
        Storage::makeDirectory($folderNonStaff, 0777, true);
        Storage::makeDirectory($folderSewing, 0777, true);
        Storage::makeDirectory($folderNonSewing, 0777, true);

        /*
        |--------------------------------------------------------------------------
        | EXCEL (UNCHANGED)
        |--------------------------------------------------------------------------
        */
        // if ($this->type === 'process') {

        $zipService = app(\App\Services\ExcelZipEncryptService::class);

        $export->update([
            'progress' => 30,
            'status' => 'Processing Excel Files for ALL'
        ]);
        (new PayrollExportExcel($run_id))
            ->export(storage_path("app/$folder/REKAP_$periodNameFormatted.xlsx"));

        $export->update([
            'progress' => 32,
            'status' => 'Processing Excel Files for STAFF'
        ]);
        (new PayrollExportStaffExcel($run_id))
            ->export(storage_path("app/$folderStaff/REKAP_$periodNameFormatted.xlsx"));

        $export->update([
            'progress' => 35,
            'status' => 'Processing Excel Files for NON STAFF'
        ]);
        (new PayrollExportNonStaffExcel($run_id))
            ->export(storage_path("app/$folderNonStaff/REKAP_$periodNameFormatted.xlsx"));

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
        // }

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
            ->reject(fn($c) => strtolower($c) === 'compensation')
            ->reject(fn($c) => strtolower($c) === 'late_minutes');

        $componentMasters = PayrollComponent::whereIn('code', $componentKeys)
            ->get()->keyBy('code');

        $componentTypeMap = [];
        foreach ($data as $row) {
            $decoded = json_decode($row->components, true) ?? [];
            foreach ($decoded as $code => $val) {
                if (!isset($componentTypeMap[$code]) && is_array($val) && isset($val['type'])) {
                    $componentTypeMap[$code] = $val['type'];
                }
            }
        }

        $allComponents = $componentKeys->mapWithKeys(function ($code) use ($componentMasters, $componentTypeMap) {

            $m = $componentMasters[$code] ?? null;

            return [$code => (object)[
                'code' => $code,
                'name' => $m->name ?? strtoupper(str_replace('_', ' ', $code)),
                'type' => $componentTypeMap[$code] ?? ($m->type ?? 'earning'),
                'orders' => 0
            ]];
        });

        foreach ($data as $item) {
            foreach (json_decode($item->components, true) ?? [] as $k => $v) {
                // dukung struktur baru {"amount":..,"type":..} maupun struktur lama (angka langsung)
                $item->$k = is_array($v) ? ($v['amount'] ?? 0) : $v;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SPLIT EMPLOYEE BASE
        |--------------------------------------------------------------------------
        */
        /*
|--------------------------------------------------------------------------
| ACTIVE
|--------------------------------------------------------------------------
*/
        $activeEmployees = $data->filter(function ($r) {

            $tmk = !empty($r->TMK) ? \Carbon\Carbon::parse($r->TMK) : null;
            $isTMKInPeriod = $tmk && $tmk->betweenIncluded(
                \Carbon\Carbon::parse($r->start_date),
                \Carbon\Carbon::parse($r->end_date)
            );

            // Baru (TMK di periode) tetap dianggap Active di export ini,
            // atau TKK kosong, atau TKK di atas akhir periode (belum resign di periode ini)
            return empty($r->TKK) || $r->TKK > $r->end_date;
        });

        /*
|--------------------------------------------------------------------------
| RESIGN
|--------------------------------------------------------------------------
*/
        $resignEmployees = $data->filter(function ($r) {

            $tmk = !empty($r->TMK) ? \Carbon\Carbon::parse($r->TMK) : null;
            $isTMKInPeriod = $tmk && $tmk->betweenIncluded(
                \Carbon\Carbon::parse($r->start_date),
                \Carbon\Carbon::parse($r->end_date)
            );

            $ket = strtoupper(trim($r->KETERANGAN ?? ''));

            // Resign: bukan Baru (TMK tidak di periode), TKK ada di dalam range periode, dan keterangan BUKAN MA
            return !empty($r->TKK)
                && $r->TKK >= $r->start_date
                && $r->TKK <= $r->end_date
                && $ket !== 'MA';
        });

        /*
|--------------------------------------------------------------------------
| MANGKIR
|--------------------------------------------------------------------------
*/
        $mangkirEmployees = $data->filter(function ($r) {

            $tmk = !empty($r->TMK) ? \Carbon\Carbon::parse($r->TMK) : null;
            $isTMKInPeriod = $tmk && $tmk->betweenIncluded(
                \Carbon\Carbon::parse($r->start_date),
                \Carbon\Carbon::parse($r->end_date)
            );

            $ket = strtoupper(trim($r->KETERANGAN ?? ''));

            // Mangkir: bukan Baru (TMK tidak di periode), TKK ada di dalam range periode, dan keterangan MA
            return !empty($r->TKK)
                && $r->TKK >= $r->start_date
                && $r->TKK <= $r->end_date
                && $ket === 'MA';
        });

        /*
        |--------------------------------------------------------------------------
        | CATEGORY SPLIT (NEW ADDITION)
        |--------------------------------------------------------------------------
        */
        $categories = [

            'ALL' => fn($r) => true,

            'STAFF' => fn($r) => $r->IS_STAFF == 1,

            'NONSTAFF' => fn($r) => $r->IS_STAFF == 0,

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
                'NONSTAFF' => $folderNonStaff,
                'SEWING' => $folderSewing,
                'NON_SEWING' => $folderNonSewing,
                default => $folder
            };

            $password = match ($key) {
                'STAFF' => PdfPassword::generate('staff', $data->first()->start_date),
                'NONSTAFF' => PdfPassword::generate('nonstaff', $data->first()->start_date),
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
            $mangkir = $mangkirEmployees->filter($filter);

            // dd($resign, $mangkir);

            if ($active->isEmpty() && $resign->isEmpty() && $mangkir->isEmpty()) continue;

            $folderName = match ($key) {
                'STAFF'       => 'STAFF',
                'NONSTAFF'    => 'NON STAFF',
                'SEWING'      => 'SEWING',
                'NON_SEWING'  => 'NON SEWING',
                default       => 'ALL',
            };

            $viewData = [
                'groupedActive' => $active->groupBy('DEPARTEMENT'),
                'groupedResign' => $resign->groupBy('DEPARTEMENT'),
                'groupedMangkir' => $mangkir->groupBy('DEPARTEMENT'),

                'allComponents' => $allComponents,

                'activeTotals' => $calcTotals($active),
                'resignTotals' => $calcTotals($resign),
                'mangkirTotals' => $calcTotals($mangkir),

                'run_id' => $run_id,
                'approvals' => $approvals,
                'folderName' => $folderName,
            ];

            /*
            |--------------------------------------------------------------------------
            | RENDER HTML MANUAL
            |--------------------------------------------------------------------------
            */
            // dd(
            //     $active->pluck('employee_npk'),
            //     $resign->pluck('employee_npk'),
            //     $mangkir->pluck('employee_npk'),

            //     $active->groupBy('DEPARTEMENT'),
            //     $resign->groupBy('DEPARTEMENT'),
            //     $mangkir->groupBy('DEPARTEMENT')
            // );
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
                ->setOption('no-stop-slow-scripts', true)
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
