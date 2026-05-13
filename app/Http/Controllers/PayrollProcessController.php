<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
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

    public function approvalStatus($periodId)
    {
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

        return response()->json($data);
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
