<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmployeeMutation;

class EmployeeMutationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST DATA
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $data = EmployeeMutation::latest()->get();

        return response()->json($data);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'from_dept' => 'required|integer',
            'to_dept' => 'required|integer',
            'date' => 'required|date',
        ]);

        $mutation = EmployeeMutation::create($request->all());

        return response()->json([
            'message' => 'Mutation created',
            'data' => $mutation
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $data = EmployeeMutation::findOrFail($id);

        return response()->json($data);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $mutation = EmployeeMutation::findOrFail($id);

        $request->validate([
            'npk' => 'required',
            'from_dept' => 'required|integer',
            'to_dept' => 'required|integer',
            'date' => 'required|date',
        ]);

        $mutation->update($request->all());

        return response()->json([
            'message' => 'Mutation updated',
            'data' => $mutation
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $mutation = EmployeeMutation::findOrFail($id);
        $mutation->delete();

        return response()->json([
            'message' => 'Mutation deleted'
        ]);
    }
}
