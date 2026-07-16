<?php

namespace App\Http\Controllers;

use App\Models\BreakMaster;
use Illuminate\Http\Request;

class BreakMasterController extends Controller
{
    public function index()
    {
        $breaks = BreakMaster::all();

        return view('break_master.index', compact('breaks'));
    }

    public function create()
    {
        return view('break_master.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'sesi' => 'required',
            'time_start' => 'required',
            'time_end' => 'required',
        ]);

        BreakMaster::create($request->all());

        return redirect()->route('break-master.index')
            ->with('success', 'Break berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $break = BreakMaster::findOrFail($id);

        return view('break_master.edit', compact('break'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'sesi' => 'required',
            'time_start' => 'required',
            'time_end' => 'required',
        ]);

        $break = BreakMaster::findOrFail($id);

        $break->update($request->all());

        return redirect()->route('break-master.index')
            ->with('success', 'Break berhasil diupdate.');
    }

    public function destroy($id)
    {
        BreakMaster::findOrFail($id)->delete();

        return redirect()->route('break-master.index')
            ->with('success', 'Break berhasil dihapus.');
    }
}
