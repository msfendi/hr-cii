<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeThrController extends Controller
{

    public function index(Request $request)
    {
        $periods = DB::table('thr_runs as pr')
            ->join('thr_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->select(
                'pr.id',
                'pr.id as run_id',
                'pp.start_date',
                'pp.end_date'
            )
            ->orderBy('pp.start_date', 'desc')
            ->get();

        return view('thr.employee_thr', [
            'npk' => $request->npk,
            'periods' => $periods
        ]);
    }

    public function apiPeriods()
    {
        $periods = DB::table('thr_runs as pr')
            ->leftJoin('thr_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->orderBy('pp.cutoff_date', 'desc')
            ->select(
                'pr.*',
                'pp.cutoff_date',
                'pp.name'
            )
            ->get();

        return response()->json($periods);
    }

    public function verifyPassword(Request $request)
    {

        $birth = DB::table('PKWT')
            ->where('NPK', $request['npk'])
            ->value('TGLLAHIR');

        if (!$birth) {
            return back()->with('error', 'Data tanggal lahir tidak ditemukan');
        }

        $password = date('ymd', strtotime($birth));

        if ($request->password != $password) {
            return back()->with('error', 'Password salah');
        }

        return redirect()->route('view-slip', [$request['run_id'], $request['npk']]);
    }

    public function qrLogin(Request $request)
    {

        $npk = $request->npk;
        $run_id = $request->run_id;

        $data = DB::table('PKWT')
            ->where('NPK', $npk)
            ->first();

        if (!$data) {
            return redirect()->back()->with('error', 'Data tidak ditemukan');
        }

        $password = date('ymd', strtotime($data->TGLLAHIR));

        return redirect()->route('employee-thr.verify-password', [
            'npk' => $npk,
            'password' => $password,
            'run_id' => $run_id
        ]);
    }

    public function showSlip($run_id, $npk)
    {
        /*
    |--------------------------------------------------------------------------
    | GET THR EMPLOYEE
    |--------------------------------------------------------------------------
    */

        $employee = DB::table('thr_run_details as trd')
            ->leftJoin('thr_runs as tr', 'tr.id', '=', 'trd.run_id')
            ->leftJoin('thr_periods as tp', 'tp.id', '=', 'tr.period_id')
            ->leftJoin('BIODATA as b', 'b.NPK', '=', 'trd.employee_npk')
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'b.id_dept')
            ->where('trd.run_id', $run_id)
            ->where('trd.employee_npk', $npk)
            ->select(
                'trd.*',
                'tp.name as period_name',
                'b.NAMA_KARYAWAN as employee_name',
                'd.DEPARTEMENT'
            )
            ->first();

        if (!$employee) {
            abort(404);
        }

        /*
    |--------------------------------------------------------------------------
    | DECODE THR COMPONENTS
    |--------------------------------------------------------------------------
    */

        $components = json_decode($employee->components, true);

        /*
        Contoh components:
        [
            basic_salary,
            allowance,
            working_months,
            thr
        ]
    */

        /*
    |--------------------------------------------------------------------------
    | LOAD PDF
    |--------------------------------------------------------------------------
    */

        $pdf = Pdf::loadView('thr.viewslip', [
            'employee'   => $employee,
            'components' => $components
        ])->setPaper('A4', 'portrait');

        return $pdf->download(
            'SLIP_THR_' .
                $employee->period_name . '_' .
                $employee->employee_npk . '.pdf'
        );
    }
}
