<?php

namespace App\Http\Controllers;

use App\Models\Employee6sAssignment;
use App\Models\PayrollPeriod;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class Employee6sAssignmentController extends Controller
{
    public function index()
    {
        $data = Employee6sAssignment::with('period')
            ->latest()
            ->get();

        return view('6s_insentif.index', compact('data'));
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
}
