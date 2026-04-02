<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePayrollExport;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\DataTables;

class PayrollProcessController extends Controller
{

    public function index()
    {
        $periods = PayrollRun::query()
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
            )
            ->orderByDesc('payroll_runs.processed_at')
            ->get();

        // dd($periods);

        return view('payroll.index', compact('periods'));
    }

    public function generate()
    {
        $periods = PayrollPeriod::orderBy('start_date')->get();
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
            ]);

        return response()->json($data);
    }

    public function process(Request $request)
    {
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

        $periodStart = $period->start_date;
        $periodEnd   = $period->end_date;

        $employeeBase = DB::connection('cii')
            ->table('PKWT as p')
            ->leftJoin('BIODATA as b', 'p.NPK', '=', 'b.NPK')
            ->leftJoin('BIODATA_KELUAR as bk', 'p.NPK', '=', 'bk.NPK')
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })
            ->select(
                'p.NPK',
                DB::raw('COALESCE(b.NAMA_KARYAWAN, bk.NAMA_KARYAWAN) as NAMA_KARYAWAN'),
                'p.TMK',
                'p.TKK'
            );

        $overtimeSummary = DB::connection('cii')
            ->table('overtimes')
            ->select(
                'NPK',
                DB::raw("
                SUM(
                    CASE 
                        WHEN DAY NOT IN ('Sabtu','Minggu')
                        AND TRY_CAST(JUMLAH_JAM_LEMBUR as FLOAT) IS NOT NULL
                        THEN TRY_CAST(JUMLAH_JAM_LEMBUR as FLOAT)
                        ELSE 0
                    END
                ) as overtime_hours
            "),
                DB::raw("
                SUM(
                    CASE 
                        WHEN DAY IN ('Sabtu','Minggu')
                        AND TRY_CAST(JUMLAH_JAM_LEMBUR as FLOAT) IS NOT NULL
                        THEN TRY_CAST(JUMLAH_JAM_LEMBUR as FLOAT)
                        ELSE 0
                    END
                ) as special_overtime_hours
            "),
                DB::raw("
                SUM(
                    CASE
                        WHEN JUMLAH_JAM_LEMBUR IS NOT NULL
                        AND TRY_CAST(JUMLAH_JAM_LEMBUR as FLOAT) IS NULL
                        THEN 1
                        ELSE 0
                    END
                ) as absence_days
            ")
            )
            ->groupBy('NPK');

        $employees = DB::connection('cii')
            ->query()
            ->fromSub($employeeBase, 'emp')
            ->leftJoinSub($overtimeSummary, 'ot', function ($join) {
                $join->on('emp.NPK', '=', 'ot.NPK');
            })
            ->leftJoin('payroll_masters as pm', 'emp.NPK', '=', 'pm.npk')
            ->leftJoin('payroll_adjusments as pa', function ($join) use ($period) {
                $join->on('emp.NPK', '=', 'pa.npk')
                    ->where('pa.period_id', '=', $period->id);
            })
            ->select(
                'emp.NPK',
                'emp.NAMA_KARYAWAN',
                'pm.salary',
                'pm.allowance',
                'pm.pph21',
                DB::raw('COALESCE(pa.adjusment,0) as adjusment'),
                DB::raw('COALESCE(ot.overtime_hours,0) as overtime_hours'),
                DB::raw('COALESCE(ot.special_overtime_hours,0) as special_overtime_hours'),
                DB::raw('COALESCE(ot.absence_days,0) as absence_days'),
                DB::raw("DATEDIFF(YEAR, emp.TMK, '$periodEnd') as working_years")
            )
            ->get();

        $components = PayrollComponent::where('is_active', 1)
            ->where('code', '!=', 'thr')
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

        $totalPayroll = 0;

        foreach ($employees as $employee) {

            $inputVariables = [
                'basic_salary'   => (float) $employee->salary,
                'allowance'      => (float) $employee->allowance,
                'absence_days'   => (float) $employee->absence_days,
                'working_years'  => (float) $employee->working_years,
                'adjusment'      => (float) $employee->adjusment,
                'pph_21'         => (float) $employee->pph21,
            ];

            $results = [];
            $grandTotal = 0;

            foreach ($components as $component) {
                if ($component->code === 'thr') continue;

                if ($component->calculation_method === 'fixed') {
                    $amount = $component->value;
                } else {

                    if ($component->code === 'overtime_pay') {
                        $amount = $this->evaluateFormula(
                            $overtimeFormula,
                            $results,
                            [
                                'basic_salary' => (float) $employee->salary,
                                'overtime_hours' => (float) $employee->overtime_hours
                            ]
                        );
                    } else if ($component->code === 'special_overtime_pay') {
                        $amount = $this->evaluateFormula(
                            $specialOvertimeFormula,
                            $results,
                            [
                                'basic_salary' => (float) $employee->salary,
                                'special_overtime_hours' => (float) $employee->special_overtime_hours
                            ]
                        );
                    } else if ($component->code === 'sewing_insentif') {

                        $amount = 0;

                        // Operator & Supervisor
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
                            ->where('le.period_id', $period->id)
                            ->whereBetween('le.date', [$period->start_date, $period->end_date])
                            ->select('le.line_number', 'le.efficiency', 'le.date', 'ela.role')
                            ->get();

                        foreach ($lineefficiencies as $row) {
                            if (!in_array($row->role, ['operator', 'supervisor'])) continue;
                            $lineInsentif = $this->getInsentifByEfficiency($row->efficiency, $sewingInsentifFormula);
                            $amount += $this->calculateRoleSewingInsentif($row->role, $lineInsentif, 1);
                        }

                        // Chief / Mekanik / Mekanik Leader
                        $grouped = DB::table('line_efficiencies')
                            ->where('period_id', $period->id)
                            ->whereBetween('date', [$period->start_date, $period->end_date])
                            ->select('date', DB::raw('count(distinct line_number) as jumlah_line'))
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
                                ->where('period_id', $period->id)
                                ->where('date', $day->date)
                                ->get();

                            $totalLineInsentif = 0;
                            foreach ($lines as $line) {
                                $totalLineInsentif += $this->getInsentifByEfficiency($line->efficiency, $sewingInsentifFormula);
                            }

                            $amount += $this->calculateRoleSewingInsentif($assignment->role, $totalLineInsentif, $day->jumlah_line);
                        }
                    } else if ($component->code === 'pad_insentif') {

                        $assignments = DB::table('employee_pad_assignments')
                            ->where('npk', $employee->NPK)
                            ->where('period_id', $period->id)
                            ->where(function ($q) use ($period) {
                                $q->whereBetween('start_date', [$period->start_date, $period->end_date])
                                    ->orWhere(function ($q2) use ($period) {
                                        $q2->where('start_date', '<=', $period->end_date)
                                            ->where(function ($q3) use ($period) {
                                                $q3->whereNull('end_date')->orWhere('end_date', '>=', $period->start_date);
                                            });
                                    });
                            })
                            ->get();

                        $amount = 0;

                        foreach ($assignments as $assignment) {
                            $dept = $assignment->dept;
                            $role = $assignment->role;

                            $start = max($assignment->start_date, $period->start_date);
                            $end   = $assignment->end_date ? min($assignment->end_date, $period->end_date) : $period->end_date;

                            if ($role === 'operator') {
                                $padEfficiencies = DB::table('pad_efficiencies')
                                    ->where('npk', $employee->NPK)
                                    ->where('period_id', $period->id)
                                    ->whereBetween('date', [$start, $end])
                                    ->get();

                                foreach ($padEfficiencies as $row) {
                                    $rate = $this->getInsentifByEfficiency($row->efficiency, $padInsentifFormula);
                                    $amount += $rate * $row->piece;
                                }
                            } else {
                                $padEfficiencies = DB::table('pad_efficiencies as pe')
                                    ->join('employee_pad_assignments as epa', function ($join) {
                                        $join->on('pe.npk', '=', 'epa.npk')
                                            ->on('pe.dept', '=', 'epa.dept');
                                    })
                                    ->where('pe.period_id', $period->id)
                                    ->where('epa.period_id', $period->id)
                                    ->where('epa.role', 'operator')
                                    ->where('pe.dept', $dept)
                                    ->whereBetween('pe.date', [$start, $end])
                                    ->whereColumn('pe.date', '>=', 'epa.start_date')
                                    ->where(function ($q) {
                                        $q->whereColumn('pe.date', '<=', 'epa.end_date')
                                            ->orWhereNull('epa.end_date');
                                    })
                                    ->select('pe.efficiency', 'pe.piece')
                                    ->get();

                                $totalDeptInsentif = 0;
                                foreach ($padEfficiencies as $row) {
                                    $rate = $this->getInsentifByEfficiency($row->efficiency, $padInsentifFormula);
                                    $totalDeptInsentif += $rate * $row->piece;
                                }

                                $jumlahOperator = DB::table('employee_pad_assignments')
                                    ->where('dept', $dept)
                                    ->where('role', 'operator')
                                    ->where('period_id', $period->id)
                                    ->pluck('npk')
                                    ->unique()
                                    ->count();

                                $amount += $this->calculateRolePadInsentif($role, $totalDeptInsentif, $jumlahOperator);
                            }
                        }
                    } else if ($component->code === 'cutting_insentif') {

                        $cuttingEfficiencies = DB::table('cutting_efficiencies as ce')
                            ->join('employee_cutting_assignments as eca', function ($join) {
                                $join->on('ce.npk', '=', 'eca.npk')
                                    ->whereColumn('ce.date', '>=', 'eca.start_date')
                                    ->where(function ($q) {
                                        $q->whereColumn('ce.date', '<=', 'eca.end_date')
                                            ->orWhereNull('eca.end_date');
                                    });
                            })
                            ->where('ce.npk', $employee->NPK)
                            ->where('ce.period_id', $period->id)
                            ->where('eca.period_id', $period->id)
                            ->whereBetween('ce.date', [$period->start_date, $period->end_date])
                            ->select('ce.efficiency', 'eca.role')
                            ->get();

                        $amount = 0;
                        foreach ($cuttingEfficiencies as $row) {
                            $insentif = $this->getInsentifByEfficiency($row->efficiency, $cuttingInsentifFormula);
                            $amount += $this->calculateRoleCuttingInsentif($row->role, $insentif);
                        }
                    } else {
                        $amount = $this->evaluateFormula($component->formula, $results, $inputVariables);
                    }
                }

                // 🔹 PERBAIKAN: bulatkan setiap komponen
                $amount = round((float) $amount, 0);
                $results[$component->code] = $amount;

                if ($component->type === 'earning') {
                    $grandTotal += $amount;
                } else {
                    $grandTotal -= $amount;
                }
            }

            $grandTotal = round($grandTotal, 0);

            PayrollRunDetail::create([
                'run_id'        => $run->id,
                'employee_npk'  => $employee->NPK,
                'employee_name' => $employee->NAMA_KARYAWAN,
                'components'    => $results,
                'total_salary'  => $grandTotal
            ]);

            $totalPayroll += $grandTotal;
        }

        $run->update([
            'employee_count' => $employees->count(),
            'total_payroll'  => round($totalPayroll, 0)
        ]);

        // ==============================
        // CREATE APPROVAL PAYROLL
        // ==============================

        $existsApprove = PayrollApprove::where('payroll_run_id', $run->id)->exists();

        if (!$existsApprove) {
            $settings = PayrollSetting::where('component', 'payroll')->get();

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

                PayrollApprove::create([
                    'payroll_run_id' => $run->id,
                    'approval'       => $approvals,
                    'progress'       => $progress,
                    'approved_at'    => [],
                    'status'         => 'pending'
                ]);
            }
        }

        Alert::success('Payroll generated successfully!');
        return redirect('payroll-process/index');
    }

    public function details($id)
    {
        $data = DB::table('payroll_run_details')
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

    public function destroy($period_id)
    {
        DB::beginTransaction();

        try {

            // ambil semua run id dari period
            $runIds = PayrollRun::where('id', $period_id)->pluck('id');
            if ($runIds->count() > 0) {

                // hapus detail payroll
                PayrollRunDetail::whereIn('run_id', $runIds)->delete();

                // hapus run payroll
                PayrollRun::whereIn('id', $runIds)->delete();
            }

            DB::commit();

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

        $export = PayrollExport::create([
            'run_id' => $run_id,
            'status' => 'processing',
            'progress' => 0
        ]);

        GeneratePayrollExport::dispatch($export->id);

        Alert::success('Sukses', 'Export payroll selesai diproses!');
        return redirect('payroll-process/index');
        // return response()->json([
        //     'message' => 'Export started',
        //     'export_id' => $export->id
        // ]);
    }

    public function progress($id)
    {

        $export = PayrollExport::findOrFail($id);

        return response()->json([
            'progress' => $export->progress,
            'status' => $export->status
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
