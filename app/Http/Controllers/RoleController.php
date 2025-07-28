<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class RoleController extends Controller
{
    public function index() {
        $roles = Role::all();
         return view('role.index', compact('roles'));
    }

    public function create() {
        return view('role.create');
    }

    public function store(Request $request)
    {
        Role::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name,
        ]);

        Alert::success('Create Successfully!', 'Role successfully created!');
        return redirect()
            ->route('role.create');
    }

    public function delete($id) {
        $roles = Role::find($id);    
        $roles->delete();
        Alert::success('Delete Successfully!', 'Role successfully deleted!');
        return redirect('role/index');
    }
}
