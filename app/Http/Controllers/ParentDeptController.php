<?php

namespace App\Http\Controllers;

use App\Models\ParentDept;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;
use App\Imports\ParentDeptImport;
use App\Exports\ParentDeptTemplateExport;
use App\Exports\ParentDeptExport;

class ParentDeptController extends Controller
{
    public function index()
    {
        return view('parent-dept.index');
    }

    public function import(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|mimes:xls,xlsx'
        ]);

        $file = $request->file('file');
        $nama_file = $file->hashName();
        $path = $file->storeAs('public/excel/', $nama_file);

        $import = Excel::import(new ParentDeptImport(), storage_path('app/public/excel/' . $nama_file));
        Storage::delete($path);

        if ($import) {
            Alert::success('Import Successfully!', 'Parent Dept data successfully imported!');
            return redirect()->route('parent-dept.index');
        } else {
            return redirect()->back()->with('error', 'Failed to import data');
        }
    }

    public function exportTemplate()
    {
        return Excel::download(new ParentDeptTemplateExport(), 'Template_Import_ParentDept.xlsx');
    }

    public function exportData()
    {
        return Excel::download(new ParentDeptExport(), 'Data_Parent_Dept.xlsx');
    }

    public function getData(Request $request)
    {
        $query = ParentDept::query()
            ->withCount('depts'); // Menghitung jumlah child department

        // Handle DataTables search
        if ($request->has('search') && !empty($request->input('search')['value'])) {
            $searchValue = $request->input('search')['value'];
            $query->where('parent_dept_name', 'LIKE', '%' . $searchValue . '%');
        }

        // Handle DataTables ordering
        if ($request->has('order') && count($request->input('order')) > 0) {
            $orderColumnIndex = $request->input('order')[0]['column'];
            $orderDirection = $request->input('order')[0]['dir'];
            $columns = ['id', 'parent_dept_name'];
            
            if (isset($columns[$orderColumnIndex])) {
                $query->orderBy($columns[$orderColumnIndex], $orderDirection);
            }
        } else {
            $query->orderBy('id', 'desc'); // Default order
        }

        // Handle DataTables pagination
        $start = $request->input('start', 0);
        $length = $request->input('length', 10);

        $totalRecords = ParentDept::count();
        $filteredRecords = $query->count();

        $data = $query->offset($start)->limit($length)->get();

        // Format data for DataTables
        $formattedData = [];
        foreach ($data as $index => $item) {
            $formattedData[] = [
                'id' => $item->id,
                'parent_dept_name' => $item->parent_dept_name,
                'depts_count' => $item->depts_count,
            ];
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $formattedData,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'parent_dept_name' => 'required|string|max:100|unique:cii.parent_dept,parent_dept_name',
        ]);

        try {
            ParentDept::create([
                'parent_dept_name' => $request->parent_dept_name,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Parent Department berhasil ditambahkan'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menambahkan Parent Department: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'parent_dept_name' => 'required|string|max:100|unique:cii.parent_dept,parent_dept_name,' . $id . ',id',
        ]);

        try {
            $parentDept = ParentDept::findOrFail($id);
            $parentDept->update([
                'parent_dept_name' => $request->parent_dept_name,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Parent Department berhasil diupdate'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengupdate Parent Department: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $parentDept = ParentDept::findOrFail($id);
            
            // Cek apakah ada relasi
            if ($parentDept->depts()->count() > 0) {
                 return response()->json([
                    'status' => 'error',
                    'message' => 'Parent Department tidak bisa dihapus karena masih digunakan oleh Department.'
                ], 400);
            }

            $parentDept->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Parent Department berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus Parent Department: ' . $e->getMessage()
            ], 500);
        }
    }
}
