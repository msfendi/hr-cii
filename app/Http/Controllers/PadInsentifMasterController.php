<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use Illuminate\Http\Request;
use App\Models\InsentifMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PadInsentifTemplateExport;
use App\Imports\PadInsentifImport;
use App\Models\InsentifApproval;
use App\Models\InsentifRoleFormula;
use App\Models\PadEfficiency;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class PadInsentifMasterController extends Controller
{
    public function index()
    {
        $data = DB::table('pad_efficiencies as p')
            ->join('BIODATA as b', function ($join) {
                $join->on('p.npk', '=', 'b.NPK');
            })
            ->join('DEPT as d', function ($join) {
                $join->on('b.ID_DEPT', '=', 'd.ID_DEPT');
            })->join('payroll_periods as pp', function ($join) {
                $join->on('p.period_id', '=', 'pp.id');
            })
            ->select(
                'p.id',
                'b.npk',
                'b.NAMA_KARYAWAN as name',
                'pp.name as period',
                'd.DEPARTEMENT as dept',
                'p.efficiency',
                'p.piece',
                'p.date'
            )
            ->where('pp.is_closed', 0)
            ->orderBy('p.npk')
            ->orderBy('p.date')
            ->get();
        $periods = PayrollPeriod::select('id', 'name')
            ->where('is_closed', 0)
            ->orderBy('id', 'desc')
            ->get();
        // dd($data);
        return view('pad_insentif_master.index', compact('data', 'periods'));
    }

    public function create()
    {
        return view('pad_insentif_master.create');
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

        return redirect()->route('pad-insentif-master.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = InsentifMaster::findOrFail($id);
        return view('pad-insentif-master.edit', compact('data'));
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

        return redirect()->route('pad-insentif-master.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $data = PadEfficiency::findOrFail($id);
        $data->delete();

        event(new NotificationEvent(
            'Pad Print Insentif!',
            'User : ' . $user->name . ' has deleted Pad Print Insentif!',
            'danger'
        ));

        Alert::success('Deleted Successfully!', 'Pad Print Insentif succesfully deleted!');

        return redirect()->route('pad-insentif-master.index')
            ->with('success', 'Data berhasil dihapus');
    }

    public function template()
    {
        return Excel::download(new PadInsentifTemplateExport, 'template_pad_insentif_master.xlsx');
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

        $component = 'pad_insentif';

        /*
    =====================================
    JIKA INSENTIF → IMPORT EXCEL
    =====================================
    */
        PadEfficiency::where('period_id', $period->id)->delete();
        if ($request->is_insentif == 1) {

            Excel::import(
                new PadInsentifImport($request->period_id),
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
            'Pad Print Insentif!',
            'User : ' . $user->name . ' has imported Pad Print Insentif ' . $period->name . '!',
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
        SELECT NPK, ID_DEPT FROM BIODATA
        UNION ALL
        SELECT NPK, ID_DEPT FROM BIODATA_KELUAR
    ) emp
"))
            ->join('dept_insentif_role as lir', 'emp.ID_DEPT', '=', 'lir.id_dept')
            ->join('insentif_role_formulas as irf', 'lir.role', '=', 'irf.id')
            ->where('irf.dept', 'pad')
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
            ->get();

        // dd($employees);


        /*
    |--------------------------------------------------------------------------
    | FORMULA (TETAP)
    |--------------------------------------------------------------------------
    */

        $padInsentifFormula = json_decode(
            PayrollComponent::where('code', 'pad_insentif')
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

            $pad = $this->calculatePad(
                $employee,
                $period,
                $padInsentifFormula,
                $employee->role,
            );

            if ($pad <= 0) continue;

            $results[] = [
                'npk' => $employee->NPK,
                'name' => $employee->NAMA_KARYAWAN,
                'pad_insentif' => $pad,
                'dept' => $employee->DEPARTEMENT,
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

    private function calculatePad($employee, $period, $formula, $role)
    {
        $amount = 0;

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
        $assignments = DB::table('pad_efficiencies')
            ->where('npk', $employee->NPK)
            ->where('period_id', $period->id)
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->get();

        // dd($assignments);


        /*
            |--------------------------------------------------------------------------
            | OPERATOR
            |--------------------------------------------------------------------------
            */
        if ($role === 'operator') {
            foreach ($assignments as $assignment) {

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

            // dd($employee);

            /*
            |----------------------------------
            | TOTAL DEPT INSENTIF
            | ONLY VALID OPERATOR
            |----------------------------------
            */
            $totalDeptInsentif = 0;


            $operators = DB::table('pad_efficiencies')
                ->where('period_id', $period->id)
                ->whereBetween('date', [$period->start_date, $period->end_date])
                ->get();

            // dd($operators);


            foreach ($operators as $operator) {
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
            $jumlahOperator = DB::table('pad_efficiencies as pe')
                ->where('pe.period_id', $period->id)
                ->pluck('pe.npk')
                ->unique()
                ->count();

            $amount += $this->calculateRolePadInsentif(
                $role,
                'pad',
                $totalDeptInsentif,
                $jumlahOperator
            );
        }


        return $amount;
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
}
