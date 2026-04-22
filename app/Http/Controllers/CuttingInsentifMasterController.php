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
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;

class CuttingInsentifMasterController extends Controller
{
    public function index()
    {
        $data = DB::table('cutting_efficiencies as l')
            ->join('employee_cutting_assignments as e', function ($join) {
                $join->on('l.npk', '=', 'e.npk')
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
        $cuttingInsentifFormula = json_decode(
            PayrollComponent::where('code', 'cutting_insentif')->value('formula'),
            true
        );


        $results = [];

        foreach ($employees as $employee) {
            $cutting = $this->calculateCutting($employee, $period, $cuttingInsentifFormula);

            if (($cutting) <= 0) continue;

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
