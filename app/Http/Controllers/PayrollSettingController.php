<?php

namespace App\Http\Controllers;

use App\Models\PayrollSetting;
use Illuminate\Http\Request;

class PayrollSettingController extends Controller
{
    public function index()
    {
        return PayrollSetting::orderBy('level')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'component' => 'required',
            'approval' => 'required',
            'level' => 'required|integer'
        ]);

        return PayrollSetting::create($request->all());
    }

    public function update(Request $request, $id)
    {
        $data = PayrollSetting::findOrFail($id);
        $data->update($request->all());

        return $data;
    }

    public function destroy($id)
    {
        PayrollSetting::findOrFail($id)->delete();
        return response()->json(['message' => 'deleted']);
    }
}
