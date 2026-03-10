<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PayrollMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PayrollMasterImport;
use App\Exports\PayrollMasterTemplateExport;

class PayrollMasterController extends Controller
{

    public function index()
    {
        $data = PayrollMaster::all();

        return view('payroll_master.index', compact('data'));
    }

    public function create()
    {
        return view('payroll_master.create');
    }

    public function template()
    {
        return Excel::download(
            new PayrollMasterTemplateExport,
            'template_payroll_master.xlsx'
        );
    }

    public function store(Request $request)
    {

        $request->validate([
            'npk' => 'required',
            'salary' => 'required|numeric',
            'allowance' => 'required|numeric'
        ]);

        PayrollMaster::create($request->all());

        return redirect()->back()->with('success', 'Data berhasil disimpan');
    }

    public function import(Request $request)
    {

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {

            Excel::import(new PayrollMasterImport, $request->file('file'));

            return response()->json([
                'status' => 'success',
                'message' => 'Import berhasil'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
