<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use App\Jobs\GeneratePayrollCheck;
use App\Jobs\GeneratePayrollExport;
use App\Jobs\GeneratePayrollProcess;
use App\Jobs\GeneratePayrollProcessV2;
use App\Jobs\GeneratePayrollRekap;
use App\Models\InsentifApproval;
use App\Models\PayrollApprove;
use App\Models\PayrollComponent;
use App\Models\PayrollExport;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use App\Models\PayrollSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\DataTables;
use App\Models\InsentifRoleFormula;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Services\PayrollRoleFilterService;
use App\Models\RolePayroll;

class PayrollProcessController extends Controller
{

    private function scopePeriodsByRole($periods, ?string $role)
    {
        if (PayrollRoleFilterService::isAll($role)) {
            return $periods;
        }

        if (!PayrollRoleFilterService::isRegistered($role)) {
            return collect(); // belum terdaftar di role_payrolls -> kosong
        }

        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'IS_STAFF')
            );

        return $periods->map(function ($period) use ($role, $bioUnion) {

            $query = DB::table('payroll_run_details as prd')
                ->leftJoinSub($bioUnion, 'bio', fn($j) => $j->on('bio.NPK', '=', 'prd.employee_npk'))
                ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'prd.employee_dept')
                ->where('prd.run_id', $period->id);

            PayrollRoleFilterService::applyToQuery($query, $role, 'bio.IS_STAFF', 'd.IS_SEWING');

            $scoped = $query
                ->selectRaw('COUNT(DISTINCT prd.employee_npk) as employee_count')
                ->selectRaw('COALESCE(SUM(prd.total_salary), 0) as total_payroll')
                ->first();

            $period->employee_count = $scoped->employee_count ?? 0;
            $period->total_payroll  = $scoped->total_payroll ?? 0;

            return $period;
        })->values();
    }

    public function index(Request $request)
    {
        $user    = Auth::user();
        $role    = PayrollRoleFilterService::getRole($user);
        // $isAdmin = $user->hasRole('Admin');

        // =========================
        // FILTER STATUS PERIOD
        // =========================
        $filter = $request->get('status', 'open');

        $query = PayrollRun::query()
            ->leftJoin('payroll_periods', 'payroll_runs.period_id', '=', 'payroll_periods.id')
            ->leftJoin('payroll_exports', 'payroll_exports.run_id', '=', 'payroll_runs.id')
            ->leftJoin('payroll_approve', 'payroll_approve.payroll_run_id', '=', 'payroll_runs.id')
            ->select(
                'payroll_runs.*',
                'payroll_periods.name as period_name',
                'payroll_exports.status as export_status',
                'payroll_exports.file_excel',
                'payroll_exports.file_pdf',
                'payroll_exports.file_bank_active',
                'payroll_exports.file_bank_resign',
                'payroll_exports.file_peng',
                'payroll_approve.status as approve_status'
            );

        if ($filter === 'open') {
            $query->where('payroll_periods.is_closed', false);
        }

        if ($filter === 'closed') {
            $query->where('payroll_periods.is_closed', true);
        }

        $periods = $query->latest('payroll_runs.id')->get();

        // Batasi/hitung ulang sesuai payroll_role user login
        $periods = $this->scopePeriodsByRole($periods, $role);

        $noRoleAssigned  = !PayrollRoleFilterService::isRegistered($role);
        $payrollRoleLabel = $role ? (RolePayroll::ROLES[$role] ?? $role) : null;

        return view('payroll.index', compact(
            'periods',
            'filter',
            'noRoleAssigned',
            'payrollRoleLabel'
        ));
    }

    public function generate()
    {
        $user    = Auth::user();
        $role    = PayrollRoleFilterService::getRole($user);
        // $isAdmin = $user->hasRole('Admin');

        // $noRoleAssigned = !$isAdmin && !PayrollRoleFilterService::isRegistered($role);
        $noRoleAssigned = !PayrollRoleFilterService::isRegistered($role);

        // Kalau user belum terdaftar di role_payrolls (dan bukan Admin),
        // halaman generate payroll tidak menampilkan periode apapun --
        // form pilih periode / check / process jadi tidak bisa dipakai.
        $periods = $noRoleAssigned
            ? collect()
            : PayrollPeriod::orderBy('start_date')->where('is_closed', 0)->get();

        $payrollRoleLabel = $role ? (RolePayroll::ROLES[$role] ?? $role) : null;

        return view('payroll.process', compact(
            'periods',
            'noRoleAssigned',
            'payrollRoleLabel'
        ));
    }

    public function generatev2()
    {
        $user    = Auth::user();
        $role    = PayrollRoleFilterService::getRole($user);
        // $isAdmin = $user->hasRole('Admin');

        // $noRoleAssigned = !$isAdmin && !PayrollRoleFilterService::isRegistered($role);
        $noRoleAssigned = !PayrollRoleFilterService::isRegistered($role);

        // Kalau user belum terdaftar di role_payrolls (dan bukan Admin),
        // halaman generate payroll tidak menampilkan periode apapun --
        // form pilih periode / check / process jadi tidak bisa dipakai.
        $periods = $noRoleAssigned
            ? collect()
            : PayrollPeriod::orderBy('start_date')->where('is_closed', 0)->get();

        $payrollRoleLabel = $role ? (RolePayroll::ROLES[$role] ?? $role) : null;

        return view('payroll.processv2', compact(
            'periods',
            'noRoleAssigned',
            'payrollRoleLabel'
        ));
    }

    public function checkPayroll($period_id)
    {
        $period = PayrollPeriod::findOrFail($period_id);

        // Filter payroll_role sudah dilakukan DI DALAM job (mode simulation),
        // karena job dijalankan sinkron di request yang sama sehingga
        // Auth::user() valid di sana. Tidak perlu filter ulang di sini.
        $service = new GeneratePayrollProcess($period->id);
        $raw     = $service->simulation();

        $payrollResults = collect($raw['data'] ?? $raw);

        return response()->json([
            'data' => $payrollResults->values()
        ]);
    }

    public function checkPayrollV2($period_id)
    {
        $period = PayrollPeriod::findOrFail($period_id);

        // Filter payroll_role sudah dilakukan DI DALAM job (mode simulation),
        // karena job dijalankan sinkron di request yang sama sehingga
        // Auth::user() valid di sana. Tidak perlu filter ulang di sini.
        $service = new GeneratePayrollProcessV2($period->id);
        $raw     = $service->simulation();

        $payrollResults = collect($raw['data'] ?? $raw);

        return response()->json([
            'data' => $payrollResults->values()
        ]);
    }

    public function approvalStatus($periodId)
    {
        /*
        ============================================
        GET APPROVAL DATA
        ============================================
        */
        $user = Auth::user();
        $role = \App\Services\PayrollRoleFilterService::getRole($user);
        // $isAdmin = $user->hasRole('Admin');

        $data = InsentifApproval::where('period_id', $periodId)
            ->orderBy('payroll_component')
            ->get([
                'id',
                'payroll_component',
                'status',
                'approved_at',
                'progress'
            ])
            ->map(function ($item) {

                /*
        ============================================
        DECODE APPROVED_AT (LIST TIMESTAMP APPROVE)
        ============================================
        */
                $approvedAtRaw = $item->approved_at;

                if (is_string($approvedAtRaw)) {
                    $approvedAtRaw = json_decode($approvedAtRaw, true);
                }

                // approved_at formatnya: [["ts1","ts2",...]] -> flatten jadi satu list linear
                $timestampList = [];

                if (is_array($approvedAtRaw)) {
                    foreach ($approvedAtRaw as $group) {
                        if (is_array($group)) {
                            foreach ($group as $ts) {
                                $timestampList[] = $ts;
                            }
                        } elseif (is_string($group)) {
                            $timestampList[] = $group;
                        }
                    }
                }

                // simpan timestamp terakhir untuk kompatibilitas lama (kalau masih dipakai di tempat lain)
                $item->approved_at = end($timestampList) ?: null;

                /*
        ============================================
        DECODE APPROVAL PROGRESS (NPK + STATUS + WAKTU)
        ============================================
        */
                $progress = $item->progress;

                if (is_string($progress)) {
                    $progress = json_decode($progress, true);
                }

                $progressList = [];

                if (is_array($progress) && count($progress) > 0) {

                    $first = $progress[0];

                    $npkList    = $first['npk'] ?? null;
                    $statusList = $first['status'] ?? null;

                    if (is_string($npkList)) {
                        $npkList = json_decode($npkList, true);
                    }

                    if (is_string($statusList)) {
                        $decodedStatus = json_decode($statusList, true);

                        if (is_array($decodedStatus)) {
                            // ternyata json array yang ter-encode sebagai string
                            $statusList = $decodedStatus;
                        } else {
                            // status tunggal, berlaku untuk semua npk
                            $statusList = array_fill(0, count($npkList), $statusList);
                        }
                    }

                    if (is_array($npkList)) {

                        $tsCursor = 0; // pointer ke timestampList, hanya maju saat status = approve

                        foreach ($npkList as $i => $npk) {

                            $status = $statusList[$i] ?? null;

                            $approvedTime = null;

                            if ($status === 'approve' && isset($timestampList[$tsCursor])) {
                                $approvedTime = $timestampList[$tsCursor];
                                $tsCursor++;
                            }

                            $progressList[] = [
                                'npk'          => $npk,
                                'status'       => $status,
                                'nama'         => null, // diisi setelah lookup BIODATA
                                'approved_at'  => $approvedTime,
                            ];
                        }
                    }
                }

                $item->progress = $progressList;

                return $item;
            });

        /*
============================================
ATTACH NAMA_KARYAWAN KE APPROVAL PROGRESS
============================================
*/

        $allNpks = $data->pluck('progress')
            ->flatten(1)
            ->pluck('npk')
            ->filter()
            ->unique()
            ->values();

        if ($allNpks->count() > 0) {

            $namesMap = DB::table('BIODATA')
                ->select('NPK', 'NAMA_KARYAWAN')
                ->whereIn('NPK', $allNpks)
                ->union(
                    DB::table('BIODATA_KELUAR')
                        ->select('NPK', 'NAMA_KARYAWAN')
                        ->whereIn('NPK', $allNpks)
                )
                ->get()
                ->pluck('NAMA_KARYAWAN', 'NPK');

            $data = $data->map(function ($item) use ($namesMap) {

                $item->progress = collect($item->progress)
                    ->map(function ($p) use ($namesMap) {
                        $p['nama'] = $namesMap[$p['npk']] ?? $p['npk'];
                        return $p;
                    })
                    ->values();

                return $item;
            });
        }


        /*
        ============================================
        GET PERIOD
        ============================================
        */

        $period = DB::table('payroll_periods')
            ->where('id', $periodId)
            ->first();

        /*
        ============================================
        VALIDATE CONTRACT
        ============================================
        */

        $invalidContracts = [];

        if ($period) {

            $periodStart = $period->start_date;
            $periodEnd   = $period->end_date;

            /*
            ============================================================
            CEK BIODATA YANG TIDAK MEMILIKI CONTRACT VALID
            ============================================================

            CONTRACT VALID JIKA:
            payroll_period.start_date dan end_date
            masih dalam range employees_contract.start_date dan end_date
            */

            $biodataUnion = DB::table('BIODATA')
                ->select(
                    'NPK',
                    'NAMA_KARYAWAN',
                    'ID_DEPT',
                    'IS_STAFF'
                )
                ->union(
                    DB::table('BIODATA_KELUAR')
                        ->select(
                            'NPK',
                            'NAMA_KARYAWAN',
                            'ID_DEPT',
                            'IS_STAFF'
                        )
                );

            $invalidContracts = DB::table('PKWT as p')
                ->leftJoinSub($biodataUnion, 'b', function ($join) {
                    $join->on('p.NPK', '=', 'b.NPK');
                })
                ->leftJoin('DEPT as d', 'b.ID_DEPT', '=', 'd.ID_DEPT')
                ->leftJoin('employees_contract as ec', 'p.NPK', '=', 'ec.npk')
                ->whereDate('p.TMK', '<=', $periodEnd)
                ->where(function ($q) use ($periodStart) {
                    $q->whereDate('p.TKK', '>=', $periodStart)
                        ->orWhereNull('p.TKK');
                })
                ->where(function ($q) use ($periodStart, $periodEnd) {
                    $q->whereNull('ec.id')
                        ->orWhere(function ($sub) use ($periodStart, $periodEnd) {
                            $sub->where('ec.status_contract', 'AKTIF')
                                ->where('ec.end_date', '<', $periodStart);
                        });
                })
                ->select(
                    'p.NPK',
                    'p.TMK',
                    'p.TKK',
                    'ec.id',
                    'b.NAMA_KARYAWAN',
                    'ec.contract_ke',
                    'ec.start_date',
                    'ec.end_date',
                    'ec.status_contract',
                    'b.IS_STAFF',
                    'd.IS_SEWING'
                )
                ->orderBy('p.NPK')
                ->get();



            $invalidBankAccounts = DB::table('PKWT as p')
                ->leftJoinSub($biodataUnion, 'b', function ($join) {
                    $join->on('p.NPK', '=', 'b.NPK');
                })
                ->leftJoin('DEPT as d', 'b.ID_DEPT', '=', 'd.ID_DEPT')
                ->leftJoin('payroll_masters as pm', 'pm.npk', '=', 'p.NPK')
                ->where('p.NPK', '!=', 'C-00017') // IGNORE C-00017
                ->where('p.TMK', '<=', $periodEnd)
                ->where(function ($q) use ($periodStart, $periodEnd) {
                    $q->whereBetween('p.TKK', [$periodStart, $periodEnd])
                        ->orWhereNull('p.TKK');
                })
                ->where(function ($q) {
                    $q->whereNull('pm.bank_account')
                        ->orWhereRaw("LTRIM(RTRIM(pm.bank_account)) = ''");
                })
                ->select(
                    'p.NPK',
                    'p.NAMA',
                    'p.TMK',
                    'p.TKK',
                    'pm.bank_account',
                    'b.IS_STAFF',
                    'd.IS_SEWING'
                )
                ->distinct()
                ->orderBy('p.NPK')
                ->get();

            $filterByRole = function ($rows) use ($role) {
                return \App\Services\PayrollRoleFilterService::filterCollection($rows, $role, 'IS_STAFF', 'IS_SEWING');
            };

            $invalidContracts = $filterByRole($invalidContracts);

            $invalidBankAccounts = $filterByRole($invalidBankAccounts); // <-- baris existing

            /*
============================================
CEK DUPLICATE BANK ACCOUNT (payroll_masters)
============================================
*/

            $duplicateBankAccountNumbers = DB::table('payroll_masters')
                ->select('bank_account')
                ->whereNotNull('bank_account')
                ->whereRaw("LTRIM(RTRIM(bank_account)) != ''")
                ->groupBy('bank_account')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('bank_account');

            $duplicateBankAccounts = [];

            if ($duplicateBankAccountNumbers->count() > 0) {

                $rawDuplicates = DB::table('payroll_masters as pm')
                    ->leftJoinSub($biodataUnion, 'b', function ($join) {
                        $join->on('pm.npk', '=', 'b.NPK');
                    })
                    ->leftJoin('DEPT as d', 'b.ID_DEPT', '=', 'd.ID_DEPT')
                    ->whereIn('pm.bank_account', $duplicateBankAccountNumbers)
                    ->select(
                        'pm.npk as NPK',
                        'pm.bank_account',
                        'b.NAMA_KARYAWAN',
                        'b.IS_STAFF',
                        'd.IS_SEWING'
                    )
                    ->orderBy('pm.bank_account')
                    ->orderBy('pm.npk')
                    ->get();

                /*
                ============================================
                HANYA ANGGAP DUPLICATE JIKA NAMA BERBEDA
                (NPK lama & baru dari orang yang sama = bukan duplicate)
                ============================================
                */

                $duplicateBankAccounts = collect($rawDuplicates)
                    ->groupBy('bank_account')
                    ->filter(function ($group) {

                        $uniqueNames = $group->pluck('NAMA_KARYAWAN')
                            ->map(function ($name) {
                                return strtoupper(trim($name ?? ''));
                            })
                            ->unique();

                        // kalau nama semua sama -> bukan duplicate beneran
                        return $uniqueNames->count() > 1;
                    })
                    ->flatten(1)
                    ->values();

                $duplicateBankAccounts = $filterByRole($duplicateBankAccounts);
            }
        }

        /*
        ============================================
        RETURN JSON
        ============================================
        */

        return response()->json([
            'approval' => $data,
            'invalid_contracts' => $invalidContracts,
            'invalid_bank_accounts' => $invalidBankAccounts,
            'duplicate_bank_accounts' => $duplicateBankAccounts   // <-- tambahan
        ]);
    }

    public function process(Request $request)
    {
        $payrollResults = [];
        $user = Auth::user();
        $period = PayrollPeriod::findOrFail($request->period_id);

        // PROTEKSI: cek apakah payroll sudah pernah digenerate
        $exists = PayrollRun::where('period_id', $period->id)->exists();

        if ($exists) {
            Alert::error('Gagal', 'Payroll untuk periode ini sudah tergenerate sebelumnya.');
            return redirect()->back();
        }

        $run = PayrollRun::create([
            'period_id' => $period->id,
            'processed_at' => now(),
        ]);

        GeneratePayrollProcess::dispatch($run->id);

        // return response()->json($payrollResults);
        event(new NotificationEvent(
            'Process Payroll!',
            'Users : ' . $user->name . ' has been process Payroll ' . $period->name . '!',
            'success'
        ));
        return redirect('payroll-process/index');
    }

    public function details($id)
    {
        /*
    |--------------------------------------------------------------------------
    | CHECK USER ROLE
    |--------------------------------------------------------------------------
    */

        $user = Auth::user();

        $payrollRole = \App\Services\PayrollRoleFilterService::getRole($user);

        $canSeeSalary = $user->hasRole(['Admin', 'Audit', 'Management'])
            || \App\Services\PayrollRoleFilterService::canSeeSalary($payrollRole);

        $period = DB::table('payroll_runs')->leftJoin('payroll_periods', 'payroll_runs.period_id', '=', 'payroll_periods.id')->where('payroll_runs.id', $id)->first();
        // dd($period);

        $periodStart = $period->start_date;
        $periodEnd = $period->end_date;
        $count_days  = Carbon::parse($periodStart)->diffInDays(Carbon::parse($periodEnd)) + 1;

        /*
    |--------------------------------------------------------------------------
    | EMPLOYEE UNION
    |--------------------------------------------------------------------------
    */


        $employeeUnion = DB::connection('cii')
            ->query()
            ->fromSub(function ($q) {

                $q->from('BIODATA')
                    ->select(
                        'NPK',
                        'ID_DEPT',
                        'SECTION',
                        'NAMA_KARYAWAN',
                        'IS_STAFF',
                        DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'),
                        'IS_EXPAT'
                    )

                    ->unionAll(

                        DB::connection('cii')
                            ->table('BIODATA_KELUAR')
                            ->select(
                                'NPK',
                                'ID_DEPT',
                                'SECTION',
                                'NAMA_KARYAWAN',
                                'IS_STAFF',
                                DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'),
                                'IS_EXPAT'
                            )

                    );
            }, 'bio')

            ->leftJoin('DEPT as d', 'bio.ID_DEPT', '=', 'd.ID_DEPT')

            ->select(
                'bio.*',
                'd.DEPARTEMENT'
            );

        $latestContract = DB::table('employees_contract as ec1')
            ->select(
                'ec1.npk',
                'ec1.salary',
                'ec1.allowance',
                'ec1.pph21',
                'ec1.type',
                'ec1.daily_salary'
            )
            ->where('ec1.npk', '!=', 'C-00017')
            // ->where('ec1.npk', '=', 'C-00827')

            // ✅ contract harus masuk range periode
            ->whereDate('ec1.start_date', '<=', $periodEnd)
            ->whereDate('ec1.end_date', '>=', $periodStart)
            // ->where('ec1.status_contract', '=', 'AKTIF')

            // ✅ ambil contract terbaru
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
    ", [$periodEnd, $periodStart]);


        $overtimeDetails = DB::connection('cii')
            ->table('overtimes')
            ->leftJoinSub($latestContract, 'ec', function ($join) {
                $join->on('overtimes.NPK', '=', 'ec.npk');
            })
            ->leftJoinSub($employeeUnion, 'bio', function ($join) {
                $join->on('overtimes.NPK', '=', 'bio.NPK');
            })
            ->leftJoin('DEPT as d', 'bio.ID_DEPT', '=', 'd.ID_DEPT')
            ->whereBetween('OVERTIME_DATE', [$periodStart, $periodEnd])
            ->select(
                'overtimes.NPK',
                'bio.NAMA_KARYAWAN',
                'd.DEPARTEMENT',
                'overtimes.OVERTIME_DATE',

                DB::raw("
            CASE
                WHEN DAY NOT IN ('Sabtu','Minggu','Saturday','Sunday')
                AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NOT NULL
                THEN TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT)
                ELSE 0
            END AS overtime_hours
        "),

                DB::raw("
            CASE
                WHEN DAY IN ('Sabtu','Minggu','Saturday','Sunday')
                AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NOT NULL
                THEN
                    CASE
                        WHEN
                            (
                                COALESCE(ec.salary,0)
                                + COALESCE(ec.allowance,0)
                            ) >= 3800000

                            OR

                            (
                                (COALESCE(ec.daily_salary,0) * {$count_days})
                                + COALESCE(ec.allowance,0)
                            ) >= 3800000

                        THEN
                            CASE
                                WHEN TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) > 8
                                THEN 8
                                ELSE TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT)
                            END

                        ELSE
                            TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT)
                    END

                ELSE 0
            END AS special_overtime_hours
        "),

                DB::raw("
            CASE
                WHEN UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) = 'H'
                    THEN 0.5

                WHEN JUMLAH_JAM_LEMBUR IS NOT NULL
                    AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NULL
                    AND UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) IN ('MA','P1','BR','OUT')
                    THEN 1

                ELSE 0
            END AS absence_days
        "),
                DB::raw("
            CASE
                WHEN UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) = 'H'
                    THEN JUMLAH_JAM_LEMBUR

                WHEN JUMLAH_JAM_LEMBUR IS NOT NULL
                    AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NULL
                    AND UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) IN ('MA','P1','H','BR','OUT','SD')
                    THEN JUMLAH_JAM_LEMBUR
            END AS absence_status
        ")
            )
            ->orderBy('overtimes.NPK')
            ->orderBy('overtimes.OVERTIME_DATE')
            ->get()
            ->groupBy('NPK');

        $lateDetails =
            DB::connection('cii')
            ->query()

            /*
        |--------------------------------------------------------------------------
        | EMPLOYEE + CALENDAR
        |--------------------------------------------------------------------------
        */
            ->fromSub(function ($q) use ($employeeUnion, $periodStart, $periodEnd) {

                $q->fromSub($employeeUnion, 'emp')

                    ->crossJoinSub(

                        DB::connection('cii')
                            ->query()
                            ->selectRaw("
                            DATEADD(
                                DAY,
                                v.number,
                                CAST(? AS DATE)
                            ) as shift_date
                        ", [$periodStart])
                            ->from(DB::raw('master..spt_values v'))
                            ->where('v.type', 'P')
                            ->whereRaw("
                            v.number <= DATEDIFF(
                                DAY,
                                CAST(? AS DATE),
                                CAST(? AS DATE)
                            )
                        ", [$periodStart, $periodEnd]),

                        'cal'
                    )

                    ->select(
                        'emp.*',
                        DB::raw('cal.shift_date')
                    );
            }, 'emp')

            /*
        |--------------------------------------------------------------------------
        | SHIFT
        |--------------------------------------------------------------------------
        */
            ->leftJoin('employee_shifts as es', function ($join) {
                $join->on('emp.NPK', '=', 'es.npk')
                    ->on(
                        DB::raw('CAST(emp.shift_date AS DATE)'),
                        '=',
                        DB::raw('CAST(es.shift_date AS DATE)')
                    );
            })

            ->leftJoin('shifts as s', function ($join) {
                $join->on('es.shift_id', '=', 's.id')
                    ->whereNotNull('es.shift_id');
            })

            /*
        |--------------------------------------------------------------------------
        | ATT LOG (FIX: 1 ROW ONLY PER EMP-DATE)
        |--------------------------------------------------------------------------
        */
            ->leftJoinSub(
                DB::connection('cii')
                    ->table('att_log')
                    ->where('sn', '!=', '66208026030047')
                    ->selectRaw("
                    CAST(pin AS VARCHAR(50)) as pin,
                    CAST(scan_date AS DATE) as scan_day,
                    MIN(CAST(scan_date AS DATETIME)) as first_scan
                ")
                    ->groupBy(
                        DB::raw('CAST(pin AS VARCHAR(50))'),
                        DB::raw('CAST(scan_date AS DATE)')
                    ),
                'att',
                function ($join) {
                    $join->on(
                        DB::raw('CAST(emp.BARCODE AS VARCHAR(50))'),
                        '=',
                        'att.pin'
                    )
                        ->on(
                            DB::raw('CAST(emp.shift_date AS DATE)'),
                            '=',
                            'att.scan_day'
                        );
                }
            )

            /*
        |--------------------------------------------------------------------------
        | LATE COMPENSATION
        |--------------------------------------------------------------------------
        */
            ->leftJoin('late_compensations as lc', function ($join) {
                $join->on('emp.NPK', '=', 'lc.npk')
                    ->whereRaw("
                    CAST(lc.date AS DATE) = CAST(emp.shift_date AS DATE)
                ");
            })

            /*
        |--------------------------------------------------------------------------
        | SHIFT RESOLUTION
        |--------------------------------------------------------------------------
        */
            ->selectRaw("
            emp.NPK,
            emp.NAMA_KARYAWAN,
            emp.DEPARTEMENT,
            CAST(emp.BARCODE AS VARCHAR(50)) as pin,
            CAST(emp.shift_date AS DATE) as scan_day,

            COALESCE(CAST(s.work_start AS TIME), '08:00:00') as work_start,
            COALESCE(CAST(s.work_end AS TIME), '17:00:00') as work_end,

            att.first_scan
        ")

            /*
        |--------------------------------------------------------------------------
        | FINAL LATE CALC (NO NULL POSSIBILITY)
        |--------------------------------------------------------------------------
        */
            ->selectRaw("
            CASE
                WHEN lc.id IS NOT NULL THEN 0
                WHEN att.first_scan IS NULL THEN 0

                ELSE
                    CASE
                        WHEN att.first_scan >
                            DATEADD(
                                SECOND,
                                DATEDIFF(SECOND,'00:00:00',COALESCE(CAST(s.work_end AS TIME),'17:00:00')),
                                CAST(emp.shift_date AS DATETIME)
                            )
                        THEN 0

                        WHEN DATEDIFF(
                            MINUTE,
                            DATEADD(
                                MINUTE,5,
                                DATEADD(
                                    SECOND,
                                    DATEDIFF(SECOND,'00:00:00',COALESCE(CAST(s.work_start AS TIME),'08:00:00')),
                                    CAST(emp.shift_date AS DATETIME)
                                )
                            ),
                            att.first_scan
                        ) < 0 THEN 0

                        ELSE
                            DATEDIFF(
                                MINUTE,
                                DATEADD(
                                    MINUTE,5,
                                    DATEADD(
                                        SECOND,
                                        DATEDIFF(SECOND,'00:00:00',COALESCE(CAST(s.work_start AS TIME),'08:00:00')),
                                        CAST(emp.shift_date AS DATETIME)
                                    )
                                ),
                                att.first_scan
                            )
                    END
            END as late_minute
        ")

            /*
        |--------------------------------------------------------------------------
        | GROUP BY CLEAN
        |--------------------------------------------------------------------------
        */
            ->groupBy(
                'emp.NPK',
                'emp.NAMA_KARYAWAN',
                'emp.DEPARTEMENT',
                DB::raw('CAST(emp.BARCODE AS VARCHAR(50))'),
                DB::raw('CAST(emp.shift_date AS DATE)'),
                's.work_start',
                's.work_end',
                'att.first_scan',
                'lc.id'
            )

            ->whereBetween(
                DB::raw('CAST(emp.shift_date AS DATE)'),
                [$periodStart, $periodEnd]
            )

            ->orderBy(DB::raw('CAST(emp.shift_date AS DATE)'))
            ->get()
            ->groupBy('NPK');

        $ijinDetails = DB::table('ijin_meninggalkan_pekerjaans')
            ->selectRaw("
        ijin_meninggalkan_pekerjaans.npk,
        NAMA_KARYAWAN,
        DEPARTEMENT,
        tanggal,
        jam_keluar,
        rencana_kembali,
        jam_kembali,
        reason,
        CASE 
            WHEN jam_kembali IS NOT NULL 
            THEN DATEDIFF(MINUTE, jam_keluar, jam_kembali)
            ELSE 0 
        END as ijin_minutes
    ")
            ->leftJoin('BIODATA', 'BIODATA.NPK', '=', 'ijin_meninggalkan_pekerjaans.npk')
            ->leftJoin('DEPT', 'DEPT.ID_DEPT', '=', 'BIODATA.ID_DEPT')
            ->whereBetween('tanggal', [$periodStart, $periodEnd])
            ->orderBy('tanggal', 'asc')
            ->get()
            ->groupBy('npk');

        /*
    |--------------------------------------------------------------------------
    | NIGHT SHIFT DETAILS (untuk modal detail Night Shift Compensation)
    |--------------------------------------------------------------------------
    */
        $nightShiftDetails = DB::connection('cii')
            ->table('employee_shifts as es')
            ->join('shifts as s', 'es.shift_id', '=', 's.id')
            ->leftJoinSub($employeeUnion, 'bio', function ($join) {
                $join->on('es.npk', '=', 'bio.NPK');
            })
            ->leftJoin('DEPT as d', 'bio.ID_DEPT', '=', 'd.ID_DEPT')
            ->whereBetween(DB::raw('CAST(es.shift_date AS DATE)'), [$periodStart, $periodEnd])
            ->where(DB::raw('CAST(s.work_start AS TIME)'), '>=', '15:00:00')
            ->whereRaw("LOWER(LTRIM(RTRIM(s.name))) NOT LIKE '%security%'")
            ->select(
                'es.npk as NPK',
                'bio.NAMA_KARYAWAN',
                'd.DEPARTEMENT',
                DB::raw('CAST(es.shift_date AS DATE) as shift_date'),
                's.name as shift_name',
                DB::raw('CAST(s.work_start AS TIME) as work_start'),
                DB::raw('CAST(s.work_end AS TIME) as work_end')
            )
            ->orderBy('es.npk')
            ->orderBy('es.shift_date')
            ->get()
            ->groupBy('NPK');

        /*
    |--------------------------------------------------------------------------
    | BASE QUERY
    |--------------------------------------------------------------------------
    */

        $query = DB::table('payroll_run_details as prd')
            ->leftJoinSub(
                $employeeUnion,
                'emp',
                fn($j) => $j->on('emp.NPK', '=', 'prd.employee_npk')
            )
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'prd.employee_dept')
            ->leftJoin('PKWT as p', 'p.NPK', '=', 'emp.NPK')
            ->where('prd.run_id', $id)
            ->select(
                'prd.id',
                'prd.run_id',
                'prd.employee_npk',
                'prd.employee_name',
                'p.TMK as tmk',
                'p.TKK as tkk',
                'd.DEPARTEMENT as dept',
                'prd.components',
                'prd.total_salary',
                'emp.IS_STAFF',
                'd.IS_SEWING',
                'p.KETERANGAN'
            );

        /*
    |--------------------------------------------------------------------------
    | ROLE FILTERING (NEW)
    |--------------------------------------------------------------------------
    */

        if (!$user->hasRole('Admin')) {
            \App\Services\PayrollRoleFilterService::applyToQuery($query, $payrollRole, 'emp.IS_STAFF', 'd.IS_SEWING');
        }

        /*
    |--------------------------------------------------------------------------
    | GET DATA
    |--------------------------------------------------------------------------
    */

        $data = $query
            ->orderBy('prd.employee_npk')
            ->get();

        /*
    |--------------------------------------------------------------------------
    | TRANSFORM COMPONENTS
    |--------------------------------------------------------------------------
    */
        $componentTypeMap = PayrollComponent::pluck('type', 'code')->toArray();

        $data->transform(function ($item) use (
            $canSeeSalary,
            $overtimeDetails,
            $lateDetails,
            $ijinDetails,
            $nightShiftDetails,
            $componentTypeMap,
            $periodStart,
            $periodEnd
        ) {

            $rawComponents = json_decode($item->components, true) ?? [];

            /*
    |--------------------------------------------------------------------------
    | NORMALISASI COMPONENTS -> { amount, type }
    |--------------------------------------------------------------------------
    | components di DB masih berupa { code: amount } scalar biasa.
    | Type (earning/deduction) diambil dari lookup $componentTypeMap
    | (payroll_components.code -> payroll_components.type), bukan dari
    | JSON itu sendiri, supaya data run lama maupun baru tetap konsisten
    | tanpa perlu re-generate payroll.
    |--------------------------------------------------------------------------
    */
            $components = [];

            foreach ($rawComponents as $code => $value) {

                // Jika value sudah berbentuk { amount, type } (format baru)
                if (is_array($value) && array_key_exists('amount', $value)) {

                    $amount = $value['amount'];
                    $type   = $value['type'] ?? ($componentTypeMap[$code] ?? null);
                } else {
                    // Format lama: value adalah scalar langsung
                    $amount = $value;
                    $type   = $componentTypeMap[$code] ?? null;
                }

                $components[$code] = [
                    'amount' => $canSeeSalary ? (float) $amount : '***',
                    'type'   => $type,
                ];
            }

            $item->components = $components;

            // flatten juga ke top-level property (kompatibilitas lama),
            // pakai amount saja
            foreach ($components as $key => $comp) {
                $item->$key = $comp['amount'];
            }

            $item->total_salary = $canSeeSalary
                ? (float) $item->total_salary
                : '***';

            $tkk = $item->tkk ? Carbon::parse($item->tkk) : null;
            $tmk = $item->tmk ? Carbon::parse($item->tmk) : null;

            $periodStart = Carbon::parse($periodStart);
            $periodEnd   = Carbon::parse($periodEnd);

            $isTMKInPeriod = $tmk &&
                $tmk->betweenIncluded($periodStart, $periodEnd);

            $isTKKInPeriod = $tkk &&
                $tkk->betweenIncluded($periodStart, $periodEnd);

            // Prioritas 1: Baru
            if ($isTMKInPeriod && $tkk === null) {
                // Walaupun TKK juga berada di periode payroll, tetap dianggap Baru
                $item->employment_status = 'Baru';

                // Prioritas 2: Active
            } elseif (!$isTKKInPeriod) {
                // TKK kosong atau di luar periode payroll
                $item->employment_status = 'Active';

                // Prioritas 3: Mangkir
            } elseif (strtoupper(trim($item->KETERANGAN ?? '')) === 'MA') {
                // TKK di periode payroll + MA
                $item->employment_status = 'Mangkir';

                // Prioritas 4: Resign
            } else {
                // TKK di periode payroll + selain MA
                $item->employment_status = 'Resign';
            }

            // push detail overtime
            $item->overtime_details =
                $overtimeDetails->get($item->employee_npk, collect())
                ->values();

            // push detail keterlambatan
            $item->late_details =
                $lateDetails->get($item->employee_npk, collect())
                ->values();

            // push detail ijin
            $item->ijin_details =
                $ijinDetails->get($item->employee_npk, collect())
                ->values();

            // push detail night shift
            $item->night_shift_details =
                $nightShiftDetails->get($item->employee_npk, collect())
                ->values();

            return $item;
        });

        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'data' => $data
        ]);
    }

    public function destroy($period_id)
    {

        DB::beginTransaction();

        try {

            $user = Auth::user();
            $runPeriods = PayrollPeriod::where('payroll_runs.id', $period_id)->leftJoin('payroll_runs', 'payroll_runs.period_id', '=', 'payroll_periods.id');
            // ambil semua run id dari period
            $periodName = $runPeriods->pluck('payroll_periods.name');
            $runIds = $runPeriods->pluck('payroll_runs.id');

            if ($runIds->count() > 0) {

                // hapus detail payroll
                PayrollRunDetail::whereIn('run_id', $runIds)->delete();

                // hapus run payroll
                PayrollRun::whereIn('id', $runIds)->delete();
            }

            DB::commit();

            event(new NotificationEvent(
                'Deleted Payroll!',
                'Users : ' . $user->name . ' has been deleted Payroll ' . $periodName . '!',
                'danger'
            ));

            return redirect()->back()->with('success', 'Payroll deleted successfully');
        } catch (\Exception $e) {

            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function slip($run_id, $npk)
    {
        $employee = DB::table('payroll_run_details')
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'payroll_run_details.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('run_id', $run_id)
            ->where('employee_npk', $npk)
            ->first();

        $components = json_decode($employee->components, true);

        $componentTypes = DB::table('payroll_components')
            ->pluck('type', 'code');

        $earnings = [];
        $deductions = [];

        foreach ($components as $code => $value) {

            // Komponen bisa tersimpan dalam format baru { amount, type }
            // atau format lama (scalar langsung). Normalisasi ke scalar
            // supaya konsisten dipakai number_format() di blade slip.
            if (is_array($value) && array_key_exists('amount', $value)) {
                $amount = $value['amount'];
                $type   = $value['type'] ?? ($componentTypes[$code] ?? 'earning');
            } else {
                $amount = $value;
                $type   = $componentTypes[$code] ?? 'earning';
            }

            if ($type == 'earning') {
                $earnings[$code] = $amount;
            } else {
                $deductions[$code] = $amount;
            }
        }

        $data = [
            'employee' => $employee,
            'earnings' => $earnings,
            'deductions' => $deductions
        ];

        $pdf = Pdf::loadView('payroll.slip', $data)
            ->setPaper('A4', 'portrait');

        return $pdf->download('slip-gaji-' . $employee->employee_npk . '.pdf');
    }

    public function export($run_id)
    {
        $user = Auth::user();
        // dd($exportPeriod);
        $export = PayrollExport::create([
            'run_id' => $run_id,
            'status' => 'processing',
            'progress' => 0
        ]);

        $exportPeriod = PayrollExport::leftJoin('payroll_runs', 'payroll_runs.id', '=', 'payroll_exports.run_id')->leftJoin('payroll_periods', 'payroll_runs.period_id', '=', 'payroll_periods.id')->where('payroll_exports.run_id', '=', $run_id)->pluck('payroll_periods.name');
        $type = 'process';

        // dd($exportPeriod);

        GeneratePayrollExport::dispatch($export->id, $type);

        Alert::success('Sukses', 'Export payroll selesai diproses!');

        event(new NotificationEvent(
            'Export Payroll!',
            'Users : ' . $user->name . ' has been export Payroll ' . $exportPeriod . '!',
            'success'
        ));
        return redirect('payroll-process/index');
        // return response()->json([
        //     'message' => 'Export started',
        //     'export_id' => $export->id
        // ]);
    }

    // public function updatePph21(Request $request)
    // {
    //     try {

    //         $request->validate([
    //             'id' => 'required',
    //             'pph21' => 'required'
    //         ]);

    //         $payroll = DB::table('payroll_run_details')
    //             ->where('id', $request->id)
    //             ->first();

    //         if (!$payroll) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Data payroll tidak ditemukan'
    //             ]);
    //         }

    //         $components = json_decode($payroll->components, true);

    //         if (!$components) {
    //             $components = [];
    //         }

    //         /*
    //     |--------------------------------------------------------------------------
    //     | UPDATE PPH21
    //     |--------------------------------------------------------------------------
    //     */
    //         $components['pph_21'] = (float)$request->pph21;
    //         $components['pph_21_deduction'] = (float)$request->pph21;

    //         /*
    //     |--------------------------------------------------------------------------
    //     | RECALCULATE TOTAL SALARY
    //     |--------------------------------------------------------------------------
    //     */

    //         $income =
    //             ($components['basic_salary'] ?? 0) +
    //             ($components['overtime_pay'] ?? 0) +
    //             ($components['special_overtime_pay'] ?? 0) +
    //             ($components['monthly_premi'] ?? 0) +
    //             ($components['long_service_allowance'] ?? 0) +
    //             ($components['allowance'] ?? 0) +
    //             ($components['sewing_insentif'] ?? 0) +
    //             ($components['pad_insentif'] ?? 0) +
    //             ($components['cutting_insentif'] ?? 0) +
    //             ($components['heat_insentif'] ?? 0) +
    //             ($components['adjusment'] ?? 0);

    //         $deduction =
    //             ($components['bpjs_kesehatan'] ?? 0) +
    //             ($components['bpjs_ketenagakerjaan'] ?? 0) +
    //             ($components['pph_21'] ?? 0) +
    //             ($components['pph_21_deduction'] ?? 0) +
    //             ($components['absence_deduction'] ?? 0) +
    //             ($components['late_deduction'] ?? 0);

    //         $totalSalary = $income - $deduction;

    //         /*
    //     |--------------------------------------------------------------------------
    //     | UPDATE DATABASE
    //     |--------------------------------------------------------------------------
    //     */

    //         DB::table('payroll_run_details')
    //             ->where('id', $request->id)
    //             ->update([
    //                 'total_salary' => $totalSalary,
    //                 'components' => json_encode($components),
    //                 'updated_at' => now()
    //             ]);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'PPh21 berhasil diupdate',
    //             'total_salary' => $totalSalary
    //         ]);
    //     } catch (\Exception $e) {

    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ]);
    //     }
    // }

    public function updatePphByContract($run_id)
    {
        DB::beginTransaction();

        try {

            /*
        |--------------------------------------------------------------------------
        | GET PAYROLL RUN
        |--------------------------------------------------------------------------
        */

            $payrollRun = DB::table('payroll_runs')
                ->leftJoin(
                    'payroll_periods',
                    'payroll_runs.period_id',
                    '=',
                    'payroll_periods.id'
                )
                ->select(
                    'payroll_runs.*',
                    'payroll_periods.start_date',
                    'payroll_periods.end_date'
                )
                ->where('payroll_runs.id', $run_id)
                ->first();

            if (!$payrollRun) {

                return response()->json([
                    'success' => false,
                    'message' => 'Payroll run tidak ditemukan'
                ]);
            }

            $periodStart = $payrollRun->start_date;
            $periodEnd   = $payrollRun->end_date;

            /*
        |--------------------------------------------------------------------------
        | GET PAYROLL DETAILS
        |--------------------------------------------------------------------------
        */

            $details = DB::table('payroll_run_details')
                ->where('run_id', $run_id)
                ->get();

            foreach ($details as $detail) {

                /*
            |--------------------------------------------------------------------------
            | GET LATEST CONTRACT
            |--------------------------------------------------------------------------
            */

                $latestContract = DB::table('employees_contract as ec1')
                    ->select(
                        'ec1.npk',
                        'ec1.salary',
                        'ec1.allowance',
                        'ec1.pph21',
                        'ec1.type',
                        'ec1.daily_salary'
                    )

                    ->where('ec1.npk', $detail->employee_npk)

                    // CONTRACT RANGE
                    ->whereDate('ec1.start_date', '<=', $periodEnd)
                    ->whereDate('ec1.end_date', '>=', $periodStart)

                    // LATEST CONTRACT
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
                ", [$periodEnd, $periodStart])

                    ->first();

                if (!$latestContract) {
                    continue;
                }

                /*
            |--------------------------------------------------------------------------
            | COMPONENTS
            |--------------------------------------------------------------------------
            */

                $components = json_decode($detail->components, true);

                if (!$components) {
                    $components = [];
                }

                /*
            |--------------------------------------------------------------------------
            | UPDATE PPH21
            |--------------------------------------------------------------------------
            */

                $pph21 = (float)($latestContract->pph21 ?? 0);

                $components['pph_21'] = $pph21;
                $components['pph_21_deduction'] = $pph21;

                /*
            |--------------------------------------------------------------------------
            | RECALCULATE TOTAL SALARY
            |--------------------------------------------------------------------------
            */

                $income =
                    ($components['basic_salary'] ?? 0) +
                    ($components['overtime_pay'] ?? 0) +
                    ($components['special_overtime_pay'] ?? 0) +
                    ($components['monthly_premi'] ?? 0) +
                    ($components['long_service_allowance'] ?? 0) +
                    ($components['allowance'] ?? 0) +
                    ($components['sewing_insentif'] ?? 0) +
                    ($components['pad_insentif'] ?? 0) +
                    ($components['cutting_insentif'] ?? 0) +
                    ($components['heat_insentif'] ?? 0) +
                    ($components['sixs_insentif'] ?? 0) +
                    ($components['night_shift_compensation'] ?? 0) +
                    ($components['adjusment'] ?? 0);

                $deduction =
                    ($components['bpjs_kesehatan'] ?? 0) +
                    ($components['bpjs_ketenagakerjaan'] ?? 0) +
                    ($components['pph_21'] ?? 0) +
                    ($components['pph_21_deduction'] ?? 0) +
                    ($components['absence_deduction'] ?? 0) +
                    ($components['late_deduction'] ?? 0);

                $totalSalary = $income - $deduction;

                /*
            |--------------------------------------------------------------------------
            | UPDATE DATABASE
            |--------------------------------------------------------------------------
            */

                DB::table('payroll_run_details')
                    ->where('id', $detail->id)
                    ->update([
                        'total_salary' => $totalSalary,
                        'components'   => json_encode($components),
                        'updated_at'   => now()
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PPH21 berhasil diupdate dari employee contract'
            ]);
        } catch (\Exception $e) {

            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function recreateDocument($run_id)
    {
        try {

            $user = Auth::user();

            $getType = PayrollExport::where('run_id', $run_id)->latest()->first();

            /*
        |--------------------------------------------------------------------------
        | CREATE NEW EXPORT
        |--------------------------------------------------------------------------
        */

            $export = PayrollExport::updateOrCreate([
                'run_id' => $run_id,
            ], [
                'status' => 'recreating',
                'progress' => 0
            ]);

            $exportPeriod = PayrollExport::leftJoin(
                'payroll_runs',
                'payroll_runs.id',
                '=',
                'payroll_exports.run_id'
            )
                ->leftJoin(
                    'payroll_periods',
                    'payroll_runs.period_id',
                    '=',
                    'payroll_periods.id'
                )
                ->where('payroll_exports.run_id', '=', $run_id)
                ->pluck('payroll_periods.name');

            if ($getType->status == 'finished') {
                $type = 'process';
            } else if ($getType->status == 'approved') {
                $type = 'approved';
            }

            GeneratePayrollExport::dispatch($export->id, $type);

            event(new NotificationEvent(
                'Recreate Payroll Document!',
                'Users : ' . $user->name .
                    ' has recreate Payroll ' . $exportPeriod . '!',
                'success'
            ));

            return response()->json([
                'success' => true,
                'message' => 'Document recreate process started'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function progress($run_id)
    {

        $export = PayrollExport::where('run_id', $run_id)->latest()->first();

        return response()->json([
            'progress' => $export->progress,
            'status' => $export->status
        ]);
    }

    public function progressRun($period_id)
    {

        $runs = PayrollRun::where('period_id', $period_id)->latest()->first();

        return response()->json([
            'progress' => $runs->progress,
            'status' => $runs->status
        ]);
    }
}
