<?php

namespace App\Http\Controllers;

use App\Models\IjinMeninggalkanPekerjaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class IjinMeninggalkanPekerjaanController extends Controller
{
    public function index()
    {
        $data = DB::table('ijin_meninggalkan_pekerjaans')->leftJoin('BIODATA', 'BIODATA.NPK', '=', 'ijin_meninggalkan_pekerjaans.npk')
            ->leftJoin('DEPT', 'DEPT.ID_DEPT', '=', 'BIODATA.ID_DEPT')->latest()->get();

        return view('ijin_meninggalkan_pekerjaan.index', compact('data'));
    }

    public function create()
    {
        $biodatas = DB::table('BIODATA')->orderBy('NPK')->get();
        return view('ijin_meninggalkan_pekerjaan.create', compact('biodatas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'tanggal' => 'required',
            'jam_keluar' => 'required',
        ]);

        IjinMeninggalkanPekerjaan::create([
            'npk' => $request->npk,
            'tanggal' => $request->tanggal,
            'jam_keluar' => $request->jam_keluar,
            'rencana_kembali' => $request->rencana_kembali,
            'jam_kembali' => $request->jam_kembali,
            'reason' => $request->reason,
        ]);

        Alert::success('Success', 'Ijin Meninggalkan Pekerjaan successfully created!');
        return redirect()->route('ijin-meninggalkan-pekerjaan.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = IjinMeninggalkanPekerjaan::findOrFail($id);
        $biodatas = DB::table('BIODATA')->orderBy('NPK')->get();

        return view('ijin_meninggalkan_pekerjaan.edit', compact('data', 'biodatas'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'npk' => 'required',
            'tanggal' => 'required|date',
            'jam_keluar' => 'required',
            'rencana_kembali' => 'nullable',
            'jam_kembali' => 'nullable',
            'reason' => 'nullable',
        ]);

        $data = IjinMeninggalkanPekerjaan::findOrFail($id);

        $data->update($request->all());

        Alert::success('Success', 'Ijin Meninggalkan Pekerjaan successfully updated!');
        return redirect()
            ->route('ijin-meninggalkan-pekerjaan.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $data = IjinMeninggalkanPekerjaan::findOrFail($id);

        $data->delete();

        return redirect()
            ->route('ijin-meninggalkan-pekerjaan.index')
            ->with('success', 'Data berhasil dihapus');
    }
}
