<?php

namespace App\Http\Controllers;

use App\Models\PayrollComponent;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class PayrollComponentController extends Controller
{
    public function index()
    {
        $components = PayrollComponent::all();
        return view('payroll_components.index', compact('components'));
    }


    public function create()
    {
        return view('payroll_components.create');
    }

    public function store(Request $request)
    {
        PayrollComponent::create([
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'calculation_method' => $request->calculation_method,
            'value' => $request->value,
            'formula' => $request->formula,
            'description' => $request->description,
            'category' => $request->category,
            'priority' => $request->priority,
            'is_taxable' => $request->is_taxable,
            'is_active' => $request->is_active,
        ]);

        Alert::success('Create Successfully!', 'Component ' . $request->name . ' successfully created!');
        return redirect()
            ->route('payroll-components.index');
    }

    public function edit($id)
    {
        $components = PayrollComponent::find($id);
        return view('payroll_components.update', compact('components'));
    }

    public function update(Request $request)
    {
        $component = PayrollComponent::findOrFail($request->id);

        $component->fill([
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'calculation_method' => $request->calculation_method,
            'value' => $request->value,
            'formula' => $request->formula,
            'description' => $request->description,
            'category' => $request->category,
            'priority' => $request->priority,
            'is_taxable' => $request->is_taxable,
            'is_active' => $request->is_active,
        ]);

        $component->save();

        Alert::success('Update Successfully!', 'Payroll Component ' . $component->name . ' successfully updated!');
        return redirect('payroll-components/index');
    }
}
