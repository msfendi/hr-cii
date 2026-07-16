<?php

namespace App\Http\Controllers;

use App\Models\EmployeeViolation;
use App\Models\PayrollPeriod;
use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeViolationController extends Controller
{
    public function index()
    {
        $data = EmployeeViolation::leftJoin('payroll_periods', 'employee_violations.period_id', '=', 'payroll_periods.id')
            ->leftJoin('biodata', 'employee_violations.npk', '=', 'biodata.NPK')
            ->leftJoin('DEPT', 'biodata.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->select('employee_violations.*', 'payroll_periods.name as name', 'biodata.NAMA_KARYAWAN as employee_name', 'DEPT.DEPARTEMENT as department')
            ->orderBy('employee_violations.id', 'desc')
            ->get();

        return view('employee_violation.index', compact('data'));
    }

    public function create()
    {
        $employees = DB::table('biodata')
            ->select(
                'NPK as npk',
                'NAMA_KARYAWAN as name',
                'ID_DEPT'
            )
            ->union(
                DB::table('biodata_keluar')
                    ->select(
                        'NPK as npk',
                        'NAMA_KARYAWAN as name',
                        'ID_DEPT'
                    )
            )->get();
        $periods = PayrollPeriod::orderBy('id', 'desc')->where('is_closed', '=', '0')->get();
        // dd($employees);

        return view('employee_violation.create', compact('periods', 'employees'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'period_id'  => 'required',
            'npk'        => 'required|string|max:50',
            'percentage' => 'required|numeric|min:0|max:100',
        ]);

        EmployeeViolation::updateOrCreate(
            [
                'period_id' => $request->period_id,
                'npk'       => $request->npk,
            ],
            [
                'percentage' => $request->percentage,
            ]
        );

        return redirect()
            ->route('employee-violation.index')
            ->with('success', 'Data pelanggaran karyawan berhasil disimpan.');
    }

    public function edit($id)
    {
        $employees = DB::table('biodata')
            ->select(
                'NPK as npk',
                'NAMA_KARYAWAN as name',
                'ID_DEPT'
            )
            ->union(
                DB::table('biodata_keluar')
                    ->select(
                        'NPK as npk',
                        'NAMA_KARYAWAN as name',
                        'ID_DEPT'
                    )
            )->get();
        $row     = EmployeeViolation::findOrFail($id);
        $periods = PayrollPeriod::orderBy('id', 'desc')->get();

        return view('employee_violation.edit', compact('row', 'periods', 'employees'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'period_id'  => 'required',
            'npk'        => 'required|string|max:50',
            'percentage' => 'required|numeric|min:0|max:100',
        ]);

        $row = EmployeeViolation::findOrFail($id);
        $row->update($request->only('period_id', 'npk', 'percentage'));

        return redirect()
            ->route('employee-violation.index')
            ->with('success', 'Data pelanggaran karyawan berhasil diperbarui.');
    }

    public function delete($id)
    {
        $row = EmployeeViolation::findOrFail($id);
        $row->delete();

        return redirect()
            ->route('employee-violation.index')
            ->with('success', 'Data pelanggaran karyawan berhasil dihapus.');
    }
}
