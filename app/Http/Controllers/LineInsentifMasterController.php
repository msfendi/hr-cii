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
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;

class LineInsentifMasterController extends Controller
{
    public function index()
    {
        $data = DB::table('line_efficiencies as l')
            ->join('employee_line_assignments as e', function ($join) {
                $join->on('l.line_number', '=', 'e.line_number')
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
                'e.line_number',
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

        $sewingInsentifFormula = json_decode(
            PayrollComponent::where('code', 'sewing_insentif')->value('formula'),
            true
        );


        $results = [];

        foreach ($employees as $employee) {

            $sewing  = $this->calculateSewing($employee, $period, $sewingInsentifFormula);

            if (($sewing) <= 0) continue;

            $results[] = [
                'npk' => $employee->NPK,
                'name' => $employee->NAMA_KARYAWAN,
                'sewing_insentif' => $sewing,
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
}
