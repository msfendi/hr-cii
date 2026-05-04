<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InsentifRoleFormula;
use Illuminate\Support\Facades\DB;

class InsentifRoleFormulaController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $data = InsentifRoleFormula::orderBy('id', 'desc')->get();

        return view('insentif_role_formulas.index', compact('data'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        $depts = ['pad', 'cutting', 'sewing', 'heat'];

        return view('insentif_role_formulas.create', compact('depts'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'role' => 'required',
            'dept' => 'required',
            'formula' => 'required'
        ]);

        $role = strtolower(str_replace(' ', '_', $request->role));

        InsentifRoleFormula::create([
            'role' => $role,
            'dept' => $request->dept,
            'formula' => $request->formula,
            'is_active' => $request->is_active ?? 1
        ]);

        return redirect()
            ->route('insentif-role-formulas.index')
            ->with('success', 'Data berhasil disimpan');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */
    public function edit($id)
    {
        $data = DB::table('insentif_role_formulas')
            ->where('id', $id)
            ->first();

        $depts = ['pad', 'cutting', 'sewing', 'heat'];

        return view(
            'insentif_role_formulas.edit',
            compact('data', 'depts')
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
            'role' => 'required',
            'dept' => 'required',
            'formula' => 'required'
        ]);

        $role = strtolower(str_replace(' ', '_', $request->role));

        $data = InsentifRoleFormula::findOrFail($id);

        $data->update([
            'role' => $role,
            'dept' => $request->dept,
            'formula' => $request->formula,
            'is_active' => $request->is_active ?? 1
        ]);

        return redirect()
            ->route('insentif-role-formulas.index')
            ->with('success', 'Data berhasil diupdate');
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function delete($id)
    {
        InsentifRoleFormula::findOrFail($id)->delete();

        return back()->with('success', 'Data berhasil dihapus');
    }
}
