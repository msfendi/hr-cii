<?php

namespace App\Http\Controllers;

use App\Models\InsentifApproval;
use App\Models\InsentifRoleFormula;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            ->where('payroll_periods.is_closed', false)
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

        // SEWING
        $assignmentNpkSewing = DB::table('employee_line_assignments')
            ->where('period_id', $period->id)
            ->distinct()
            ->pluck('npk');

        $employeeBaseSewing = DB::connection('cii')
            ->table('PKWT as p')
            ->leftJoin('BIODATA as b', 'p.NPK', '=', 'b.NPK')
            ->leftJoin('BIODATA_KELUAR as bk', 'p.NPK', '=', 'bk.NPK')
            ->whereIn('p.NPK', $assignmentNpkSewing)
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })
            ->select(
                'p.NPK',
                DB::raw('COALESCE(b.NAMA_KARYAWAN,bk.NAMA_KARYAWAN) as NAMA_KARYAWAN'),
                'p.TMK'
            );

        $employeesSewing = DB::connection('cii')
            ->query()
            ->fromSub($employeeBaseSewing, 'emp')
            ->get();


        // CUTTING
        $assignmentNpkCutting = DB::table('employee_cutting_assignments')
            ->where('period_id', $period->id)
            ->distinct()
            ->pluck('npk');

        $employeeBaseCutting = DB::connection('cii')
            ->table('PKWT as p')
            ->leftJoin('BIODATA as b', 'p.NPK', '=', 'b.NPK')
            ->leftJoin('BIODATA_KELUAR as bk', 'p.NPK', '=', 'bk.NPK')
            ->whereIn('p.NPK', $assignmentNpkCutting)
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })
            ->select(
                'p.NPK',
                DB::raw('COALESCE(b.NAMA_KARYAWAN,bk.NAMA_KARYAWAN) as NAMA_KARYAWAN'),
                'p.TMK'
            );

        $employeesCutting = DB::connection('cii')
            ->query()
            ->fromSub($employeeBaseCutting, 'emp')
            ->get();


        // PAD PRINT
        $assignmentNpkPadPrint = DB::table('employee_pad_assignments')
            ->where('period_id', $period->id)
            ->distinct()
            ->pluck('npk');

        $employeeBasePadPrint = DB::connection('cii')
            ->table('PKWT as p')
            ->leftJoin('BIODATA as b', 'p.NPK', '=', 'b.NPK')
            ->leftJoin('BIODATA_KELUAR as bk', 'p.NPK', '=', 'bk.NPK')
            ->whereIn('p.NPK', $assignmentNpkPadPrint)
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })
            ->select(
                'p.NPK',
                DB::raw('COALESCE(b.NAMA_KARYAWAN,bk.NAMA_KARYAWAN) as NAMA_KARYAWAN'),
                'p.TMK'
            );

        $employeesPadPrint = DB::connection('cii')
            ->query()
            ->fromSub($employeeBasePadPrint, 'emp')
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

        /*
    |--------------------------------------------------------------------------
    | MERGE RESULT BY NPK
    |--------------------------------------------------------------------------
    */

        $results = [];

        /*
    | SEWING
    */
        foreach ($employeesSewing as $employee) {

            $sewing = $this->calculateSewing($employee, $period, $sewingInsentifFormula);
            if ($sewing <= 0) continue;

            $npk = $employee->NPK;

            if (!isset($results[$npk])) {
                $results[$npk] = [
                    'npk' => $npk,
                    'name' => $employee->NAMA_KARYAWAN,
                    'sewing_insentif' => 0,
                    'cutting_insentif' => 0,
                    'pad_insentif' => 0,
                    'total_insentif' => 0,
                ];
            }

            $results[$npk]['sewing_insentif'] += $sewing;
            $results[$npk]['total_insentif'] += $sewing;
        }


        /*
    | CUTTING
    */
        foreach ($employeesCutting as $employee) {

            $cutting = $this->calculateCutting($employee, $period, $cuttingInsentifFormula);
            if ($cutting <= 0) continue;

            $npk = $employee->NPK;

            if (!isset($results[$npk])) {
                $results[$npk] = [
                    'npk' => $npk,
                    'name' => $employee->NAMA_KARYAWAN,
                    'sewing_insentif' => 0,
                    'cutting_insentif' => 0,
                    'pad_insentif' => 0,
                    'total_insentif' => 0,
                ];
            }

            $results[$npk]['cutting_insentif'] += $cutting;
            $results[$npk]['total_insentif'] += $cutting;
        }


        /*
    | PAD PRINT
    */
        foreach ($employeesPadPrint as $employee) {

            $pad = $this->calculatePad($employee, $period, $padInsentifFormula);
            if ($pad <= 0) continue;

            $npk = $employee->NPK;

            if (!isset($results[$npk])) {
                $results[$npk] = [
                    'npk' => $npk,
                    'name' => $employee->NAMA_KARYAWAN,
                    'sewing_insentif' => 0,
                    'cutting_insentif' => 0,
                    'pad_insentif' => 0,
                    'total_insentif' => 0,
                ];
            }

            $results[$npk]['pad_insentif'] += $pad;
            $results[$npk]['total_insentif'] += $pad;
        }

        return response()->json(array_values($results));
    }


    /*
|--------------------------------------------------------------------------
| SEWING INSENTIF (COPY 1:1 PAYROLL)
|--------------------------------------------------------------------------
*/

    private function calculateSewing($employee, $period, $formula)
    {
        $amount = 0;

        /*
    |--------------------------------------------------------------------------
    | LOAD THRESHOLD
    |--------------------------------------------------------------------------
    */
        $thresholds = DB::table('insentif_thresholds')
            ->where('insentif_type', 'Sewing')
            ->where('type', 'Percentage')
            ->pluck('minimum', 'days');

        $getMinEfficiency = function ($dayIndex) use ($thresholds) {

            if (isset($thresholds[$dayIndex])) {
                return $thresholds[$dayIndex];
            }

            return $thresholds->max();
        };


        /*
    |--------------------------------------------------------------------------
    | LOAD OVERTIME (ONCE)
    |--------------------------------------------------------------------------
    */
        $overtimes = DB::table('overtimes')
            ->where('NPK', $employee->NPK)
            ->whereBetween('OVERTIME_DATE', [
                $period->start_date,
                $period->end_date
            ])
            ->get()
            ->keyBy(fn($o) => $o->OVERTIME_DATE);


        /*
    |--------------------------------------------------------------------------
    | FUNCTION VALIDATE OVERTIME
    |--------------------------------------------------------------------------
    */
        $isValidOvertime = function ($date) use ($overtimes) {

            if (!isset($overtimes[$date])) {
                return true; // tidak ada overtime → tetap hitung
            }

            $lembur = $overtimes[$date]->JUMLAH_JAM_LEMBUR;

            // NULL → tetap dihitung
            if ($lembur === null || $lembur === '') {
                return true;
            }

            // numeric → tetap dihitung
            if (is_numeric($lembur)) {
                return true;
            }

            // karakter (MA, CT, BR, S1, dll)
            return false;
        };


        /*
    |--------------------------------------------------------------------------
    | OPERATOR & SUPERVISOR
    |--------------------------------------------------------------------------
    */
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
                'ela.role',
                'ela.start_date'
            )
            ->orderBy('le.date')
            ->get();

        foreach ($lineefficiencies as $row) {

            if (!in_array($row->role, ['operator', 'supervisor'])) {
                continue;
            }

            /*
        |----------------------------------
        | CHECK OVERTIME
        |----------------------------------
        */
            if (!$isValidOvertime($row->date)) {
                continue;
            }

            /*
        |----------------------------------
        | DAY INDEX
        |----------------------------------
        */
            $dayIndex =
                \Carbon\Carbon::parse($row->start_date)
                ->diffInDays(\Carbon\Carbon::parse($row->date)) + 1;

            $minEfficiency = $getMinEfficiency($dayIndex);

            if ($row->efficiency < $minEfficiency) {
                continue;
            }

            $lineInsentif =
                $this->getInsentifByEfficiency($row->efficiency, $formula);

            $amount += $this->calculateRoleSewingInsentif(
                $row->role,
                'sewing',
                $lineInsentif,
                1
            );
        }


        /*
    |--------------------------------------------------------------------------
    | CHIEF / MEKANIK / MEKANIK LEADER
    |--------------------------------------------------------------------------
    */
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

            /*
        |----------------------------------
        | CHECK OVERTIME
        |----------------------------------
        */
            if (!$isValidOvertime($day->date)) {
                continue;
            }

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
            )) {
                continue;
            }

            $dayIndex =
                \Carbon\Carbon::parse($assignment->start_date)
                ->diffInDays(\Carbon\Carbon::parse($day->date)) + 1;

            $minEfficiency = $getMinEfficiency($dayIndex);

            $lines = DB::table('line_efficiencies')
                ->where('period_id', $period->id)
                ->where('date', $day->date)
                ->get();

            $totalLineInsentif = 0;

            foreach ($lines as $line) {

                if ($line->efficiency < $minEfficiency) {
                    continue;
                }

                $totalLineInsentif +=
                    $this->getInsentifByEfficiency($line->efficiency, $formula);
            }

            if ($totalLineInsentif <= 0) {
                continue;
            }

            $amount += $this->calculateRoleSewingInsentif(
                $assignment->role,
                'sewing',
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

        /*
    |--------------------------------------------------------------------------
    | VALIDATE OVERTIME
    |--------------------------------------------------------------------------
    */
        $isValidOvertime = function ($npk, $date) {

            $ot = DB::table('overtimes')
                ->where('NPK', $npk)
                ->where('OVERTIME_DATE', $date)
                ->first();

            if (!$ot) return true;

            $lembur = $ot->JUMLAH_JAM_LEMBUR;

            if ($lembur === null || $lembur === '') return true;

            if (is_numeric($lembur)) return true;

            return false; // MA / CT / BR / S1
        };

        /*
    |--------------------------------------------------------------------------
    | LOAD ASSIGNMENT
    |--------------------------------------------------------------------------
    */
        $assignments = DB::table('employee_pad_assignments')
            ->where('npk', $employee->NPK)
            ->where('period_id', $period->id)
            ->get();

        foreach ($assignments as $assignment) {

            $dept = $assignment->dept;
            $role = $assignment->role;

            $start = max($assignment->start_date, $period->start_date);
            $end = $assignment->end_date
                ? min($assignment->end_date, $period->end_date)
                : $period->end_date;

            /*
        |--------------------------------------------------------------------------
        | OPERATOR
        |--------------------------------------------------------------------------
        */
            if ($role === 'operator') {

                $rows = DB::table('pad_efficiencies')
                    ->where('npk', $employee->NPK)
                    ->where('period_id', $period->id)
                    ->whereBetween('date', [$start, $end])
                    ->get();

                foreach ($rows as $row) {

                    if (!$isValidOvertime($employee->NPK, $row->date)) {
                        continue;
                    }

                    $rate = $this->getInsentifByEfficiency(
                        $row->efficiency,
                        $formula
                    );

                    $amount += $rate * $row->piece;
                }
            }

            /*
        |--------------------------------------------------------------------------
        | NON OPERATOR (SPV / LEADER / HELPER)
        |--------------------------------------------------------------------------
        */ else {

                /*
            |----------------------------------
            | TOTAL DEPT INSENTIF
            | ONLY VALID OPERATOR
            |----------------------------------
            */
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
                    ->select('pe.npk', 'pe.efficiency', 'pe.piece', 'pe.date')
                    ->get();

                $totalDeptInsentif = 0;

                foreach ($rows as $row) {

                    // FILTER HANYA NUMERATOR
                    if (!$isValidOvertime($row->npk, $row->date)) {
                        continue;
                    }

                    $rate = $this->getInsentifByEfficiency(
                        $row->efficiency,
                        $formula
                    );

                    $totalDeptInsentif += $rate * $row->piece;
                }

                /*
            |----------------------------------
            | DENOMINATOR (ALL OPERATOR)
            |----------------------------------
            */
                $jumlahOperator = DB::table('employee_pad_assignments')
                    ->where('dept', $dept)
                    ->where('role', 'operator')
                    ->where('period_id', $period->id)
                    ->pluck('npk')
                    ->unique()
                    ->count();

                if ($jumlahOperator == 0) {
                    continue;
                }

                $amount += $this->calculateRolePadInsentif(
                    $role,
                    'pad',
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

        /*
    |--------------------------------------------------------------------------
    | LOAD OVERTIME (ONLY ONCE)
    |--------------------------------------------------------------------------
    */
        $overtimes = DB::table('overtimes')
            ->where('NPK', $employee->NPK)
            ->whereBetween('OVERTIME_DATE', [
                $period->start_date,
                $period->end_date
            ])
            ->get()
            ->keyBy(fn($o) => $o->OVERTIME_DATE);


        /*
    |--------------------------------------------------------------------------
    | VALIDATE OVERTIME
    |--------------------------------------------------------------------------
    */
        $isValidOvertime = function ($date) use ($overtimes) {

            // tidak ada record → tetap dihitung
            if (!isset($overtimes[$date])) {
                return true;
            }

            $lembur = $overtimes[$date]->JUMLAH_JAM_LEMBUR;

            // NULL / kosong → tetap dihitung
            if ($lembur === null || $lembur === '') {
                return true;
            }

            // angka → tetap dihitung
            if (is_numeric($lembur)) {
                return true;
            }

            // MA / CT / BR / S1 dll → skip
            return false;
        };


        /*
    |--------------------------------------------------------------------------
    | LOAD ASSIGNMENTS (NO JOIN)
    |--------------------------------------------------------------------------
    */
        $assignments = DB::table('employee_cutting_assignments')
            ->where('npk', $employee->NPK)
            ->where('period_id', $period->id)
            ->get();


        /*
    |--------------------------------------------------------------------------
    | LOAD CUTTING EFFICIENCY
    |--------------------------------------------------------------------------
    */
        $cuttingEfficiencies = DB::table('cutting_efficiencies')
            ->where('period_id', $period->id)
            ->whereBetween('date', [
                $period->start_date,
                $period->end_date
            ])
            ->get();


        /*
    |--------------------------------------------------------------------------
    | CALCULATE INSENTIF
    |--------------------------------------------------------------------------
    */
        foreach ($cuttingEfficiencies as $row) {

            /*
        |----------------------------------
        | CHECK OVERTIME
        |----------------------------------
        */
            if (!$isValidOvertime($row->date)) {
                continue;
            }

            /*
        |----------------------------------
        | FIND ACTIVE ASSIGNMENT BY DATE
        |----------------------------------
        */
            $assignment = $assignments->first(function ($a) use ($row) {

                if ($row->date < $a->start_date) {
                    return false;
                }

                if ($a->end_date && $row->date > $a->end_date) {
                    return false;
                }

                return true;
            });

            // tidak ada role di tanggal tersebut
            if (!$assignment) {
                continue;
            }

            /*
        |----------------------------------
        | GET INSENTIF BY EFFICIENCY
        |----------------------------------
        */
            $insentif = $this->getInsentifByEfficiency(
                $row->efficiency,
                $formula
            );

            /*
        |----------------------------------
        | ADD AMOUNT BASED ROLE
        |----------------------------------
        */
            $amount += $this->calculateRoleCuttingInsentif(
                $assignment->role,
                'cutting',
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
