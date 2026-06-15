<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use Illuminate\Http\Request;
use App\Models\InsentifMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\InsentifMasterTemplateExport;
use App\Exports\InsentifTemplateExport;
use App\Exports\LineInsentifTemplateExport;
use App\Imports\InsentifImport;
use App\Imports\InsentifMasterImport;
use App\Imports\LineInsentifImport;
use App\Models\InsentifApproval;
use App\Models\InsentifRoleFormula;
use App\Models\LineEfficiency;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class LineInsentifMasterController extends Controller
{
    public function index()
    {
        $data = DB::table('line_efficiencies as l')
            ->join('payroll_periods as pp', function ($join) {
                $join->on('l.period_id', '=', 'pp.id');
            })
            ->select(
                'l.id',
                'pp.name as period',
                'l.efficiency',
                'l.line_number',
                'l.date'
            )
            ->where('pp.is_closed', 0)
            ->orderBy('l.date')
            ->get();

        $periods = PayrollPeriod::select('id', 'name')
            ->where('is_closed', 0)
            ->orderBy('id', 'desc')
            ->get();
        // dd($data);
        return view('line_insentif_master.index', compact('data', 'periods'));
    }

    public function create()
    {
        return view('line_insentif_master.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'type' => 'required',
            'efficiency' => 'nullable|numeric',
            'piece' => 'nullable|numeric',
        ]);

        InsentifMaster::create($request->all());

        return redirect()->route('line-insentif-master.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = InsentifMaster::findOrFail($id);
        return view('line-insentif-master.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'npk' => 'required',
            'type' => 'required',
            'efficiency' => 'nullable|numeric',
            'piece' => 'nullable|numeric',
        ]);

        $data = InsentifMaster::findOrFail($id);
        $data->update($request->all());

        return redirect()->route('line-insentif-master.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $data = LineEfficiency::findOrFail($id);
        $data->delete();

        event(new NotificationEvent(
            'Line Insentif!',
            'User : ' . $user->name . ' has deleted Line Insentif!',
            'danger'
        ));

        Alert::success('Deleted Successfully!', 'Line Insentif succesfully deleted!');

        return redirect()->route('line-insentif-master.index')
            ->with('success', 'Data berhasil dihapus');
    }

    public function template()
    {
        return Excel::download(new LineInsentifTemplateExport, 'template_line_insentif_master.xlsx');
    }

    public function import(Request $request)
    {

        $user = Auth::user();
        $period = PayrollPeriod::where('id', '=', $request->period_id)->first();
        $request->validate([
            'period_id'   => 'required|exists:payroll_periods,id',
            'is_insentif' => 'required|in:0,1',
            'file'        => 'required_if:is_insentif,1|mimes:xlsx,xls'
        ]);

        $component = 'sewing_insentif';

        /*
    =====================================
    JIKA INSENTIF → IMPORT EXCEL
    =====================================
    */
        LineEfficiency::where('period_id', $period->id)->delete();
        if ($request->is_insentif == 1) {

            Excel::import(
                new LineInsentifImport($request->period_id),
                $request->file('file')
            );
        }

        /*
    =====================================
    GET APPROVAL SETTING
    =====================================
    */

        $setting = DB::table('payroll_settings')
            ->where('component', $component)
            ->first();

        if ($setting) {

            $approvalArray = json_decode($setting->approval, true);

            $approval = [
                json_encode($approvalArray)
            ];

            $waitingStatus = array_fill(
                0,
                count($approvalArray),
                'waiting'
            );

            $progress = [
                [
                    'npk' => json_encode($approvalArray),
                    'status' => json_encode($waitingStatus),
                ]
            ];

            /*
        =====================================
        STATUS AUTO FINISH JIKA NO INSENTIF
        =====================================
        */

            // $status = $request->is_insentif == 1
            //     ? 'pending'
            //     : 'finish';

            InsentifApproval::updateOrCreate(
                [
                    'period_id' => $request->period_id,
                    'payroll_component' => $component
                ],
                [
                    'approval'     => $approval,
                    'progress'     => $progress,
                    'approved_at'  => null,
                    'status'       => 'pending'
                ]
            );
        }


        event(new NotificationEvent(
            'Line Insentif!',
            'User : ' . $user->name . ' has imported Line Insentif ' . $period->name . '!',
            'success'
        ));

        return back()->with('success', 'Process berhasil dijalankan');
    }

    public function check($period_id)
    {
        $period = PayrollPeriod::findOrFail($period_id);

        $periodStart = $period->start_date;
        $periodEnd   = $period->end_date;

        /*
    |--------------------------------------------------------------------------
    | AMBIL NPK YANG ADA ASSIGNMENT
    |--------------------------------------------------------------------------
    */

        $assignmentNpk = DB::table(DB::raw("
        (
            SELECT NPK, ID_DEPT FROM BIODATA
            UNION ALL
            SELECT NPK, ID_DEPT FROM BIODATA_KELUAR
        ) emp
    "))
            ->join('dept_insentif_role as lir', 'emp.ID_DEPT', '=', 'lir.id_dept')
            ->join('insentif_role_formulas as irf', 'lir.role', '=', 'irf.id')
            ->where('irf.dept', 'sewing')
            ->distinct()
            ->pluck('emp.NPK');

        /*
    |--------------------------------------------------------------------------
    | EMPLOYEE SOURCE
    |--------------------------------------------------------------------------
    */

        $employeeBase = DB::connection('cii')
            ->table('PKWT as p')

            ->join(DB::raw("
            (
                SELECT NPK, NAMA_KARYAWAN, ID_DEPT, SECTION FROM BIODATA
                UNION ALL
                SELECT NPK, NAMA_KARYAWAN, ID_DEPT, SECTION FROM BIODATA_KELUAR
            ) emp
        "), 'p.NPK', '=', 'emp.NPK')

            ->leftJoin('DEPT as d', 'emp.ID_DEPT', '=', 'd.ID_DEPT')
            ->leftJoin('sections as s', function ($join) {
                $join->on(
                    DB::raw('TRY_CAST(emp.SECTION AS BIGINT)'),
                    '=',
                    's.id'
                );
            })
            ->join('dept_insentif_role as lir', 'emp.ID_DEPT', '=', 'lir.id_dept')
            ->join('insentif_role_formulas as irf', 'lir.role', '=', 'irf.id')

            ->whereIn('p.NPK', $assignmentNpk)

            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })

            ->select(
                'p.NPK',
                'emp.NAMA_KARYAWAN',
                'p.TMK',
                'p.TKK as tkk',
                'emp.ID_DEPT',
                'd.DEPARTEMENT as DEPARTEMENT',
                'irf.role as role',
                'emp.SECTION as SECTION',
                's.line_start',
                's.line_end'
            );

        $employees = DB::connection('cii')
            ->query()
            ->fromSub($employeeBase, 'emp')
            ->get();

        /*
    |--------------------------------------------------------------------------
    | FORMULA
    |--------------------------------------------------------------------------
    */

        $sewingInsentifFormula = json_decode(
            PayrollComponent::where('code', 'sewing_insentif')->value('formula'),
            true
        );

        /*
    |--------------------------------------------------------------------------
    | CALCULATION
    |--------------------------------------------------------------------------
    */

        $results = [];

        foreach ($employees as $employee) {

            $status = $employee->tkk ? 'Resign' : 'Active';

            $sewing = $this->calculateSewing(
                $employee,
                $period,
                $sewingInsentifFormula,
                $employee->role
            );

            if ($sewing <= 0) continue;

            $dept = $employee->DEPARTEMENT;

            if ($employee->line_start !== null && $employee->line_end !== null) {
                $dept .= " ({$employee->line_start}-{$employee->line_end})";
            }

            $results[] = [
                'npk' => $employee->NPK,
                'name' => $employee->NAMA_KARYAWAN,
                'dept' => $dept,
                'sewing_insentif' => $sewing,
                'tkk' => $employee->tkk,
                'status' => $status
            ];
        }

        return response()->json([
            'data' => $results
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SEWING INSENTIF (COPY 1:1 PAYROLL)
    |--------------------------------------------------------------------------
    */

    private function calculateSewing($employee, $period, $formula, $role)
    {
        // dd($role);
        $amount = 0;

        $collectionLinesTest = collect([]);

        /*
        |--------------------------------------------------------------------------
        | TKK (RESIGN DATE)
        |--------------------------------------------------------------------------
        */
        $tkkDate = !empty($employee->tkk)
            ? Carbon::parse($employee->tkk)->format('Y-m-d')
            : null;


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
        | GET MUTATIONS EMPLOYEE
        |--------------------------------------------------------------------------
        */
        $mutations = DB::table('employee_mutations')
            ->leftJoin('DEPT as d', 'employee_mutations.to_dept', '=', 'd.ID_DEPT')
            ->where('npk', $employee->NPK)
            ->orderBy('date')
            ->get();

        // dd($mutations);


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
        | OPERATOR
        |--------------------------------------------------------------------------
        */
        $lineViolations = 0;
        if ($role == 'operator' || $role == 'supervisor') {

            /*
            |--------------------------------------------------------------------------
            | GET INITIAL LINE
            |--------------------------------------------------------------------------
            */
            preg_match('/\d+/', $employee->DEPARTEMENT, $matches);
            $defaultLine = $matches[0] ?? null;

            /*
            |--------------------------------------------------------------------------
            | GET ALL LINE EFFICIENCIES
            |--------------------------------------------------------------------------
            */
            $lineefficiencies = DB::table('line_efficiencies as le')
                ->where('le.period_id', $period->id)
                ->whereBetween('le.date', [$period->start_date, $period->end_date])
                ->select(
                    'le.line_number',
                    'le.efficiency',
                    'le.date'
                )
                ->orderBy('le.date')
                ->get();

            if (strtolower($role) == 'operator') {

                $lineViolations = DB::table('sewing_violations')
                    ->whereBetween('tanggal', [
                        $period->start_date,
                        $period->end_date
                    ])
                    ->where('id_dept', $employee->ID_DEPT)
                    ->count();
            } elseif (strtolower($role) == 'supervisor') {

                $leaderDept = DB::table('DEPT')
                    ->where('ID_DEPT', $employee->ID_DEPT)
                    ->value('DEPARTEMENT');

                $lineNumber = null;

                if (
                    preg_match('/LINE\s+(\d+)/i', $leaderDept, $matches)
                ) {
                    $lineNumber = $matches[1];
                }

                $lineDeptId = DB::table('DEPT')
                    ->where('DEPARTEMENT', 'LINE ' . $lineNumber)
                    ->value('ID_DEPT');

                $lineViolations = DB::table('sewing_violations')
                    ->whereBetween('tanggal', [
                        $period->start_date,
                        $period->end_date
                    ])
                    ->where('id_dept', $lineDeptId)
                    ->count();
            } else {

                $lineViolations = 0;
            }

            // dd($employee, $lineViolations);

            foreach ($lineefficiencies as $row) {

                /*
                |--------------------------------------------------------------------------
                | CHECK RESIGN (NEW)
                |--------------------------------------------------------------------------
                */
                if ($tkkDate && $row->date >= $tkkDate) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | CHECK OVERTIME
                |--------------------------------------------------------------------------
                */
                if (!$isValidOvertime($row->date)) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | DETERMINE EMPLOYEE LINE BY DATE
                |--------------------------------------------------------------------------
                */
                $employeeLine = $defaultLine;

                foreach ($mutations as $mutation) {
                    if ($mutation->date <= $row->date) {
                        preg_match('/\d+/', $mutation->DEPARTEMENT, $m);
                        // dd($employeeLine, $m[0]);
                        $employeeLine = $m[0] ?? $employeeLine;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | SKIP IF NOT EMPLOYEE LINE
                |--------------------------------------------------------------------------
                */

                $collectionLinesTest->push($row->date . '-' . $row->line_number . '-' . $employeeLine);
                // dd($row->line_number, $employeeLine);
                if ($row->line_number != $employeeLine) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | CALCULATE INSENTIF
                |--------------------------------------------------------------------------
                */
                $lineInsentif =
                    $this->getInsentifByEfficiency($row->efficiency, $formula);

                $amount += $this->calculateRoleSewingInsentif(
                    $role,
                    'sewing',
                    $lineInsentif,
                    1, //karena hanya 1 line
                    $lineViolations
                );
            }
        } else {

            /*
            |--------------------------------------------------------------------------
            | CHIEF / MEKANIK / MEKANIK LEADER
            |--------------------------------------------------------------------------
            */
            $validRoles = ['chief', 'mekanik', 'mekanik_leader'];

            if (!in_array($role, $validRoles)) {
                return $amount;
            }


            $section = DB::table('sections')
                ->whereRaw('id = ?', [(int) $employee->SECTION])
                ->select('line_start', 'line_end')
                ->first();

            // dd($employee->SECTION, $section);

            if (!$section) {
                return $amount;
            }

            $lineStart = $section->line_start;
            $lineEnd   = $section->line_end;

            $grouped = DB::table('line_efficiencies')
                ->where('period_id', $period->id)
                ->whereBetween('date', [$period->start_date, $period->end_date])
                ->whereBetween('line_number', [$lineStart, $lineEnd]) // ✅ FILTER SECTION
                ->select(
                    'date',
                )
                ->groupBy('date')
                ->get();

            // dd($grouped);


            $lineViolations = DB::table('sewing_violations')
                ->leftJoin('DEPT as d', 'sewing_violations.id_dept', '=', 'd.ID_DEPT')
                ->whereBetween('sewing_violations.tanggal', [
                    $period->start_date,
                    $period->end_date
                ])
                ->where('d.DEPARTEMENT', 'like', 'LINE %')
                ->whereRaw("
                        CAST(REPLACE(d.DEPARTEMENT,'LINE ','') AS INT)
                        BETWEEN ? AND ?
                    ", [$lineStart, $lineEnd])
                ->count();

            // dd($lineViolations);

            $jumlahLine = DB::table('line_efficiencies')
                ->where('period_id', $period->id)
                ->whereBetween('date', [$period->start_date, $period->end_date])
                ->whereBetween('line_number', [$lineStart, $lineEnd])
                ->selectRaw('COUNT(DISTINCT line_number) as jumlah_line')
                ->get();

            // dd($jumlahLine);

            $collectionDay = collect([]);
            $collectionLines = collect([]);
            foreach ($grouped as $day) {

                /*
                |--------------------------------------------------------------------------
                | CHECK RESIGN (NEW)
                |--------------------------------------------------------------------------
                */
                if ($tkkDate && $day->date >= $tkkDate) {
                    continue;
                }
                /*
                |----------------------------------
                | CHECK OVERTIME
                |----------------------------------
                */
                if (!$isValidOvertime($day->date)) {
                    continue;
                }

                $lines = DB::table('line_efficiencies')
                    ->where('period_id', $period->id)
                    ->where('date', $day->date)
                    ->get();

                $totalLineInsentif = 0;

                foreach ($lines as $line) {

                    $totalLineInsentif +=
                        $this->getInsentifByEfficiency($line->efficiency, $formula);

                    if ($totalLineInsentif <= 0) {
                        continue;
                    }

                    $collectionLines->push($totalLineInsentif);

                    // dd($grouped, $line, $totalLineInsentif, $day->jumlah_line, $amount);
                }

                // dd($collectionDay, $collectionLines);

                $amount += $this->calculateRoleSewingInsentif(
                    $role,
                    'sewing',
                    $totalLineInsentif,
                    $jumlahLine->first()->jumlah_line,
                    $lineViolations
                );

                $collectionDay->push($amount);
            }
            // dd($collectionDay->values()->toJson());
        }
        // dd($collectionLinesTest->values()->toJson());
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
        $jumlahLine,
        $violationsCount
    ) {

        $jumlahLine = max($jumlahLine, 1);
        // dd($violationsCount);

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
            'violationsCount'   => $violationsCount ?? 0
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
}
