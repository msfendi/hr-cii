<?php

namespace App\Http\Controllers;

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
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        $data = InsentifMaster::findOrFail($id);
        $data->delete();

        return redirect()->route('line-insentif-master.index')
            ->with('success', 'Data berhasil dihapus');
    }

    public function template()
    {
        return Excel::download(new LineInsentifTemplateExport, 'template_line_insentif_master.xlsx');
    }

    public function import(Request $request)
    {
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

        return back()->with('success', 'Process berhasil dijalankan');
    }

    public function check($period_id)
    {
        $period = PayrollPeriod::findOrFail($period_id);

        $periodStart = $period->start_date;
        $periodEnd   = $period->end_date;


        /*
        |--------------------------------------------------------------------------
        | AMBIL NPK YANG BENAR-BENAR ADA ASSIGNMENT
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

        // dd($assignmentNpk->toArray());


        /*
    |--------------------------------------------------------------------------
    | EMPLOYEE SOURCE (TETAP SAMA LOGIC)
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
            ->join('dept_insentif_role as lir', 'emp.ID_DEPT', '=', 'lir.id_dept')
            ->join('insentif_role_formulas as irf', 'lir.role', '=', 'irf.id')

            ->whereIn('p.NPK', $assignmentNpk)
            // ->where('p.NPK', '=', 'C-01803')
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
                'emp.SECTION as SECTION'
            );

        $employees = DB::connection('cii')
            ->query()
            ->fromSub($employeeBase, 'emp')
            ->get();

        // dd($employees);


        /*
    |--------------------------------------------------------------------------
    | FORMULA (TETAP)
    |--------------------------------------------------------------------------
    */

        $sewingInsentifFormula = json_decode(
            PayrollComponent::where('code', 'sewing_insentif')
                ->value('formula'),
            true
        );


        /*
    |--------------------------------------------------------------------------
    | CALCULATION (LOGIC TIDAK DIUBAH)
    |--------------------------------------------------------------------------
    */

        $results = [];

        foreach ($employees as $employee) {

            // dd($employee->SECTION);
            $sewing = $this->calculateSewing(
                $employee,
                $period,
                $sewingInsentifFormula,
                $employee->role,
            );

            if ($sewing <= 0) continue;

            $results[] = [
                'npk' => $employee->NPK,
                'name' => $employee->NAMA_KARYAWAN,
                'sewing_insentif' => $sewing,
                'dept' => $employee->DEPARTEMENT,
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

            foreach ($lineefficiencies as $row) {

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
                    1 //karena hanya 1 line
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
                    $jumlahLine->first()->jumlah_line
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
}
