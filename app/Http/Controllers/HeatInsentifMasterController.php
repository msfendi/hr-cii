<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use Illuminate\Http\Request;
use App\Models\InsentifMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\HeatInsentifTemplateExport;
use App\Imports\HeatInsentifImport;
use App\Models\HeatEfficiency;
use App\Models\InsentifApproval;
use App\Models\InsentifRoleFormula;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class HeatInsentifMasterController extends Controller
{
    public function index()
    {
        $data = DB::table('heat_efficiencies as h')
            ->join('payroll_periods as pp', 'h.period_id', '=', 'pp.id')
            ->leftJoin('BIODATA as b', 'b.NPK', '=', 'h.npk')
            ->select(
                'h.id',
                'h.npk',
                'b.NAMA_KARYAWAN as name',
                'h.role',
                'pp.name as period',
                'h.efficiency',
                'h.piece',
                'h.date'
            )
            ->where('pp.is_closed', 0)
            ->orderBy('h.date')
            ->get();
        $periods = PayrollPeriod::select('id', 'name')
            ->where('is_closed', 0)
            ->orderBy('id', 'desc')
            ->get();
        // dd($data);
        return view('heat_insentif_master.index', compact('data', 'periods'));
    }

    public function getData($period)
    {
        $data = DB::table('heat_efficiencies as h')
            ->join('payroll_periods as pp', 'h.period_id', '=', 'pp.id')
            ->leftJoin('BIODATA as b', 'b.NPK', '=', 'h.npk')
            ->select(
                'h.id',
                'h.npk',
                'b.NAMA_KARYAWAN as name',
                'h.role',
                'pp.name as period',
                'h.efficiency',
                'h.piece',
                'h.date'
            )
            ->where('h.period_id', $period)
            ->orderBy('h.date')
            ->get();

        return response()->json($data);
    }

    public function create()
    {
        return view('heat_insentif_master.create');
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

        return redirect()->route('heat-insentif-master.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = InsentifMaster::findOrFail($id);
        return view('heat-insentif-master.edit', compact('data'));
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

        return redirect()->route('heat-insentif-master.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $data = HeatEfficiency::findOrFail($id);
        $data->delete();

        event(new NotificationEvent(
            'Heat Seal Insentif!',
            'User : ' . $user->name . ' has deleted Heat Seal Insentif!',
            'danger'
        ));

        Alert::success('Deleted Successfully!', 'Heat Seal Insentif succesfully deleted!');

        return redirect()->route('heat-insentif-master.index')
            ->with('success', 'Data berhasil dihapus');
    }

    public function template()
    {
        return Excel::download(new HeatInsentifTemplateExport, 'template_heat_insentif_master.xlsx');
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

        $component = 'heat_insentif';

        /*
    =====================================
    JIKA INSENTIF → IMPORT EXCEL
    =====================================
    */
        // HeatEfficiency::where('period_id', $period->id)->delete();
        if ($request->is_insentif == 1) {

            Excel::import(
                new HeatInsentifImport($request->period_id),
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
            'Heat Seal Insentif!',
            'User : ' . $user->name . ' has imported Heat Seal Insentif ' . $period->name . '!',
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
        | AMBIL NPK YANG BENAR-BENAR ADA ASSIGNMENT
        |--------------------------------------------------------------------------
        */

        $assignmentNpk = DB::table(DB::raw("
        (
            SELECT * FROM heat_efficiencies
        ) he
    "))->where('he.period_id', $period->id);

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

            ->leftJoinSub($assignmentNpk, 'anpk', function ($join) {
                $join->on('p.NPK', '=', 'anpk.npk');
            })
            ->leftJoin('DEPT as d', 'emp.ID_DEPT', '=', 'd.ID_DEPT')
            ->joinSub(
                DB::table('insentif_role_formulas')
                    ->select('role')
                    ->distinct(),
                'irf',
                function ($join) {
                    $join->on('anpk.role', '=', 'irf.role');
                }
            )
            // ->where('p.NPK', '=', 'C-00795')
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
            ->distinct()
            ->get();

        // dd($employeeBase->get(), $employees);


        /*
    |--------------------------------------------------------------------------
    | FORMULA (TETAP)
    |--------------------------------------------------------------------------
    */

        $heatInsentifFormula = json_decode(
            PayrollComponent::where('code', 'heat_insentif')
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
            $status = $employee->tkk ? 'Resign' : 'Active';

            $heat = $this->calculateHeat(
                $employee,
                $period,
                $heatInsentifFormula,
                $employee->role,
            );

            // if ($heat <= 0) continue;

            $results[] = [
                'npk' => $employee->NPK,
                'name' => $employee->NAMA_KARYAWAN,
                'heat_insentif' => $heat,
                'dept' => $employee->DEPARTEMENT,
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

    /*
    |--------------------------------------------------------------------------
    | PAD PRINT INSENTIF (COPY PAYROLL)
    |--------------------------------------------------------------------------
    */

    private function calculateHeat($employee, $period, $formula, $role)
    {
        $amount = 0;

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
        | GET MUTATIONS EMPLOYEE
        |--------------------------------------------------------------------------
        */
        $mutations = DB::table('employee_mutations')
            ->leftJoin('DEPT as d', 'employee_mutations.to_dept', '=', 'd.ID_DEPT')
            ->where('npk', $employee->NPK)
            ->orderBy('date')
            ->get();

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
    | LOAD ASSIGNMENT
    |--------------------------------------------------------------------------
    */
        $query = DB::table('heat_efficiencies')
            ->where('npk', $employee->NPK)
            ->where('period_id', $period->id)
            ->whereBetween('date', [$period->start_date, $period->end_date]);

        $isOperator = (clone $query)->value('role') === 'operator';

        $assignments = $isOperator
            ? $query->get()
            : $query->limit(1)->get();

        // dd($assignments);


        /*
            |--------------------------------------------------------------------------
            | OPERATOR
            |--------------------------------------------------------------------------
            */
        if ($role === 'operator') {
            foreach ($assignments as $assignment) {

                /*
                |--------------------------------------------------------------------------
                | CHECK RESIGN (NEW)
                |--------------------------------------------------------------------------
                */
                if ($tkkDate && $assignment->date >= $tkkDate) {
                    continue;
                }

                // dd($rows);

                if (!$isValidOvertime($assignment->npk, $assignment->date)) {
                    continue;
                }

                $rate = $this->getInsentifByEfficiency(
                    $assignment->efficiency,
                    $formula
                );

                $amount += $rate * $assignment->piece;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | NON OPERATOR (SPV / LEADER / HELPER)
        |--------------------------------------------------------------------------
        */ else {
            $employeeDates = DB::table('heat_efficiencies')
                ->where('period_id', $period->id)
                ->where('npk', $employee->NPK)
                ->pluck('date')
                ->unique()
                ->toArray();
            // dd($employee);

            /*
            |----------------------------------
            | TOTAL DEPT INSENTIF
            | ONLY VALID OPERATOR
            |----------------------------------
            */
            $totalDeptInsentif = 0;


            $operators = DB::table('heat_efficiencies')
                ->where('period_id', $period->id)
                ->where('role', '=', 'operator')
                ->whereBetween('date', [$period->start_date, $period->end_date])
                ->whereIn('date', $employeeDates)
                ->get();

            // dd($operators);


            foreach ($operators as $operator) {
                if ($tkkDate && $operator->date >= $tkkDate) {
                    continue;
                }
                // FILTER HANYA NUMERATOR
                if (!$isValidOvertime($operator->npk, $operator->date)) {
                    continue;
                }

                $rate = $this->getInsentifByEfficiency(
                    $operator->efficiency,
                    $formula
                );

                $totalDeptInsentif += $rate * $operator->piece;

                // dd($totalDeptInsentif);
            }

            /*
                |----------------------------------
                | DENOMINATOR (ALL OPERATOR)
                |----------------------------------
                */
            $jumlahOperator = DB::table('heat_efficiencies as he')
                ->where('he.period_id', $period->id)
                ->whereIn('he.date', $employeeDates)
                ->where('he.role', '=', 'operator')
                ->pluck('he.npk')
                ->unique()
                ->count();

            $amount += $this->calculateRoleHeatInsentif(
                $role,
                'heat',
                $totalDeptInsentif,
                $jumlahOperator
            );
        }


        return $amount;
    }


    private function calculateRoleHeatInsentif(
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
}
