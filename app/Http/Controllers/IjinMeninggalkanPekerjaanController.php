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
        $employee = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            );

        $data = DB::table('ijin_meninggalkan_pekerjaans')
            ->leftJoinSub($employee, 'biodata', function ($join) {
                $join->on('biodata.NPK', '=', 'ijin_meninggalkan_pekerjaans.npk');
            })
            ->leftJoin('DEPT', 'DEPT.ID_DEPT', '=', 'biodata.ID_DEPT')
            ->leftJoin('break_masters', 'break_masters.id', '=', 'ijin_meninggalkan_pekerjaans.id_break')
            ->select(
                'ijin_meninggalkan_pekerjaans.*',
                'biodata.NAMA_KARYAWAN',
                'DEPT.DEPARTEMENT',
                'break_masters.sesi as break_sesi',
                'break_masters.time_start as break_time_start',
                'break_masters.time_end as break_time_end'
            )
            ->get();

        return view('ijin_meninggalkan_pekerjaan.index', compact('data'));
    }

    public function create()
    {
        $biodatas = $this->getBiodatas();
        $breaks = $this->getBreaks();

        return view('ijin_meninggalkan_pekerjaan.create', compact('biodatas', 'breaks'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'tanggal' => 'required',
            'jam_keluar' => 'required',
            'id_break' => 'nullable|exists:break_masters,id',
            'is_deduction' => 'required|boolean',
        ]);

        IjinMeninggalkanPekerjaan::create([
            'npk' => $request->npk,
            'tanggal' => $request->tanggal,
            'jam_keluar' => $request->jam_keluar,
            'rencana_kembali' => $request->rencana_kembali,
            'jam_kembali' => $request->jam_kembali,
            'id_break' => $request->id_break,
            'is_deduction' => $request->boolean('is_deduction'),
            'reason' => $request->reason,
        ]);

        Alert::success('Success', 'Ijin Meninggalkan Pekerjaan successfully created!');
        return redirect()->route('ijin-meninggalkan-pekerjaan.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = IjinMeninggalkanPekerjaan::findOrFail($id);
        $biodatas = $this->getBiodatas();
        $breaks = $this->getBreaks();

        return view('ijin_meninggalkan_pekerjaan.edit', compact('data', 'biodatas', 'breaks'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'npk' => 'required',
            'tanggal' => 'required|date',
            'jam_keluar' => 'required',
            'rencana_kembali' => 'nullable',
            'jam_kembali' => 'nullable',
            'id_break' => 'nullable|exists:break_masters,id',
            'is_deduction' => 'required|boolean',
            'reason' => 'nullable',
        ]);

        $data = IjinMeninggalkanPekerjaan::findOrFail($id);

        $data->update([
            'npk' => $request->npk,
            'tanggal' => $request->tanggal,
            'jam_keluar' => $request->jam_keluar,
            'rencana_kembali' => $request->rencana_kembali,
            'jam_kembali' => $request->jam_kembali,
            'id_break' => $request->id_break,
            'is_deduction' => $request->boolean('is_deduction'),
            'reason' => $request->reason,
        ]);

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

    /**
     * Union of active + resigned employees for the NPK dropdown.
     */
    private function getBiodatas()
    {
        return DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NPK')
            ->get();
    }

    /**
     * Break master list for the id_break dropdown.
     */
    private function getBreaks()
    {
        return DB::table('break_masters')
            ->select('id', 'sesi', 'time_start', 'time_end')
            ->orderBy('time_start')
            ->get();
    }
}
