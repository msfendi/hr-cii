<?php

namespace App\Http\Controllers;

use App\Models\ExpatMaster;
use App\Models\ExpatOnleave;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ExpatMasterImport;
use App\Imports\ExpatOnleaveImport;
use App\Exports\ExpatMasterTemplateExport;
use App\Exports\ExpatOnleaveTemplateExport;
use App\Exports\ExpatRekapExport;
use App\Models\ExpatCost;
use App\Models\ExpatCostComponent;
use Illuminate\Support\Facades\DB;

class ExpatController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | MASTER CRUD
    |--------------------------------------------------------------------------
    */

    public function indexMaster()
    {
        $data = ExpatMaster::latest()->get();
        return view('expat_master.index', compact('data'));
    }


    public function createMaster()
    {
        return view('expat_master.create');
    }

    public function storeMaster(Request $request)
    {
        ExpatMaster::updateOrCreate(
            ['npk' => $request->npk],
            $request->all()
        );

        return redirect()
            ->route('expat.master.index')
            ->with('success', 'Expat master berhasil dibuat');
    }

    public function indexOnleave()
    {
        $data = ExpatOnleave::latest()->get();

        $components = DB::table('expat_cost_components')
            ->pluck('component', 'id');

        foreach ($data as $row) {

            // COMPONENT ARRAY
            $row->component_array = is_array($row->component)
                ? $row->component
                : json_decode($row->component, true) ?? [];

            // AMOUNT ARRAY
            $row->amount_array = is_array($row->amount)
                ? $row->amount
                : json_decode($row->amount, true) ?? [];

            // COMPONENT NAME
            $row->component_name = collect($row->component_array)
                ->map(fn($id) => $components[$id] ?? $id)
                ->values()
                ->toArray();

            // TRANSACTION DATE
            $row->transactions_date = is_array($row->transactions_date)
                ? $row->transactions_date
                : json_decode($row->transactions_date, true) ?? [];
        }

        // dd($row->component_name, $row->amount_array, $row->transactions_date);

        return view('expat_onleave.index', compact('data'));
    }

    public function createOnLeave()
    {
        $components = ExpatCostComponent::orderBy('component')->get();

        return view('expat_onleave.create', compact('components'));
    }

    public function storeOnleave(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'component' => 'required|array',
            'amount' => 'required|array',
            'onleave_start' => 'required|date',
            'onleave_end' => 'required|date',
            'leave_type' => 'required',
            'transactions_date' => 'required|array',
        ]);

        ExpatOnleave::create([
            'npk' => $request->npk,
            'onleave_start' => $request->onleave_start,
            'onleave_end' => $request->onleave_end,
            'leave_type' => $request->leave_type,

            // SAVE ARRAY AS JSON
            'component' => $request->component,
            'amount' => $request->amount,

            'transactions_date' => $request->transactions_date,
            'remark' => $request->remark,
        ]);

        return redirect()
            ->route('expat.onleave.index')
            ->with('success', 'Expat On Leave saved successfully');
    }

    public function indexCost()
    {
        $data = ExpatCost::latest()
            ->leftJoin(
                'expat_cost_components',
                'expat_cost.component',
                '=',
                'expat_cost_components.id'
            )
            ->select(
                'expat_cost.*',
                'expat_cost_components.component as component_name'
            )
            ->get();

        // dd($data);

        return view('expat_cost.index', compact('data'));
    }

    public function createCost()
    {
        $components = ExpatCostComponent::orderBy('component')->get();

        return view('expat_cost.create', compact('components'));
    }

    public function storeCost(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'component' => 'required',
            'amount' => 'required|numeric',
            'transactions_date' => 'required|date',
            'remark' => 'nullable'
        ]);

        ExpatCost::create([
            'npk' => $request->npk,
            'component' => $request->component,
            'amount' => $request->amount,
            'transactions_date' => $request->transactions_date,
            'remark' => $request->remark,
        ]);

        return redirect()
            ->route('expat.cost.create')
            ->with('success', 'Expat Cost saved successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT
    |--------------------------------------------------------------------------
    */

    public function importMaster(Request $request)
    {
        Excel::import(new ExpatMasterImport, $request->file('file'));

        return back()->with('success', 'Import success');
    }

    public function importOnleave(Request $request)
    {
        Excel::import(new ExpatOnleaveImport, $request->file('file'));

        return back()->with('success', 'Import success');
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT TEMPLATE
    |--------------------------------------------------------------------------
    */

    public function templateMaster()
    {
        return Excel::download(
            new ExpatMasterTemplateExport,
            'expat_master_template.xlsx'
        );
    }

    public function templateOnleave()
    {
        return Excel::download(
            new ExpatOnleaveTemplateExport,
            'expat_onleave_template.xlsx'
        );
    }

    public function exportRekap(Request $request)
    {
        $start = $request->start_date;
        $end   = $request->end_date;
        $filename = 'Expat_Rekap_' . date('Ymd_His') . '.xlsx';

        return Excel::download(
            new ExpatRekapExport($start, $end),
            $filename
        );
    }
}
