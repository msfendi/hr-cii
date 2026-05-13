<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;
use RealRashid\SweetAlert\Facades\Alert;

class ShiftController extends Controller
{
    public function index()
    {
        $shifts = Shift::latest()->get();

        return view('shift.index', compact('shifts'));
    }

    public function create()
    {
        return view('shift.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'work_start' => 'required',
            'work_end' => 'required',
            'gender' => 'required'
        ]);


        Shift::create($request->all());

        Alert::success('Shift created successfully!');
        return redirect()->route('shift.index');
    }

    public function edit($id)
    {
        $shift = Shift::findOrFail($id);

        return view('shift.edit', compact('shift'));
    }

    public function update(Request $request, $id)
    {
        $shift = Shift::findOrFail($id);

        $shift->update($request->all());

        Alert::success('Shift updated successfully!');
        return redirect()->route('shift.index');
    }

    public function delete($id)
    {
        Shift::findOrFail($id)->delete();

        Alert::success('Shift deleted successfully!');
        return redirect()->route('shift.index');
    }
}
