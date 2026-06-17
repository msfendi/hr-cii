<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use App\Jobs\GeneratePayrollCheck;
use App\Jobs\GeneratePayrollExport;
use App\Jobs\GeneratePayrollProcess;
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

class PayrollProcessController extends Controller
{

    public function index(Request $request)
    {
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
                'payroll_approve.status as approve_status' // 🔥 penting
            );

        if ($filter === 'open') {
            $query->where('payroll_periods.is_closed', false);
        }

        if ($filter === 'closed') {
            $query->where('payroll_periods.is_closed', true);
        }

        $periods = $query
            ->latest('payroll_runs.id')
            ->get();

        // dd($periods);

        return view('payroll.index', compact('periods', 'filter'));
    }

    public function generate()
    {
        $periods = PayrollPeriod::orderBy('start_date')->where('is_closed', 0)->get();
        return view('payroll.process', compact('periods'));
    }

    public function overtimeDetail($periodId, $npk)
    {
        $period = DB::table('payroll_periods')
            ->where('id', $periodId)
            ->first();

        if (!$period) {
            return response()->json([
                'data' => []
            ]);
        }

        $periodStart = $period->start_date;
        $periodEnd   = $period->end_date;

        $count_days = \Carbon\Carbon::parse($periodStart)
            ->diffInDays(
                \Carbon\Carbon::parse($periodEnd)
            ) + 1;

        $overtimeDetails = DB::connection('cii')
            ->table('overtimes')

            ->leftJoin('PKWT as p', 'overtimes.NPK', '=', 'p.NPK')

            ->where('overtimes.NPK', $npk)

            ->whereBetween(
                'OVERTIME_DATE',
                [$periodStart, $periodEnd]
            )

            ->select(
                'overtimes.NPK',
                'overtimes.OVERTIME_DATE',
                'overtimes.DAY',
                'overtimes.JUMLAH_JAM_LEMBUR',

                DB::raw("
                CASE
                    WHEN DAY NOT IN ('Sabtu','Minggu')
                    AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NOT NULL
                    THEN TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT)
                    ELSE 0
                END AS overtime_hours
            "),

                DB::raw("
                CASE
                    WHEN DAY IN ('Sabtu','Minggu')
                    AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NOT NULL
                    THEN
                        CASE
                            WHEN
                            (
                                COALESCE(p.GAJI,0)
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
            ")
            )
            ->orderBy('OVERTIME_DATE')
            ->get();

        return response()->json([
            'data' => $overtimeDetails
        ]);
    }

    public function approvalStatus($periodId)
    {
        /*
        ============================================
        GET APPROVAL DATA
        ============================================
        */

        $data = InsentifApproval::where('period_id', $periodId)
            ->orderBy('payroll_component')
            ->get([
                'id',
                'payroll_component',
                'status',
                'approved_at'
            ])
            ->map(function ($item) {

                $approved = $item->approved_at;

                // jika masih string JSON
                if (is_string($approved)) {
                    $approved = json_decode($approved, true);
                }

                // ambil data terakhir
                $item->approved_at = is_array($approved)
                    ? end($approved)
                    : null;

                return $item;
            });

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
                    'NAMA_KARYAWAN'
                )
                ->union(
                    DB::table('BIODATA_KELUAR')
                        ->select(
                            'NPK',
                            'NAMA_KARYAWAN'
                        )
                );

            $invalidContracts = DB::table('PKWT as p')
                ->leftJoinSub($biodataUnion, 'b', function ($join) {
                    $join->on('p.NPK', '=', 'b.NPK');
                })
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
                    'ec.status_contract'
                )
                ->orderBy('p.NPK')
                ->get();



            $invalidBankAccounts = DB::table('PKWT as p')
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
                    'pm.bank_account'
                )
                ->distinct()
                ->orderBy('p.NPK')
                ->get();
        }

        /*
        ============================================
        RETURN JSON
        ============================================
        */

        return response()->json([
            'approval' => $data,
            'invalid_contracts' => $invalidContracts,
            'invalid_bank_accounts' => $invalidBankAccounts
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

    public function checkPayroll($period_id)
    {
        $period = PayrollPeriod::findOrFail($period_id);

        $service = new GeneratePayrollProcess(
            $period->id,
            'check'
        );

        $payrollResults = $service->simulation();

        // $payrollResults = GeneratePayrollCheck::dispatchSync($period->id);
        $user = Auth::user();

        $role = $user->role;

        if ($role === 'Payroll_STAFF') {

            $payrollResults = collect($payrollResults)
                ->where('IS_STAFF', 1)
                ->values();
        } elseif ($role === 'Payroll_SEWING') {

            $payrollResults = collect($payrollResults)
                ->filter(function ($row) {
                    return $row['IS_STAFF'] == 0
                        && $row['IS_SEWING'] == 0;
                })
                ->values();
        } elseif ($role === 'Payroll_NONSEWING') {

            $payrollResults = collect($payrollResults)
                ->filter(function ($row) {
                    return $row['IS_STAFF'] == 1
                        && $row['IS_SEWING'] == 1;
                })
                ->values();
        }

        return response()->json([
            'data' => $payrollResults
        ]);
    }

    public function details($id)
    {
        /*
    |--------------------------------------------------------------------------
    | CHECK USER ROLE
    |--------------------------------------------------------------------------
    */

        $user = Auth::user();

        $canSeeSalary = $user->hasRole([
            'Admin',
            'Payroll_STAFF',
            'Payroll_SEWING',
            'Payroll_NONSEWING'
        ]);

        /*
    |--------------------------------------------------------------------------
    | EMPLOYEE UNION
    |--------------------------------------------------------------------------
    */

        $employeeUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'id_dept', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'id_dept', 'IS_STAFF')
            );

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
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'emp.id_dept')
            ->leftJoin('PKWT as p', 'p.NPK', '=', 'emp.NPK')
            ->where('prd.run_id', $id)
            ->select(
                'prd.id',
                'prd.run_id',
                'prd.employee_npk',
                'prd.employee_name',
                'p.TKK as tkk',
                'd.DEPARTEMENT as dept',
                'prd.components',
                'prd.total_salary',
                'emp.IS_STAFF',
                'd.IS_SEWING'
            );

        /*
    |--------------------------------------------------------------------------
    | ROLE FILTERING (NEW)
    |--------------------------------------------------------------------------
    */

        if (!$user->hasRole('Admin')) {

            if ($user->hasRole('Payroll_STAFF')) {
                $query->where('emp.IS_STAFF', 1);
            }

            if ($user->hasRole('Payroll_SEWING')) {
                $query->where('d.IS_SEWING', 0)->where('emp.IS_STAFF', 0);
            }

            if ($user->hasRole('Payroll_NONSEWING')) {
                $query->where('d.IS_SEWING', 1)->where('emp.IS_STAFF', 0);
            }
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

        $data->transform(function ($item) use ($canSeeSalary) {

            $components = json_decode($item->components, true) ?? [];

            foreach ($components as $key => $value) {
                $item->$key = $canSeeSalary
                    ? (float) $value
                    : '***';
            }

            $item->total_salary = $canSeeSalary
                ? (float) $item->total_salary
                : '***';

            /*
    |--------------------------------------------------------------------------
    | EMPLOYMENT STATUS (NEW)
    |--------------------------------------------------------------------------
    */

            $item->employment_status = empty($item->tkk)
                ? 'Active'
                : 'Resign';

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

            $type = $componentTypes[$code] ?? 'earning';

            if ($type == 'earning') {
                $earnings[$code] = $value;
            } else {
                $deductions[$code] = $value;
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

    private function evaluateFormula($formula, $results, $inputVariables)
    {
        $variables = array_merge($inputVariables, $results);

        foreach ($variables as $key => $value) {
            $formula = preg_replace('/\b' . $key . '\b/', $value, $formula);
        }

        try {
            return eval("return $formula;");
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function getInsentifByEfficiency($efficiency, $rules)
    {
        krsort($rules);

        foreach ($rules as $threshold => $value) {
            if ($efficiency >= $threshold) {
                return $value;
            }
        }

        return 0;
    }

    private function calculateRoleSewingInsentif(
        $role,
        $dept,
        $totalLineInsentif,
        $jumlahLine
    ) {

        $jumlahLine = max($jumlahLine, 1);

        /*
    |--------------------------------------------------------------------------
    | GET FORMULA FROM DB (CACHE)
    |--------------------------------------------------------------------------
    */

        $formula = Cache::remember(
            "insentif_formula_{$dept}_{$role}",
            300,
            function () use ($role, $dept) {

                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', $dept)
                    ->value('formula');
            }
        );

        /*
    |--------------------------------------------------------------------------
    | DEFAULT FALLBACK
    |--------------------------------------------------------------------------
    */

        if (!$formula) {
            return $totalLineInsentif;
        }

        /*
    |--------------------------------------------------------------------------
    | VARIABLE REPLACEMENT
    |--------------------------------------------------------------------------
    */

        $variables = [
            'totalLineInsentif' => $totalLineInsentif,
            'jumlahLine'        => $jumlahLine,
        ];

        foreach ($variables as $key => $value) {
            $formula = str_replace($key, $value, $formula);
        }

        /*
    |--------------------------------------------------------------------------
    | SAFE EVALUATION
    |--------------------------------------------------------------------------
    */

        try {

            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {

            return $totalLineInsentif;
        }
    }

    private function calculateRolePadInsentif(
        $role,
        $dept,
        $totalDeptInsentif,
        $jumlahOperator
    ) {

        $jumlahOperator = max($jumlahOperator, 1);

        /*
    |--------------------------------------------------------------------------
    | GET FORMULA FROM DB (CACHE)
    |--------------------------------------------------------------------------
    */

        $formula = Cache::remember(
            "insentif_formula_{$dept}_{$role}",
            300,
            function () use ($role, $dept) {

                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', $dept)
                    ->value('formula');
            }
        );

        /*
    |--------------------------------------------------------------------------
    | DEFAULT FALLBACK
    |--------------------------------------------------------------------------
    */

        if (!$formula) {
            return $totalDeptInsentif;
        }

        /*
    |--------------------------------------------------------------------------
    | VARIABLE REPLACEMENT
    |--------------------------------------------------------------------------
    */

        $variables = [
            'totalDeptInsentif' => $totalDeptInsentif,
            'jumlahOperator'    => $jumlahOperator,
        ];

        foreach ($variables as $key => $value) {
            $formula = str_replace($key, $value, $formula);
        }

        /*
    |--------------------------------------------------------------------------
    | SAFE EVALUATION
    |--------------------------------------------------------------------------
    */

        try {

            // hanya izinkan karakter matematika
            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {

            return $totalDeptInsentif;
        }
    }

    private function calculateRoleHeatInsentif(
        $role,
        $dept,
        $totalDeptInsentif,
        $jumlahOperator
    ) {

        $jumlahOperator = max($jumlahOperator, 1);

        /*
    |--------------------------------------------------------------------------
    | GET FORMULA FROM DB (CACHE)
    |--------------------------------------------------------------------------
    */

        $formula = Cache::remember(
            "insentif_formula_{$dept}_{$role}",
            300,
            function () use ($role, $dept) {

                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', $dept)
                    ->value('formula');
            }
        );

        /*
    |--------------------------------------------------------------------------
    | DEFAULT FALLBACK
    |--------------------------------------------------------------------------
    */

        if (!$formula) {
            return $totalDeptInsentif;
        }

        /*
    |--------------------------------------------------------------------------
    | VARIABLE REPLACEMENT
    |--------------------------------------------------------------------------
    */

        $variables = [
            'totalDeptInsentif' => $totalDeptInsentif,
            'jumlahOperator'    => $jumlahOperator,
        ];

        foreach ($variables as $key => $value) {
            $formula = str_replace($key, $value, $formula);
        }

        /*
    |--------------------------------------------------------------------------
    | SAFE EVALUATION
    |--------------------------------------------------------------------------
    */

        try {

            // hanya izinkan karakter matematika
            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {

            return $totalDeptInsentif;
        }
    }

    private function calculateRoleCuttingInsentif(
        $role,
        $dept,
        $insentif
    ) {

        /*
    |--------------------------------------------------------------------------
    | GET FORMULA FROM DB (CACHE)
    |--------------------------------------------------------------------------
    */

        $formula = Cache::remember(
            "insentif_formula_{$dept}_{$role}",
            300,
            function () use ($role, $dept) {

                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', $dept)
                    ->value('formula');
            }
        );

        /*
    |--------------------------------------------------------------------------
    | DEFAULT FALLBACK
    |--------------------------------------------------------------------------
    */

        if (!$formula) {
            return $insentif;
        }

        /*
    |--------------------------------------------------------------------------
    | VARIABLE REPLACEMENT
    |--------------------------------------------------------------------------
    */

        $variables = [
            'insentif' => $insentif,
        ];

        foreach ($variables as $key => $value) {
            $formula = str_replace($key, $value, $formula);
        }

        /*
    |--------------------------------------------------------------------------
    | SAFE EVALUATION
    |--------------------------------------------------------------------------
    */

        try {

            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {

            return $insentif;
        }
    }
}
