<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class DeptController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('dept.index');
    }

    /**
     * Return JSON for DataTables AJAX.
     */
    public function getData()
    {
        $depts = DB::connection('cii')
            ->table('DEPT')
            ->select('ID_DEPT', 'DEPARTEMENT', 'IS_SEWING', 'SECTION')
            ->orderBy('ID_DEPT')
            ->get();

        return response()->json(['data' => $depts]);
    }

    /**
     * Store a newly created resource.
     */
    public function store(Request $request)
    {
        $request->validate([
            'departement' => 'required|string|max:255',
        ]);

        try {
            $idDept = DB::connection('cii')->table('DEPT')->max('ID_DEPT') + 1;
            DB::connection('cii')->table('DEPT')->insert([
                'ID_DEPT'     => $idDept,
                'DEPARTEMENT' => strtoupper($request->departement),
                'IS_SEWING'   => $request->has('is_sewing') ? 0 : 1,
                'SECTION'     => strtoupper($request->section ?? 'CHUTEX'),
            ]);

            return response()->json(['status' => 'success', 'message' => 'Departemen berhasil ditambahkan']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal menambahkan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Return a single record for the edit modal.
     */
    public function show($id)
    {
        $dept = DB::connection('cii')
            ->table('DEPT')
            ->where('ID_DEPT', $id)
            ->first();

        if (!$dept) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json($dept);
    }

    /**
     * Update the specified resource.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'departement' => 'required|string|max:255',
        ]);

        try {
            DB::connection('cii')->table('DEPT')->where('ID_DEPT', $id)->update([
                'DEPARTEMENT' => strtoupper($request->departement),
                'IS_SEWING'   => $request->has('is_sewing') ? 0 : 1,
                'SECTION'     => strtoupper($request->section ?? 'CHUTEX'),
            ]);

            return response()->json(['status' => 'success', 'message' => 'Departemen berhasil diperbarui']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal memperbarui: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource.
     */
    public function destroy($id)
    {
        try {
            // Prevent deleting dept that is still referenced by BIODATA
            $usedCount = DB::connection('cii')
                ->table('BIODATA')
                ->where('ID_DEPT', $id)
                ->count();

            if ($usedCount > 0) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Departemen tidak dapat dihapus karena masih digunakan oleh ' . $usedCount . ' karyawan.',
                ], 422);
            }

            DB::connection('cii')->table('DEPT')->where('ID_DEPT', $id)->delete();

            return response()->json(['status' => 'success', 'message' => 'Departemen berhasil dihapus']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal menghapus: ' . $e->getMessage()], 500);
        }
    }
}
