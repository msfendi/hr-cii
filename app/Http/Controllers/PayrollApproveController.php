<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use App\Jobs\GeneratePayrollExport;
use App\Models\PayrollApprove;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class PayrollApproveController extends Controller
{

    public function index(Request $request)
    {

        // =========================
        // FILTER STATUS PERIOD
        // =========================
        $filter = $request->get('status', 'open');
        // =========================
        // JOIN PAYROLL RUN + PERIOD + EXPORT
        // =========================
        $query = PayrollApprove::query()
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
            );

        if ($filter === 'open') {
            $query->where('payroll_periods.is_closed', false);
        }

        if ($filter === 'closed') {
            $query->where('payroll_periods.is_closed', true);
        }

        $data = $query
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

        return view('payroll_approve.index', compact('data', 'filter'));
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
        $user = Auth::user();
        $data = PayrollApprove::findOrFail($id);
        $npkLogin = $request->npk;

        $export = DB::table('payroll_exports')->select('payroll_exports.id', 'payroll_periods.name')->leftJoin('payroll_runs', 'payroll_runs.id', '=', 'payroll_exports.run_id')->leftJoin('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.period_id')->where('run_id', $data->payroll_run_id)->first();
        // dd($export);
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
            'Payroll Approval!',
            'Users : ' . $user->name . ' has been approve Payroll ' . $export->name . '!',
            'success'
        ));

        // =========================
        // 🔥 AUTO GENERATE BANK
        // =========================
        if ($finalApprove) {
            event(new NotificationEvent(
                'Payroll Approval!',
                'Payroll ' . $export->name . ' has been approved!',
                'success'
            ));

            GeneratePayrollExport::dispatch($export->id, 'approve');
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
    | EMPLOYEE UNION (AKTIF + RESIGN) + PKWT
    |--------------------------------------------------------------------------
    */
        $aktif = DB::table('BIODATA as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.id_dept', 'p.TKK', 'p.TMK', 'b.IS_STAFF', 'p.KETERANGAN');

        $keluar = DB::table('BIODATA_KELUAR as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.id_dept', 'p.TKK', 'p.TMK', 'b.IS_STAFF', 'p.KETERANGAN');

        $union = $aktif->union($keluar);

        /*
    |--------------------------------------------------------------------------
    | BASE QUERY PAYROLL
    |--------------------------------------------------------------------------
    */
        $baseQuery = DB::table('payroll_run_details as prd')
            ->leftJoinSub($union, 'bio', function ($join) {
                $join->on('bio.NPK', '=', 'prd.employee_npk');
            })
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'prd.employee_dept')
            ->leftJoinSub(
                DB::table('payroll_masters')->select('npk', 'bank_name', 'bank_account'),
                'pm',
                function ($join) {
                    $join->on('pm.npk', '=', 'prd.employee_npk')
                        ->whereRaw("LOWER(pm.bank_name) = 'permata bank'");
                }
            )
            ->where('prd.run_id', $runId)
            ->select(
                'prd.employee_npk',
                'prd.employee_name',
                'prd.total_salary',
                'pm.bank_account',
                'd.DEPARTEMENT',
                'd.IS_SEWING',
                'bio.IS_STAFF',
                'bio.TKK',
                'bio.TMK',
                'bio.KETERANGAN'
            )
            ->orderBy('d.DEPARTEMENT')
            ->orderBy('prd.employee_npk');

        /*
    |--------------------------------------------------------------------------
    | ACTIVE
    | isActive = is_null(TKK) || (TKK > end_date) || (TMK between start & end)
    |--------------------------------------------------------------------------
    */
        $activeEmployees = (clone $baseQuery)
            ->where(function ($query) use ($period) {
                $query->whereNull('bio.TKK')
                    ->orWhere('bio.TKK', '>', $period->end_date);
            })
            ->get();

        /*
    |--------------------------------------------------------------------------
    | RESIGN
    | isResign = !is_null(TKK) && TKK between period && KETERANGAN <> 'MA'
    |            && !isTMKInPeriod
    |--------------------------------------------------------------------------
    */
        $resignEmployees = (clone $baseQuery)
            ->where(function ($query) use ($period) {
                $query->whereNotNull('bio.TKK')
                    ->whereBetween('bio.TKK', [$period->start_date, $period->end_date])
                    ->where(function ($q) {
                        $q->whereNull('bio.KETERANGAN')
                            ->orWhereRaw('UPPER(LTRIM(RTRIM(bio.KETERANGAN))) <> ?', ['MA']);
                    });
            })
            ->get();

        /*
    |--------------------------------------------------------------------------
    | MANGKIR
    | sama seperti RESIGN, hanya KETERANGAN = 'MA'
    |--------------------------------------------------------------------------
    */
        $mangkirEmployees = (clone $baseQuery)
            ->where(function ($query) use ($period) {
                $query->whereNotNull('bio.TKK')
                    ->whereBetween('bio.TKK', [$period->start_date, $period->end_date])
                    ->whereRaw('UPPER(LTRIM(RTRIM(bio.KETERANGAN))) = ?', ['MA']);
            })
            ->get();

        if ($activeEmployees->isEmpty() && $resignEmployees->isEmpty() && $mangkirEmployees->isEmpty()) {
            return false;
        }

        $cleanPeriod = str_replace(' ', '_', strtoupper($period->name));

        /*
    |--------------------------------------------------------------------------
    | GENERATE CSV UMUM (SEMUA ROLE DIGABUNG)
    | Disimpan langsung di payroll/{PERIODE}/, TIDAK masuk folder role
    |--------------------------------------------------------------------------
    */
        $activeFileName  = "PERMATA_{$cleanPeriod}_AKTIF.csv";
        $resignFileName  = "PERMATA_{$cleanPeriod}_RESIGN.csv";
        $mangkirFileName = "PERMATA_{$cleanPeriod}_MANGKIR.csv";

        $activePath  = "payroll/{$cleanPeriod}/{$activeFileName}";
        $resignPath  = "payroll/{$cleanPeriod}/{$resignFileName}";
        $mangkirPath = "payroll/{$cleanPeriod}/{$mangkirFileName}";

        $this->createBankCSV($activeEmployees, $period->name, $activePath);
        $this->createBankCSV($resignEmployees, $period->name, $resignPath);
        $this->createBankCSV($mangkirEmployees, $period->name, $mangkirPath);

        /*
    |--------------------------------------------------------------------------
    | GROUP BY ROLE (STAFF / SEWING / NON SEWING / NON STAFF)
    |--------------------------------------------------------------------------
    */
        $groupedActive  = $activeEmployees->groupBy(fn($emp) => $this->resolveGroup($emp));
        $groupedResign  = $resignEmployees->groupBy(fn($emp) => $this->resolveGroup($emp));
        $groupedMangkir = $mangkirEmployees->groupBy(fn($emp) => $this->resolveGroup($emp));

        /*
    |--------------------------------------------------------------------------
    | GENERATE CSV PER ROLE FOLDER
    | Disimpan di payroll/{PERIODE}/{ROLE}/
    |--------------------------------------------------------------------------
    */
        $roles = ['STAFF', 'SEWING', 'NON SEWING', 'NON STAFF'];

        foreach ($roles as $role) {
            $roleFolder = str_replace(' ', '_', $role); // NON_SEWING, NON_STAFF, dst
            $basePath   = "payroll/{$cleanPeriod}/{$roleFolder}";

            $statuses = [
                'AKTIF'   => $groupedActive->get($role, collect()),
                'RESIGN'  => $groupedResign->get($role, collect()),
                'MANGKIR' => $groupedMangkir->get($role, collect()),
            ];

            foreach ($statuses as $statusLabel => $employees) {
                if ($employees->isEmpty()) {
                    continue; // skip, tidak buat file kosong
                }

                $roleFileName = "PERMATA_{$cleanPeriod}_{$roleFolder}_{$statusLabel}.csv";
                $roleFilePath = "{$basePath}/{$roleFileName}";

                $this->createBankCSV($employees, $period->name, $roleFilePath);
            }
        }

        /*
    |--------------------------------------------------------------------------
    | UPDATE EXPORT TABLE
    | Hanya nama file umum (gabungan semua role) yang disimpan
    |--------------------------------------------------------------------------
    */
        DB::table('payroll_exports')->updateOrInsert(
            ['run_id' => $runId],
            [
                'file_bank_active'  => $activeFileName,
                'file_bank_resign'  => $resignFileName,
                'file_bank_mangkir' => $mangkirFileName,
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

    /*
|--------------------------------------------------------------------------
| RESOLVE GROUP / ROLE MAPPING
|--------------------------------------------------------------------------
| STAFF      : IS_STAFF = 1
| SEWING     : IS_STAFF = 0 && IS_SEWING = 0
| NON SEWING : IS_STAFF = 0 && IS_SEWING = 1
| NON STAFF  : IS_STAFF = 0 && IS_SEWING = null (dept tidak ketemu / kosong)
|--------------------------------------------------------------------------
*/
    private function resolveGroup($emp)
    {
        if ((int) $emp->IS_STAFF === 1) {
            return 'STAFF';
        } elseif ((int) $emp->IS_SEWING === 0 && (int) $emp->IS_STAFF === 0) {
            return 'SEWING';
        } elseif ((int) $emp->IS_SEWING === 1 && (int) $emp->IS_STAFF === 0) {
            return 'NON SEWING';
        } elseif (is_null($emp->IS_SEWING) && (int) $emp->IS_STAFF === 0) {
            return 'NON STAFF';
        }

        return '-';
    }

    private function createBankCSV($employees, $periodName, $path)
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($employees as $emp) {

            // if (empty($emp->bank_account) || $emp->total_salary <= 0) {
            //     continue;
            // }

            fputcsv($handle, [
                'PERMATA',                                          // A
                '',                                                 // B
                '',                                                 // C
                '',                                                 // D
                strtoupper(trim($emp->employee_name)),                    // E
                $emp->bank_account ?? '',                           // F
                'IDR',                                               // G
                number_format($emp->total_salary ?? 0, 0, '', ''),   // H
                'GAJI ' . strtoupper($periodName),                  // I
                '',                                                 // J
                '',                                                 // K
                'OVB',                                              // L
                0,                                                  // M
                0,                                                  // N
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('public')->put($path, $content);
    }
}
