<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateThrExport;
use App\Jobs\GenerateThrProcess;
use App\Models\PayrollComponent;
use App\Models\PayrollSetting;
use App\Models\ThrApprove;
use App\Models\ThrExport;
use App\Models\ThrPeriod;
use App\Models\ThrRun;
use App\Models\ThrRunDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class ThrProcessController extends Controller
{

    public function index()
    {
        $periods = ThrRun::query()
            ->leftJoin('thr_periods', 'thr_periods.id', '=', 'thr_runs.period_id')
            ->leftJoin('thr_exports', 'thr_exports.run_id', '=', 'thr_runs.id')
            ->leftJoin('thr_approve', 'thr_approve.thr_run_id', '=', 'thr_runs.id')
            ->select(
                'thr_runs.*',
                'thr_periods.name',
                'thr_exports.file_excel',
                'thr_exports.file_pdf',
                'thr_exports.file_bank',
                'thr_exports.file_peng',
                'thr_periods.name as period_name',
                'thr_exports.status as export_status',
                'thr_approve.status as approve_status'
            )
            ->latest('processed_at')
            ->get();

        return view('thr.index', compact('periods'));
    }



    public function progress($run_id)
    {

        $export = ThrExport::where('run_id', $run_id)->latest()->first();

        return response()->json([
            'progress' => $export->progress,
            'status' => $export->status
        ]);
    }

    public function progressRun($period_id)
    {

        $runs = ThrRun::where('period_id', $period_id)->latest()->first();

        return response()->json([
            'progress' => $runs->progress,
            'status' => $runs->status
        ]);
    }

    public function destroy($period_id)
    {
        DB::beginTransaction();

        try {

            // ambil semua run id dari period
            $runIds = ThrRun::where('id', $period_id)->pluck('id');
            if ($runIds->count() > 0) {

                // hapus detail payroll
                ThrRunDetail::whereIn('run_id', $runIds)->delete();

                // hapus run payroll
                ThrRun::whereIn('id', $runIds)->delete();
            }

            DB::commit();

            return redirect()->back()->with('success', 'THR deleted successfully');
        } catch (\Exception $e) {

            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function process(Request $request)
    {
        $period = ThrPeriod::findOrFail($request->period_id);

        /*
    ============================
    PREVENT DOUBLE PROCESS
    ============================
    */
        if (ThrRun::where('period_id', $period->id)->exists()) {
            Alert::error('THR already processed');
            return back();
        }

        $run = ThrRun::create([
            'period_id'    => $period->id,
            'processed_at' => now(),
            'status'       => 'Initializing'
        ]);

        GenerateThrProcess::dispatch($run->id);

        Alert::success('THR Processed Successfully');

        return redirect('thr-process/index');
    }

    public function generate()
    {
        $periods = ThrPeriod::orderBy('cutoff_date')->get();
        return view('thr.process', compact('periods'));
    }

    public function export($run_id)
    {
        $export = ThrExport::create([
            'run_id' => $run_id,
            'status' => 'processing',
            'progress' => 0
        ]);

        $type = 'process';

        GenerateThrExport::dispatch($export->id, $type);

        Alert::success('Export THR Finished');
        return redirect('thr-process/index');
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

        $query = DB::table('thr_run_details as prd')
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
}
