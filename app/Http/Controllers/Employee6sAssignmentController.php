<?php

namespace App\Http\Controllers;

use App\Models\Employee6sAssignment;
use App\Models\PayrollPeriod;
use App\Models\PayrollComponent;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use App\Models\InsentifApproval;

class Employee6sAssignmentController extends Controller
{
    public function index()
    {
        $sixsInsentifComponent = PayrollComponent::where('code', 'sixs_insentif')->first();
        $sixsInsentifFormula = $sixsInsentifComponent->formula;

        $periods = PayrollPeriod::select('id', 'name')
            ->where('is_closed', 0)
            ->orderBy('id', 'desc')
            ->get();

        $data = DB::connection('cii')
            ->table('employee_6s_assignments as esa')
            ->leftJoin('payroll_periods as pp', 'esa.period_id', '=', 'pp.id')
            ->where('pp.is_closed', 0)
            ->get();
        // dd($data);

        return view(
            '6s_insentif.index',
            compact(
                'data',
                'periods',
                'sixsInsentifFormula'
            )
        );
    }

    public function create()
    {
        $periods = PayrollPeriod::orderBy('name')->get();
        $employees = DB::table('BIODATA')->select('*')->get();
        $sections = Section::orderBy('id')->get();

        return view('6s_insentif.create', compact('periods', 'employees', 'sections'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'period_id'       => 'required|exists:payroll_periods,id',
            'npk'             => 'required',
            'section_id'      => 'required',
            'inspector'       => 'required',
            'inspection_date' => 'required|date',
            'total_score'     => 'required|numeric',
            'percentage'      => 'required|numeric',
            'file' => [
                'nullable',
                'file',
                'mimes:xls,xlsx,pdf',
                'max:10240', // 10 MB
            ],
        ]);

        $filePath = null;

        if ($request->hasFile('file')) {

            $file = $request->file('file');

            $filename = $request->npk . '_' . $request->inspection_date . '_' . $file->getClientOriginalName();

            $filePath = $file->storeAs(
                '6s_insentif',
                $filename,
                'public'
            );
        }

        Employee6sAssignment::create([
            'period_id'       => $request->period_id,
            'npk'             => $request->npk,
            'section_id'      => $request->section_id,
            'inspector'       => $request->inspector,
            'inspection_date' => $request->inspection_date,
            'total_score'     => $request->total_score,
            'percentage'      => $request->percentage,
            'file_path'       => $filePath,
        ]);

        /*
        =====================================
        GET APPROVAL SETTING
        =====================================
        */

        $component = 'sixs_insentif';

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

        Alert::success('Success', '6S Insentif successfully created!');
        return redirect()
            ->route('employee6s.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = Employee6sAssignment::findOrFail($id);

        $periods = PayrollPeriod::orderBy('name')->get();
        $employees = DB::table('BIODATA')->select('*')->get();
        $sections = Section::orderBy('id')->get();

        return view('6s_insentif.edit', compact('data', 'periods', 'employees', 'sections'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'period_id'       => 'required|exists:payroll_periods,id',
            'npk'             => 'required',
            'section_id'      => 'required',
            'inspector'       => 'required',
            'inspection_date' => 'required|date',
            'total_score'     => 'required|numeric',
            'percentage'      => 'required|numeric',
        ]);

        $data = Employee6sAssignment::findOrFail($id);

        $data->update($request->all());

        Alert::success('Success', '6S Insentif successfully updated!');
        return redirect()
            ->route('employee6s.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        Employee6sAssignment::findOrFail($id)->delete();

        return redirect()
            ->route('employee6s.index')
            ->with('success', 'Data berhasil dihapus');
    }

    public function check($period_id)
    {
        $period = PayrollPeriod::findOrFail($period_id);

        $periodStart = $period->start_date;
        $periodEnd   = $period->end_date;

        $assignmentNpk = DB::table('employee_6s_assignments')
            ->where('period_id', $period->id);

        $employeeBase = DB::connection('cii')
            ->table(DB::raw("(
        SELECT *
        FROM employee_6s_assignments
        WHERE period_id = {$period->id}
    ) anpk"))

            ->join('PKWT as p', 'p.NPK', '=', 'anpk.npk')

            ->join(DB::raw("
        (
            SELECT NPK, NAMA_KARYAWAN, ID_DEPT, SECTION FROM BIODATA
            UNION ALL
            SELECT NPK, NAMA_KARYAWAN, ID_DEPT, SECTION FROM BIODATA_KELUAR
        ) emp
    "), 'p.NPK', '=', 'emp.NPK')

            ->leftJoin('DEPT as d', 'emp.ID_DEPT', '=', 'd.ID_DEPT')

            ->leftJoin('sections as s', function ($join) {
                $join->on(DB::raw('TRY_CAST(emp.SECTION AS BIGINT)'), '=', 's.id');
            })

            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })

            ->select(
                'p.NPK',
                'emp.NAMA_KARYAWAN',
                'anpk.percentage',
                'p.TMK',
                'p.TKK as tkk',
                'emp.ID_DEPT',
                'd.DEPARTEMENT as DEPARTEMENT',
                'emp.SECTION as SECTION'
            );

        $employees = DB::connection('cii')
            ->query()
            ->fromSub($employeeBase, 'emp')
            ->distinct()
            ->get();

        // dd($employees);

        // ❗ AMBIL FORMULA (JANGAN JSON DECODE)
        $sixsInsentifFormula = PayrollComponent::where('code', 'sixs_insentif')
            ->value('formula');

        $results = [];

        foreach ($employees as $employee) {

            $status = $employee->tkk ? 'Resign' : 'Active';

            // hitung insentif dari formula
            $sixs = $this->evaluateFormula($sixsInsentifFormula, $employee->percentage);

            $results[] = [
                'npk' => $employee->NPK,
                'name' => $employee->NAMA_KARYAWAN,
                'dept' => $employee->DEPARTEMENT, // ❗ fix ini
                'sixs_insentif' => $sixs,
                'tkk' => $employee->tkk,
                'status' => $status
            ];
        }

        return response()->json([
            'data' => $results
        ]);
    }

    private function evaluateFormula($formula, $percentage)
    {
        if (!$formula) {
            return 0;
        }

        // replace variable percentage
        $expr = str_replace('percentage', $percentage, $formula);

        // evaluate expression
        try {
            return eval("return ($expr);");
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
