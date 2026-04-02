<?php

namespace App\Jobs;

use App\Models\ThrPeriod;
use App\Models\ThrRun;
use App\Models\ThrRunDetail;
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
    protected $period_id;

    public function __construct($period_id)
    {
        $this->period_id = $period_id;
    }

    public function handle()
    {
        ini_set('memory_limit', '2048M');

        $period = ThrPeriod::findOrFail($this->period_id);

        /*
        |--------------------------------------------------------------------------
        | CREATE RUN
        |--------------------------------------------------------------------------
        */

        $run = ThrRun::create([
            'period_id' => $period->id,
            'processed_at' => now(),
            'progress' => 0,
            'status' => 'processing'
        ]);

        $cutoff = $period->cutoff_date;

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE AKTIF ONLY
        |--------------------------------------------------------------------------
        */

        $employees = DB::connection('cii')
            ->table('BIODATA as b')
            ->join('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->join('payroll_masters as pm', 'pm.npk', '=', 'b.NPK')

            ->whereNull('p.TKK') // ONLY ACTIVE

            ->select(
                'b.NPK',
                'b.NAMA_KARYAWAN',
                'pm.salary',
                'pm.allowance',
                'p.TMK'
            )
            ->get();

        $totalTHR = 0;

        foreach ($employees as $employee) {

            /*
            |--------------------------------------------------------------------------
            | MASA KERJA
            |--------------------------------------------------------------------------
            */

            $months = DB::selectOne("
                SELECT DATEDIFF(MONTH, ?, ?) as masa_kerja
            ", [$employee->TMK, $cutoff])->masa_kerja;

            $masaKerja = max($months, 0) / 12;

            /*
            |--------------------------------------------------------------------------
            | THR FORMULA
            |--------------------------------------------------------------------------
            */

            $basic = (float)$employee->salary;
            $allowance = (float)$employee->allowance;

            $thr = (($basic + $allowance) / 12) * $masaKerja;

            $components = [
                "basic_salary" => $basic,
                "allowance" => $allowance,
                "masa_kerja" => round($masaKerja, 2),
                "thr" => round($thr, 2)
            ];

            ThrRunDetail::create([
                'run_id' => $run->id,
                'employee_npk' => $employee->NPK,
                'employee_name' => $employee->NAMA_KARYAWAN,
                'components' => json_encode($components),
                'total_salary' => $thr
            ]);

            $totalTHR += $thr;
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE RUN
        |--------------------------------------------------------------------------
        */

        $run->update([
            'employee_count' => $employees->count(),
            'total_thr' => $totalTHR,
            'progress' => 100,
            'status' => 'finished'
        ]);
    }
}
