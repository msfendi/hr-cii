<?php

namespace App\Http\Controllers;

use App\Models\BreakMaster;
use App\Models\DeptBreaktime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeptBreaktimeController extends Controller
{
    public function index()
    {
        $deptBreaks = DB::table('dept_breaktimes')->select('DEPT.DEPARTEMENT', 'break_masters.*')->leftJoin('break_masters', 'dept_breaktimes.id_break', '=', 'break_masters.id')->leftJoin('DEPT', 'DEPT.ID_DEPT', '=', 'dept_breaktimes.id_dept')->get();

        return view('dept_breaktime.index', compact('deptBreaks'));
    }

    public function create()
    {
        $breaks = BreakMaster::all();

        return view('dept_breaktime.create', compact('breaks'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_dept' => 'required',
            'id_break' => 'required|exists:break_masters,id',
        ]);

        DeptBreaktime::create($request->all());

        return redirect()->route('dept-breaktime.index')
            ->with('success', 'Department Break berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $deptBreak = DeptBreaktime::findOrFail($id);
        $breaks = BreakMaster::all();

        return view('dept_breaktime.edit', compact('deptBreak', 'breaks'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'id_dept' => 'required',
            'id_break' => 'required|exists:break_masters,id',
        ]);

        $deptBreak = DeptBreaktime::findOrFail($id);

        $deptBreak->update($request->all());

        return redirect()->route('dept-breaktime.index')
            ->with('success', 'Department Break berhasil diupdate.');
    }

    public function destroy($id)
    {
        DeptBreaktime::findOrFail($id)->delete();

        return redirect()->route('dept-breaktime.index')
            ->with('success', 'Department Break berhasil dihapus.');
    }
}
