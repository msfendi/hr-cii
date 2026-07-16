<?php

namespace App\Http\Controllers;

use App\Exports\EpoExport;
use App\Exports\EpoTemplateExport;
use App\Models\Epo;
use Illuminate\Http\Request;
use App\Imports\EpoImport;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;

class EpoController extends Controller
{
    public function index()
    {
        $data = Epo::latest()->paginate(10);

        return view('epo.index', compact('data'));
    }


    public function create()
    {
        return view('epo.create');
    }


    public function edit($id)
    {
        $data = Epo::findOrFail($id);
        return view('epo.edit', compact('data'));
    }

    public function store(Request $request)
    {
        Epo::create($request->all());

        Alert::success('Success', 'EPO has been created!');
        return redirect('epo/index');
    }

    public function update(Request $request, Epo $epo)
    {
        $epo->update($request->all());

        Alert::success('Success', 'EPO has been updated!');
        return redirect('epo/index');
    }

    public function destroy(Epo $epo)
    {
        $epo->delete();

        Alert::success('Success', 'EPO has been deleted!');
        return back()->with('success', 'EPO deleted');
    }

    /**
     * IMPORT EXCEL
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        Excel::import(new EpoImport, $request->file('file'));

        return back()->with('success', 'Import success');
    }

    public function template()
    {
        return Excel::download(
            new EpoTemplateExport,
            'EPO-TEMPLATE.xlsx'
        );
    }

    public function export()
    {
        return Excel::download(
            new EpoExport,
            'EPO-DATA.xlsx'
        );
    }
}
