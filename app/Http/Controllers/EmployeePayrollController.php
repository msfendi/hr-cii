<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

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

        $employee = DB::table('payroll_run_details as prd')
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->leftJoin('BIODATA as b', 'b.NPK', '=', 'prd.employee_npk')
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'b.id_dept')
            ->where('prd.run_id', $run_id)
            ->where('prd.employee_npk', $npk)
            ->select(
                'prd.*',
                'pp.id as period_id',
                'pp.name as period_name',
                'b.NAMA_KARYAWAN as employee_name',
                'b.BARCODE',
                'd.DEPARTEMENT'
            )
            ->first();
        // dd($employee);

        if (!$employee) {
            abort(404);
        }

        /*
    |--------------------------------------------------------------------------
    | Payroll Components
    |--------------------------------------------------------------------------
    */

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

        /*
|--------------------------------------------------------------------------
| Attendance (1 bulan sesuai periode payroll)
|--------------------------------------------------------------------------
*/

        $period = DB::table('payroll_periods')
            ->where('id', $employee->period_id)
            ->first();

        // dd($period);

        $startDate = Carbon::parse($period->start_date);
        $endDate   = Carbon::parse($period->end_date);

        $logs = DB::table('att_log')
            ->where('pin', $employee->BARCODE)
            ->whereBetween('scan_date', [$startDate, $endDate])
            ->orderBy('scan_date')
            ->get();

        $attendance = [];

        $dates = CarbonPeriod::create($startDate, $endDate);

        foreach ($dates as $date) {

            $dailyLogs = $logs->filter(function ($log) use ($date) {
                return Carbon::parse($log->scan_date)->format('Y-m-d') == $date->format('Y-m-d');
            });

            $jamMasuk = null;
            $jamPulang = null;

            if ($dailyLogs->count() > 0) {

                $first = Carbon::parse($dailyLogs->first()->scan_date);
                $last  = Carbon::parse($dailyLogs->last()->scan_date);

                // jika hanya 1 scan
                if ($dailyLogs->count() == 1) {

                    if ($first->hour < 12) {
                        $jamMasuk = $first->format('H:i');
                    } else {
                        $jamPulang = $first->format('H:i');
                    }
                } else {

                    $jamMasuk = $first->format('H:i');
                    $jamPulang = $last->format('H:i');
                }
            }

            $attendance[] = (object)[
                'tanggal' => $date->format('Y-m-d'),
                'jam_masuk' => $jamMasuk,
                'jam_pulang' => $jamPulang
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | Return View
    |--------------------------------------------------------------------------
    */

        return view('payroll.viewslip', [
            'employee' => $employee,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'attendance' => $attendance
        ]);
    }
}
