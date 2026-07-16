<?php

namespace App\Http\Controllers;

use App\Models\RecruitmentPosition;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class RecruitmentPositionController extends Controller
{
    public function index()
    {
        return view('recruitment_position.index');
    }

    public function getData()
    {
        $data = RecruitmentPosition::query();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('is_aktif_badge', function ($row) {
                if ($row->is_aktif) {
                    return '<span class="badge badge-success">Aktif</span>';
                }
                return '<span class="badge badge-danger">Tidak Aktif</span>';
            })
            ->addColumn('action', function ($row) {
                return '
                    <button class="btn btn-sm btn-primary btn-edit" 
                        data-id="' . $row->id . '" 
                        data-position="' . htmlspecialchars($row->position, ENT_QUOTES) . '" 
                        data-dept="' . htmlspecialchars($row->dept, ENT_QUOTES) . '" 
                        data-is_aktif="' . $row->is_aktif . '">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-danger btn-delete" data-id="' . $row->id . '">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                ';
            })
            ->rawColumns(['is_aktif_badge', 'action'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $request->validate([
            'position' => 'required|string|max:255',
            'dept' => 'required|string|max:255',
            'is_aktif' => 'required',
        ]);

        try {
            RecruitmentPosition::create([
                'position' => $request->position,
                'dept' => $request->dept,
                'is_aktif' => $request->is_aktif,
            ]);
            return response()->json(['status' => 'success', 'message' => 'Posisi berhasil ditambahkan.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'position' => 'required|string|max:255',
            'dept' => 'required|string|max:255',
            'is_aktif' => 'required|boolean',
        ]);

        try {
            $position = RecruitmentPosition::findOrFail($id);
            $position->update([
                'position' => $request->position,
                'dept' => $request->dept,
                'is_aktif' => $request->is_aktif,
            ]);
            return response()->json(['status' => 'success', 'message' => 'Posisi berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $position = RecruitmentPosition::findOrFail($id);
            $position->delete();
            return response()->json(['status' => 'success', 'message' => 'Posisi berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
