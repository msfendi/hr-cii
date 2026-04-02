<?php

namespace App\Http\Controllers;

use App\Models\ThrPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RealRashid\SweetAlert\Facades\Alert;

class ThrPeriodController extends Controller
{
    public function index()
    {
        $periods = ThrPeriod::latest()->get();
        return view('thr_periods.index', compact('periods'));
    }

    public function create()
    {
        return view('thr_periods.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'cutoff_date' => 'required|date'
        ]);

        $year = Carbon::parse($request->cutoff_date)->year;

        $exists = ThrPeriod::whereYear('cutoff_date', $year)->exists();

        if ($exists) {
            Alert::error('Gagal', 'THR tahun ini sudah dibuat.');
            return redirect()
                ->route('thr-periods.index');
        }

        ThrPeriod::create([
            'name' => "THR " . $year,
            'cutoff_date' => $request->cutoff_date
        ]);

        Alert::success('Create Successfully!', 'Period ' . $request->name . ' successfully created!');
        return redirect()
            ->route('thr-periods.index');
    }

    public function delete($id)
    {
        $periods = ThrPeriod::findOrFail($id);
        $periods->delete();

        Alert::success('Delete Successfully!', 'THR Period ' . $periods->name . ' successfully deleted!');
        return redirect()->route('thr-periods.index');
    }
}
