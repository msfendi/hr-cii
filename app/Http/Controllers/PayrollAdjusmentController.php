<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PayrollAdjusment;
use Illuminate\Support\Facades\DB;

class PayrollAdjusmentController extends Controller
{
    /**
     * LIST DATA
     */
    public function index()
    {
        $employeeQuery = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            ->union(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            );

        $data = PayrollAdjusment::query()
            ->with('period')
            ->leftJoinSub($employeeQuery, 'employees', function ($join) {
                $join->on('payroll_adjusments.npk', '=', 'employees.NPK');
            })
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'employees.ID_DEPT')
            ->select(
                'payroll_adjusments.*',
                'employees.NAMA_KARYAWAN',
                'd.DEPARTEMENT'
            )
            ->orderBy('payroll_adjusments.id')
            ->get();

        return view('payroll_adjusments.index', compact('data'));
    }

    /**
     * FORM CREATE
     */
    public function create()
    {
        $periods = DB::table('payroll_periods')->where('is_closed', 0)->get();

        $employees = DB::select("
        SELECT NPK, NAMA_KARYAWAN FROM BIODATA
        UNION
        SELECT NPK, NAMA_KARYAWAN FROM BIODATA_KELUAR
        ORDER BY NPK
    ");

        return view('payroll_adjusments.create', compact(
            'periods',
            'employees'
        ));
    }

    /**
     * STORE DATA
     */
    public function store(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'period_id' => 'required',
            'adjusment' => 'required|numeric',
            'keterangan' => 'required',
        ]);

        PayrollAdjusment::updateOrCreate(
            [
                'npk'       => $request->npk,
                'period_id' => $request->period_id,
            ],
            [
                'adjusment' => $request->adjusment,
                'keterangan' => $request->keterangan,
            ]
        );

        return redirect()
            ->route('payroll-adjusments.index')
            ->with('success', 'Adjusment berhasil disimpan');
    }

    /**
     * FORM EDIT
     */
    public function edit($id)
    {
        $data = PayrollAdjusment::findOrFail($id);
        $periods = DB::table('payroll_periods')->get();

        return view('payroll_adjusments.edit', compact('data', 'periods'));
    }

    /**
     * UPDATE DATA
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'npk' => 'required',
            'period_id' => 'required',
            'adjusment' => 'required|numeric',
            'keterangan' => 'required',
        ]);

        $data = PayrollAdjusment::findOrFail($id);
        $data->update($request->all());

        return redirect()
            ->route('payroll-adjusments.index')
            ->with('success', 'Adjusment berhasil diupdate');
    }

    /**
     * DELETE DATA
     */
    public function destroy($id)
    {
        PayrollAdjusment::findOrFail($id)->delete();

        return redirect()
            ->route('payroll-adjusments.index')
            ->with('success', 'Adjusment berhasil dihapus');
    }
}
