<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateThrExport;
use App\Models\PayrollComponent;
use App\Models\PayrollSetting;
use App\Models\ThrApprove;
use App\Models\ThrExport;
use App\Models\ThrPeriod;
use App\Models\ThrRun;
use App\Models\ThrRunDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        /*
    ============================
    CREATE RUN
    ============================
    */
        $run = ThrRun::create([
            'period_id'    => $period->id,
            'processed_at' => now(),
            'status'       => 'processing'
        ]);

        $cutoff = Carbon::parse($period->cutoff_date);

        /*
    ============================
    GET ACTIVE EMPLOYEES
    ============================
    */
        $employees = DB::connection('cii')
            ->table('PKWT as p')
            ->join('BIODATA as b', 'p.NPK', '=', 'b.NPK')
            ->join('payroll_masters as pm', 'pm.npk', '=', 'p.NPK')
            ->whereNull('p.TKK')
            ->select(
                'p.NPK',
                'b.NAMA_KARYAWAN',
                'pm.salary',
                'pm.allowance',
                'p.TMK'
            )
            ->get();

        /*
    ============================
    GET THR FORMULA FROM DB
    ============================
    */
        $thrComponent = PayrollComponent::where('code', 'thr')->first();
        $formula = $thrComponent ? $thrComponent->formula : '(working_months >= 12 ? (basic_salary + allowance) : ((basic_salary + allowance) / 12 * working_months))';

        $totalTHR = 0;

        /*
    ============================
    LOOP EMPLOYEES
    ============================
    */
        foreach ($employees as $emp) {

            $basic_salary = $emp->salary ?? 0;
            $allowance    = $emp->allowance ?? 0;

            /*
        ============================
        WORKING MONTHS
        ============================
        */
            $working_months = Carbon::parse($emp->TMK)
                ->diffInMonths($cutoff);

            /*
        ============================
        THR CALCULATION USING FORMULA
        ============================
        */
            $thr = 0;

            try {
                // Ganti variabel formula sesuai DB: working_months, basic_salary, allowance
                $evalFormula = str_replace(
                    ['basic_salary', 'allowance', 'working_months'],
                    [$basic_salary, $allowance, $working_months],
                    $formula
                );
                eval('$thr = ' . $evalFormula . ';');
            } catch (\Throwable $e) {
                $thr = 0;
            }

            /*
        ============================
        🔥 ROUND ONLY ONCE
        ============================
        */
            $thrRounded = round($thr, 0);

            /*
        ============================
        COMPONENT JSON
        ============================
        */
            $components = [
                'basic_salary'   => $basic_salary,
                'allowance'      => $allowance,
                'working_months' => $working_months,
                'thr'            => $thrRounded
            ];

            /*
        ============================
        SAVE DETAIL
        ============================
        */
            ThrRunDetail::create([
                'run_id'        => $run->id,
                'employee_npk'  => $emp->NPK,
                'employee_name' => $emp->NAMA_KARYAWAN,
                'components'    => json_encode($components),
                'total_salary'  => $thrRounded
            ]);

            $totalTHR += $thrRounded;
        }

        /*
    ============================
    UPDATE RUN SUMMARY
    ============================
    */
        $run->update([
            'employee_count' => $employees->count(),
            'total_thr'      => $totalTHR,
            'status'         => 'completed'
        ]);

        /*
    ============================
    AUTO CREATE APPROVAL
    ============================
    */
        $existsApprove = ThrApprove::where('thr_run_id', $run->id)->exists();

        if (!$existsApprove) {

            $settings = PayrollSetting::where('component', 'thr')->get();

            if ($settings->count() > 0) {

                $approvals = $settings->pluck('approval')->toArray();

                $progress = collect($approvals)->map(function ($npk) {
                    $npkList = is_array($npk) ? $npk : json_decode($npk, true);
                    if (!is_array($npkList)) $npkList = [$npk];
                    $statusList = array_fill(0, count($npkList), 'waiting');
                    return [
                        'npk' => json_encode($npkList),
                        'status' => json_encode($statusList)
                    ];
                })->values();

                ThrApprove::create([
                    'thr_run_id'     => $run->id,
                    'approval'       => $approvals,
                    'progress'       => $progress,
                    'approved_at'    => [],
                    'status'         => 'pending'
                ]);
            }
        }

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
        $data = DB::table('thr_run_details')
            ->where('run_id', $id)
            ->select(
                'run_id',
                'employee_npk',
                'employee_name',
                'components',
                'total_salary'
            )
            ->orderBy('employee_npk')
            ->get();

        $data->transform(function ($item) {

            $components = json_decode($item->components, true);

            foreach ($components as $key => $value) {
                $item->$key = $value;
            }

            return $item;
        });

        return response()->json([
            'data' => $data
        ]);
    }
}
