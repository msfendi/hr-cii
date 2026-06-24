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
        $biodataUnion = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            ->unionAll(
                DB::connection('cii')
                    ->table('BIODATA_KELUAR')
                    ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            );

        $data = DB::table('employee_line_assignments as ela')

            ->leftJoin('line_efficiencies as l', function ($join) {
                $join->on('ela.period_id', '=', 'l.period_id')
                    ->on('ela.line_number', '=', 'l.line_number')
                    ->whereNotNull('ela.line_number')
                    ->whereColumn('ela.start_date', 'l.date');
            })

            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('ela.NPK', '=', 'bio.NPK');
            })

            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'bio.ID_DEPT')

            ->join('payroll_periods as pp', 'l.period_id', '=', 'pp.id')
            ->select(
                'ela.id',
                'pp.name as period',
                'ela.npk',
                'bio.NAMA_KARYAWAN as nama',
                'd.DEPARTEMENT as dept',
                'l.efficiency',
                'l.line_number',
                'l.date'
            )
            ->where('pp.is_closed', 0)
            ->orderBy('l.date')
            ->get();

        // dd($data);

        $periods = PayrollPeriod::select('id', 'name')
            ->where('is_closed', 0)
            ->orderBy('id', 'desc')
            ->get();
        // dd($data);
        return view('line_insentif_master.index', compact('data', 'periods'));
    }

    public function getData($period)
    {
        $biodataUnion = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            ->unionAll(
                DB::connection('cii')
                    ->table('BIODATA_KELUAR')
                    ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            );

        $data = DB::table('employee_line_assignments as ela')

            ->leftJoin('line_efficiencies as l', function ($join) {
                $join->on('ela.period_id', '=', 'l.period_id')
                    ->on('ela.line_number', '=', 'l.line_number')
                    ->whereNotNull('ela.line_number')
                    ->whereColumn('ela.start_date', 'l.date');
            })

            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('ela.NPK', '=', 'bio.NPK');
            })

            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'bio.ID_DEPT')

            ->join('payroll_periods as pp', 'l.period_id', '=', 'pp.id')
            ->select(
                'ela.id',
                'pp.name as period',
                'ela.npk',
                'bio.NAMA_KARYAWAN as nama',
                'd.DEPARTEMENT as dept',
                'l.efficiency',
                'l.line_number',
                'l.date'
            )
            ->where('l.period_id', $period)
            ->orderBy('l.date')
            ->get();

        return response()->json($data);
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
        // LineEfficiency::where('period_id', $period->id)->delete();
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
            SELECT * FROM employee_line_assignments
        ) ela
    "))->where('ela.period_id', $period->id);

        // dd($assignmentNpk->get());

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

            ->leftJoinSub($assignmentNpk, 'anpk', function ($join) {
                $join->on('p.NPK', '=', 'anpk.npk');
            })
            ->leftJoin('DEPT as d', 'emp.ID_DEPT', '=', 'd.ID_DEPT')
            ->leftJoin('sections as s', function ($join) {
                $join->on(
                    DB::raw('TRY_CAST(emp.SECTION AS BIGINT)'),
                    '=',
                    's.id'
                );
            })
            ->joinSub(
                DB::table('insentif_role_formulas')
                    ->select('role')
                    ->distinct(),
                'irf',
                function ($join) {
                    $join->on('anpk.role', '=', 'irf.role');
                }
            )
            ->leftJoin('line_efficiencies as le', function ($join) {
                $join->on('le.period_id', '=', 'anpk.period_id')
                    ->on('le.line_number', '=', 'anpk.line_number')
                    ->on('le.date', '=', 'anpk.start_date');
            })
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })

            // ->where('emp.NPK', '=', 'C-00796')

            ->select(
                'p.NPK',
                'emp.NAMA_KARYAWAN',
                'anpk.role',
                'p.TMK',
                'p.TKK as tkk',
                'emp.ID_DEPT',
                'd.DEPARTEMENT as DEPARTEMENT',
                'emp.SECTION as SECTION',
                's.line_start',
                's.line_end'
            );

        $employees = DB::connection('cii')
            ->query()
            ->fromSub($employeeBase, 'emp')
            ->distinct()
            ->get();

        // dd($employees);

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
        // dd(
        //     $employees->groupBy('NPK')
        //         ->map(fn($x) => $x->count())
        //         ->filter(fn($x) => $x > 1)
        // );
        foreach ($employees as $employee) {

            $status = $employee->tkk ? 'Resign' : 'Active';

            $sewing = $this->calculateSewing(
                $employee,
                $period,
                $sewingInsentifFormula,
                $employee->role
            );

            // dd(
            //     $employee,
            //     $period,
            //     $sewingInsentifFormula,
            //     $employee->role
            // );

            // dd($sewing);

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
            $lineefficiencies = DB::table('employee_line_assignments as ela')
                ->leftJoin('line_efficiencies as le', function ($join) {
                    $join->on('le.period_id', '=', 'ela.period_id')
                        ->on('le.line_number', '=', 'ela.line_number')
                        ->on('le.date', '=', 'ela.start_date');
                })

                ->leftJoinSub(
                    DB::table('employee_line_assignments')
                        ->select(
                            'period_id',
                            'line_number',
                            'start_date',
                            DB::raw('MAX(work_hours) as max_work_hours')
                        )
                        ->groupBy(
                            'period_id',
                            'line_number',
                            'start_date'
                        ),
                    'max_wh',
                    function ($join) {
                        $join->on('max_wh.period_id', '=', 'ela.period_id')
                            ->on('max_wh.line_number', '=', 'ela.line_number')
                            ->on('max_wh.start_date', '=', 'ela.start_date');
                    }
                )

                ->where('ela.period_id', $period->id)
                ->where('ela.npk', $employee->NPK)
                ->whereBetween('le.date', [$period->start_date, $period->end_date])

                ->select(
                    'ela.npk',
                    'le.line_number',
                    'le.efficiency',
                    'le.date',

                    // work hours employee
                    'ela.work_hours',

                    // max work hours pada line & tanggal yang sama
                    'max_wh.max_work_hours'
                )

                ->orderBy('le.date')
                ->get();

            // dd($lineefficiencies);

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
                | CALCULATE INSENTIF
                |--------------------------------------------------------------------------
                */
                $lineInsentif =
                    $this->getInsentifByEfficiency($row->efficiency, $formula) * $row->work_hours / $row->max_work_hours;

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

            $grouped = DB::table('employee_line_assignments as ela')
                ->join('line_efficiencies as le', function ($join) {
                    $join->on('le.period_id', '=', 'ela.period_id')
                        ->on('le.date', '=', 'ela.start_date');
                })

                ->where('ela.npk', $employee->NPK)
                ->where('ela.period_id', $period->id)

                ->whereBetween('ela.start_date', [
                    $period->start_date,
                    $period->end_date
                ])

                ->whereBetween('le.line_number', [
                    $lineStart,
                    $lineEnd
                ])

                ->select(
                    // 'le.line_number',
                    'le.date'
                )

                ->groupBy(
                    'le.date',
                    // 'le.line_number'
                )

                ->orderBy('le.date')
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

            $collectionDay = collect([]);
            $collectionTotalLines = collect([]);
            $collectionLines = collect([]);

            $jumlahLine = DB::table('line_efficiencies')
                ->where('period_id', $period->id)
                ->whereBetween('date', [$period->start_date, $period->end_date])
                ->whereBetween('line_number', [$lineStart, $lineEnd])
                ->selectRaw('COUNT(DISTINCT line_number) as jumlah_line')
                ->get();

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
                    ->whereBetween('line_number', [$lineStart, $lineEnd])
                    ->get();

                $totalLineInsentif = 0;

                foreach ($lines as $line) {

                    $totalLineInsentif +=
                        $this->getInsentifByEfficiency($line->efficiency, $formula);

                    if ($totalLineInsentif <= 0) {
                        continue;
                    }

                    $collectionLines->push($totalLineInsentif);

                    // dd($grouped, $lines, $totalLineInsentif, $amount);
                }

                // dd($grouped, $collectionLines);

                $amount += $this->calculateRoleSewingInsentif(
                    $role,
                    'sewing',
                    $totalLineInsentif,
                    $jumlahLine->first()->jumlah_line,
                    $lineViolations
                );

                $collectionDay->push($amount);
                $collectionTotalLines->push($jumlahLine->first()->jumlah_line);
            }
            // dd($collectionDay->values()->toJson(), $collectionTotalLines->values()->toJson(), $collectionLines->values()->toJson());
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
