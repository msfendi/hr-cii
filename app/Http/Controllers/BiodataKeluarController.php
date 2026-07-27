<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiodataKeluarController extends Controller
{
    public function index()
    {
        $departments = DB::connection('cii')
            ->table('DEPT')
            ->select('ID_DEPT', 'DEPARTEMENT')
            ->where('SECTION', 'CHUTEX')
            ->get();

        return view('biodata_keluar.index', compact('departments'));
    }

    // Ambil data dari PKWT yang TKK-nya sudah terisi
    public function getData(Request $request)
    {
        $query = DB::connection('cii')->table('PKWT')
            ->select('NPK', 'NAMA', 'KTP', 'TMK', 'TKK', 'KETERANGAN', 'leave_reasons', 'BAGIAN')
            ->whereNotNull('TKK')
            ->orderBy('NPK', 'asc');

        $data = $query->get();

        return response()->json(['data' => $data]);
    }

    // Update hanya kolom KETERANGAN di PKWT berdasarkan NPK
    public function updateKeterangan(Request $request, $npk)
    {
        $request->validate([
            'keterangan' => 'required|in:SPD,HK,MA',
        ]);

        try {
            DB::connection('cii')->table('PKWT')
                ->where('NPK', strtoupper($npk))
                ->update([
                    'KETERANGAN' => $request->keterangan,
                    'leave_reasons' => $request->leave_reasons
                ]);

            return response()->json(['status' => 'success', 'message' => 'Keterangan berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}

