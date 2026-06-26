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
use RealRashid\SweetAlert\Facades\Alert;

class ExpatController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | MASTER CRUD
    |--------------------------------------------------------------------------
    */

    public function indexMaster()
    {
        $data = ExpatMaster::select('expat_master.*', 'PKWT.TGLLAHIR')->leftJoin('PKWT', 'expat_master.npk', '=', 'PKWT.NPK')->get();
        return view('expat_master.index', compact('data'));
    }


    public function createMaster()
    {
        $employees = DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NAMA_KARYAWAN')
            ->get();
        return view('expat_master.create', compact('employees'));
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
        $data = ExpatOnleave::leftJoin('BIODATA', 'expat_onleave.npk', '=', 'BIODATA.NPK')
            ->select('expat_onleave.*', 'BIODATA.NAMA_KARYAWAN')
            ->latest('expat_onleave.created_at')
            ->get();

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

            // ✅ TOTAL AMOUNT
            $row->total_amount = collect($row->amount_array)
                ->map(fn($val) => (float) $val)
                ->sum();

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

        return view('expat_onleave.index', compact('data'));
    }

    public function createOnLeave()
    {
        $components = ExpatCostComponent::orderBy('component')->get();
        $employees = DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NAMA_KARYAWAN')
            ->get();

        return view('expat_onleave.create', compact('components', 'employees'));
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
            ->leftJoin(
                'BIODATA',
                'expat_cost.npk',
                '=',
                'BIODATA.NPK'
            )
            ->select(
                'expat_cost.*',
                'expat_cost_components.component as component_name',
                'BIODATA.NAMA_KARYAWAN as NAMA_KARYAWAN'
            )
            ->get();

        // dd($data);

        return view('expat_cost.index', compact('data'));
    }

    public function createCost()
    {
        $employees = DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NAMA_KARYAWAN')
            ->get();
        $components = ExpatCostComponent::orderBy('component')->get();

        return view('expat_cost.create', compact('components', 'employees'));
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
            ->route('expat.cost.index')
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

    public function deleteMaster($id)
    {
        ExpatMaster::findOrFail($id)->delete();

        Alert::success('Expat Master deleted successfully!');
        return back();
    }

    public function deleteOnleave($id)
    {
        ExpatOnleave::findOrFail($id)->delete();

        Alert::success('Expat On Leave deleted successfully!');
        return back();
    }

    public function deleteCost($id)
    {
        ExpatCost::findOrFail($id)->delete();

        Alert::success('Expat Cost deleted successfully!');
        return back();
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function editMaster($id)
    {
        $data = ExpatMaster::findOrFail($id);
        $employees = DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NAMA_KARYAWAN')
            ->get();

        return view('expat_master.edit', compact('data', 'employees'));
    }

    public function editOnLeave($id)
    {
        $data = ExpatOnleave::findOrFail($id);
        $employees = DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NAMA_KARYAWAN')
            ->get();
        $components = ExpatCostComponent::orderBy('component')->get();

        return view('expat_onleave.edit', compact('data', 'employees', 'components'));
    }


    public function editCost($id)
    {
        $data = ExpatCost::findOrFail($id);
        $employees = DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NAMA_KARYAWAN')
            ->get();
        $components = ExpatCostComponent::orderBy('component')->get();

        return view('expat_cost.edit', compact('data', 'employees', 'components'));
    }

    public function updateCost(Request $request, $id)
    {
        $request->validate([
            'npk' => 'required',
            'component' => 'required',
            'amount' => 'required|numeric',
            'transactions_date' => 'required|date',
            'remark' => 'nullable'
        ]);

        $data = ExpatCost::findOrFail($id);
        $data->update([
            'npk' => $request->npk,
            'component' => $request->component,
            'amount' => $request->amount,
            'transactions_date' => $request->transactions_date,
            'remark' => $request->remark,
        ]);

        return redirect()
            ->route('expat.cost.index')
            ->with('success', 'Expat Cost updated successfully');
    }

    public function updateOnleave(Request $request, $id)
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

        $data = ExpatOnleave::findOrFail($id);
        $data->update([
            'npk' => $request->npk,
            'onleave_start' => $request->onleave_start,
            'onleave_end' => $request->onleave_end,
            'leave_type' => $request->leave_type,
            'component' => $request->component,
            'amount' => $request->amount,
            'transactions_date' => $request->transactions_date,
            'remark' => $request->remark,
        ]);

        return redirect()
            ->route('expat.onleave.index')
            ->with('success', 'Expat On Leave updated successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function updateMaster(Request $request, $id)
    {
        DB::table('expat_master')
            ->where('id', $id)
            ->update([
                'npk' => $request->npk,
                'name' => $request->name,
                'position' => $request->position,
                'joining_date' => $request->joining_date,
                'end_date' => $request->end_date,
                'passport_number' => $request->passport_number,
                'passport_expiry' => $request->passport_expiry,
                'kitas_expiry' => $request->kitas_expiry,
                'rptka_expiry' => $request->rptka_expiry,
                'merp_expiry' => $request->merp_expiry,
                'house_address' => $request->house_address,
                'house_startdate' => $request->house_startdate,
                'lease_enddate' => $request->lease_enddate,
                'place' => $request->place,
                'nationality' => $request->nationality,
                'direct_report' => $request->direct_report,
                'npwp' => $request->npwp,
                'updated_at' => now()
            ]);

        return redirect()
            ->route('expat.master.index')
            ->with('success', 'Expat Master Updated');
    }
}
