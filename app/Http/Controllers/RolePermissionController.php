<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class RolePermissionController extends Controller
{
    public function index()
    {
        $roles = Role::orderBy('name')->get();
        return view('role-permission.index', compact('roles'));
    }

    public function edit($roleId)
    {
        $role             = Role::findOrFail($roleId);
        $permissions      = Permission::orderBy('group')->orderBy('name')->get()->groupBy('group');
        $assignedIds      = $role->permissions()->pluck('permissions.id')->toArray();

        return view('role-permission.edit', compact('role', 'permissions', 'assignedIds'));
    }

    public function update(Request $request, $roleId)
    {
        $role = Role::findOrFail($roleId);

        $permissionIds = $request->input('permission_ids', []);

        $role->permissions()->sync($permissionIds);

        Alert::success('Success', "Permission untuk role {$role->name} berhasil diupdate.");
        return redirect()->route('role-permission.index')->with('success', "Permission untuk role {$role->name} berhasil diupdate.");
    }
}
