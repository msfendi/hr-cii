<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Cmixin\BusinessDay;

class EmployeePayrollController extends Controller
{

    public function index(Request $request)
    {
        $periods = DB::table('payroll_runs as pr')
            ->join('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->select(
                'pr.id',
                'pr.id as run_id',
                'pp.start_date',
                'pp.end_date'
            )
            ->orderBy('pp.start_date', 'desc')
            ->get();

        return view('payroll.employee_payroll', [
            'npk' => $request->npk,
            'periods' => $periods
        ]);
    }

    public function apiPeriods()
    {
        $periods = DB::table('payroll_runs as pr')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->orderBy('pp.start_date', 'desc')
            ->select(
                'pr.*',
                'pp.start_date',
                'pp.end_date',
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

        $overtimeRaw = DB::table('overtimes')
            ->where('NPK', $employee->employee_npk)
            ->whereBetween('OVERTIME_DATE', [$startDate, $endDate])
            ->select('OVERTIME_DATE', 'JUMLAH_JAM_LEMBUR')
            ->get();

        // dd($overtimeRaw);

        $overtimes = [];

        foreach ($overtimeRaw as $ot) {
            $key = Carbon::parse($ot->OVERTIME_DATE)->format('Y-m-d');
            $overtimes[$key] = trim($ot->JUMLAH_JAM_LEMBUR);
        }

        $summary = [
            'hadir' => 0,
            'absent' => 0,
            'lembur_resmi' => 0,
            'lembur_khusus' => 0,
            'status' => []
        ];

        $attendance = [];

        $dates = CarbonPeriod::create($startDate, $endDate);

        $holidays = DB::table('holidays')
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->pluck('holiday_date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        foreach ($dates as $date) {

            $tanggal = $date->format('Y-m-d');

            $dailyLogs = $logs->filter(
                fn($log) =>
                Carbon::parse($log->scan_date)->format('Y-m-d') == $tanggal
            );

            $jamMasuk = null;
            $jamPulang = null;
            $status = '';
            $overtime = null;

            $lembur = $overtimes[$tanggal] ?? null;

            $isWeekend = $date->isWeekend();
            $isHoliday = in_array($tanggal, $holidays);
            $isWorkday = !($isWeekend || $isHoliday);

            $hasLog = $dailyLogs->count() > 0;
            $isNumericOT = is_numeric($lembur);

            /*
    ======================================================
    GROUP STATUS IZIN
    ======================================================
    */
            $izinCodes = ['MA', 'BR', 'PE', 'SI', 'CT', 'H'];

            if (in_array($lembur, $izinCodes)) {

                $jamMasuk = '-';
                $jamPulang = '-';

                $status = $lembur;

                if (!isset($summary['status'][$status])) {
                    $summary['status'][$status] = 0;
                }
                $summary['status'][$status]++;

                if ($isWorkday) {
                    $summary['absent']++;
                }
            }

            /*
    ======================================================
    ADA FINGERPRINT
    ======================================================
    */ elseif ($hasLog) {

                $first = Carbon::parse($dailyLogs->first()->scan_date);
                $last  = Carbon::parse($dailyLogs->last()->scan_date);

                if ($dailyLogs->count() == 1) {

                    if ($first->hour < 12) {
                        $jamMasuk = $first->format('H:i');
                        if ($first->hour >= 8) {
                            $status = 'Terlambat';
                        } else {
                            $status = 'Scan Masuk';
                        }
                    } else {
                        $jamPulang = $first->format('H:i');
                        $status = 'Scan Pulang';
                    }
                } else {

                    $jamMasuk = $first->format('H:i');
                    $jamPulang = $last->format('H:i');
                    if ($first->hour >= 8) {
                        $status = 'Terlambat';
                    } else {
                        $status = 'Hadir';
                    }
                }

                if ($isWorkday) {
                    $summary['hadir']++;
                }
            }

            /*
    ======================================================
    TIDAK ADA FINGERPRINT
    ======================================================
    */ else {

                if ($lembur === 'IN') {

                    $status = 'Masuk (Finger tidak terbaca)';

                    if ($isWorkday) {
                        $summary['hadir']++;
                    }
                } elseif ($isNumericOT) {

                    $status = 'Lembur';

                    // ⭐ lembur hari libur TIDAK dihitung hadir
                    if ($isWorkday) {
                        $summary['hadir']++;
                    }
                } elseif ($isWeekend || $isHoliday) {

                    $status = 'Holiday';
                } else {

                    $status = 'Tidak Masuk';
                    $summary['absent']++;
                }
            }

            /*
    ======================================================
    HITUNG OVERTIME
    ======================================================
    */
            if ($isNumericOT) {

                $overtime = (float)$lembur;

                if ($isWeekend || $isHoliday) {
                    $summary['lembur_khusus'] += $overtime;
                } else {
                    $summary['lembur_resmi'] += $overtime;
                }
            }

            $attendance[] = (object)[
                'tanggal' => $tanggal,
                'jam_masuk' => $jamMasuk,
                'jam_pulang' => $jamPulang,
                'status' => $status,
                'overtime' => $overtime,
                'is_holiday' => ($isWeekend || $isHoliday),
            ];
        }
        // dd($attendance);

        /*
        |--------------------------------------------------------------------------
        | Return View
        |--------------------------------------------------------------------------
        */

        $pdf = Pdf::loadView('payroll.viewslip', [
            'employee' => $employee,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'attendance' => $attendance,
            'summary' => $summary,
            'holidays' => $holidays
        ])
            ->setPaper('A4', 'portrait');

        return $pdf->download('SLIP_' . $employee->period_name . '_' . $employee->employee_npk . '.pdf');

        // return view('payroll.viewslip', [
        //     'employee' => $employee,
        //     'earnings' => $earnings,
        //     'deductions' => $deductions,
        //     'attendance' => $attendance,
        //     'summary' => $summary,
        //     'holidays' => $holidays
        // ]);
    }
}
