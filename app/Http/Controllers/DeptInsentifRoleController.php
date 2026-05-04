<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeptInsentifRoleController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $data = DB::table('dept_insentif_role as dir')
            ->join('DEPT as d', 'd.ID_DEPT', '=', 'dir.id_dept')
            ->join('insentif_role_formulas as f', 'f.id', '=', 'dir.role')
            ->select(
                'dir.id',
                'dir.id_dept',
                'd.DEPARTEMENT as dept_name',
                'dir.role',
                'f.role',
                'f.formula',
                'dir.created_at'
            )
            ->orderBy('dir.id', 'desc')
            ->get();

        return view('dept_insentif_role.index', compact('data'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        $departments = DB::table('DEPT')->get();
        $roles = DB::table('insentif_role_formulas')->orderBy('dept')->get();

        return view('dept_insentif_role.create', compact(
            'departments',
            'roles'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'id_dept' => 'required',
            'role' => 'required'
        ]);

        DB::table('dept_insentif_role')->insert([
            'id_dept' => $request->id_dept,
            'role' => $request->role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('dept-insentif-role.index')
            ->with('success', 'Data created');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */
    public function edit($id)
    {
        $data = DB::table('dept_insentif_role')
            ->where('id', $id)
            ->first();

        $departments = DB::table('DEPT')->get();
        $roles = DB::table('insentif_role_formulas')->get();

        return view(
            'dept_insentif_role.edit',
            compact('data', 'departments', 'roles')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $request->validate([
            'id_dept' => 'required',
            'role' => 'required'
        ]);

        DB::table('dept_insentif_role')
            ->where('id', $id)
            ->update([
                'id_dept' => $request->id_dept,
                'role' => $request->role,
                'updated_at' => now()
            ]);

        return redirect()
            ->route('dept-insentif-role.index')
            ->with('success', 'Data updated');
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function delete($id)
    {
        DB::table('dept_insentif_role')
            ->where('id', $id)
            ->delete();

        return back()->with('success', 'Data deleted');
    }
}
