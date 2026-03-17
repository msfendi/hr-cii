<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PayrollMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PayrollMasterImport;
use App\Exports\PayrollMasterTemplateExport;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Auth;

class PayrollMasterController extends Controller
{

    public function index()
    {
        $canViewSalary = Auth::user()->hasRole(['Admin', 'Payroll']);

        // dd(Auth::user()->getRoleNames(), $canViewSalary);

        if ($canViewSalary) {
            $data = PayrollMaster::all();
        } else {
            $data = PayrollMaster::select('id', 'npk')->get();
        }
        return view('payroll_master.index', compact('data', 'canViewSalary'));
    }

    public function create()
    {
        return view('payroll_master.create');
    }

    public function edit($id)
    {
        $data = PayrollMaster::findOrFail($id);
        return view('payroll_master.update', compact('data'));
    }


    public function update(Request $request, $id)
    {

        $request->validate([
            'salary' => 'required|numeric',
            'allowance' => 'nullable|numeric',
            'pph21' => 'nullable|numeric'
        ]);

        $data = PayrollMaster::findOrFail($id);

        $data->update([
            'salary' => $request->salary,
            'bank_name' => $request->bank_name,
            'bank_account' => $request->bank_account,
            'allowance' => $request->allowance,
            'pph21' => $request->pph21
        ]);

        Alert::success('Update Successfully!', 'Payroll Master ' . $request->id . ' successfully updated!');
        return redirect()
            ->route('payroll-master.index')
            ->with('success', 'Payroll Master berhasil diupdate');
    }


    public function delete($id)
    {

        $data = PayrollMaster::findOrFail($id);
        $data->delete();

        Alert::success('Delete Successfully!', 'Payroll Master ' . $id . ' successfully deleted!');
        return redirect()
            ->route('payroll-master.index')
            ->with('success', 'Payroll master berhasil dihapus');
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
            'bank_name' => 'required',
            'bank_account' => 'required',
            'salary' => 'required|numeric',
            'allowance' => 'required|numeric',
            'pph21' => 'required|numeric'
        ]);

        PayrollMaster::create($request->all());

        Alert::success('Create Successfully!', 'Payroll Master ' . $request->id . ' successfully created!');
        return redirect()
            ->route('payroll-master.index')
            ->with('success', 'Payroll master berhasil dibuat');
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
