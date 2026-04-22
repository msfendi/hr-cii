<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InsentifMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PadInsentifTemplateExport;
use App\Imports\PadInsentifImport;
use App\Models\InsentifApproval;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;

class PadInsentifMasterController extends Controller
{
    public function index()
    {
        $data = DB::table('pad_efficiencies as l')
            ->join('employee_pad_assignments as e', function ($join) {
                $join->on('l.dept', '=', 'e.dept')
                    ->on('l.period_id', '=', 'e.period_id')
                    ->whereRaw('l.date BETWEEN e.start_date AND COALESCE(e.end_date, l.date)');
            })->join('BIODATA as b', function ($join) {
                $join->on('e.npk', '=', 'b.NPK');
            })->join('payroll_periods as pp', function ($join) {
                $join->on('e.period_id', '=', 'pp.id');
            })
            ->select(
                'e.id',
                'e.npk',
                'b.NAMA_KARYAWAN as name',
                'pp.name as period',
                'e.dept',
                'e.role',
                'l.efficiency',
                'l.date'
            )
            ->orderBy('e.npk')
            ->orderBy('l.date')
            ->get();
        $periods = PayrollPeriod::select('id', 'name')
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
        $data = InsentifMaster::findOrFail($id);
        $data->delete();

        return redirect()->route('pad-insentif-master.index')
            ->with('success', 'Data berhasil dihapus');
    }

    public function template()
    {
        return Excel::download(new PadInsentifTemplateExport, 'template_pad_insentif_master.xlsx');
    }

    public function import(Request $request)
    {
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

        return back()->with('success', 'Process berhasil dijalankan');
    }

    public function check($period_id)
    {
        $period   = PayrollPeriod::findOrFail($period_id);

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


        $padInsentifFormula = json_decode(
            PayrollComponent::where('code', 'pad_insentif')->value('formula'),
            true
        );


        $results = [];

        foreach ($employees as $employee) {
            $pad     = $this->calculatePad($employee, $period, $padInsentifFormula);

            if (($pad) <= 0) continue;

            $results[] = [
                'npk' => $employee->NPK,
                'name' => $employee->NAMA_KARYAWAN,
                'pad_insentif' => $pad,
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
}
