<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InsentifThreshold;

class InsentifThresholdController extends Controller
{
    public function index()
    {
        $data = InsentifThreshold::orderBy('days')->get();

        return view('insentif_threshold.index', compact('data'));
    }

    public function create()
    {
        return view('insentif_threshold.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'insentif_type' => 'required',
            'days' => 'required|integer',
            'minimum' => 'required|numeric',
            'type' => 'required'
        ]);

        InsentifThreshold::create($request->all());

        return redirect()
            ->route('insentif.threshold.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = InsentifThreshold::findOrFail($id);

        return view('insentif_threshold.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'insentif_type' => 'required',
            'days' => 'required',
            'minimum' => 'required',
            'type' => 'required'
        ]);

        InsentifThreshold::findOrFail($id)
            ->update($request->all());

        return redirect()
            ->route('insentif.threshold.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function delete($id)
    {
        InsentifThreshold::findOrFail($id)->delete();

        return redirect()
            ->back()
            ->with('success', 'Data berhasil dihapus');
    }
}
