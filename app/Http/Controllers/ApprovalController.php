<?php

namespace App\Http\Controllers;

use App\Models\ApprovalDept;
use App\Models\ApprovalRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    /**
     * Display the Master Approval index page.
     */
    public function index()
    {
        $depts = DB::connection('cii')
            ->table('DEPT')
            ->select('ID_DEPT', 'DEPARTEMENT')
            ->orderBy('DEPARTEMENT')
            ->get();

        return view('approval.index', compact('depts'));
    }

    /**
     * Return JSON for ApprovalDept DataTable.
     */
    public function getData()
    {
        $data = ApprovalDept::with('rules')->get()->map(function ($item) {
            // Resolve department names from CII DB
            $deptNames = DB::connection('cii')
                ->table('DEPT')
                ->whereIn('ID_DEPT', $item->dept ?? [])
                ->pluck('DEPARTEMENT')
                ->toArray();

            // Resolve approval names from BIODATA
            $rulesData = $item->rules->map(function ($rule) {
                $bio = DB::connection('cii')
                    ->table('BIODATA')
                    ->where('NPK', $rule->approval_id)
                    ->first();

                return [
                    'id'          => $rule->id,
                    'name'        => $rule->name,
                    'approval_id' => $rule->approval_id,
                    'approval_name' => $bio ? $bio->NAMA_KARYAWAN : $rule->approval_id,
                    'level'       => $rule->level,
                ];
            });

            return [
                'id'         => $item->id,
                'name'       => $item->name,
                'dept'       => $item->dept,
                'dept_names' => $deptNames,
                'rules'      => $rulesData,
                'rules_count' => $rulesData->count(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Store a new ApprovalDept.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'dept' => 'required|array|min:1',
            'dept.*' => 'string',
        ]);

        try {
            $approvalDept = ApprovalDept::create([
                'name' => $request->name,
                'dept' => array_map('strval', $request->dept),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Approval Group berhasil ditambahkan',
                'data' => $approvalDept,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menambahkan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing ApprovalDept.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'dept' => 'required|array|min:1',
            'dept.*' => 'string',
        ]);

        try {
            $approvalDept = ApprovalDept::findOrFail($id);
            $approvalDept->update([
                'name' => $request->name,
                'dept' => array_map('strval', $request->dept),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Approval Group berhasil diperbarui',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an ApprovalDept and its rules.
     */
    public function destroy($id)
    {
        try {
            $dept = ApprovalDept::findOrFail($id);
            // Delete related rules first
            ApprovalRule::where('rules_id', $id)->delete();
            $dept->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Approval Group berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================
    // APPROVAL RULES (nested under a dept group)
    // =========================================================

    /**
     * Store a new ApprovalRule under a group.
     */
    public function storeRule(Request $request)
    {
        $request->validate([
            'rules_id'    => 'required|exists:approval_depts,id',
            'name'        => 'required|string|max:255',
            'approval_id' => 'required|string|max:50',
            'level'       => 'required|integer|min:1',
        ]);

        try {
            $rule = ApprovalRule::create($request->only('rules_id', 'name', 'approval_id', 'level'));

            return response()->json([
                'status' => 'success',
                'message' => 'Approval Rule berhasil ditambahkan',
                'data' => $rule,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menambahkan rule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing ApprovalRule.
     */
    public function updateRule(Request $request, $id)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'approval_id' => 'required|string|max:50',
            'level'       => 'required|integer|min:1',
        ]);

        try {
            $rule = ApprovalRule::findOrFail($id);
            $rule->update($request->only('name', 'approval_id', 'level'));

            return response()->json([
                'status' => 'success',
                'message' => 'Approval Rule berhasil diperbarui',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui rule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an ApprovalRule.
     */
    public function destroyRule($id)
    {
        try {
            ApprovalRule::findOrFail($id)->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Approval Rule berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus rule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search employees by NPK or name for autocomplete.
     */
    public function searchEmployee(Request $request)
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $results = DB::connection('cii')
            ->table('BIODATA')
            ->where(function ($query) use ($q) {
                $query->where('NPK', 'like', "%{$q}%")
                      ->orWhere('NAMA_KARYAWAN', 'like', "%{$q}%");
            })
            ->select('NPK', 'NAMA_KARYAWAN')
            ->take(15)
            ->get();

        return response()->json($results);
    }
}
