<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePayrollExport;
use App\Models\PayrollApprove;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class PayrollApproveController extends Controller
{

    public function index()
    {
        // =========================
        // JOIN PAYROLL RUN + PERIOD + EXPORT
        // =========================
        $data = PayrollApprove::query()
            ->join('payroll_runs', 'payroll_approve.payroll_run_id', '=', 'payroll_runs.id')
            ->join('payroll_periods', 'payroll_runs.period_id', '=', 'payroll_periods.id')
            ->leftJoin('payroll_exports', 'payroll_exports.run_id', '=', 'payroll_runs.id') // 🔥 tambahan
            ->select(
                'payroll_approve.*',
                'payroll_periods.name as period_name',
                'payroll_exports.file_bank_active',
                'payroll_exports.file_bank_resign',
                'payroll_exports.file_excel',
                'payroll_exports.file_pdf',
                'payroll_exports.file_peng',
                'payroll_exports.status as export_status'
            )
            ->latest('payroll_approve.id')
            ->get();

        // =========================
        // UNION BIODATA + BIODATA_KELUAR
        // =========================
        $employees = collect(DB::select("
        SELECT NPK, NAMA_KARYAWAN FROM BIODATA
        UNION
        SELECT NPK, NAMA_KARYAWAN FROM BIODATA_KELUAR
    "))->keyBy('NPK');

        // =========================
        // MAPPING NAMA + FLAG EXPORT
        // =========================
        // dd($data);
        $data->transform(function ($row) use ($employees) {

            // 🔥 FLAG EXPORT
            $row->is_exported = !empty($row->file_excel) && !empty($row->file_pdf);

            $progress = collect($row->progress)->map(function ($p) use ($employees) {

                $npkList = is_array($p['npk'])
                    ? $p['npk']
                    : json_decode($p['npk'], true);

                if (!is_array($npkList)) $npkList = [];

                $names = collect($npkList)->map(function ($npk) use ($employees) {
                    return [
                        'npk' => $npk,
                        'name' => $employees[$npk]->NAMA_KARYAWAN ?? '-'
                    ];
                });

                $p['users'] = $names;

                return $p;
            });

            $row->progress = $progress;

            return $row;
        });

        return view('payroll_approve.index', compact('data'));
    }
    // 🔹 Create approval dari setting
    public function store($payroll_run_id)
    {
        $settings = PayrollSetting::orderBy('level')->get();

        $approvals = $settings->pluck('approval')->toArray();

        $progress = collect($approvals)->map(function ($npk) {
            return [
                'npk' => $npk,
                'status' => 'pending'
            ];
        });

        return PayrollApprove::create([
            'payroll_run_id' => $payroll_run_id,
            'approval' => $approvals,
            'progress' => $progress,
            'status' => 'pending'
        ]);
    }

    // 🔹 Approve by NPK
    public function approve(Request $request, $id)
    {
        $data = PayrollApprove::findOrFail($id);
        $npkLogin = $request->npk;

        $export = DB::table('payroll_exports')->where('run_id', $data->payroll_run_id)->first();

        $progress = collect($data->progress);
        $approvedAt = collect($data->approved_at ?? []);

        $currentIndex = $progress->search(function ($item) {
            return $item['status'] === 'pending' || str_contains($item['status'], 'waiting');
        });

        if ($currentIndex === false) {
            return response()->json(['message' => 'Semua sudah approve'], 400);
        }

        $row = $progress[$currentIndex];

        $npkList = is_array($row['npk']) ? $row['npk'] : json_decode($row['npk'], true);

        if (!is_array($npkList)) {
            return response()->json(['message' => 'Format NPK invalid'], 500);
        }

        // status decode
        if ($row['status'] === 'pending') {
            $statusList = array_fill(0, count($npkList), 'waiting');
        } else {
            $statusList = json_decode($row['status'], true);
        }

        $userIndex = array_search($npkLogin, $npkList);

        if ($userIndex === false) {
            return response()->json(['message' => 'Anda bukan approver'], 403);
        }

        if ($statusList[$userIndex] === 'approve') {
            return response()->json(['message' => 'Sudah approve'], 400);
        }

        // update status
        $statusList[$userIndex] = 'approve';

        $approvedAtArr = $approvedAt->toArray();
        $approvedAtArr[$currentIndex][$userIndex] = now();

        // cek level selesai
        $allApproved = collect($statusList)->every(fn($s) => $s === 'approve');

        $progressArr = $progress->toArray();

        if ($allApproved) {
            $progressArr[$currentIndex]['status'] = 'approve';
        } else {
            $progressArr[$currentIndex]['status'] = json_encode($statusList);
        }

        $progress = collect($progressArr);
        $approvedAt = collect($approvedAtArr);

        // cek final
        $finalApprove = $progress->every(fn($item) => $item['status'] === 'approve');

        $data->update([
            'progress' => $progress->values(),
            'approved_at' => $approvedAt->values(),
            'status' => $finalApprove ? 'finish' : 'pending'
        ]);

        // =========================
        // 🔥 AUTO GENERATE BANK
        // =========================
        if ($finalApprove) {
            $this->generateBank($data->payroll_run_id, $export->id); // 🔥 pakai run_id dan export_id
        }

        return response()->json([
            'message' => 'Approval berhasil'
        ]);
    }

    public function generateBank($runId, $exportId)
    {
        /*
    |--------------------------------------------------------------------------
    | VALIDASI APPROVAL
    |--------------------------------------------------------------------------
    */
        $approve = DB::table('payroll_approve')
            ->where('payroll_run_id', $runId)
            ->first();

        if (!$approve || $approve->status !== 'finish') {
            return false;
        }

        /*
    |--------------------------------------------------------------------------
    | PERIODE
    |--------------------------------------------------------------------------
    */
        $period = DB::table('payroll_runs as pr')
            ->join('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('pr.id', $runId)
            ->select('pp.name', 'pp.start_date', 'pp.end_date', 'pp.id')
            ->first();

        if (!$period) {
            return false;
        }

        /*
    |--------------------------------------------------------------------------
    | EMPLOYEE UNION (AKTIF + RESIGN)
    |--------------------------------------------------------------------------
    */
        $employeeUnion = DB::table('BIODATA')
            ->select('NPK', 'id_dept')

            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'id_dept')
            );

        $employeeData = DB::query()
            ->fromSub($employeeUnion, 'bio')
            ->leftJoin('payroll_masters as pm', 'pm.npk', '=', 'bio.NPK')
            ->select(
                'bio.NPK',
                'bio.id_dept',
                'pm.bank_name',
                'pm.bank_account'
            );

        /*
    |--------------------------------------------------------------------------
    | TANGGAL RESIGN TERBARU
    |--------------------------------------------------------------------------
    */
        $pkwtLatest = DB::table('PKWT')
            ->select('NPK', DB::raw('MAX(TKK) as TKK'))
            ->groupBy('NPK');

        /*
    |--------------------------------------------------------------------------
    | DATA PAYROLL
    |--------------------------------------------------------------------------
    */
        $data = DB::table('payroll_run_details as prd')

            ->leftJoinSub($employeeData, 'emp', function ($join) {
                $join->on('emp.NPK', '=', 'prd.employee_npk')
                    ->whereRaw("LOWER(emp.bank_name) = 'permata'");
            })

            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'emp.id_dept')

            ->leftJoinSub($pkwtLatest, 'p', function ($join) {
                $join->on('p.NPK', '=', 'prd.employee_npk');
            })

            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')

            ->where('prd.run_id', $runId)

            ->select(
                'prd.employee_npk',
                'prd.employee_name',
                'prd.total_salary',
                'emp.bank_account',
                'd.DEPARTEMENT',
                'pp.start_date',
                'pp.end_date',
                'p.TKK'
            )

            ->orderBy('d.DEPARTEMENT')
            ->orderBy('prd.employee_npk')
            ->get();

        if ($data->isEmpty()) {
            return false;
        }

        /*
    |--------------------------------------------------------------------------
    | PISAH AKTIF VS RESIGN
    |--------------------------------------------------------------------------
    */
        $activeEmployees = $data->filter(function ($row) {
            return empty($row->TKK);
        });

        $resignEmployees = $data->filter(function ($row) {

            if (empty($row->TKK)) {
                return false;
            }

            return $row->TKK >= $row->start_date &&
                $row->TKK <= $row->end_date;
        });

        /*
    |--------------------------------------------------------------------------
    | GROUP DEPARTMENT
    |--------------------------------------------------------------------------
    */
        $groupedActive = $activeEmployees->groupBy('DEPARTEMENT');
        $groupedResign = $resignEmployees->groupBy('DEPARTEMENT');

        /*
    |--------------------------------------------------------------------------
    | GENERATE CSV
    |--------------------------------------------------------------------------
    */
        $cleanPeriod = str_replace(' ', '_', $period->name);

        $activePath = "payroll/PERMATA_{$cleanPeriod}_AKTIF.csv";
        $resignPath = "payroll/PERMATA_{$cleanPeriod}_RESIGN.csv";

        $this->createBankCSV($groupedActive, $period->name, $activePath);
        $this->createBankCSV($groupedResign, $period->name, $resignPath);


        GeneratePayrollExport::dispatch($exportId, 'approve');

        /*
    |--------------------------------------------------------------------------
    | UPDATE EXPORT TABLE
    |--------------------------------------------------------------------------
    */
        DB::table('payroll_exports')->updateOrInsert(
            ['run_id' => $runId],
            [
                'file_bank_active' => $activePath,
                'file_bank_resign' => $resignPath
            ]
        );

        DB::table('payroll_periods')->updateOrInsert(
            ['id' => $period->id],
            [
                'is_closed' => 1
            ]
        );

        return true;
    }

    private function createBankCSV($groupedData, $periodName, $path)
    {
        $handle = fopen('php://temp', 'r+');

        /*
    |--------------------------------------------------------------------------
    | HEADER
    |--------------------------------------------------------------------------
    */
        fputcsv($handle, [
            'No Rekening Tujuan',
            'Nama Penerima',
            'Bank',
            'Kode Bank',
            'Nominal',
            'Keterangan'
        ]);

        /*
    |--------------------------------------------------------------------------
    | DATA
    |--------------------------------------------------------------------------
    */
        foreach ($groupedData as $dept => $employees) {

            foreach ($employees as $emp) {

                // if (empty($emp->bank_account) || $emp->total_salary <= 0) {
                //     continue;
                // }

                fputcsv($handle, [
                    $emp->bank_account ?? '',
                    strtoupper($emp->employee_name),
                    'PERMATA',
                    '013',
                    number_format($emp->total_salary ?? 0, 0, '', ''),
                    'GAJI ' . strtoupper($periodName)
                ]);
            }
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('public')->put($path, $content);
    }
}
