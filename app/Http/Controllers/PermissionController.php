<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteFacadeAlias;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get();
        return view('permission.index', compact('permissions'));
    }

    public function create()
    {
        // Ambil semua nama route yang terdaftar di aplikasi, agar admin tinggal pilih
        $routeNames = collect(Route::getRoutes())
            ->map(fn($r) => $r->getName())
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $permissions = Permission::select('route_name', 'group', 'name')
            ->whereNotNull('route_name')
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        return view('permission.create', compact('routeNames', 'permissions'));
    }

    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'name'        => 'required|string|max:150',
            'route_name'  => 'required|string|max:150|unique:permissions,route_name',
            'group'       => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ])->validate();

        Permission::create($validated);

        return redirect()->route('permission.index')->with('success', 'Permission berhasil dibuat.');
    }

    public function edit($id)
    {
        $permission = Permission::findOrFail($id);

        $routeNames = collect(Route::getRoutes())
            ->map(fn($r) => $r->getName())
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('permission.edit', compact('permission', 'routeNames'));
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $validated = Validator::make($request->all(), [
            'name'        => 'required|string|max:150',
            'route_name'  => 'required|string|max:150|unique:permissions,route_name,' . $permission->id,
            'group'       => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ])->validate();

        $permission->update($validated);

        return redirect()->route('permission.index')->with('success', 'Permission berhasil diupdate.');
    }

    public function destroy($id)
    {
        Permission::findOrFail($id)->delete();
        return redirect()->route('permission.index')->with('success', 'Permission berhasil dihapus.');
    }
}
