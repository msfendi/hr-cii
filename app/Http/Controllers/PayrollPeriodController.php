<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class PayrollPeriodController extends Controller
{
    public function index()
    {
        $periods = PayrollPeriod::all();
        return view('payroll_periods.index', compact('periods'));
    }


    public function create()
    {
        return view('payroll_periods.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'periode' => 'required|date'
        ]);

        $periode = Carbon::parse($request->periode);

        $startDate = $periode->startOfMonth()->toDateString();
        $endDate   = $periode->endOfMonth()->toDateString();


        // PROTEKSI: cek apakah payroll sudah pernah digenerate
        $exists = PayrollPeriod::where('name', $request->name)->exists();

        if ($exists) {
            Alert::error('Gagal', 'Payroll untuk periode ini sudah tergenerate sebelumnya.');
            return redirect()
                ->route('payroll-periods.index');
        }

        PayrollPeriod::create([
            'name' => $request->name,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        Alert::success('Create Successfully!', 'Period ' . $request->name . ' successfully created!');
        return redirect()
            ->route('payroll-periods.index');
    }

    public function edit($id)
    {
        $periods = PayrollPeriod::find($id);
        return view('payroll_periods.update', compact('periods'));
    }

    public function update(Request $request)
    {
        $periods = PayrollPeriod::findOrFail($request->id);

        $periods->fill([
            'name' => $request->name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        $periods->save();

        Alert::success('Update Successfully!', 'Period ' . $periods->name . ' successfully updated!');
        return redirect()->route('payroll-periods.index');
    }

    public function delete($id)
    {
        $periods = PayrollPeriod::findOrFail($id);
        $periods->delete();

        Alert::success('Delete Successfully!', 'Payroll Period ' . $periods->name . ' successfully deleted!');
        return redirect()->route('payroll-periods.index');
    }
}
