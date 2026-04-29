<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LateCompensation;
use Illuminate\Support\Facades\DB;

class LateCompensationController extends Controller
{

    public function index()
    {
        $data = LateCompensation::orderByDesc('id')->get();
        return view('late_compensation.index', compact('data'));
    }

    public function create()
    {
        $employees = DB::table('BIODATA')->select('NPK', 'NAMA_KARYAWAN')->get();
        return view('late_compensation.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'date' => 'required|date',
            'reason' => 'required'
        ]);

        LateCompensation::create($request->all());

        return redirect()
            ->route('late-compensation.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = LateCompensation::findOrFail($id);
        return view('late_compensation.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'npk' => 'required',
            'date' => 'required|date',
            'reason' => 'required'
        ]);

        LateCompensation::findOrFail($id)
            ->update($request->all());

        return redirect()
            ->route('late-compensation.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function delete($id)
    {
        LateCompensation::findOrFail($id)->delete();

        return redirect()
            ->route('late-compensation.index')
            ->with('success', 'Data berhasil dihapus');
    }
}
