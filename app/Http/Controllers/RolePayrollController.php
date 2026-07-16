<?php

namespace App\Http\Controllers;

use App\Models\RolePayroll;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;

class RolePayrollController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {

            $data = RolePayroll::with(['user', 'createdBy'])->select('role_payrolls.*');

            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('user_name', function ($row) {
                    return optional($row->user)->name ?? '-';
                })
                ->editColumn('user_email', function ($row) {
                    return optional($row->user)->email ?? '-';
                })
                ->editColumn('payroll_role', function ($row) {
                    return RolePayroll::ROLES[$row->payroll_role] ?? $row->payroll_role;
                })
                ->addColumn('action', function ($row) {
                    return '
                        <button class="btn btn-warning btn-sm btn-edit-role-payroll"
                            data-id="' . $row->id . '"
                            data-user_id="' . $row->user_id . '"
                            data-payroll_role="' . $row->payroll_role . '">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-danger btn-sm btn-delete-role-payroll" data-id="' . $row->id . '">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    ';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        // Hanya user yang belum punya assignment yang muncul di dropdown "tambah baru"
        $users = User::whereDoesntHave('rolePayroll')->orderBy('name')->get(['id', 'name', 'email']);
        $roles = RolePayroll::ROLES;

        return view('role_payroll.index', compact('users', 'roles'));
    }

    /**
     * Dipakai saat modal Edit dibuka: kembalikan daftar user termasuk user yang sedang diedit,
     * supaya user tsb tetap muncul & terpilih di Select2.
     */
    public function usersForEdit($id)
    {
        $current = RolePayroll::findOrFail($id);

        $users = User::where('id', $current->user_id)
            ->orWhereDoesntHave('rolePayroll')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'      => 'required|exists:users,id|unique:role_payrolls,user_id',
            'payroll_role' => 'required|in:' . implode(',', array_keys(RolePayroll::ROLES)),
        ], [
            'user_id.unique' => 'User ini sudah punya assignment payroll role. Silakan edit baris yang sudah ada.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        RolePayroll::create([
            'user_id'      => $request->user_id,
            'payroll_role' => $request->payroll_role,
            'created_by'   => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payroll role berhasil ditambahkan.',
        ]);
    }

    public function update(Request $request, $id)
    {
        $rolePayroll = RolePayroll::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'user_id'      => 'required|exists:users,id|unique:role_payrolls,user_id,' . $rolePayroll->id,
            'payroll_role' => 'required|in:' . implode(',', array_keys(RolePayroll::ROLES)),
        ], [
            'user_id.unique' => 'User ini sudah punya assignment payroll role lain.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $rolePayroll->update([
            'user_id'      => $request->user_id,
            'payroll_role' => $request->payroll_role,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payroll role berhasil diupdate.',
        ]);
    }

    public function destroy($id)
    {
        $rolePayroll = RolePayroll::findOrFail($id);
        $rolePayroll->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payroll role berhasil dihapus.',
        ]);
    }
}
