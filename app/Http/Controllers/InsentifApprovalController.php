<?php

namespace App\Http\Controllers;

use App\Models\InsentifApproval;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InsentifApprovalController extends Controller
{
    public function index()
    {
        // =========================
        // JOIN PERIOD
        // =========================
        $data = InsentifApproval::query()
            ->join('payroll_periods', 'insentif_approvals.period_id', '=', 'payroll_periods.id')
            ->select(
                'insentif_approvals.*',
                'payroll_periods.name as period_name'
            )
            ->latest('insentif_approvals.id')
            ->get();

        // dd($data);

        // =========================
        // EMPLOYEE MASTER
        // =========================
        $employees = collect(DB::select("
        SELECT NPK, NAMA_KARYAWAN FROM BIODATA
        UNION
        SELECT NPK, NAMA_KARYAWAN FROM BIODATA_KELUAR
    "))->keyBy('NPK');

        // =========================
        // FORMAT PROGRESS USER
        // =========================
        $data = $data
            ->sortByDesc('id')
            ->values()
            ->transform(function ($row) use ($employees) {

                $progress = collect($row->progress)->map(function ($p) use ($employees) {

                    $npkList = is_array($p['npk'])
                        ? $p['npk']
                        : json_decode($p['npk'], true);

                    if (!is_array($npkList)) $npkList = [];

                    $p['users'] = collect($npkList)->map(function ($npk) use ($employees) {
                        return [
                            'npk' => $npk,
                            'name' => $employees[$npk]->NAMA_KARYAWAN ?? '-'
                        ];
                    });

                    return $p;
                });

                $row->progress = $progress;

                return $row;
            });

        // dd($data);

        return view('insentif_approve.index', compact('data'));
    }

    public function approve(Request $request, $id)
    {
        $data = InsentifApproval::findOrFail($id);
        $npkLogin = $request->npk;

        $progress = collect($data->progress);
        $approvedAt = collect($data->approved_at ?? []);

        // cari level approval aktif
        $currentIndex = $progress->search(function ($item) {
            return $item['status'] === 'pending'
                || str_contains($item['status'], 'waiting');
        });

        if ($currentIndex === false) {
            return response()->json(['message' => 'Semua sudah approve'], 400);
        }

        $row = $progress[$currentIndex];

        $npkList = is_array($row['npk'])
            ? $row['npk']
            : json_decode($row['npk'], true);

        if (!is_array($npkList)) {
            return response()->json(['message' => 'Format approver invalid'], 500);
        }

        // INIT STATUS
        if ($row['status'] === 'pending') {
            $statusList = array_fill(0, count($npkList), 'waiting');
        } else {
            $statusList = json_decode($row['status'], true);
        }

        $userIndex = array_search($npkLogin, $npkList);

        if ($userIndex === false) {
            return response()->json(['message' => 'Anda bukan approver'], 403);
        }

        if ($statusList[$userIndex] === 'approve') {
            return response()->json(['message' => 'Sudah approve'], 400);
        }

        // approve user
        $statusList[$userIndex] = 'approve';

        $approvedAtArr = $approvedAt->toArray();
        $approvedAtArr[$currentIndex][$userIndex] = now();

        $allApproved = collect($statusList)
            ->every(fn($s) => $s === 'approve');

        $progressArr = $progress->toArray();

        $progressArr[$currentIndex]['status'] =
            $allApproved
            ? 'approve'
            : json_encode($statusList);

        $progress = collect($progressArr);
        $approvedAt = collect($approvedAtArr);

        // FINAL CHECK
        $finalApprove = $progress
            ->every(fn($item) => $item['status'] === 'approve');

        $data->update([
            'progress' => $progress->values(),
            'approved_at' => $approvedAt->values(),
            'status' => $finalApprove ? 'finish' : 'pending'
        ]);

        return response()->json([
            'message' => 'Approval berhasil'
        ]);
    }



    /*
|--------------------------------------------------------------------------
| DETAIL INSENTIF (IDENTIK PAYROLL ENGINE)
|--------------------------------------------------------------------------
*/

    public function detail($id)
    {
        $approval = InsentifApproval::findOrFail($id);
        $period   = PayrollPeriod::findOrFail($approval->period_id);

        $periodStart = $period->start_date;
        $periodEnd   = $period->end_date;

        /*
    |--------------------------------------------------------------------------
    | EMPLOYEE SOURCE (COPY PAYROLL)
    |--------------------------------------------------------------------------
    */

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
                DB::raw('COALESCE(b.NAMA_KARYAWAN,bk.NAMA_KARYAWAN) as NAMA_KARYAWAN'),
                'p.TMK'
            );

        $employees = DB::connection('cii')
            ->query()
            ->fromSub($employeeBase, 'emp')
            ->get();


        /*
    |--------------------------------------------------------------------------
    | FORMULA (COPY PAYROLL)
    |--------------------------------------------------------------------------
    */

        $sewingInsentifFormula = json_decode(
            PayrollComponent::where('code', 'sewing_insentif')->value('formula'),
            true
        );

        $padInsentifFormula = json_decode(
            PayrollComponent::where('code', 'pad_insentif')->value('formula'),
            true
        );

        $cuttingInsentifFormula = json_decode(
            PayrollComponent::where('code', 'cutting_insentif')->value('formula'),
            true
        );


        $results = [];

        foreach ($employees as $employee) {

            $sewing  = $this->calculateSewing($employee, $period, $sewingInsentifFormula);
            $pad     = $this->calculatePad($employee, $period, $padInsentifFormula);
            $cutting = $this->calculateCutting($employee, $period, $cuttingInsentifFormula);

            if (($sewing + $pad + $cutting) <= 0) continue;

            $results[] = [
                'npk' => $employee->NPK,
                'name' => $employee->NAMA_KARYAWAN,
                'sewing_insentif' => $sewing,
                'pad_insentif' => $pad,
                'cutting_insentif' => $cutting,
                'total_insentif' => $sewing + $pad + $cutting
            ];
        }

        return response()->json($results);
    }


    /*
|--------------------------------------------------------------------------
| SEWING INSENTIF (COPY 1:1 PAYROLL)
|--------------------------------------------------------------------------
*/

    private function calculateSewing($employee, $period, $formula)
    {
        $amount = 0;

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
            ->select(
                'le.line_number',
                'le.efficiency',
                'le.date',
                'ela.role'
            )
            ->get();

        foreach ($lineefficiencies as $row) {

            if (!in_array($row->role, ['operator', 'supervisor'])) continue;

            $lineInsentif =
                $this->getInsentifByEfficiency($row->efficiency, $formula);

            $amount += $this->calculateRoleSewingInsentif(
                $row->role,
                $lineInsentif,
                1
            );
        }


        $grouped = DB::table('line_efficiencies')
            ->where('period_id', $period->id)
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

            if (!in_array(
                $assignment->role,
                ['chief', 'mekanik', 'mekanik_leader']
            )) continue;

            $lines = DB::table('line_efficiencies')
                ->where('period_id', $period->id)
                ->where('date', $day->date)
                ->get();

            $totalLineInsentif = 0;

            foreach ($lines as $line) {
                $totalLineInsentif +=
                    $this->getInsentifByEfficiency($line->efficiency, $formula);
            }

            $amount += $this->calculateRoleSewingInsentif(
                $assignment->role,
                $totalLineInsentif,
                $day->jumlah_line
            );
        }

        return $amount;
    }


    /*
|--------------------------------------------------------------------------
| PAD PRINT INSENTIF (COPY PAYROLL)
|--------------------------------------------------------------------------
*/

    private function calculatePad($employee, $period, $formula)
    {
        $amount = 0;

        $assignments = DB::table('employee_pad_assignments')
            ->where('npk', $employee->NPK)
            ->where('period_id', $period->id)
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

        foreach ($assignments as $assignment) {

            $dept = $assignment->dept;
            $role = $assignment->role;

            $start = max($assignment->start_date, $period->start_date);
            $end = $assignment->end_date
                ? min($assignment->end_date, $period->end_date)
                : $period->end_date;

            if ($role === 'operator') {

                $rows = DB::table('pad_efficiencies')
                    ->where('npk', $employee->NPK)
                    ->where('period_id', $period->id)
                    ->whereBetween('date', [$start, $end])
                    ->get();

                foreach ($rows as $row) {
                    $rate = $this->getInsentifByEfficiency(
                        $row->efficiency,
                        $formula
                    );
                    $amount += $rate * $row->piece;
                }
            } else {

                $rows = DB::table('pad_efficiencies as pe')
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

                foreach ($rows as $row) {
                    $rate = $this->getInsentifByEfficiency(
                        $row->efficiency,
                        $formula
                    );
                    $totalDeptInsentif += $rate * $row->piece;
                }

                $jumlahOperator = DB::table('employee_pad_assignments')
                    ->where('dept', $dept)
                    ->where('role', 'operator')
                    ->where('period_id', $period->id)
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

        return $amount;
    }


    /*
|--------------------------------------------------------------------------
| CUTTING INSENTIF (COPY PAYROLL)
|--------------------------------------------------------------------------
*/

    private function calculateCutting($employee, $period, $formula)
    {
        $amount = 0;

        $rows = DB::table('cutting_efficiencies as ce')
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

        foreach ($rows as $row) {

            $insentif = $this->getInsentifByEfficiency(
                $row->efficiency,
                $formula
            );

            $amount += $this->calculateRoleCuttingInsentif(
                $row->role,
                $insentif
            );
        }

        return $amount;
    }


    /*
|--------------------------------------------------------------------------
| ENGINE (COPY PAYROLL)
|--------------------------------------------------------------------------
*/

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

    private function calculateRoleSewingInsentif($role, $total, $line)
    {
        switch ($role) {
            case 'supervisor':
                return $total * 2;
            case 'chief':
                return ($total * $line) / 2;
            case 'mekanik':
                return ($total * $line) / 4;
            case 'mekanik_leader':
                return ($total * $line) * 0.15;
            default:
                return $total;
        }
    }

    private function calculateRolePadInsentif($role, $total, $operator)
    {
        $operator = max($operator, 1);

        switch ($role) {
            case 'supervisor':
            case 'leader':
            case 'inkmaking':
                return $total / $operator;

            case 'helper':
                return ($total / $operator) * 0.75;

            default:
                return $total;
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
