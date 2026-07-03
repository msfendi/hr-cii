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
            ->where('pp.is_closed', '=', 1)
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
            ->where('pp.is_closed', '=', 1)
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
            ->where('NPK', $request->npk)
            ->value('TGLLAHIR');

        if (!$birth) {
            return response()->json([
                'status' => false,
                'message' => 'Data tanggal lahir tidak ditemukan'
            ]);
        }

        $password = date('ymd', strtotime($birth));

        if ($request->password != $password) {
            return response()->json([
                'status' => false,
                'message' => 'Password salah'
            ]);
        }

        return response()->json([
            'status' => true,
            'redirect' => route(
                'employee-payroll.view-slip',
                [$request->run_id, $request->npk, $password]
            )
        ]);
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

        $birth = DB::table('PKWT')
            ->where('NPK', $npk)
            ->value('TGLLAHIR');

        $password = date('ymd', strtotime($birth));
        $biodataUnion = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'), 'IS_EXPAT')
            ->unionAll(
                DB::connection('cii')
                    ->table('BIODATA_KELUAR')
                    ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'), 'IS_EXPAT')
            );

        $employee = DB::table('payroll_run_details as prd')
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->leftJoinSub($biodataUnion, 'b', function ($join) {
                $join->on('b.NPK', '=', 'prd.employee_npk');
            })
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'prd.employee_dept')
            ->where('prd.run_id', $run_id)
            ->where('prd.employee_npk', $npk)
            ->select(
                'prd.*',
                'pp.id as period_id',
                'pp.name as period_name',
                'b.NAMA_KARYAWAN as employee_name',
                'b.BARCODE',
                'b.IS_STAFF',
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

        $components = json_decode($employee->components, true) ?? [];

        $componentTypes = DB::table('payroll_components')
            ->pluck('type', 'code');

        $earnings = [];
        $deductions = [];

        /*
|--------------------------------------------------------------------------
| NORMALISASI COMPONENTS
|--------------------------------------------------------------------------
| Format baru: { code: { amount, type } }
| Format lama: { code: angka_scalar }
| Type diprioritaskan dari data itu sendiri (sudah akurat & konsisten
| dengan hasil generate payroll terbaru), fallback ke lookup
| payroll_components jika data lama / type tidak ada.
|--------------------------------------------------------------------------
*/

        $late_minutes = 0;

        foreach ($components as $code => $value) {

            if (is_array($value) && array_key_exists('amount', $value)) {
                $amount = (float) $value['amount'];
                $type   = $value['type'] ?? ($componentTypes[$code] ?? 'earning');
            } else {
                $amount = (float) $value;
                $type   = $componentTypes[$code] ?? 'earning';
            }

            if ($type === 'earning') {
                $earnings[$code] = $amount;
            } else {
                $deductions[$code] = $amount;
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



        $ijinSummary = DB::table('ijin_meninggalkan_pekerjaans')
            ->selectRaw("
        npk,
        SUM(
            CASE
                WHEN jam_kembali IS NOT NULL
                THEN DATEDIFF(MINUTE, jam_keluar, jam_kembali)
                ELSE 0
            END
        ) as total_ijin_minutes
    ")
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->where('npk', $npk)
            ->groupBy('npk')
            ->get()
            ->keyBy('npk');

        $ijinDetails = DB::table('ijin_meninggalkan_pekerjaans')
            ->selectRaw("
        ijin_meninggalkan_pekerjaans.npk,
        NAMA_KARYAWAN,
        DEPARTEMENT,
        tanggal,
        jam_keluar,
        rencana_kembali,
        jam_kembali,
        reason,
        CASE 
            WHEN jam_kembali IS NOT NULL 
            THEN DATEDIFF(MINUTE, jam_keluar, jam_kembali)
            ELSE 0 
        END as ijin_minutes
    ")
            ->leftJoin('BIODATA', 'BIODATA.NPK', '=', 'ijin_meninggalkan_pekerjaans.npk')
            ->leftJoin('DEPT', 'DEPT.ID_DEPT', '=', 'BIODATA.ID_DEPT')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->where('ijin_meninggalkan_pekerjaans.npk', $npk)
            ->orderBy('tanggal', 'asc')
            ->get()
            ->groupBy('npk');

        $payrollAdjustmentDetails = DB::table('payroll_adjusments as pa')
            ->where('pa.period_id', $period->id)
            ->where('npk', $npk)
            ->select(
                'pa.*',
            )
            ->orderBy('pa.npk')
            ->orderBy('pa.id')
            ->get()
            ->groupBy('npk');

        // dd($ijinDetails);

        $logs = DB::table('att_log')
            ->where('pin', $employee->BARCODE)
            ->where('sn', '!=', '66208026030047') // EXCLUDE SN REGISTER
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
        $late_minutes = 0;

        $isStaff = ($employee->IS_STAFF == '1' || $employee->IS_STAFF === 1);
        $dates = CarbonPeriod::create($startDate, $endDate);

        $holidays = DB::table('holidays')
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->pluck('holiday_date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        foreach ($dates as $date) {

            $tanggal = $date->format('Y-m-d');

            /*
    ======================================================
    AMBIL SHIFT (ANCHOR)
    ======================================================
    */
            $shift = DB::connection('cii')
                ->table('employee_shifts as es')
                ->join('shifts as s', 's.id', '=', 'es.shift_id')
                ->where('es.npk', $employee->employee_npk)
                ->whereDate('es.shift_date', $tanggal)
                ->select('s.name', 's.work_start', 's.work_end')
                ->first();

            /*
    ======================================================
    FALLBACK SHIFT NORMAL
    ======================================================
    */
            if (!$shift) {
                $shift = (object)[
                    'name' => 'NORMAL',
                    'work_start' => '08:00:00',
                    'work_end'   => '17:00:00',
                ];
            }

            $workStart = Carbon::parse($shift->work_start);
            $workEnd   = Carbon::parse($shift->work_end);

            /*
    ======================================================
    DETECT NIGHT SHIFT
    ======================================================
    */
            $isNightShift = $workStart->gt($workEnd);

            /*
    ======================================================
    BUILD SHIFT RANGE
    ======================================================
    */
            if ($isNightShift) {

                $shiftStartDT = Carbon::parse($tanggal)
                    ->setTimeFrom($workStart);

                $shiftEndDT = Carbon::parse($tanggal)
                    ->addDay()
                    ->setTimeFrom($workEnd);

                $dailyLogs = $logs->filter(function ($log) use ($shiftStartDT, $shiftEndDT) {
                    $scan = Carbon::parse($log->scan_date);
                    return $scan->between($shiftStartDT, $shiftEndDT);
                });
            } else {

                $shiftStartDT = Carbon::parse($tanggal)
                    ->setTimeFrom($workStart);

                $shiftEndDT = Carbon::parse($tanggal)
                    ->setTimeFrom($workEnd);

                $dailyLogs = $logs->filter(
                    fn($log) =>
                    Carbon::parse($log->scan_date)->format('Y-m-d') == $tanggal
                );
            }

            /*
    ======================================================
    ORIGINAL ATTENDANCE LOGIC
    ======================================================
    */

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
    IZIN
    ======================================================
    */
            $izinCodes = ['MA', 'BR', 'P1', 'SD', 'CT', 'H', 'OUT'];

            if (in_array($lembur, $izinCodes)) {

                $jamMasuk = '-';
                $jamPulang = '-';
                $status = $lembur;

                $summary['status'][$status] =
                    ($summary['status'][$status] ?? 0) + 1;

                if ($isWorkday) {
                    $summary['absent']++;
                }
            }

            /*
            ======================================================
            LIBUR / WEEKEND TAPI ADA LEMBUR (CEK SEBELUM FINGERPRINT)
            ======================================================
            | Hari weekend / libur nasional dengan data lembur numerik
            | harus tetap berstatus "Lembur" walaupun ada fingerprint
            | scan — karyawan datang untuk lembur, bukan shift normal,
            | sehingga late-detection tidak relevan dan tidak boleh
            | menimpa status ini.
            ======================================================
            */ elseif (($isWeekend || $isHoliday) && $isNumericOT) {

                if ($hasLog) {

                    $dailyLogs = $dailyLogs->sortBy('scan_date')->values();

                    $jamMasuk  = Carbon::parse($dailyLogs->first()->scan_date)->format('H:i');
                    $jamPulang = Carbon::parse($dailyLogs->last()->scan_date)->format('H:i');
                }

                $status = 'Lembur';
            }

            /*
            ======================================================
            ADA FINGERPRINT
            ======================================================
            */ elseif ($hasLog) {

                $dailyLogs = $dailyLogs->sortBy('scan_date')->values();

                $last = Carbon::parse($dailyLogs->last()->scan_date);

                /*
|--------------------------------------------------------------------------
| Cari scan masuk terbaik
| Ambil log yang paling dekat dengan jam mulai shift,
| tetapi jangan menggunakan data terakhir (karena diasumsikan scan pulang).
|--------------------------------------------------------------------------
*/
                $bestIn = null;
                $bestDiff = PHP_INT_MAX;

                foreach ($dailyLogs as $index => $log) {

                    // skip data terakhir
                    if ($index == ($dailyLogs->count() - 1)) {
                        continue;
                    }

                    $scan = Carbon::parse($log->scan_date);

                    $diff = abs($scan->diffInSeconds($shiftStartDT, false));

                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $bestIn = $scan;
                    }
                }

                // kalau hanya ada 1 log atau tidak ditemukan
                if (!$bestIn) {
                    $bestIn = Carbon::parse($dailyLogs->first()->scan_date);
                }

                $first = $bestIn;

                $isLate = $isStaff && $first->gt(
                    $shiftStartDT->copy()->addMinutes(5)
                );

                /*
        ======================================================
        ⭐ SINGLE SCAN SMART DETECTION ⭐
        ======================================================
        */
                if ($dailyLogs->count() == 1) {

                    $scan = $first;

                    $distanceStart = abs($scan->diffInSeconds($shiftStartDT, false));
                    $distanceEnd   = abs($scan->diffInSeconds($shiftEndDT, false));

                    if ($distanceEnd < $distanceStart) {

                        // ✅ lebih dekat ke pulang
                        $jamPulang = $scan->format('H:i');
                        $status = 'Scan Pulang';
                    } else {

                        // ✅ lebih dekat ke masuk
                        $jamMasuk = $scan->format('H:i');

                        $isLateSingle = $isStaff && $scan->gt(
                            $shiftStartDT->copy()->addMinutes(5)
                        );

                        $status = $isLateSingle
                            ? 'Terlambat'
                            : 'Scan Masuk';

                        if ($isLateSingle) {
                            $late_minutes += $shiftStartDT->copy()
                                ->addMinutes(5)
                                ->diffInMinutes($scan);
                        }
                    }
                } else {

                    /*
MULTI SCAN
*/
                    $jamMasuk  = $first->format('H:i');
                    $jamPulang = $last->format('H:i');

                    $status = $isLate
                        ? 'Terlambat'
                        : 'Hadir';

                    if ($isLate) {
                        $late_minutes += $shiftStartDT->copy()
                            ->addMinutes(5)
                            ->diffInMinutes($first);
                    }
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

                    if ($isWorkday) {
                        $summary['hadir']++;
                    }
                } elseif ($isWeekend || $isHoliday) {

                    $status = 'Libur';
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
            'holidays' => $holidays,
            'late_minutes' => $late_minutes,
            'ijin_details' => ($ijinDetails[$npk] ?? collect())->values(),
            'adjusment_details' => ($payrollAdjustmentDetails[$npk] ?? collect())->values(),
            'total_ijin' => optional($ijinSummary->get($npk))->total_ijin_minutes ?? 0,
        ])
            ->setPaper('A4', 'portrait');

        // SET PASSWORD
        $pdf->getDomPDF()->getCanvas()->get_cpdf()
            ->setEncryption($password, $password, ['print', 'copy']);

        return $pdf->download('SLIP_' . $employee->period_name . '_' . $employee->employee_npk . '.pdf');

        // return view('payroll.viewslip', [
        //     'employee' => $employee,
        //     'earnings' => $earnings,
        //     'deductions' => $deductions,
        //     'attendance' => $attendance,
        //     'summary' => $summary,
        //     'holidays' => $holidays,
        //     'late_minutes' => $late_minutes,
        //     'ijin_details' => ($ijinDetails[$npk] ?? collect())->values(),
        //     'adjusment_details' => ($payrollAdjustmentDetails[$npk] ?? collect())->values(),
        //     'total_ijin' => optional($ijinSummary->get($npk))->total_ijin_minutes ?? 0,
        // ]);
    }

    public function showSlipAudit($run_id, $npk)
    {

        $birth = DB::table('PKWT')
            ->where('NPK', $npk)
            ->value('TGLLAHIR');

        $password = date('ymd', strtotime($birth));

        $biodataUnion = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'), 'IS_EXPAT')
            ->unionAll(
                DB::connection('cii')
                    ->table('BIODATA_KELUAR')
                    ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'), 'IS_EXPAT')
            );

        $employee = DB::table('payroll_run_details as prd')
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->leftJoinSub($biodataUnion, 'b', function ($join) {
                $join->on('b.NPK', '=', 'prd.employee_npk');
            })
            ->leftJoin('DEPT as d', 'd.id_dept', '=', 'prd.employee_dept')
            ->where('prd.run_id', $run_id)
            ->where('prd.employee_npk', $npk)
            ->select(
                'prd.*',
                'pp.id as period_id',
                'pp.name as period_name',
                'b.NAMA_KARYAWAN as employee_name',
                'b.BARCODE',
                'b.IS_STAFF',
                'd.DEPARTEMENT'
            )
            ->first();

        if (!$employee) {
            abort(404);
        }

        /*
    |--------------------------------------------------------------------------
    | Payroll Components (SAMA PERSIS DENGAN showSlip)
    |--------------------------------------------------------------------------
    */

        $components = json_decode($employee->components, true) ?? [];

        $componentTypes = DB::table('payroll_components')
            ->pluck('type', 'code');

        $earnings = [];
        $deductions = [];

        foreach ($components as $code => $value) {

            if (is_array($value) && array_key_exists('amount', $value)) {
                $amount = (float) $value['amount'];
                $type   = $value['type'] ?? ($componentTypes[$code] ?? 'earning');
            } else {
                $amount = (float) $value;
                $type   = $componentTypes[$code] ?? 'earning';
            }

            if ($type === 'earning') {
                $earnings[$code] = $amount;
            } else {
                $deductions[$code] = $amount;
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

        $startDate = Carbon::parse($period->start_date);
        $endDate   = Carbon::parse($period->end_date);

        $ijinSummary = DB::table('ijin_meninggalkan_pekerjaans')
            ->selectRaw("
            npk,
            SUM(
                CASE
                    WHEN jam_kembali IS NOT NULL
                    THEN DATEDIFF(MINUTE, jam_keluar, jam_kembali)
                    ELSE 0
                END
            ) as total_ijin_minutes
        ")
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->where('npk', $npk)
            ->groupBy('npk')
            ->get()
            ->keyBy('npk');

        $ijinDetails = DB::table('ijin_meninggalkan_pekerjaans')
            ->selectRaw("
            ijin_meninggalkan_pekerjaans.npk,
            NAMA_KARYAWAN,
            DEPARTEMENT,
            tanggal,
            jam_keluar,
            rencana_kembali,
            jam_kembali,
            reason,
            CASE 
                WHEN jam_kembali IS NOT NULL 
                THEN DATEDIFF(MINUTE, jam_keluar, jam_kembali)
                ELSE 0 
            END as ijin_minutes
        ")
            ->leftJoin('BIODATA', 'BIODATA.NPK', '=', 'ijin_meninggalkan_pekerjaans.npk')
            ->leftJoin('DEPT', 'DEPT.ID_DEPT', '=', 'BIODATA.ID_DEPT')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->where('ijin_meninggalkan_pekerjaans.npk', $npk)
            ->orderBy('tanggal', 'asc')
            ->get()
            ->groupBy('npk');

        $payrollAdjustmentDetails = DB::table('payroll_adjusments as pa')
            ->where('pa.period_id', $period->id)
            ->where('npk', $npk)
            ->select('pa.*')
            ->orderBy('pa.npk')
            ->orderBy('pa.id')
            ->get()
            ->groupBy('npk');

        /*
    |--------------------------------------------------------------------------
    | AMBIL DATA ABSENSI DARI TABEL AUDIT (BUKAN att_log)
    |--------------------------------------------------------------------------
    | JAM_PAGI  -> dianggap sebagai jam masuk (berangkat)
    | JAM_SIANG -> dianggap sebagai jam pulang
    |--------------------------------------------------------------------------
    */

        $auditRows = DB::table('AUDIT')
            ->where('NPK', $npk)
            ->whereBetween(DB::raw('CAST(TANGGAL AS DATE)'), [$startDate, $endDate])
            ->get();

        // key by tanggal (Y-m-d) supaya mudah di-lookup per hari di loop
        $auditByDate = $auditRows->keyBy(function ($row) {
            return Carbon::parse($row->TANGGAL)->format('Y-m-d');
        });

        // overtime tetap dari tabel overtimes (sama seperti showSlip, tidak diubah)
        $overtimeRaw = DB::table('overtimes')
            ->where('NPK', $employee->employee_npk)
            ->whereBetween('OVERTIME_DATE', [$startDate, $endDate])
            ->select('OVERTIME_DATE', 'JUMLAH_JAM_LEMBUR')
            ->get();

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
        $late_minutes = 0;

        $isStaff = ($employee->IS_STAFF == '1' || $employee->IS_STAFF === 1);
        $dates = CarbonPeriod::create($startDate, $endDate);

        $holidays = DB::table('holidays')
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->pluck('holiday_date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        foreach ($dates as $date) {

            $tanggal = $date->format('Y-m-d');

            /*
        ======================================================
        AMBIL SHIFT (ANCHOR - masih dipakai untuk hitung telat)
        ======================================================
        */
            $shift = DB::connection('cii')
                ->table('employee_shifts as es')
                ->join('shifts as s', 's.id', '=', 'es.shift_id')
                ->where('es.npk', $employee->employee_npk)
                ->whereDate('es.shift_date', $tanggal)
                ->select('s.name', 's.work_start', 's.work_end')
                ->first();

            if (!$shift) {
                $shift = (object)[
                    'name' => 'NORMAL',
                    'work_start' => '08:00:00',
                    'work_end'   => '17:00:00',
                ];
            }

            $workStart = Carbon::parse($shift->work_start);

            $shiftStartDT = Carbon::parse($tanggal)->setTimeFrom($workStart);

            $jamMasuk = null;
            $jamPulang = null;
            $status = '';
            $overtime = null;

            $lembur = $overtimes[$tanggal] ?? null;

            $isWeekend = $date->isWeekend();
            $isHoliday = in_array($tanggal, $holidays);
            $isWorkday = !($isWeekend || $isHoliday);

            $isNumericOT = is_numeric($lembur);

            /*
        ======================================================
        IZIN (sama seperti showSlip — dari data overtimes)
        ======================================================
        */
            $izinCodes = ['MA', 'BR', 'P1', 'SD', 'CT', 'H', 'OUT'];

            $audit = $auditByDate->get($tanggal);

            if (in_array($lembur, $izinCodes)) {

                $jamMasuk = '-';
                $jamPulang = '-';
                $status = $lembur;

                $summary['status'][$status] =
                    ($summary['status'][$status] ?? 0) + 1;

                if ($isWorkday) {
                    $summary['absent']++;
                }
            }
            /*
            ======================================================
            LIBUR / WEEKEND TAPI ADA LEMBUR (CEK SEBELUM AUDIT)
            ======================================================
            | Hari weekend / libur nasional dengan data lembur numerik
            | harus tetap berstatus "Lembur", walaupun tabel AUDIT punya
            | data JAM_PAGI/JAM_SIANG — status tidak boleh tertimpa jadi
            | Hadir/Terlambat karena itu bukan shift kerja normal.
            ======================================================
            */ elseif (($isWeekend || $isHoliday) && $isNumericOT) {

                $hasMasuk  = $audit && !empty($audit->JAM_PAGI);
                $hasPulang = $audit && !empty($audit->JAM_SIANG);

                $jamMasuk  = $hasMasuk
                    ? Carbon::parse($tanggal . ' ' . $audit->JAM_PAGI)->format('H:i')
                    : '-';

                $jamPulang = $hasPulang
                    ? Carbon::parse($tanggal . ' ' . $audit->JAM_SIANG)->format('H:i')
                    : '-';

                $status = 'Lembur';
            }
            /*
            ======================================================
            ADA DATA DI TABEL AUDIT (JAM_PAGI / JAM_SIANG)
            ======================================================
            */ elseif ($audit && (!empty($audit->JAM_PAGI) || !empty($audit->JAM_SIANG))) {

                $hasMasuk  = !empty($audit->JAM_PAGI);
                $hasPulang = !empty($audit->JAM_SIANG);

                $first = $hasMasuk
                    ? Carbon::parse($tanggal . ' ' . $audit->JAM_PAGI)
                    : null;

                $last = $hasPulang
                    ? Carbon::parse($tanggal . ' ' . $audit->JAM_SIANG)
                    : null;

                $isLate = ($isStaff && $first)
                    ? $first->gt($shiftStartDT->copy()->addMinutes(5))
                    : false;

                if ($hasMasuk && $hasPulang) {

                    $jamMasuk  = $first->format('H:i');
                    $jamPulang = $last->format('H:i');

                    $status = $isLate ? 'Terlambat' : 'Hadir';
                } elseif ($hasMasuk) {

                    $jamMasuk = $first->format('H:i');

                    $status = $isLate ? 'Terlambat' : 'Scan Masuk';
                } else {

                    $jamPulang = $last->format('H:i');
                    $status = 'Scan Pulang';
                }

                if ($isLate) {
                    $late_minutes += $shiftStartDT->copy()
                        ->addMinutes(5)
                        ->diffInMinutes($first);
                }

                if ($isWorkday) {
                    $summary['hadir']++;
                }
            }
            /*
        ======================================================
        TIDAK ADA DATA DI AUDIT
        ======================================================
        */ else {

                if ($lembur === 'IN') {

                    $status = 'Masuk (Finger tidak terbaca)';

                    if ($isWorkday) {
                        $summary['hadir']++;
                    }
                } elseif ($isNumericOT) {

                    $status = 'Lembur';

                    if ($isWorkday) {
                        $summary['hadir']++;
                    }
                } elseif ($isWeekend || $isHoliday) {

                    $status = 'Libur';
                } else {

                    $status = 'Tidak Masuk';
                    $summary['absent']++;
                }
            }

            /*
        ======================================================
        HITUNG OVERTIME (sama seperti showSlip)
        ======================================================
        */
            if ($isNumericOT) {

                $overtime = (float) $lembur;

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

        /*
    |--------------------------------------------------------------------------
    | Return View (PDF — sama seperti showSlip, view blade tetap dipakai ulang)
    |--------------------------------------------------------------------------
    */

        $pdf = Pdf::loadView('payroll.viewslip', [
            'employee' => $employee,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'attendance' => $attendance,
            'summary' => $summary,
            'holidays' => $holidays,
            'late_minutes' => $late_minutes,
            'ijin_details' => ($ijinDetails[$npk] ?? collect())->values(),
            'adjusment_details' => ($payrollAdjustmentDetails[$npk] ?? collect())->values(),
            'total_ijin' => optional($ijinSummary->get($npk))->total_ijin_minutes ?? 0,
        ])
            ->setPaper('A4', 'portrait');

        $pdf->getDomPDF()->getCanvas()->get_cpdf()
            ->setEncryption($password, $password, ['print', 'copy']);

        return $pdf->download('SLIP_AUDIT_' . $employee->period_name . '_' . $employee->employee_npk . '.pdf');


        // return view('payroll.viewslip', [
        //     'employee' => $employee,
        //     'earnings' => $earnings,
        //     'deductions' => $deductions,
        //     'attendance' => $attendance,
        //     'summary' => $summary,
        //     'holidays' => $holidays,
        //     'late_minutes' => $late_minutes,
        //     'ijin_details' => ($ijinDetails[$npk] ?? collect())->values(),
        //     'adjusment_details' => ($payrollAdjustmentDetails[$npk] ?? collect())->values(),
        //     'total_ijin' => optional($ijinSummary->get($npk))->total_ijin_minutes ?? 0,
        // ]);
    }
}
