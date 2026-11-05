<?php

namespace App\Jobs;

use App\Exports\NonSewing\PayrollExportNonSewingExcel;
use App\Exports\NonStaff\PayrollExportNonStaffExcel;
use App\Exports\PayrollExportExcel;
use App\Exports\Sewing\PayrollExportSewingExcel;
use App\Exports\Staff\PayrollExportStaffExcel;
use App\Helpers\PdfPassword;
use App\Models\PayrollComponent;
use App\Models\PayrollExport;
use App\Services\ExcelZipEncryptService;
use App\Services\PdfService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

class GeneratePayrollExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 1;

    /** Maksimum total_salary per file CSV bank (3 Milyar) */
    private const MAX_TOTAL_PER_BANK_FILE = 3000000000;

    protected $export_id;
    protected $type;

    public function __construct($export_id, $type)
    {
        $this->export_id = $export_id;
        $this->type = $type;
    }

    public function failed(\Throwable $e): void
    {
        PayrollExport::where('id', $this->export_id)->update(['status' => 'failed']);
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
        | IS_EXCLUDE dipakai hanya untuk CSV bank (generateBank).
        | Jika BIODATA_KELUAR tidak punya kolom IS_EXCLUDE, ganti dengan
        | DB::raw('0 as IS_EXCLUDE') pada select BIODATA_KELUAR.
        |--------------------------------------------------------------------------
        */
        $export->update([
            'progress' => 15,
            'status' => 'Processing Employee Data'
        ]);

        $employeeUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'ID_DEPT', 'IS_STAFF', 'IS_EXCLUDE')
            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'ID_DEPT', 'IS_STAFF', 'IS_EXCLUDE')
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
            ->select('p1.NPK', 'p1.TMK', 'p1.TKK', 'p1.KETERANGAN')
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
                SUM(CASE WHEN JUMLAH_JAM_LEMBUR = 'CU' THEN 1 ELSE 0 END) as CU,
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

        // Jumlah hari periode (sama seperti GeneratePayrollProcess)
        $countDays = Carbon::parse($period->start_date)
            ->diffInDays(Carbon::parse($period->end_date)) + 1;

        // Kontrak terbaru yang berlaku di periode -> salary & daily_salary
        $latestContract = DB::table('employees_contract as ec1')
            ->select('ec1.npk', 'ec1.salary', 'ec1.daily_salary', 'ec1.type')
            ->whereDate('ec1.start_date', '<=', $period->end_date)
            ->whereDate('ec1.end_date', '>=', $period->start_date)
            ->whereRaw("
                ec1.id = (
                    SELECT TOP 1 ec2.id
                    FROM employees_contract ec2
                    WHERE ec2.npk = ec1.npk
                      AND ec2.start_date <= ?
                      AND ec2.end_date >= ?
                    ORDER BY ec2.contract_ke DESC,
                             ec2.start_date DESC
                )
            ", [$period->end_date, $period->start_date]);

        // Rekening Permata (dipakai untuk CSV bank)
        $permataAccount = DB::table('payroll_masters')
            ->select('npk', 'bank_account')
            ->whereRaw("LOWER(bank_name) = 'permata bank'");

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
            ->leftJoinSub(
                $latestContract,
                'ec',
                fn($j) => $j->on('ec.npk', '=', 'prd.employee_npk')
            )
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->leftJoinSub(
                $overtimeAgg,
                'ot',
                fn($j) => $j->on('ot.NPK', '=', 'prd.employee_npk')
            )
            ->leftJoinSub(
                $ijinSummary,
                'ij',
                fn($j) => $j->on('emp.NPK', '=', 'ij.npk')
            )
            ->leftJoinSub(
                $permataAccount,
                'pm',
                fn($j) => $j->on('pm.npk', '=', 'prd.employee_npk')
            )
            ->where('prd.run_id', $run_id)
            ->select(
                'prd.*',
                'd.DEPARTEMENT',
                'pp.id as period_id',
                'pp.name as period_name',
                'pp.start_date',
                'pp.end_date',
                'p.TKK',
                'p.TMK',
                'prd.employee_staff as IS_STAFF',
                'prd.employee_expat as IS_EXPAT',
                'emp.IS_EXCLUDE',
                'd.IS_SEWING',
                'p.KETERANGAN',

                // overtime hasil agregasi
                'ot.MA',
                'ot.P1',
                'ot.CU',
                'ot.CT',
                'ot.SD',
                'ot.BR',
                'ot.OUT',
                'ij.total_ijin_minutes',
                'ec.salary as contract_salary',
                'ec.daily_salary as contract_daily_salary',
                'ec.type as contract_type',

                // rekening bank (CSV Permata)
                'pm.bank_account'
            )
            ->orderBy('d.DEPARTEMENT')
            ->orderBy('prd.employee_npk')
            ->get();

        if ($data->isEmpty()) {
            $export->update([
                'status' => 'No Data',
                'progress' => 100,
            ]);
            return;
        }

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
        $folderSewing = "$folder/SEWING";
        $folderNonSewing = "$folder/NON_SEWING";

        Storage::makeDirectory($folder, 0777, true);
        Storage::makeDirectory($folderStaff, 0777, true);
        Storage::makeDirectory($folderNonStaff, 0777, true);
        Storage::makeDirectory($folderSewing, 0777, true);
        Storage::makeDirectory($folderNonSewing, 0777, true);

        /*
        |--------------------------------------------------------------------------
        | EXCEL
        |--------------------------------------------------------------------------
        */
        $zipService = app(ExcelZipEncryptService::class);

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

        /*
        |--------------------------------------------------------------------------
        | COMPONENT MASTER
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

            // Selisih khusus STAFF + EXPAT bertipe Daily: (daily_salary * count_days) - salary.
            // total_salary dari GeneratePayrollProcess SUDAH memuat selisih ini, tetapi komponen
            // basic_salary disimpan = salary (kontrak). Rekap/Pengeluaran PDF menjumlah dari komponen,
            // jadi selisih ditambahkan ke basic_salary supaya total & net sama dengan total_salary.
            // (total_salary sendiri TIDAK diubah, agar tidak dihitung dua kali.)
            $item->selisih_expat = 0;
            if (
                $item->IS_STAFF == '1' && $item->IS_EXPAT == '1'
                && strtolower(trim($item->contract_type ?? '')) === 'daily'
                && (float) $item->contract_daily_salary > 0
            ) {
                $item->selisih_expat = ((float) $item->contract_daily_salary * (float) $countDays)
                    - (float) $item->contract_salary;

                $item->basic_salary = (float) ($item->basic_salary ?? 0) + $item->selisih_expat;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SPLIT EMPLOYEE: ACTIVE / RESIGN / MANGKIR
        |--------------------------------------------------------------------------
        | Dipakai bersama oleh PDF dan CSV bank, supaya total keduanya selalu sama.
        | Catatan: karyawan IS_EXCLUDE = 1 tetap ada di sini (ikut PDF & Excel);
        | filter exclude hanya diterapkan di generateBank().
        |--------------------------------------------------------------------------
        */

        // ACTIVE: TKK kosong, atau TKK di atas akhir periode (belum resign di periode ini).
        // Karyawan baru (TMK di periode) tetap Active.
        $activeEmployees = $data->filter(function ($r) {
            return empty($r->TKK) || $r->TKK > $r->end_date;
        });

        // RESIGN: TKK di dalam periode dan keterangan BUKAN MA
        $resignEmployees = $data->filter(function ($r) {
            $ket = strtoupper(trim($r->KETERANGAN ?? ''));

            return !empty($r->TKK)
                && $r->TKK >= $r->start_date
                && $r->TKK <= $r->end_date
                && $ket !== 'MA';
        });

        // MANGKIR: TKK di dalam periode dan keterangan MA
        $mangkirEmployees = $data->filter(function ($r) {
            $ket = strtoupper(trim($r->KETERANGAN ?? ''));

            return !empty($r->TKK)
                && $r->TKK >= $r->start_date
                && $r->TKK <= $r->end_date
                && $ket === 'MA';
        });

        /*
        |--------------------------------------------------------------------------
        | CATEGORY SPLIT
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
                unset($total);
            }

            return $totals;
        };

        /*
        |--------------------------------------------------------------------------
        | APPROVAL BUILDER
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
        | PDF + EXCEL ENCRYPT PER CATEGORY
        |--------------------------------------------------------------------------
        */
        $suffix = $this->type === 'process' ? '' : '_APPROVED';

        $totalCategories = count($categories);
        $step = 0;

        foreach ($categories as $key => $filter) {

            $step++;
            $progress = 60 + (int) round(30 * $step / $totalCategories);

            $export->update([
                'progress' => $progress,
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
                    $password
                );
            }

            $active = $activeEmployees->filter($filter);
            $resign = $resignEmployees->filter($filter);
            $mangkir = $mangkirEmployees->filter($filter);

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
                'progress' => $progress,
                'status' => "Generating PDF Files for $key"
            ]);

            // ===== Ukuran halaman REKAP dinamis (mm) =====
            // Lebar kolom HARUS sama dengan konstanta di view payroll/rekap_pdf.blade.php
            $wNpk = 22;
            $wName = 48;
            $wAbs = 12;
            $wIjin = 18;
            $wComp = 28;
            $wTotal = 34;

            $rekapTableMm = $wNpk + $wName + ($wAbs * 7) + $wIjin
                + ($wComp * max($allComponents->count(), 1)) + $wTotal;

            // + margin kiri/kanan (8mm x 2) + cadangan 4mm
            $rekapPageW = $rekapTableMm + 16 + 4;

            $pdf = App::make('snappy.pdf.wrapper');
            $pdf->setTimeout(600);
            $pdf->loadHTML($htmlRekap)
                ->setOption('page-width', $rekapPageW . 'mm')
                ->setOption('page-height', '297mm')
                ->setOption('margin-top', '10mm')
                ->setOption('margin-bottom', '10mm')
                ->setOption('margin-left', '8mm')
                ->setOption('margin-right', '8mm')
                ->setOption('disable-smart-shrinking', true)
                ->setOption('enable-local-file-access', true)
                ->setOption('no-stop-slow-scripts', true)
                ->setOption('encoding', 'UTF-8');

            $pdfPeng = App::make('snappy.pdf.wrapper');
            $pdfPeng->setTimeout(600);
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

            foreach ([$fullPdfPath, $fullPdfPengPath, $fullPdfPathTemp, $fullPdfPengPathTemp] as $old) {
                if (File::exists($old)) File::delete($old);
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE + ENCRYPT
            |--------------------------------------------------------------------------
            */
            $pdf->save($fullPdfPathTemp);
            $pdfPeng->save($fullPdfPengPathTemp);

            $export->update([
                'progress' => $progress,
                'status' => "Encrypting PDF Files for $key"
            ]);

            PdfService::protect($fullPdfPathTemp, $fullPdfPath, $password);
            PdfService::protect($fullPdfPengPathTemp, $fullPdfPengPath, $password);

            $export->update([
                'progress' => $progress,
                'status' => "Deleting Temporary PDF Files for $key"
            ]);

            if (File::exists($fullPdfPathTemp)) File::delete($fullPdfPathTemp);
            if (File::exists($fullPdfPengPathTemp)) File::delete($fullPdfPengPathTemp);

            if ($key === 'ALL') {
                $export->update([
                    'file_pdf' => str_replace('public/', '', "REKAP_{$periodNameFormatted}{$suffix}.pdf"),
                    'file_peng' => str_replace('public/', '', "PENGELUARAN_{$periodNameFormatted}{$suffix}.pdf"),
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | BANK CSV (hanya setelah final approve)
        |--------------------------------------------------------------------------
        */
        if ($this->type !== 'process') {
            $export->update([
                'progress' => 95,
                'status' => 'Generating Bank Files'
            ]);

            $this->generateBank(
                $run_id,
                $approve,
                $data->first()->period_id,
                $periodName,
                $periodNameFormatted,
                $categories,
                $activeEmployees,
                $resignEmployees,
                $mangkirEmployees
            );
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

    /*
    |--------------------------------------------------------------------------
    | GENERATE BANK (dipindah dari PayrollApproveController)
    |--------------------------------------------------------------------------
    | Pengelompokan karyawan (active/resign/mangkir + STAFF/SEWING/dst) memakai
    | hasil logic export di atas, jadi total CSV bank = total PDF/Excel,
    | KECUALI karyawan BIODATA.IS_EXCLUDE = 1 yang tidak diikutkan di CSV bank.
    | Format CSV (kolom Permata) tetap sama seperti di controller sebelumnya.
    |
    | File disimpan di payroll/{PERIODE}/ (gabungan) dan payroll/{PERIODE}/{ROLE}/.
    | Kolom file_bank_active/resign/mangkir diisi JSON per role, contoh:
    | {"ALL":"PERMATA_JULI_2026_AKTIF.csv",
    |  "STAFF":"PERMATA_JULI_2026_STAFF_AKTIF.csv",
    |  "NON_STAFF":["..._NON_STAFF_AKTIF_PART1.csv","..._NON_STAFF_AKTIF_PART2.csv"]}
    |--------------------------------------------------------------------------
    */
    private function generateBank(
        $runId,
        $approve,
        $periodId,
        $periodName,
        $cleanPeriod,
        array $categories,
        $activeEmployees,
        $resignEmployees,
        $mangkirEmployees
    ): bool {
        // hanya boleh jika semua level sudah approve
        if (!$approve || $approve->status !== 'finish') {
            return false;
        }

        // Karyawan BIODATA.IS_EXCLUDE = 1 tidak ikut CSV bank
        // (tetap ada di PDF & Excel karena filter hanya berlaku di sini).
        // NULL dianggap tidak di-exclude.
        $notExcluded = fn($r) => (int) ($r->IS_EXCLUDE ?? 0) !== 1;

        $activeEmployees  = $activeEmployees->filter($notExcluded)->values();
        $resignEmployees  = $resignEmployees->filter($notExcluded)->values();
        $mangkirEmployees = $mangkirEmployees->filter($notExcluded)->values();

        if ($activeEmployees->isEmpty() && $resignEmployees->isEmpty() && $mangkirEmployees->isEmpty()) {
            return false;
        }

        $sourceByStatus = [
            'AKTIF'   => $activeEmployees,
            'RESIGN'  => $resignEmployees,
            'MANGKIR' => $mangkirEmployees,
        ];

        // key kategori export -> nama folder / key JSON bank
        $roleFolders = [
            'STAFF'      => 'STAFF',
            'SEWING'     => 'SEWING',
            'NON_SEWING' => 'NON_SEWING',
            'NONSTAFF'   => 'NON_STAFF',
        ];

        $bankFilesByStatus = ['AKTIF' => [], 'RESIGN' => [], 'MANGKIR' => []];

        foreach ($sourceByStatus as $statusLabel => $source) {

            /*
            | FILE GABUNGAN (ALL) - tidak di-split, disimpan langsung di payroll/{PERIODE}/
            */
            if ($source->isNotEmpty()) {
                $allFileName = "PERMATA_{$cleanPeriod}_{$statusLabel}.csv";

                $this->createBankCSV($source->values(), $periodName, "payroll/{$cleanPeriod}/{$allFileName}");

                $bankFilesByStatus[$statusLabel]['ALL'] = $allFileName;
            }

            /*
            | FILE PER ROLE - di-split per maksimum total_salary
            */
            foreach ($roleFolders as $categoryKey => $roleFolder) {

                $rows = $source->filter($categories[$categoryKey])->values();

                if ($rows->isEmpty()) {
                    continue; // jangan buat file kosong
                }

                $chunks = $this->splitByMaxTotalSalary($rows, self::MAX_TOTAL_PER_BANK_FILE);
                $partNames = [];

                foreach ($chunks as $index => $chunkEmployees) {
                    $part = $index + 1;

                    $fileName = count($chunks) > 1
                        ? "PERMATA_{$cleanPeriod}_{$roleFolder}_{$statusLabel}_PART{$part}.csv"
                        : "PERMATA_{$cleanPeriod}_{$roleFolder}_{$statusLabel}.csv";

                    $this->createBankCSV(
                        $chunkEmployees,
                        $periodName,
                        "payroll/{$cleanPeriod}/{$roleFolder}/{$fileName}"
                    );

                    $partNames[] = $fileName;
                }

                // 1 part = string biasa, lebih dari 1 = array
                $bankFilesByStatus[$statusLabel][$roleFolder] = count($partNames) > 1
                    ? $partNames
                    : $partNames[0];
            }
        }

        DB::table('payroll_exports')->updateOrInsert(
            ['run_id' => $runId],
            [
                'file_bank_active'  => json_encode($bankFilesByStatus['AKTIF']),
                'file_bank_resign'  => json_encode($bankFilesByStatus['RESIGN']),
                'file_bank_mangkir' => json_encode($bankFilesByStatus['MANGKIR']),
            ]
        );

        // tutup periode setelah bank selesai dibuat
        DB::table('payroll_periods')
            ->where('id', $periodId)
            ->update(['is_closed' => 1]);

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | SPLIT EMPLOYEES BY MAX TOTAL_SALARY PER FILE
    |--------------------------------------------------------------------------
    | Karyawan diakumulasi sesuai urutan (dept, npk) ke dalam 1 file selama
    | total_salary belum melebihi $maxTotal. Begitu penambahan karyawan
    | berikutnya membuat total melebihi batas, file baru dibuka.
    |--------------------------------------------------------------------------
    */
    private function splitByMaxTotalSalary($employees, $maxTotal): array
    {
        $chunks = [];
        $currentChunk = collect();
        $currentTotal = 0;

        foreach ($employees as $emp) {
            $salary = (float) ($emp->total_salary ?? 0);

            if ($currentChunk->isNotEmpty() && ($currentTotal + $salary) > $maxTotal) {
                $chunks[] = $currentChunk;
                $currentChunk = collect();
                $currentTotal = 0;
            }

            $currentChunk->push($emp);
            $currentTotal += $salary;
        }

        if ($currentChunk->isNotEmpty()) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    private function createBankCSV($employees, $periodName, $path): void
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($employees as $emp) {
            fputcsv($handle, [
                'PERMATA',                                            // A
                '',                                                   // B
                '',                                                   // C
                '',                                                   // D
                strtoupper(trim($emp->employee_name ?? '')),          // E
                $emp->bank_account ?? '',                             // F
                'IDR',                                                // G
                number_format($emp->total_salary ?? 0, 0, '', ''),    // H
                'GAJI ' . strtoupper($periodName),                    // I
                '',                                                   // J
                '',                                                   // K
                'OVB',                                                // L
                0,                                                    // M
                0,                                                    // N
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('public')->put($path, $content);
    }
}