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
use Illuminate\Support\Str;

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
        $this->processThr(false);
    }

    public function simulation()
    {
        return $this->processThr(true);
    }

    private function processThr($isCheck = false)
    {
        ini_set('memory_limit', '2048M');
        $thrResults = [];

        if (!$isCheck) {
            $run = ThrRun::findOrFail($this->runId);
            $period = ThrPeriod::findOrFail($run->period_id);
        } else {
            $period = ThrPeriod::findOrFail($this->runId);
        }

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
        if (!$isCheck) {
            $run->update([
                'status' => 'Get Employee Biodata',
                'progress' => 5,
            ]);
        }

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
            // ->where('ec1.npk', '=', 'C-01510')

            // ✅ contract harus masuk range periode
            ->whereDate('ec1.start_date', '<=', $cutoff)
            ->whereDate('ec1.end_date', '>=', $cutoff)
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
    ", [$cutoff, $cutoff]);

        $employees = DB::connection('cii')
            ->table('PKWT as p')
            ->join('BIODATA as b', 'p.NPK', '=', 'b.NPK')
            ->leftJoinSub($latestContract, 'ec', function ($join) {
                $join->on('p.NPK', '=', 'ec.npk');
            })
            ->where('p.NPK', '!=', 'C-00017')
            // ->where('p.npk', '=', 'C-01510')
            ->where('p.TMK', '<', $cutoff)
            ->whereNull('p.TKK')
            ->select(
                'p.NPK',
                'b.NAMA_KARYAWAN',
                'ec.salary',
                'ec.allowance',
                'p.TMK',
                'ec.type'
            )->orderBy('p.NPK')
            ->get();

        // dd($latestContract->get());

        /*
    ============================
    GET THR FORMULA FROM DB
    ============================
    */
        if (!$isCheck) {
            $run->update([
                'status' => 'Get THR Component',
                'progress' => 10,
            ]);
        }
        $thrComponent = PayrollComponent::where('code', 'thr')->first();
        $formula = $thrComponent->formula;

        $totalTHR = 0;

        /*
    ============================
    LOOP EMPLOYEES
    ============================
    */
        foreach ($employees as $emp) {

            if (!$isCheck) {
                $run->update([
                    'status' => 'Calculation for ' . $emp->NPK . ' - ' . $thrComponent->name,
                    'progress' => 50,
                ]);
            }

            $basic_salary = $emp->salary ?? 0;
            $allowance    = $emp->allowance ?? 0;
            $is_contract = Str::ucfirst(Str::lower($emp->type)) === 'Contract' ? 1 : 0;
            $is_daily    = Str::ucfirst(Str::lower($emp->type)) === 'Daily' ? 1 : 0;

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
                    ['basic_salary', 'allowance', 'working_months', 'is_contract', 'is_daily'],
                    [$basic_salary, $allowance, $working_months, $is_contract, $is_daily],
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
            // dd($emp->salary, $emp->NPK, $working_months, $evalFormula, $thr);

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
            if (!$isCheck) {
                ThrRunDetail::create([
                    'run_id'        => $run->id,
                    'employee_npk'  => $emp->NPK,
                    'employee_name' => $emp->NAMA_KARYAWAN,
                    'components'    => json_encode($components),
                    'total_salary'  => $thrRounded
                ]);
            } else {

                $thrResults[] = [
                    'run_id'        => $this->runId,
                    'employee_npk'  => $emp->NPK,
                    'employee_name' => $emp->NAMA_KARYAWAN,
                    'components'    => json_encode($components),
                    'total_salary'  => $thrRounded
                ];
            }

            $totalTHR += $thrRounded;
        }

        /*
    ============================
    UPDATE RUN SUMMARY
    ============================
    */
        if (!$isCheck) {
            $run->update([
                'employee_count' => $employees->count(),
                'total_thr'      => $totalTHR,
                'status'         => 'THR calculation completed',
                'progress'       => 100
            ]);
        } else {
            return $thrResults;
        }

        /*
    ============================
    AUTO CREATE APPROVAL
    ============================
    */
        if (!$isCheck) {
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
}
