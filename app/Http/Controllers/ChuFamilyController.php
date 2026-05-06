<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChuFamily;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ChuFamilyImport;
use App\Exports\ChuFamilyExport;
use App\Exports\ChuFamilyTemplateExport;

class ChuFamilyController extends Controller
{

    public function index()
    {
        $data = ChuFamily::latest()->get();
        return view('chu_family.index', compact('data'));
    }

    public function create()
    {
        return view('chu_family.create');
    }

    public function store(Request $request)
    {
        ChuFamily::create($request->all());

        return redirect()
            ->route('chu-family.index')
            ->with('success', 'Data created');
    }

    public function edit($id)
    {
        $data = ChuFamily::findOrFail($id);
        return view('chu_family.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $data = ChuFamily::findOrFail($id);
        $data->update($request->all());

        return redirect()
            ->route('chu-family.index')
            ->with('success', 'Data updated');
    }

    public function delete($id)
    {
        ChuFamily::findOrFail($id)->delete();

        return redirect()->back()
            ->with('success', 'Data deleted');
    }

    /*
    | IMPORT
    */
    public function import(Request $request)
    {
        Excel::import(new ChuFamilyImport, $request->file('file'));

        return response()->json(['success' => true]);
    }

    /*
    | TEMPLATE
    */
    public function template()
    {
        return Excel::download(
            new ChuFamilyTemplateExport,
            'template-chu-family.xlsx'
        );
    }

    /*
    | EXPORT REKAP
    */
    public function export()
    {
        return Excel::download(
            new ChuFamilyExport,
            'chu_family.xlsx'
        );
    }
}
