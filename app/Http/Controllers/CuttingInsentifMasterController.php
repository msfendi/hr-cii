<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InsentifMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\InsentifMasterTemplateExport;
use App\Exports\InsentifTemplateExport;
use App\Exports\CuttingInsentifTemplateExport;
use App\Imports\InsentifImport;
use App\Imports\InsentifMasterImport;
use App\Imports\CuttingInsentifImport;
use App\Imports\PadInsentifImport;
use App\Models\InsentifApproval;
use App\Models\InsentifRoleFormula;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CuttingInsentifMasterController extends Controller
{
    public function index()
    {
        $data = DB::table('cutting_efficiencies as ce')

            /*
        |--------------------------------------------------------------------------
        | MATCH ASSIGNMENT BY DATE RANGE ONLY
        |--------------------------------------------------------------------------
        */
            ->join('employee_cutting_assignments as e', function ($join) {
                $join->on('ce.period_id', '=', 'e.period_id')
                    ->whereRaw('ce.date >= e.start_date')
                    ->whereRaw('(e.end_date IS NULL OR ce.date <= e.end_date)');
            })

            /*
        |--------------------------------------------------------------------------
        | BIODATA
        |--------------------------------------------------------------------------
        */
            ->join('BIODATA as b', function ($join) {
                $join->on('e.npk', '=', 'b.NPK');
            })

            /*
        |--------------------------------------------------------------------------
        | PERIOD
        |--------------------------------------------------------------------------
        */
            ->join('payroll_periods as pp', function ($join) {
                $join->on('ce.period_id', '=', 'pp.id');
            })

            ->select(
                'e.id',
                'e.npk',
                'b.NAMA_KARYAWAN as name',
                'pp.name as period',
                'e.role',
                'ce.efficiency',
                'ce.date'
            )
            ->where('pp.is_closed', 0)

            ->orderBy('e.npk')
            ->orderBy('ce.date')
            ->get();


        $periods = PayrollPeriod::select('id', 'name')
            ->where('is_closed', 0)
            ->orderBy('id', 'desc')
            ->get();

        return view('cutting_insentif_master.index', compact('data', 'periods'));
    }

    public function create()
    {
        return view('cutting_insentif_master.create');
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

        return redirect()->route('cutting-insentif-master.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = InsentifMaster::findOrFail($id);
        return view('cutting-insentif-master.edit', compact('data'));
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

        return redirect()->route('cutting-insentif-master.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $data = InsentifMaster::findOrFail($id);
        $data->delete();

        return redirect()->route('cutting-insentif-master.index')
            ->with('success', 'Data berhasil dihapus');
    }

    public function template()
    {
        return Excel::download(new CuttingInsentifTemplateExport, 'template_cutting_insentif_master.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'period_id'   => 'required|exists:payroll_periods,id',
            'is_insentif' => 'required|in:0,1',
            'file'        => 'required_if:is_insentif,1|mimes:xlsx,xls'
        ]);

        $component = 'cutting_insentif';

        /*
    =====================================
    JIKA INSENTIF → IMPORT EXCEL
    =====================================
    */
        if ($request->is_insentif == 1) {

            Excel::import(
                new CuttingInsentifImport($request->period_id),
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

        $assignmentNpk = DB::table('employee_cutting_assignments')
            ->where('period_id', $period_id)
            ->distinct()
            ->pluck('npk');


        /*
    |--------------------------------------------------------------------------
    | EMPLOYEE SOURCE (TETAP SAMA LOGIC)
    |--------------------------------------------------------------------------
    */

        $employeeBase = DB::connection('cii')
            ->table('PKWT as p')
            ->leftJoin('BIODATA as b', 'p.NPK', '=', 'b.NPK')
            ->leftJoin('BIODATA_KELUAR as bk', 'p.NPK', '=', 'bk.NPK')
            ->whereIn('p.NPK', $assignmentNpk) // 🔥 FILTER CEPAT
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
    | FORMULA (TETAP)
    |--------------------------------------------------------------------------
    */

        $cuttingInsentifFormula = json_decode(
            PayrollComponent::where('code', 'cutting_insentif')
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

            $cutting = $this->calculateCutting(
                $employee,
                $period,
                $cuttingInsentifFormula
            );

            if ($cutting <= 0) continue;

            $results[] = [
                'npk' => $employee->NPK,
                'name' => $employee->NAMA_KARYAWAN,
                'cutting_insentif' => $cutting,
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
