<?php

namespace App\Jobs;

use App\Models\PayrollComponent;
use App\Models\PayrollSetting;
use App\Models\ThrApprove;
use App\Models\ThrPeriod;
use App\Models\ThrRun;
use App\Models\ThrRunDetail;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class GenerateThrProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $runId;

    public function __construct($runId)
    {
        $this->runId = $runId;
    }

    public function handle()
    {
        ini_set('memory_limit', '2048M');

        $run = ThrRun::findOrFail($this->runId);
        $period = ThrPeriod::findOrFail($run->period_id);

        /*
    ============================
    CREATE RUN
    ============================
    */

        $cutoff = Carbon::parse($period->cutoff_date);

        /*
    ============================
    GET ACTIVE EMPLOYEES
    ============================
    */
        $run->update([
            'status' => 'Get Employee Biodata',
            'progress' => 5,
        ]);
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
        $run->update([
            'status' => 'Get THR Component',
            'progress' => 10,
        ]);
        $thrComponent = PayrollComponent::where('code', 'thr')->first();
        $formula = $thrComponent->formula;

        $totalTHR = 0;

        /*
    ============================
    LOOP EMPLOYEES
    ============================
    */
        foreach ($employees as $emp) {

            $run->update([
                'status' => 'Calculation for ' . $emp->NPK . ' - ' . $thrComponent->name,
                'progress' => 50,
            ]);

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
            'status'         => 'THR calculation completed',
            'progress'       => 100
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
    }
}
