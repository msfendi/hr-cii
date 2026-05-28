<?php

namespace App\Http\Controllers;

use App\Models\ParentDept;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Imports\DeptImport;
use App\Exports\DeptTemplateExport;
use App\Exports\DeptExport;

class DeptController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $parentDepts = ParentDept::all();
        return view('dept.index', compact('parentDepts'));
    }

    public function import(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|mimes:xls,xlsx'
        ]);

        $file = $request->file('file');
        $nama_file = $file->hashName();
        $path = $file->storeAs('public/excel/', $nama_file);

        $import = Excel::import(new DeptImport(), storage_path('app/public/excel/' . $nama_file));
        Storage::delete($path);

        if ($import) {
            Alert::success('Import Successfully!', 'Dept data successfully imported!');
            return redirect()->route('dept.index');
        } else {
            return redirect()->back()->with('error', 'Failed to import data');
        }
    }

    public function exportTemplate()
    {
        return Excel::download(new DeptTemplateExport(), 'Template_Import_Dept.xlsx');
    }

    public function exportData()
    {
        return Excel::download(new DeptExport(), 'Data_Dept.xlsx');
    }

    /**
     * Return JSON for DataTables AJAX.
     */
    public function getData()
    {
        $depts = DB::connection('cii')
            ->table('DEPT')
            ->leftJoin('parent_dept', 'DEPT.id_parent_dept', '=', 'parent_dept.id')
            ->select('DEPT.ID_DEPT', 'DEPT.DEPARTEMENT', 'DEPT.IS_SEWING', 'DEPT.SECTION', 'DEPT.id_parent_dept', 'parent_dept.parent_dept_name')
            ->orderBy('DEPT.ID_DEPT')
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
            'id_parent_dept' => 'nullable|integer',
        ]);

        try {
            $idDept = DB::connection('cii')->table('DEPT')->max('ID_DEPT') + 1;
            DB::connection('cii')->table('DEPT')->insert([
                'ID_DEPT'     => $idDept,
                'DEPARTEMENT' => strtoupper($request->departement),
                'IS_SEWING'   => $request->has('is_sewing') ? 0 : 1,
                'SECTION'     => strtoupper($request->section ?? 'CHUTEX'),
                'id_parent_dept' => $request->id_parent_dept,
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
            'id_parent_dept' => 'nullable|integer|exists:cii.parent_dept,id',
        ]);

        try {
            DB::connection('cii')->table('DEPT')->where('ID_DEPT', $id)->update([
                'DEPARTEMENT' => strtoupper($request->departement),
                'IS_SEWING'   => $request->has('is_sewing') ? 0 : 1,
                'SECTION'     => strtoupper($request->section ?? 'CHUTEX'),
                'id_parent_dept' => $request->id_parent_dept,
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
