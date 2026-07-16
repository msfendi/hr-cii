<?php

namespace App\Http\Controllers;

use App\Models\SewingViolation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class SewingViolationController extends Controller
{
    public function index()
    {
        $data = DB::table('sewing_violations as a')
            ->leftJoin('DEPT as d', 'a.id_dept', '=', 'd.ID_DEPT')
            ->select(
                'a.*',
                'd.DEPARTEMENT'
            )
            ->orderBy('a.id', 'desc')
            ->get();

        return view('sewing_violations.index', compact('data'));
    }

    public function create()
    {
        $dept = DB::table('DEPT')
            ->orderBy('DEPARTEMENT')
            ->get();

        return view('sewing_violations.create', compact('dept'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_dept' => 'required',
            'pelanggaran' => 'required',
            'tanggal' => 'required'
        ]);

        SewingViolation::create([
            'id_dept' => $request->id_dept,
            'pelanggaran' => $request->pelanggaran,
            'tanggal' => $request->tanggal
        ]);

        Alert::success('Success', 'Sewing Violations Has been Created!');
        return redirect()
            ->route('sewing-violations.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = SewingViolation::findOrFail($id);

        $dept = DB::table('DEPT')
            ->orderBy('DEPARTEMENT')
            ->get();

        return view(
            'sewing_violations.edit',
            compact('data', 'dept')
        );
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'id_dept' => 'required',
            'pelanggaran' => 'required',
            'tanggal' => 'required'
        ]);

        SewingViolation::where('id', $request->id)
            ->update([
                'id_dept' => $request->id_dept,
                'pelanggaran' => $request->pelanggaran,
                'tanggal' => $request->tanggal
            ]);

        Alert::success('Success', 'Sewing Violations Has been Updated!');
        return redirect()
            ->route('sewing-violations.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function delete($id)
    {
        SewingViolation::where('id', $id)->delete();

        Alert::success('Success', 'Sewing Violations Has been Deleted!');
        return redirect()
            ->route('sewing-violations.index')
            ->with('success', 'Data berhasil dihapus');
    }
}
