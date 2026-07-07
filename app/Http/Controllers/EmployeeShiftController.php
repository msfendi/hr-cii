<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeShiftTemplateExport;
use App\Imports\EmployeeShiftImport;
use App\Models\EmployeeShift;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;

class EmployeeShiftController extends Controller
{
    public function index()
    {
        $employeeShifts = EmployeeShift::with('shift')
            ->leftJoin('BIODATA', 'BIODATA.NPK', '=', 'employee_shifts.npk')
            ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->latest()
            ->get();

        return view('employee_shift.index', compact('employeeShifts'));
    }

    public function create()
    {
        $shifts = Shift::all();
        $biodatas = DB::table('BIODATA')->orderBy('NPK')->get();

        return view('employee_shift.create', compact('shifts', 'biodatas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'shift_id' => 'required|exists:shifts,id',
            'shift_date' => 'required|date'
        ]);

        EmployeeShift::updateOrCreate(
            [
                'npk' => $request->npk,
                'shift_date' => $request->shift_date
            ],
            [
                'shift_id' => $request->shift_id
            ]
        );

        Alert::success('Shift karyawan berhasil disimpan!');
        return redirect()->route('employee-shift.index');
    }

    public function delete($id)
    {
        EmployeeShift::findOrFail($id)->delete();

        return redirect()->route('employee-shift.index')
            ->with('success', 'Shift karyawan dihapus');
    }
    public function exportTemplate()
    {
        return Excel::download(new EmployeeShiftTemplateExport, 'employee_shifts_template.xlsx');
    }

    public function importTemplate(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx'
        ]);

        Excel::import(new EmployeeShiftImport, $request->file('file'));

        Alert::success('Shift imported successfully!');
        return redirect()->route('employee-shift.index');
    }

    // add edit, update, and delete 
    public function edit($id)
    {
        $employeeShift = EmployeeShift::findOrFail($id);
        $shifts = Shift::all();
        $biodatas = DB::table('BIODATA')->where('NPK', $employeeShift->npk)->first();

        return view('employee_shift.edit', compact('employeeShift', 'shifts', 'biodatas'));
    }

    public function update(Request $request, $id)
    {
        $employeeShift = EmployeeShift::findOrFail($id);

        $employeeShift->update($request->all());

        Alert::success('Shift karyawan berhasil diupdate!');
        return redirect()->route('employee-shift.index');
    }

    public function destroy($id)
    {
        EmployeeShift::findOrFail($id)->delete();

        Alert::success('Shift karyawan dihapus!');
        return redirect()->route('employee-shift.index');
    }
}
