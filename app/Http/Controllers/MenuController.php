<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
    public function index()
    {
        $menus = Menu::whereNull('parent_id')->with('children')->orderBy('order')->get();
        return view('menu.index', compact('menus'));
    }

    public function create()
    {
        $parents     = Menu::whereNull('parent_id')->orderBy('order')->get();
        $permissions = Permission::orderBy('group')->orderBy('name')->get();

        return view('menu.create', compact('parents', 'permissions'));
    }

    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'parent_id'     => 'nullable|exists:menus,id',
            'name'          => 'required|string|max:100',
            'route_name'    => 'nullable|string|max:150',
            'icon'          => 'nullable|string|max:100',
            'permission_id' => 'nullable|exists:permissions,id',
            'order'         => 'nullable|integer',
            'is_active'     => 'boolean',
        ])->validate();

        $validated['is_active'] = $request->boolean('is_active', true);

        Menu::create($validated);

        return redirect()->route('menu.index')->with('success', 'Menu berhasil dibuat.');
    }

    public function edit($id)
    {
        $menu        = Menu::findOrFail($id);
        $parents     = Menu::whereNull('parent_id')->where('id', '!=', $id)->orderBy('order')->get();
        $permissions = Permission::orderBy('group')->orderBy('name')->get();

        return view('menu.edit', compact('menu', 'parents', 'permissions'));
    }

    public function update(Request $request, $id)
    {
        $menu = Menu::findOrFail($id);

        $validated = Validator::make($request->all(), [
            'parent_id'     => 'nullable|exists:menus,id|different:id',
            'name'          => 'required|string|max:100',
            'route_name'    => 'nullable|string|max:150',
            'icon'          => 'nullable|string|max:100',
            'permission_id' => 'nullable|exists:permissions,id',
            'order'         => 'nullable|integer',
            'is_active'     => 'boolean',
        ])->validate();

        $validated['is_active'] = $request->boolean('is_active', true);

        $menu->update($validated);

        return redirect()->route('menu.index')->with('success', 'Menu berhasil diupdate.');
    }

    public function destroy($id)
    {
        Menu::findOrFail($id)->delete(); // children ikut null parent_id (set FK nullOnDelete) -> sebaiknya hapus manual jika perlu
        return redirect()->route('menu.index')->with('success', 'Menu berhasil dihapus.');
    }

    public function reorder(Request $request)
    {
        // dipanggil dari drag-and-drop di index, payload: [{id, order, parent_id}, ...]
        foreach ($request->input('items', []) as $item) {
            Menu::where('id', $item['id'])->update([
                'order'     => $item['order'],
                'parent_id' => $item['parent_id'] ?? null,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
