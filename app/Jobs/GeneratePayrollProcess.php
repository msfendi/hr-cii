<?php

namespace App\Jobs;

use App\Models\PayrollComponent;
use App\Models\PayrollExport;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class GeneratePayrollProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $runId;

    public function __construct($runId)
    {
        $this->runId = $runId;
    }

    public function handle()
    {

        $run = PayrollRun::findOrFail($this->runId);
        $period = PayrollPeriod::findOrFail($run->period_id);

        $endDate = $period->end_date;

        $employees = DB::connection('cii')
            ->table('BIODATA as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->leftJoin('overtimes as o', 'b.NPK', '=', 'o.NPK')
            ->leftJoin('payroll_masters as pm', 'b.NPK', '=', 'pm.npk')
            // ->where('b.NPK', '=', 'C-00827')
            ->select(
                'b.NPK',
                'b.NAMA_KARYAWAN',
                'pm.salary',
                'pm.allowance',

                DB::raw("
            COALESCE(SUM(
                CASE 
                    WHEN o.DAY NOT IN ('Sabtu','Minggu') 
                    AND TRY_CAST(o.JUMLAH_JAM_LEMBUR as FLOAT) IS NOT NULL
                    THEN TRY_CAST(o.JUMLAH_JAM_LEMBUR as FLOAT) 
                    ELSE 0 
                END
            ),0) as overtime_hours
        "),

                DB::raw("
            COALESCE(SUM(
                CASE 
                    WHEN o.DAY IN ('Sabtu','Minggu') 
                    AND TRY_CAST(o.JUMLAH_JAM_LEMBUR as FLOAT) IS NOT NULL
                    THEN TRY_CAST(o.JUMLAH_JAM_LEMBUR as FLOAT) 
                    ELSE 0 
                END
            ),0) as special_overtime_hours
        "),

                DB::raw("
            SUM(
                CASE
                    WHEN o.JUMLAH_JAM_LEMBUR IS NOT NULL
                    AND TRY_CAST(o.JUMLAH_JAM_LEMBUR as FLOAT) IS NULL
                    THEN 1
                    ELSE 0
                END
            ) as absence_days
        "),

                DB::raw("DATEDIFF(YEAR, p.TMK, '$endDate') as working_years")
            )
            ->groupBy(
                'b.NPK',
                'b.NAMA_KARYAWAN',
                'pm.salary',
                'pm.allowance',
                'p.TMK'
            )
            ->get();
        // dd($employees);

        $components = PayrollComponent::where('is_active', 1)
            ->orderByDesc('priority')
            ->get();

        $overtimeComponent = PayrollComponent::where('code', 'overtime_pay')->first();
        $overtimeFormula = $overtimeComponent->formula;

        $specialOvertimeComponent = PayrollComponent::where('code', 'special_overtime_pay')->first();
        $specialOvertimeFormula = $specialOvertimeComponent->formula;

        $sewingInsentifComponent = PayrollComponent::where('code', 'sewing_insentif')->first();
        $sewingInsentifFormula = json_decode($sewingInsentifComponent->formula, true);

        $cuttingInsentifComponent = PayrollComponent::where('code', 'cutting_insentif')->first();
        $cuttingInsentifFormula = json_decode($cuttingInsentifComponent->formula, true);

        $padInsentifComponent = PayrollComponent::where('code', 'pad_insentif')->first();
        $padInsentifFormula = json_decode($padInsentifComponent->formula, true);

        $run = PayrollRun::create([
            'period_id' => $period->id,
            'processed_at' => now(),
            'progress' => 0,
            'status' => 'Starting payroll process'
        ]);

        GeneratePayrollProcess::dispatch($run->id);

        $totalPayroll = 0;
        // dd($employees);

        foreach ($employees as $employee) {

            $inputVariables = [
                'basic_salary'   => (float) $employee->salary,
                'allowance'      => (float) $employee->allowance,
                'absence_days'   => (float) $employee->absence_days,
                'working_years'  => (float) $employee->working_years,
            ];

            $results = [];
            $grandTotal = 0;

            foreach ($components as $component) {

                if ($component->calculation_method === 'fixed') {

                    $amount = $component->value;
                } else {

                    // KHUSUS KOMPONEN LEMBUR RESMI
                    if ($component->code === 'overtime_pay') {
                        $overtimes = DB::connection('cii')
                            ->table('overtimes')
                            ->where('NPK', $employee->NPK)
                            ->whereNotIn('DAY', ['Sabtu', 'Minggu'])
                            ->select('JUMLAH_JAM_LEMBUR')
                            ->get();

                        $amount = 0;

                        foreach ($overtimes as $ot) {

                            $hours = (float) $ot->JUMLAH_JAM_LEMBUR;

                            if ($hours > 0) {

                                $variables = [
                                    'basic_salary' => (float) $employee->salary,
                                    'overtime_hours' => $hours
                                ];

                                $amount += $this->evaluateFormula(
                                    $overtimeFormula,
                                    $results,
                                    $variables
                                );
                            }
                        }
                    }
                    // KHUSUS KOMPONEN LEMBUR KHUSUS
                    else if ($component->code === 'special_overtime_pay') {
                        $overtimes = DB::connection('cii')
                            ->table('overtimes')
                            ->where('NPK', $employee->NPK)
                            ->whereIn('DAY', ['Sabtu', 'Minggu'])
                            ->select('JUMLAH_JAM_LEMBUR')
                            ->get();
                        $amount = 0;
                        foreach ($overtimes as $ot) {
                            $hours = (float) $ot->JUMLAH_JAM_LEMBUR;
                            if ($hours > 0) {
                                $variables = [
                                    'basic_salary' => (float) $employee->salary,
                                    'special_overtime_hours' => $hours
                                ];
                                $amount += $this->evaluateFormula(
                                    $specialOvertimeFormula,
                                    $results,
                                    $variables
                                );
                            }
                        }
                    } else if ($component->code === 'sewing_insentif') {
                        $lineefficiencies = DB::table('line_efficiencies as le')
                            ->join('employee_line_assignments as ela', function ($join) use ($employee) {

                                $join->on('le.line_number', '=', 'ela.line_number')
                                    ->where('ela.npk', $employee->NPK)
                                    ->whereColumn('le.date', '>=', 'ela.start_date')
                                    ->where(function ($q) {
                                        $q->whereColumn('le.date', '<=', 'ela.end_date')
                                            ->orWhereNull('ela.end_date');
                                    });
                            })
                            ->whereBetween('le.date', [$period->start_date, $period->end_date])
                            ->select(
                                'le.line_number',
                                'le.efficiency',
                                'le.date',
                                'ela.role'
                            )
                            ->get();

                        $amount = 0;

                        /*
                        ========================
                        OPERATOR & SUPERVISOR
                        ========================
                        */

                        foreach ($lineefficiencies as $row) {

                            if (in_array($row->role, ['operator', 'supervisor'])) {

                                $lineInsentif = $this->getInsentifByEfficiency(
                                    $row->efficiency,
                                    $sewingInsentifFormula
                                );

                                $amount += $this->calculateRoleSewingInsentif(
                                    $row->role,
                                    $lineInsentif,
                                    1
                                );
                            }
                        }

                        /*
                        ========================
                        CHIEF / MEKANIK
                        ========================
                        */

                        $grouped = DB::table('line_efficiencies')
                            ->whereBetween('date', [$period->start_date, $period->end_date])
                            ->select(
                                'date',
                                DB::raw('count(distinct line_number) as jumlah_line')
                            )
                            ->groupBy('date')
                            ->get();

                        foreach ($grouped as $day) {

                            $assignment = DB::table('employee_line_assignments')
                                ->where('npk', $employee->NPK)
                                ->where('start_date', '<=', $day->date)
                                ->where(function ($q) use ($day) {
                                    $q->where('end_date', '>=', $day->date)
                                        ->orWhereNull('end_date');
                                })
                                ->first();

                            if (!$assignment) continue;

                            if (!in_array($assignment->role, ['chief', 'mekanik', 'mekanik_leader'])) continue;

                            $lines = DB::table('line_efficiencies')
                                ->where('date', $day->date)
                                ->get();

                            $totalLineInsentif = 0;

                            foreach ($lines as $line) {

                                $totalLineInsentif += $this->getInsentifByEfficiency(
                                    $line->efficiency,
                                    $sewingInsentifFormula
                                );
                            }

                            $amount += $this->calculateRoleSewingInsentif(
                                $assignment->role,
                                $totalLineInsentif,
                                $day->jumlah_line
                            );
                        }
                    } else if ($component->code === 'pad_insentif') {

                        $assignments = DB::table('employee_pad_assignments')
                            ->where('npk', $employee->NPK)
                            ->where(function ($q) use ($period) {
                                $q->whereBetween('start_date', [$period->start_date, $period->end_date])
                                    ->orWhere(function ($q2) use ($period) {
                                        $q2->where('start_date', '<=', $period->end_date)
                                            ->where(function ($q3) use ($period) {
                                                $q3->whereNull('end_date')
                                                    ->orWhere('end_date', '>=', $period->start_date);
                                            });
                                    });
                            })
                            ->get();

                        $amount = 0;

                        foreach ($assignments as $assignment) {

                            $dept = $assignment->dept;
                            $role = $assignment->role;

                            $start = max($assignment->start_date, $period->start_date);
                            $end   = $assignment->end_date
                                ? min($assignment->end_date, $period->end_date)
                                : $period->end_date;

                            /*
                            ===============================
                            OPERATOR
                            ===============================
                            */
                            if ($role === 'operator') {

                                $padEfficiencies = DB::table('pad_efficiencies')
                                    ->where('npk', $employee->NPK)
                                    ->whereBetween('date', [$start, $end])
                                    ->get();

                                $totalOperatorInsentif = 0;

                                foreach ($padEfficiencies as $row) {

                                    $rate = $this->getInsentifByEfficiency(
                                        $row->efficiency,
                                        $padInsentifFormula
                                    );

                                    $totalOperatorInsentif += $rate * $row->piece;
                                }

                                $amount += $totalOperatorInsentif;
                            }

                            /*
                            ===============================
                            NON OPERATOR
                            ===============================
                            */ else {

                                $padEfficiencies = DB::table('pad_efficiencies as pe')
                                    ->join('employee_pad_assignments as epa', function ($join) {
                                        $join->on('pe.npk', '=', 'epa.npk')
                                            ->on('pe.dept', '=', 'epa.dept');
                                    })
                                    ->where('epa.role', 'operator')
                                    ->where('pe.dept', $dept)
                                    ->whereBetween('pe.date', [$start, $end])
                                    ->whereColumn('pe.date', '>=', 'epa.start_date')
                                    ->where(function ($q) {
                                        $q->whereColumn('pe.date', '<=', 'epa.end_date')
                                            ->orWhereNull('epa.end_date');
                                    })
                                    ->select(
                                        'pe.npk',
                                        'pe.efficiency',
                                        'pe.piece',
                                        'pe.date'
                                    )
                                    ->get();

                                $totalDeptInsentif = 0;

                                foreach ($padEfficiencies as $row) {

                                    $rate = $this->getInsentifByEfficiency(
                                        $row->efficiency,
                                        $padInsentifFormula
                                    );

                                    $totalDeptInsentif += $rate * $row->piece;
                                }

                                $jumlahOperator = DB::table('employee_pad_assignments')
                                    ->where('dept', $dept)
                                    ->where('role', 'operator')
                                    ->where(function ($q) use ($start, $end) {
                                        $q->whereBetween('start_date', [$start, $end])
                                            ->orWhere(function ($q2) use ($start, $end) {
                                                $q2->where('start_date', '<=', $end)
                                                    ->where(function ($q3) use ($start) {
                                                        $q3->whereNull('end_date')
                                                            ->orWhere('end_date', '>=', $start);
                                                    });
                                            });
                                    })
                                    ->pluck('npk')
                                    ->unique()
                                    ->count();

                                $amount += $this->calculateRolePadInsentif(
                                    $role,
                                    $totalDeptInsentif,
                                    $jumlahOperator
                                );
                            }
                        }
                    } else if ($component->code === 'cutting_insentif') {

                        $cuttingEfficiencies = DB::table('cutting_efficiencies as ce')
                            ->join('employee_cutting_assignments as eca', function ($join) use ($employee) {

                                $join->on('ce.npk', '=', 'eca.npk')
                                    ->whereColumn('ce.date', '>=', 'eca.start_date')
                                    ->where(function ($q) {
                                        $q->whereColumn('ce.date', '<=', 'eca.end_date')
                                            ->orWhereNull('eca.end_date');
                                    });
                            })
                            ->where('ce.npk', $employee->NPK)
                            ->whereBetween('ce.date', [$period->start_date, $period->end_date])
                            ->select(
                                'ce.efficiency',
                                'ce.date',
                                'eca.role'
                            )
                            ->get();

                        $amount = 0;

                        foreach ($cuttingEfficiencies as $row) {

                            // ambil insentif dari efficiency
                            $insentif = $this->getInsentifByEfficiency(
                                $row->efficiency,
                                $cuttingInsentifFormula
                            );

                            // hitung berdasarkan role
                            $amount += $this->calculateRoleCuttingInsentif(
                                $row->role,
                                $insentif
                            );
                        }
                    } else {
                        $amount = $this->evaluateFormula(
                            $component->formula,
                            $results,
                            $inputVariables
                        );
                    }
                }

                $amount = (float) $amount;

                $results[$component->code] = $amount;

                if ($component->type === 'earning') {
                    $grandTotal += $amount;
                } else {
                    $grandTotal -= $amount;
                }
            }

            PayrollRunDetail::create([
                'run_id'        => $run->id,
                'employee_npk'  => $employee->NPK,
                'employee_name' => $employee->NAMA_KARYAWAN,
                'components'    => $results,
                'total_salary'  => $grandTotal
            ]);
            // $payrollResults[] = [
            //     'npk' => $employee->NPK,
            //     'name' => $employee->NAMA_KARYAWAN,
            //     'components' => $results,
            //     'total_salary' => $grandTotal
            // ];

            $totalPayroll += $grandTotal;
        }

        $run->update([
            'employee_count' => $employees->count(),
            'total_payroll'  => $totalPayroll
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

    function getPadRate($efficiency)
    {
        if ($efficiency >= 100) {
            return 11;
        } elseif ($efficiency >= 95) {
            return 8;
        } elseif ($efficiency >= 90) {
            return 6;
        } elseif ($efficiency >= 85) {
            return 2.5;
        }

        return 0;
    }

    private function calculateRoleSewingInsentif($role, $totalLineInsentif, $jumlahLine)
    {

        switch ($role) {

            case 'supervisor':
                return $totalLineInsentif * 2;

            case 'chief':
                return ($totalLineInsentif * $jumlahLine) / 2;

            case 'mekanik':
                return ($totalLineInsentif * $jumlahLine) / 4;

            case 'mekanik_leader':
                return ($totalLineInsentif * $jumlahLine) * 0.15;

            default:
                return $totalLineInsentif;
        }
    }

    private function calculateRolePadInsentif($role, $totalDeptInsentif, $jumlahOperator)
    {
        $jumlahOperator = max($jumlahOperator, 1);

        switch ($role) {

            case 'supervisor':
            case 'leader':
            case 'inkmaking':
                return $totalDeptInsentif / $jumlahOperator;

            case 'helper':
                return ($totalDeptInsentif / $jumlahOperator) * 0.75;

            default:
                return $totalDeptInsentif;
        }
    }

    private function calculateRoleCuttingInsentif($role, $insentif)
    {
        switch ($role) {
            case 'Bundling':
            case 'Rib':
            case 'Htl':
            case 'Accescories':
            case 'Supermarket':
            case 'Loading to Sewing':
            case 'Waste':
            case 'Ganti BS':
            case 'Piping':
            case 'Cutting Admin':
            case 'Supermarket Admin':
                return $insentif * 0.75;

            case 'Manual Cutter':
            case 'Auto Cutter':
                return $insentif * 1.2;

            case 'Spreading Auto':
            case 'Spreading Manual':
                return $insentif * 1;

            default:
                return $insentif;
        }
    }
}
