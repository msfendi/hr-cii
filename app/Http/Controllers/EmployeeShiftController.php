<?php

namespace App\Http\Controllers;

use App\Models\EmployeeShift;
use App\Models\Shift;
use Illuminate\Http\Request;

class EmployeeShiftController extends Controller
{
    public function index()
    {
        $employeeShifts = EmployeeShift::with('shift')
            ->latest()
            ->get();

        return view('employee_shift.index', compact('employeeShifts'));
    }

    public function create()
    {
        $shifts = Shift::all();

        return view('employee_shift.create', compact('shifts'));
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

        return redirect()->route('employee-shift.index')
            ->with('success', 'Shift karyawan berhasil disimpan');
    }

    public function delete($id)
    {
        EmployeeShift::findOrFail($id)->delete();

        return redirect()->route('employee-shift.index')
            ->with('success', 'Shift karyawan dihapus');
    }
}
