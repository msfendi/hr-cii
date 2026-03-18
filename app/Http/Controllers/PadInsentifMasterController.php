<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InsentifMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PadInsentifTemplateExport;
use App\Imports\PadInsentifImport;
use Illuminate\Support\Facades\DB;

class PadInsentifMasterController extends Controller
{
    public function index()
    {
        $data = DB::table('pad_efficiencies as l')
            ->join('employee_pad_assignments as e', function ($join) {
                $join->on('l.dept', '=', 'e.dept')
                    ->whereRaw('l.date BETWEEN e.start_date AND COALESCE(e.end_date, l.date)');
            })
            ->select(
                'e.id',
                'e.npk',
                'e.dept',
                'e.role',
                'l.efficiency',
                'l.date'
            )
            ->orderBy('e.npk')
            ->orderBy('l.date')
            ->get();
        // dd($data);
        return view('pad_insentif_master.index', compact('data'));
    }

    public function create()
    {
        return view('pad_insentif_master.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'type' => 'required',
            'efficiency' => 'nullable|numeric',
            'piece' => 'nullable|numeric',
        ]);

        InsentifMaster::create($request->all());

        return redirect()->route('pad-insentif-master.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = InsentifMaster::findOrFail($id);
        return view('pad-insentif-master.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'npk' => 'required',
            'type' => 'required',
            'efficiency' => 'nullable|numeric',
            'piece' => 'nullable|numeric',
        ]);

        $data = InsentifMaster::findOrFail($id);
        $data->update($request->all());

        return redirect()->route('pad-insentif-master.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $data = InsentifMaster::findOrFail($id);
        $data->delete();

        return redirect()->route('pad-insentif-master.index')
            ->with('success', 'Data berhasil dihapus');
    }

    public function template()
    {
        return Excel::download(new PadInsentifTemplateExport, 'template_pad_insentif_master.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);

        Excel::import(new PadInsentifImport, $request->file('file'));

        return redirect()->back()->with('success', 'Data berhasil diimport');
    }
}
