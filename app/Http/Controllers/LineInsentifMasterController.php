<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InsentifMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\InsentifMasterTemplateExport;
use App\Exports\InsentifTemplateExport;
use App\Exports\LineInsentifTemplateExport;
use App\Imports\InsentifImport;
use App\Imports\InsentifMasterImport;
use App\Imports\LineInsentifImport;
use App\Models\InsentifApproval;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;

class LineInsentifMasterController extends Controller
{
    public function index()
    {
        $data = DB::table('line_efficiencies as l')
            ->join('employee_line_assignments as e', function ($join) {
                $join->on('l.line_number', '=', 'e.line_number')
                    ->whereRaw('l.date BETWEEN e.start_date AND COALESCE(e.end_date, l.date)');
            })
            ->select(
                'e.id',
                'e.npk',
                'e.role',
                'e.line_number',
                'l.efficiency',
                'l.date'
            )
            ->orderBy('e.npk')
            ->orderBy('l.date')
            ->get();

        $periods = PayrollPeriod::select('id', 'name')
            ->orderBy('id', 'desc')
            ->get();
        // dd($data);
        return view('line_insentif_master.index', compact('data', 'periods'));
    }

    public function create()
    {
        return view('line_insentif_master.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'type' => 'required',
            'efficiency' => 'nullable|numeric',
            'piece' => 'nullable|numeric',
        ]);

        InsentifMaster::create($request->all());

        return redirect()->route('line-insentif-master.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = InsentifMaster::findOrFail($id);
        return view('line-insentif-master.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'npk' => 'required',
            'type' => 'required',
            'efficiency' => 'nullable|numeric',
            'piece' => 'nullable|numeric',
        ]);

        $data = InsentifMaster::findOrFail($id);
        $data->update($request->all());

        return redirect()->route('line-insentif-master.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $data = InsentifMaster::findOrFail($id);
        $data->delete();

        return redirect()->route('line-insentif-master.index')
            ->with('success', 'Data berhasil dihapus');
    }

    public function template()
    {
        return Excel::download(new LineInsentifTemplateExport, 'template_line_insentif_master.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'period_id'   => 'required|exists:payroll_periods,id',
            'is_insentif' => 'required|in:0,1',
            'file'        => 'required_if:is_insentif,1|mimes:xlsx,xls'
        ]);

        $component = 'sewing_insentif';

        /*
    =====================================
    JIKA INSENTIF → IMPORT EXCEL
    =====================================
    */
        if ($request->is_insentif == 1) {

            Excel::import(
                new LineInsentifImport($request->period_id),
                $request->file('file')
            );
        }

        /*
    =====================================
    GET APPROVAL SETTING
    =====================================
    */

        $setting = DB::table('payroll_settings')
            ->where('component', $component)
            ->first();

        if ($setting) {

            $approvalArray = json_decode($setting->approval, true);

            $approval = [
                json_encode($approvalArray)
            ];

            $waitingStatus = array_fill(
                0,
                count($approvalArray),
                'waiting'
            );

            $progress = [
                [
                    'npk' => json_encode($approvalArray),
                    'status' => json_encode($waitingStatus),
                ]
            ];

            /*
        =====================================
        STATUS AUTO FINISH JIKA NO INSENTIF
        =====================================
        */

            // $status = $request->is_insentif == 1
            //     ? 'pending'
            //     : 'finish';

            InsentifApproval::updateOrCreate(
                [
                    'period_id' => $request->period_id,
                    'payroll_component' => $component
                ],
                [
                    'approval'     => $approval,
                    'progress'     => $progress,
                    'approved_at'  => null,
                    'status'       => 'pending'
                ]
            );
        }

        return back()->with('success', 'Process berhasil dijalankan');
    }
}
