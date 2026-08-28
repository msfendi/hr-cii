<?php

namespace App\Http\Controllers;

use App\Exports\IjinMeninggalkanExport;
use App\Models\IjinMeninggalkanPekerjaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
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
                'break_masters.sesi',
                'break_masters.time_start',
                'break_masters.time_end'
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

    // BUAT EXPORT TO EXCEL
    public function export(Request $request)
    {
        $request->validate([
            'mode'       => 'required|in:monthly,custom',
            'month'      => 'required_if:mode,monthly',
            'start_date' => 'required_if:mode,custom|nullable|date',
            'end_date'   => 'required_if:mode,custom|nullable|date|after_or_equal:start_date',
        ]);

        if ($request->mode === 'monthly') {
            // month = "YYYY-MM"
            $start_date = $request->month . '-01';
            $end_date   = \Carbon\Carbon::parse($start_date)->endOfMonth()->toDateString();
            $label      = \Carbon\Carbon::parse($start_date)->translatedFormat('F Y');
        } else {
            $start_date = $request->start_date;
            $end_date   = $request->end_date;
            $label      = $start_date . ' s/d ' . $end_date;
        }

        // ambil data dari database
        $employee = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            );

        $data = DB::table('ijin_meninggalkan_pekerjaans as im')
            ->leftJoinSub($employee, 'biodata', function ($join) {
                $join->on('biodata.NPK', '=', 'im.npk');
            })
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'biodata.ID_DEPT')
            ->leftJoin('break_masters as bm', 'bm.id', '=', 'im.id_break')
            ->select(
                'im.*',
                'biodata.NAMA_KARYAWAN',
                'd.DEPARTEMENT',
                'bm.sesi',
                'bm.time_start',
                'bm.time_end'
            )
            ->whereBetween('im.tanggal', [$start_date, $end_date])
            ->orderBy('im.tanggal')
            ->get();

        if ($data->isEmpty()) {
            return response()->json(['error' => 'Tidak ada data untuk periode tersebut.'], 404);
        }

        $filename = 'Ijin_Meninggalkan_' . str_replace([' ', '/'], '_', $label) . '.xlsx';

        return Excel::download(
            new IjinMeninggalkanExport($data, $label),
            $filename
        );
    }
}
