<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use App\Jobs\GenerateThrExport;
use App\Models\PayrollSetting;
use App\Models\ThrApprove;
use App\Models\ThrSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class ThrApproveController extends Controller
{

    public function index()
    {
        // =========================
        // JOIN PAYROLL RUN + PERIOD + EXPORT
        // =========================
        $data = ThrApprove::query()
            ->join('thr_runs', 'thr_approve.thr_run_id', '=', 'thr_runs.id')
            ->join('thr_periods', 'thr_runs.period_id', '=', 'thr_periods.id')
            ->leftJoin('thr_exports', 'thr_exports.run_id', '=', 'thr_runs.id') // 🔥 tambahan
            ->select(
                'thr_approve.*',
                'thr_periods.name as period_name',
                'thr_exports.file_bank',
                'thr_exports.file_excel',
                'thr_exports.file_pdf',
                'thr_exports.file_peng',
                'thr_exports.status as export_status'
            )
            ->latest('thr_approve.id')
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

        return view('thr_approve.index', compact('data'));
    }
    // 🔹 Create approval dari setting
    public function store($thr_run_id)
    {
        $settings = PayrollSetting::orderBy('level')->get();

        $approvals = $settings->pluck('approval')->toArray();

        $progress = collect($approvals)->map(function ($npk) {
            return [
                'npk' => $npk,
                'status' => 'pending'
            ];
        });

        return ThrApprove::create([
            'thr_run_id' => $thr_run_id,
            'approval' => $approvals,
            'progress' => $progress,
            'status' => 'pending'
        ]);
    }

    // 🔹 Approve by NPK
    public function approve(Request $request, $id)
    {
        $user = Auth::user();
        $data = ThrApprove::findOrFail($id);
        $npkLogin = $request->npk;

        $export = DB::table('thr_exports')->select('thr_exports.id', 'thr_periods.name')->leftJoin('thr_runs', 'thr_runs.id', '=', 'thr_exports.run_id')->leftJoin('thr_periods', 'thr_periods.id', '=', 'thr_runs.period_id')->where('run_id', $data->thr_run_id)->first();

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

        event(new NotificationEvent(
            'THR Approval!',
            'Users : ' . $user->name . ' has been approve THR ' . $export->name . '!',
            'success'
        ));

        // =========================
        // 🔥 AUTO GENERATE BANK
        // =========================
        if ($finalApprove) {
            event(new NotificationEvent(
                'THR Approval!',
                'THR ' . $export->name . ' has been approved!',
                'success'
            ));
            GenerateThrExport::dispatch($export->id, 'approve');
            $this->generateBank($data->thr_run_id, $export->id); // 🔥 pakai run_id
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
        $approve = DB::table('thr_approve')
            ->where('thr_run_id', $runId)
            ->first();

        if (!$approve || $approve->status !== 'finish') {
            return false;
        }

        /*
    |--------------------------------------------------------------------------
    | PERIODE
    |--------------------------------------------------------------------------
    */
        $period = DB::table('thr_runs as pr')
            ->join('thr_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('pr.id', $runId)
            ->select('pp.name')
            ->first();

        if (!$period) return false;

        /*
    |--------------------------------------------------------------------------
    | UNION EMPLOYEE
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
    | DATA THR
    |--------------------------------------------------------------------------
    */
        $data = DB::table('thr_run_details as trd')

            ->leftJoinSub($employeeData, 'emp', function ($join) {
                $join->on('emp.NPK', '=', 'trd.employee_npk')
                    ->whereRaw("LOWER(emp.bank_name) = 'permata'");
            })

            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'emp.id_dept')

            ->leftJoin('thr_runs as tr', 'tr.id', '=', 'trd.run_id')
            ->leftJoin('thr_periods as pp', 'pp.id', '=', 'tr.period_id')

            ->where('trd.run_id', $runId)

            ->select(
                'trd.employee_npk',
                'trd.employee_name',
                'trd.total_salary',
                'emp.bank_account',
                'd.DEPARTEMENT'
            )

            ->orderBy('d.DEPARTEMENT')
            ->orderBy('trd.employee_npk')
            ->get();

        if ($data->isEmpty()) return false;

        /*
    |--------------------------------------------------------------------------
    | GROUP BY DEPARTMENT
    |--------------------------------------------------------------------------
    */
        $groupedData = $data->groupBy('DEPARTEMENT');

        /*
    |--------------------------------------------------------------------------
    | GENERATE CSV
    |--------------------------------------------------------------------------
    */
        $cleanPeriod = str_replace(' ', '_', $period->name);
        $fileName = "PERMATA_{$cleanPeriod}.csv";

        $filePath = "thr/" . $cleanPeriod . "/" . $fileName;

        $this->createBankCSV($groupedData, $period->name, $filePath);

        /*
    |--------------------------------------------------------------------------
    | UPDATE EXPORT TABLE
    |--------------------------------------------------------------------------
    */
        DB::table('thr_exports')->updateOrInsert(
            ['run_id' => $runId],
            [
                'file_bank' => $fileName
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

                fputcsv($handle, [
                    $emp->bank_account ?? '',
                    strtoupper($emp->employee_name),
                    'PERMATA',
                    '013',
                    number_format($emp->total_salary ?? 0, 0, '', ''),
                    strtoupper($periodName)
                ]);
            }
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('public')->put($path, $content);
    }
}
