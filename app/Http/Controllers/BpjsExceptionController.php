<?php

namespace App\Http\Controllers;

use App\Models\BpjsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class BpjsExceptionController extends Controller
{
    public function index()
    {
        $employees = DB::table('biodata')
            ->select(
                'NPK as npk',
                'NAMA_KARYAWAN as name',
                'ID_DEPT'
            )
            ->union(
                DB::table('biodata_keluar')
                    ->select(
                        'NPK as npk',
                        'NAMA_KARYAWAN as name',
                        'ID_DEPT'
                    )
            );

        $data = DB::table('bpjs_exceptions as be')
            ->leftJoinSub($employees, 'bio', function ($join) {
                $join->on('be.npk', '=', 'bio.npk');
            })
            ->leftJoin('DEPT as d', 'bio.ID_DEPT', '=', 'd.ID_DEPT')
            ->select(
                'be.*',
                'bio.name',
                'd.DEPARTEMENT'
            )
            ->orderBy('be.npk')
            ->get();

        return view('bpjs_exceptions.index', compact('data'));
    }

    public function create()
    {
        $employees = DB::table('biodata')
            ->select('NPK as npk', 'NAMA_KARYAWAN as name')
            ->union(
                DB::table('biodata_keluar')
                    ->select('NPK as npk', 'NAMA_KARYAWAN as name')
            )
            ->orderBy('npk')
            ->get();

        return view('bpjs_exceptions.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'component' => 'required',
            'percentage' => 'required|numeric|min:0',
        ]);

        BpjsException::create([
            'npk' => $request->npk,
            'component' => $request->component,
            'percentage' => $request->percentage,
        ]);

        Alert::success('Success', 'BPJS Exceptions successfully created!');
        return redirect()
            ->route('bpjs-exceptions.index')
            ->with('success', 'Data berhasil ditambahkan');
    }

    public function edit($id)
    {
        $data = BpjsException::findOrFail($id);
        $employees = DB::table('biodata')
            ->select('NPK as npk', 'NAMA_KARYAWAN as name')
            ->union(
                DB::table('biodata_keluar')
                    ->select('NPK as npk', 'NAMA_KARYAWAN as name')
            )
            ->orderBy('npk')
            ->get();

        return view('bpjs_exceptions.edit', compact('data', 'employees'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'npk' => 'required',
            'component' => 'required',
            'percentage' => 'required|numeric|min:0',
        ]);

        $data = BpjsException::findOrFail($id);

        $data->update([
            'npk' => $request->npk,
            'component' => $request->component,
            'percentage' => $request->percentage,
        ]);

        Alert::success('Success', 'BPJS Exceptions successfully updated!');
        return redirect()
            ->route('bpjs-exceptions.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        BpjsException::findOrFail($id)->delete();

        return redirect()
            ->route('bpjs-exceptions.index')
            ->with('success', 'Data berhasil dihapus');
    }
}
