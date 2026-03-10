<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeePayrollController extends Controller
{

    public function index(Request $request)
    {
        $periods = DB::table('payroll_periods')
            ->leftJoin('payroll_runs as pr', 'pr.period_id', '=', 'payroll_periods.id')
            ->orderBy('start_date', 'desc')
            ->get();
        // dd($periods);

        return view('payroll.employee_payroll', [
            'npk' => $request['npk'],
            'periods' => $periods
        ]);
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

        return redirect()->route('employee-payroll.verify-password', [
            'npk' => $npk,
            'password' => $password,
            'run_id' => $run_id
        ]);
    }

    public function showSlip($run_id, $npk)
    {

        $employee = DB::table('payroll_run_details')
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'payroll_run_details.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('run_id', $run_id)
            ->where('employee_npk', $npk)
            ->first();

        $components = json_decode($employee->components, true);

        $componentTypes = DB::table('payroll_components')
            ->pluck('type', 'code');

        $earnings = [];
        $deductions = [];

        foreach ($components as $code => $value) {

            $type = $componentTypes[$code] ?? 'earning';

            if ($type == 'earning') {
                $earnings[$code] = $value;
            } else {
                $deductions[$code] = $value;
            }
        }

        $data = [
            'employee' => $employee,
            'earnings' => $earnings,
            'deductions' => $deductions
        ];

        // dd($data);

        return view('payroll.viewslip', [
            'data' => $data,
            'employee' => $employee,
            'earnings' => $earnings,
            'deductions' => $deductions
        ]);
    }
}
