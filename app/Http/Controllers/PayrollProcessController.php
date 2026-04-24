<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePayrollExport;
use App\Jobs\GeneratePayrollRekap;
use App\Models\InsentifApproval;
use App\Models\PayrollApprove;
use App\Models\PayrollComponent;
use App\Models\PayrollExport;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use App\Models\PayrollSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\DataTables;
use App\Models\InsentifRoleFormula;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PayrollProcessController extends Controller
{

    public function index()
    {
        $periods = PayrollRun::query()
            ->leftJoin('payroll_periods', 'payroll_runs.period_id', '=', 'payroll_periods.id')
            ->leftJoin('payroll_exports', 'payroll_exports.run_id', '=', 'payroll_runs.id')
            ->leftJoin('payroll_approve', 'payroll_approve.payroll_run_id', '=', 'payroll_runs.id')
            ->select(
                'payroll_runs.*',
                'payroll_periods.name as period_name',
                'payroll_exports.status as export_status',
                'payroll_exports.file_excel',
                'payroll_exports.file_pdf',
                'payroll_exports.file_bank_active',
                'payroll_exports.file_bank_resign',
                'payroll_exports.file_peng',
                'payroll_approve.status as approve_status' // 🔥 penting
            )
            ->orderByDesc('payroll_runs.processed_at')
            ->get();

        // dd($periods);

        return view('payroll.index', compact('periods'));
    }

    public function generate()
    {
        $periods = PayrollPeriod::orderBy('start_date')->where('is_closed', 0)->get();
        return view('payroll.process', compact('periods'));
    }

    public function approvalStatus($periodId)
    {
        $data = InsentifApproval::where('period_id', $periodId)
            ->orderBy('payroll_component')
            ->get([
                'id',
                'payroll_component',
                'status',
                'approved_at'
            ])
            ->map(function ($item) {

                $approved = $item->approved_at;

                // jika masih string JSON
                if (is_string($approved)) {
                    $approved = json_decode($approved, true);
                }

                // ambil data terakhir
                $item->approved_at = is_array($approved)
                    ? end($approved)
                    : null;

                return $item;
            });

        return response()->json($data);
    }

    public function process(Request $request)
    {
        $payrollResults = [];
        $period = PayrollPeriod::findOrFail($request->period_id);

        // PROTEKSI: cek apakah payroll sudah pernah digenerate
        $exists = PayrollRun::where('period_id', $period->id)->exists();

        if ($exists) {
            Alert::error('Gagal', 'Payroll untuk periode ini sudah tergenerate sebelumnya.');
            return redirect()->back();
        }

        $run = PayrollRun::create([
            'period_id' => $period->id,
            'processed_at' => now(),
        ]);

        $periodStart = $period->start_date;
        $periodEnd   = $period->end_date;
        $count_days  = Carbon::parse($periodStart)->diffInDays(Carbon::parse($periodEnd)) + 1;

        /*
        |--------------------------------------------------------------------------
        | BIODATA UNION (DITAMBAHKAN BARCODE)
        |--------------------------------------------------------------------------
        */

        $biodataUnion = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            ->unionAll(
                DB::connection('cii')
                    ->table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            );

        // dd($biodataUnion->get());
        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE BASE + SHIFT
        |--------------------------------------------------------------------------
        */

        $employeeBase = DB::connection('cii')
            ->table('PKWT as p')

            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('p.NPK', '=', 'bio.NPK');
            })

            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })

            // ->where('p.NPK', '=', 'C-00827')

            ->select(
                'p.NPK',
                'bio.NAMA_KARYAWAN',
                DB::raw("CAST(bio.BARCODE AS VARCHAR(50)) AS BARCODE"),
                'p.TMK',
                'p.TKK'
            )
            ->distinct();

        // dd($employeeBase->get());

        $overtimeSummary = DB::connection('cii')
            ->table('overtimes')
            ->whereBetween('OVERTIME_DATE', [$periodStart, $periodEnd])
            ->select(
                'NPK',
                DB::raw("
                SUM(
                    CASE 
                        WHEN DAY NOT IN ('Sabtu','Minggu')
                        AND TRY_CAST(JUMLAH_JAM_LEMBUR as FLOAT) IS NOT NULL
                        THEN TRY_CAST(JUMLAH_JAM_LEMBUR as FLOAT)
                        ELSE 0
                    END
                ) as overtime_hours
            "),
                DB::raw("
                SUM(
                    CASE 
                        WHEN DAY IN ('Sabtu','Minggu')
                        AND TRY_CAST(JUMLAH_JAM_LEMBUR as FLOAT) IS NOT NULL
                        THEN TRY_CAST(JUMLAH_JAM_LEMBUR as FLOAT)
                        ELSE 0
                    END
                ) as special_overtime_hours
            "),
                DB::raw("
                SUM(
                    CASE
                        WHEN UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) = 'H'
                            THEN 0.5

                        WHEN JUMLAH_JAM_LEMBUR IS NOT NULL
                            AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NULL
                            AND UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) NOT IN ('IN','CT','H')
                            THEN 1

                        ELSE 0
                    END
                ) as absence_days
            "),
            )
            ->groupBy('NPK');

        // CHECK PER DATE LATE

        // $lateSummary =
        //     DB::connection('cii')
        //     ->query()

        //     /*
        // |--------------------------------------------------------------------------
        // | EMPLOYEE + CALENDAR
        // |--------------------------------------------------------------------------
        // */
        //     ->fromSub(function ($q) use ($employeeBase, $periodStart, $periodEnd) {

        //         $q->fromSub($employeeBase, 'emp')

        //             ->crossJoinSub(

        //                 DB::connection('cii')
        //                     ->query()
        //                     ->selectRaw("
        //                     DATEADD(
        //                         DAY,
        //                         v.number,
        //                         CAST(? AS DATE)
        //                     ) as shift_date
        //                 ", [$periodStart])

        //                     ->from(DB::raw('master..spt_values v'))
        //                     ->where('v.type', 'P')
        //                     ->whereRaw("
        //                     v.number <= DATEDIFF(
        //                         DAY,
        //                         CAST(? AS DATE),
        //                         CAST(? AS DATE)
        //                     )
        //                 ", [$periodStart, $periodEnd]),

        //                 'cal'
        //             )

        //             ->select(
        //                 'emp.*',
        //                 DB::raw('cal.shift_date')
        //             );
        //     }, 'emp')


        //     /*
        // |--------------------------------------------------------------------------
        // | DAILY SHIFT (PRIORITY)
        // |--------------------------------------------------------------------------
        // */
        //     ->leftJoin('employee_shifts as es', function ($join) {

        //         $join->on('emp.NPK', '=', 'es.npk')
        //             ->on(
        //                 DB::raw('CAST(emp.shift_date AS DATE)'),
        //                 '=',
        //                 DB::raw('CAST(es.shift_date AS DATE)')
        //             );
        //     })

        //     ->leftJoin('shifts as s', 'es.shift_id', '=', 's.id')


        //     /*
        // |--------------------------------------------------------------------------
        // | ATT LOG SOURCE
        // |--------------------------------------------------------------------------
        // */
        //     ->leftJoinSub(

        //         DB::connection('cii')
        //             ->table('att_log')
        //             ->whereBetween(
        //                 DB::raw('CAST(scan_date AS DATE)'),
        //                 [$periodStart, $periodEnd]
        //             )
        //             ->select(
        //                 DB::raw('CAST(pin AS VARCHAR(50)) as pin'),
        //                 DB::raw('CAST(scan_date AS DATE) as scan_day'),
        //                 'scan_date'
        //             ),

        //         'att',

        //         function ($join) {

        //             $join->on(
        //                 DB::raw('CAST(emp.BARCODE AS VARCHAR(50))'),
        //                 '=',
        //                 'att.pin'
        //             )

        //                 ->whereRaw("
        //                 CAST(att.scan_day AS DATE)
        //                 =
        //                 CAST(emp.shift_date AS DATE)
        //             ");
        //         }
        //     )


        //     /*
        // |--------------------------------------------------------------------------
        // | FALLBACK SHIFT BY SCAN DATE ⭐ FIXED
        // |--------------------------------------------------------------------------
        // */
        //     ->leftJoin('shifts as fs', function ($join) {

        //         $join->whereRaw("
        //         es.shift_id IS NULL
        //         AND CAST(att.scan_date AS DATE)
        //             BETWEEN fs.start_date
        //             AND COALESCE(fs.end_date, CAST(att.scan_date AS DATE))
        //     ");
        //     })


        //     /*
        // |--------------------------------------------------------------------------
        // | SHIFT RESOLUTION
        // |--------------------------------------------------------------------------
        // */
        //     ->selectRaw("
        //     CAST(emp.BARCODE AS VARCHAR(50)) as pin,
        //     CAST(emp.shift_date AS DATE) as scan_day,

        //     COALESCE(
        //         CAST(s.work_start AS TIME),
        //         CAST(fs.work_start AS TIME),
        //         '08:00:00'
        //     ) as work_start,

        //     COALESCE(
        //         CAST(s.work_end AS TIME),
        //         CAST(fs.work_end AS TIME),
        //         '17:00:00'
        //     ) as work_end
        // ")


        //     /*
        // |--------------------------------------------------------------------------
        // | FIRST SCAN
        // |--------------------------------------------------------------------------
        // */
        //     ->selectRaw("
        //     MIN(att.scan_date) as first_scan
        // ")


        //     /*
        // |--------------------------------------------------------------------------
        // | LATE ENGINE
        // |--------------------------------------------------------------------------
        // */
        //     ->selectRaw("
        //     CASE
        //         WHEN MIN(att.scan_date) IS NULL THEN 0

        //         WHEN DATEDIFF(
        //             MINUTE,
        //             DATEADD(
        //                 MINUTE,5,
        //                 DATEADD(
        //                     SECOND,
        //                     DATEDIFF(
        //                         SECOND,'00:00:00',
        //                         COALESCE(
        //                             CAST(s.work_start AS TIME),
        //                             CAST(fs.work_start AS TIME),
        //                             '08:00:00'
        //                         )
        //                     ),
        //                     CAST(emp.shift_date AS DATETIME)
        //                 )
        //             ),
        //             MIN(att.scan_date)
        //         ) < 0 THEN 0

        //         ELSE
        //         DATEDIFF(
        //             MINUTE,
        //             DATEADD(
        //                 MINUTE,5,
        //                 DATEADD(
        //                     SECOND,
        //                     DATEDIFF(
        //                         SECOND,'00:00:00',
        //                         COALESCE(
        //                             CAST(s.work_start AS TIME),
        //                             CAST(fs.work_start AS TIME),
        //                             '08:00:00'
        //                         )
        //                     ),
        //                     CAST(emp.shift_date AS DATETIME)
        //                 )
        //             ),
        //             MIN(att.scan_date)
        //         )
        //     END as late_minute
        // ")


        //     ->groupBy(
        //         DB::raw('CAST(emp.BARCODE AS VARCHAR(50))'),
        //         DB::raw('CAST(emp.shift_date AS DATE)'),
        //         's.work_start',
        //         's.work_end',
        //         'fs.work_start',
        //         'fs.work_end'
        //     )

        //     ->whereBetween(
        //         DB::raw('CAST(emp.shift_date AS DATE)'),
        //         [$periodStart, $periodEnd]
        //     )

        //     ->orderBy(DB::raw('CAST(emp.shift_date AS DATE)'));

        // SUMMARY LATE
        $lateSummary =
            DB::connection('cii')
            ->query()

            /*
        |--------------------------------------------------------------------------
        | SOURCE LATE ENGINE (ORIGINAL LOGIC)
        |--------------------------------------------------------------------------
        */
            ->fromSub(function ($query) use ($employeeBase, $periodStart, $periodEnd) {

                $query->fromSub(function ($q) use ($employeeBase, $periodStart, $periodEnd) {

                    $q->fromSub($employeeBase, 'emp')

                        ->crossJoinSub(

                            DB::connection('cii')
                                ->query()
                                ->selectRaw("
                                DATEADD(
                                    DAY,
                                    v.number,
                                    CAST(? AS DATE)
                                ) as shift_date
                            ", [$periodStart])

                                ->from(DB::raw('master..spt_values v'))
                                ->where('v.type', 'P')
                                ->whereRaw("
                                v.number <= DATEDIFF(
                                    DAY,
                                    CAST(? AS DATE),
                                    CAST(? AS DATE)
                                )
                            ", [$periodStart, $periodEnd]),

                            'cal'
                        )

                        ->select(
                            'emp.NPK',
                            'emp.BARCODE',
                            DB::raw('cal.shift_date')
                        );
                }, 'emp')


                    /*
            |--------------------------------------------------------------------------
            | DAILY SHIFT
            |--------------------------------------------------------------------------
            */
                    ->leftJoin('employee_shifts as es', function ($join) {

                        $join->on('emp.NPK', '=', 'es.npk')
                            ->on(
                                DB::raw('CAST(emp.shift_date AS DATE)'),
                                '=',
                                DB::raw('CAST(es.shift_date AS DATE)')
                            );
                    })

                    ->leftJoin('shifts as s', 'es.shift_id', '=', 's.id')


                    /*
            |--------------------------------------------------------------------------
            | ATT LOG
            |--------------------------------------------------------------------------
            */
                    ->leftJoinSub(

                        DB::connection('cii')
                            ->table('att_log')
                            ->whereBetween(
                                DB::raw('CAST(scan_date AS DATE)'),
                                [$periodStart, $periodEnd]
                            )
                            ->select(
                                DB::raw('CAST(pin AS VARCHAR(50)) as pin'),
                                DB::raw('CAST(scan_date AS DATE) as scan_day'),
                                'scan_date'
                            ),

                        'att',

                        function ($join) {

                            $join->on(
                                DB::raw('CAST(emp.BARCODE AS VARCHAR(50))'),
                                '=',
                                'att.pin'
                            )

                                ->where(function ($q) {

                                    $q->whereRaw("
                                CAST(att.scan_day AS DATE)
                                =
                                CAST(emp.shift_date AS DATE)
                            ")

                                        ->orWhereRaw("
                                (
                                    es.shift_id IS NOT NULL
                                    AND CAST(s.work_start AS TIME)>
                                        CAST(s.work_end AS TIME)

                                    AND CAST(att.scan_day AS DATE)
                                    =
                                    DATEADD(DAY,1,CAST(emp.shift_date AS DATE))
                                )
                            ");
                                });
                        }
                    )


                    /*
            |--------------------------------------------------------------------------
            | FALLBACK SHIFT BY SCAN DATE ⭐ NEW
            |--------------------------------------------------------------------------
            */
                    ->leftJoin('shifts as fs', function ($join) {

                        $join->whereRaw("
                    es.shift_id IS NULL
                    AND CAST(att.scan_date AS DATE)
                        BETWEEN fs.start_date
                        AND COALESCE(fs.end_date, CAST(att.scan_date AS DATE))
                ");
                    })


                    ->groupBy(
                        'emp.NPK',
                        'emp.BARCODE',
                        DB::raw('CAST(emp.shift_date AS DATE)'),
                        's.work_start',
                        's.work_end',
                        'fs.work_start',
                        'fs.work_end',
                        'es.shift_id'
                    )


                    /*
            |--------------------------------------------------------------------------
            | DAILY LATE RESULT (ORIGINAL LOGIC — NOT MODIFIED)
            |--------------------------------------------------------------------------
            */
                    ->selectRaw("
                emp.NPK,
                CAST(emp.BARCODE AS VARCHAR(50)) as pin,

                CASE
                WHEN MIN(att.scan_date) IS NULL THEN 0

                WHEN
                COALESCE(
                    CAST(s.work_start AS TIME),
                    CAST(fs.work_start AS TIME),
                    '08:00:00'
                )
                >
                COALESCE(
                    CAST(s.work_end AS TIME),
                    CAST(fs.work_end AS TIME),
                    '17:00:00'
                )

                /* ================= NIGHT SHIFT ================= */
                THEN

                CASE
                WHEN DATEDIFF(
                    MINUTE,

                    DATEADD(
                        MINUTE,5,
                        DATEADD(
                            SECOND,
                            DATEDIFF(
                                SECOND,'00:00:00',
                                COALESCE(
                                    CAST(s.work_start AS TIME),
                                    CAST(fs.work_start AS TIME),
                                    '23:00:00'
                                )
                            ),
                            CAST(emp.shift_date AS DATETIME)
                        )
                    ),

                    MIN(att.scan_date)
                ) < 0 THEN 0

                ELSE
                DATEDIFF(
                    MINUTE,

                    DATEADD(
                        MINUTE,5,
                        DATEADD(
                            SECOND,
                            DATEDIFF(
                                SECOND,'00:00:00',
                                COALESCE(
                                    CAST(s.work_start AS TIME),
                                    CAST(fs.work_start AS TIME),
                                    '23:00:00'
                                )
                            ),
                            CAST(emp.shift_date AS DATETIME)
                        )
                    ),

                    MIN(att.scan_date)
                )
                END

                /* ================= NORMAL SHIFT ================= */
                ELSE

                CASE
                WHEN DATEDIFF(
                    MINUTE,

                    DATEADD(
                        MINUTE,5,
                        DATEADD(
                            SECOND,
                            DATEDIFF(
                                SECOND,'00:00:00',
                                COALESCE(
                                    CAST(s.work_start AS TIME),
                                    CAST(fs.work_start AS TIME),
                                    '08:00:00'
                                )
                            ),
                            CAST(emp.shift_date AS DATETIME)
                        )
                    ),

                    MIN(att.scan_date)
                ) < 0 THEN 0

                ELSE
                DATEDIFF(
                    MINUTE,

                    DATEADD(
                        MINUTE,5,
                        DATEADD(
                            SECOND,
                            DATEDIFF(
                                SECOND,'00:00:00',
                                COALESCE(
                                    CAST(s.work_start AS TIME),
                                    CAST(fs.work_start AS TIME),
                                    '08:00:00'
                                )
                            ),
                            CAST(emp.shift_date AS DATETIME)
                        )
                    ),

                    MIN(att.scan_date)
                )
                END

                END as late_minute
            ")

                    ->whereBetween(
                        DB::raw('CAST(emp.shift_date AS DATE)'),
                        [$periodStart, $periodEnd]
                    );
            }, 'daily')


            /*
        |--------------------------------------------------------------------------
        | FINAL RESULT
        |--------------------------------------------------------------------------
        */
            ->selectRaw("
            NPK as npk,
            pin,
            SUM(late_minute) as late_minutes
        ")

            ->groupBy('NPK', 'pin');

        // dd($lateSummary->get());

        $lateSummary->get();


        // dd(DB::query()
        //     ->fromSub($employeeBase, 'emp')
        //     ->select('NPK', 'BARCODE')
        //     ->where('BARCODE', '151004200')
        //     ->get(), $lateSummary->get());

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEES QUERY (DITAMBAHKAN LATE HOURS)
        |--------------------------------------------------------------------------
        */

        $employees = DB::connection('cii')
            ->query()
            ->fromSub($employeeBase, 'emp')

            ->leftJoinSub($overtimeSummary, 'ot', function ($join) {
                $join->on('emp.NPK', '=', 'ot.NPK');
            })

            ->leftJoinSub($lateSummary, 'lt', function ($join) {
                $join->on(
                    DB::raw('CAST(emp.BARCODE AS VARCHAR(50))'),
                    '=',
                    DB::raw('CAST(lt.pin AS VARCHAR(50))')
                );
            })

            ->leftJoin('payroll_masters as pm', 'emp.NPK', '=', 'pm.npk')

            ->leftJoin('payroll_adjusments as pa', function ($join) use ($period) {
                $join->on('emp.NPK', '=', 'pa.npk')
                    ->where('pa.period_id', '=', $period->id);
            })

            ->select(
                'emp.NPK',
                'emp.NAMA_KARYAWAN',
                'emp.BARCODE',

                'pm.salary',
                'pm.allowance',
                'pm.pph21',
                'pm.type',
                'pm.daily_salary',

                DB::raw('COALESCE(pa.adjusment,0) as adjusment'),
                DB::raw('COALESCE(ot.overtime_hours,0) as overtime_hours'),
                DB::raw('COALESCE(ot.special_overtime_hours,0) as special_overtime_hours'),
                DB::raw('COALESCE(ot.absence_days,0) as absence_days'),
                DB::raw('COALESCE(lt.late_minutes,0) as late_minutes'),

                DB::raw("DATEDIFF(YEAR, emp.TMK, '$periodEnd') as working_years")
            )
            ->get();

        // dd($employees);

        $components = PayrollComponent::where('is_active', 1)
            ->where('code', '!=', 'thr')
            ->orderByDesc('priority')
            ->get();

        $overtimeComponent = PayrollComponent::where('code', 'overtime_pay')->first();
        $overtimeFormula = $overtimeComponent->formula;

        $specialOvertimeComponent = PayrollComponent::where('code', 'special_overtime_pay')->first();
        $specialOvertimeFormula = $specialOvertimeComponent->formula;

        $sewingInsentifComponent = PayrollComponent::where('code', 'sewing_insentif')->first();
        $sewingInsentifFormula = json_decode($sewingInsentifComponent->formula, true);

        $cuttingInsentifComponent = PayrollComponent::where('code', 'cutting_insentif')->first();
        $cuttingInsentifFormula = json_decode($cuttingInsentifComponent->formula, true);

        $padInsentifComponent = PayrollComponent::where('code', 'pad_insentif')->first();
        $padInsentifFormula = json_decode($padInsentifComponent->formula, true);

        $totalPayroll = 0;

        foreach ($employees as $employee) {

            $inputVariables = [
                'basic_salary'   => (float) $employee->salary,
                'allowance'      => (float) $employee->allowance,
                'absence_days'   => (float) $employee->absence_days,
                'working_years'  => (float) $employee->working_years,
                'adjusment'      => (float) $employee->adjusment,
                'pph_21'         => (float) $employee->pph21,
                'daily_salary'   => (float) $employee->daily_salary,
                'count_days'     => (float) $count_days,
                'is_contract' => $employee->type === 'Contract' ? 1 : 0,
                'is_daily'    => $employee->type === 'Daily' ? 1 : 0,
                'late_minutes'     => (float) $employee->late_minutes,
            ];

            // dd($employee->late_minutes);

            $results = [];
            $grandTotal = 0;

            foreach ($components as $component) {
                if ($component->code === 'thr') continue;

                if ($component->calculation_method === 'fixed') {
                    $amount = $component->value;
                } else {

                    if ($component->code === 'overtime_pay') {
                        $amount = $this->evaluateFormula(
                            $overtimeFormula,
                            $results,
                            [
                                'basic_salary' => (float) $employee->salary,
                                'overtime_hours' => (float) $employee->overtime_hours
                            ]
                        );
                    } else if ($component->code === 'special_overtime_pay') {
                        $amount = $this->evaluateFormula(
                            $specialOvertimeFormula,
                            $results,
                            [
                                'basic_salary' => (float) $employee->salary,
                                'special_overtime_hours' => (float) $employee->special_overtime_hours
                            ]
                        );
                    } else if ($component->code === 'sewing_insentif') {

                        $amount = 0;

                        /*
                        |--------------------------------------------------------------------------
                        | LOAD THRESHOLD
                        |--------------------------------------------------------------------------
                        */
                        $thresholds = DB::table('insentif_thresholds')
                            ->where('insentif_type', 'Sewing')
                            ->where('type', 'Percentage')
                            ->pluck('minimum', 'days');

                        $getMinEfficiency = function ($dayIndex) use ($thresholds) {

                            if (isset($thresholds[$dayIndex])) {
                                return $thresholds[$dayIndex];
                            }

                            return $thresholds->max();
                        };


                        /*
                        |--------------------------------------------------------------------------
                        | LOAD OVERTIME (ONCE)
                        |--------------------------------------------------------------------------
                        */
                        $overtimes = DB::table('overtimes')
                            ->where('NPK', $employee->NPK)
                            ->whereBetween('OVERTIME_DATE', [
                                $period->start_date,
                                $period->end_date
                            ])
                            ->get()
                            ->keyBy(fn($o) => $o->OVERTIME_DATE);


                        /*
                        |--------------------------------------------------------------------------
                        | FUNCTION VALIDATE OVERTIME
                        |--------------------------------------------------------------------------
                        */
                        $isValidOvertime = function ($date) use ($overtimes) {

                            if (!isset($overtimes[$date])) {
                                return true; // tidak ada overtime → tetap hitung
                            }

                            $lembur = $overtimes[$date]->JUMLAH_JAM_LEMBUR;

                            // NULL → tetap dihitung
                            if ($lembur === null || $lembur === '') {
                                return true;
                            }

                            // numeric → tetap dihitung
                            if (is_numeric($lembur)) {
                                return true;
                            }

                            // karakter (MA, CT, BR, S1, dll)
                            return false;
                        };


                        /*
                        |--------------------------------------------------------------------------
                        | OPERATOR & SUPERVISOR
                        |--------------------------------------------------------------------------
                        */
                        $lineefficiencies = DB::table('line_efficiencies as le')
                            ->join('employee_line_assignments as ela', function ($join) use ($employee) {

                                $join->on('le.line_number', '=', 'ela.line_number')
                                    ->where('ela.npk', $employee->NPK)
                                    ->whereColumn('le.date', '>=', 'ela.start_date')
                                    ->where(function ($q) {
                                        $q->whereColumn('le.date', '<=', 'ela.end_date')
                                            ->orWhereNull('ela.end_date');
                                    });
                            })
                            ->where('le.period_id', $period->id)
                            ->whereBetween('le.date', [$period->start_date, $period->end_date])
                            ->select(
                                'le.line_number',
                                'le.efficiency',
                                'le.date',
                                'ela.role',
                                'ela.start_date'
                            )
                            ->orderBy('le.date')
                            ->get();

                        foreach ($lineefficiencies as $row) {

                            if (!in_array($row->role, ['operator', 'supervisor'])) {
                                continue;
                            }

                            /*
                            |----------------------------------
                            | CHECK OVERTIME
                            |----------------------------------
                            */
                            if (!$isValidOvertime($row->date)) {
                                continue;
                            }

                            /*
                            |----------------------------------
                            | DAY INDEX
                            |----------------------------------
                            */
                            $dayIndex =
                                \Carbon\Carbon::parse($row->start_date)
                                ->diffInDays(\Carbon\Carbon::parse($row->date)) + 1;

                            $minEfficiency = $getMinEfficiency($dayIndex);

                            if ($row->efficiency < $minEfficiency) {
                                continue;
                            }

                            $lineInsentif =
                                $this->getInsentifByEfficiency($row->efficiency, $sewingInsentifFormula);

                            $amount += $this->calculateRoleSewingInsentif(
                                $row->role,
                                'sewing',
                                $lineInsentif,
                                1
                            );
                        }


                        /*
    |--------------------------------------------------------------------------
    | CHIEF / MEKANIK / MEKANIK LEADER
    |--------------------------------------------------------------------------
    */
                        $grouped = DB::table('line_efficiencies')
                            ->where('period_id', $period->id)
                            ->whereBetween('date', [$period->start_date, $period->end_date])
                            ->select(
                                'date',
                                DB::raw('count(distinct line_number) as jumlah_line')
                            )
                            ->groupBy('date')
                            ->get();

                        foreach ($grouped as $day) {

                            /*
        |----------------------------------
        | CHECK OVERTIME
        |----------------------------------
        */
                            if (!$isValidOvertime($day->date)) {
                                continue;
                            }

                            $assignment = DB::table('employee_line_assignments')
                                ->where('npk', $employee->NPK)
                                ->where('start_date', '<=', $day->date)
                                ->where(function ($q) use ($day) {
                                    $q->where('end_date', '>=', $day->date)
                                        ->orWhereNull('end_date');
                                })
                                ->first();

                            if (!$assignment) continue;

                            if (!in_array(
                                $assignment->role,
                                ['chief', 'mekanik', 'mekanik_leader']
                            )) {
                                continue;
                            }

                            $dayIndex =
                                \Carbon\Carbon::parse($assignment->start_date)
                                ->diffInDays(\Carbon\Carbon::parse($day->date)) + 1;

                            $minEfficiency = $getMinEfficiency($dayIndex);

                            $lines = DB::table('line_efficiencies')
                                ->where('period_id', $period->id)
                                ->where('date', $day->date)
                                ->get();

                            $totalLineInsentif = 0;

                            foreach ($lines as $line) {

                                if ($line->efficiency < $minEfficiency) {
                                    continue;
                                }

                                $totalLineInsentif +=
                                    $this->getInsentifByEfficiency($line->efficiency, $sewingInsentifFormula);
                            }

                            if ($totalLineInsentif <= 0) {
                                continue;
                            }

                            $amount += $this->calculateRoleSewingInsentif(
                                $assignment->role,
                                'sewing',
                                $totalLineInsentif,
                                $day->jumlah_line
                            );
                        }
                    } else if ($component->code === 'pad_insentif') {

                        $amount = 0;

                        /*
    |--------------------------------------------------------------------------
    | VALIDATE OVERTIME
    |--------------------------------------------------------------------------
    */
                        $isValidOvertime = function ($npk, $date) {

                            $ot = DB::table('overtimes')
                                ->where('NPK', $npk)
                                ->where('OVERTIME_DATE', $date)
                                ->first();

                            if (!$ot) return true;

                            $lembur = $ot->JUMLAH_JAM_LEMBUR;

                            if ($lembur === null || $lembur === '') return true;

                            if (is_numeric($lembur)) return true;

                            return false; // MA / CT / BR / S1
                        };

                        /*
    |--------------------------------------------------------------------------
    | LOAD ASSIGNMENT
    |--------------------------------------------------------------------------
    */
                        $assignments = DB::table('employee_pad_assignments')
                            ->where('npk', $employee->NPK)
                            ->where('period_id', $period->id)
                            ->get();

                        foreach ($assignments as $assignment) {

                            $dept = $assignment->dept;
                            $role = $assignment->role;

                            $start = max($assignment->start_date, $period->start_date);
                            $end = $assignment->end_date
                                ? min($assignment->end_date, $period->end_date)
                                : $period->end_date;

                            /*
        |--------------------------------------------------------------------------
        | OPERATOR
        |--------------------------------------------------------------------------
        */
                            if ($role === 'operator') {

                                $rows = DB::table('pad_efficiencies')
                                    ->where('npk', $employee->NPK)
                                    ->where('period_id', $period->id)
                                    ->whereBetween('date', [$start, $end])
                                    ->get();

                                foreach ($rows as $row) {

                                    if (!$isValidOvertime($employee->NPK, $row->date)) {
                                        continue;
                                    }

                                    $rate = $this->getInsentifByEfficiency(
                                        $row->efficiency,
                                        $padInsentifFormula
                                    );

                                    $amount += $rate * $row->piece;
                                }
                            }

                            /*
        |--------------------------------------------------------------------------
        | NON OPERATOR (SPV / LEADER / HELPER)
        |--------------------------------------------------------------------------
        */ else {

                                /*
            |----------------------------------
            | TOTAL DEPT INSENTIF
            | ONLY VALID OPERATOR
            |----------------------------------
            */
                                $rows = DB::table('pad_efficiencies as pe')
                                    ->join('employee_pad_assignments as epa', function ($join) {
                                        $join->on('pe.npk', '=', 'epa.npk')
                                            ->on('pe.dept', '=', 'epa.dept');
                                    })
                                    ->where('pe.period_id', $period->id)
                                    ->where('epa.period_id', $period->id)
                                    ->where('epa.role', 'operator')
                                    ->where('pe.dept', $dept)
                                    ->whereBetween('pe.date', [$start, $end])
                                    ->select('pe.npk', 'pe.efficiency', 'pe.piece', 'pe.date')
                                    ->get();

                                $totalDeptInsentif = 0;

                                foreach ($rows as $row) {

                                    // FILTER HANYA NUMERATOR
                                    if (!$isValidOvertime($row->npk, $row->date)) {
                                        continue;
                                    }

                                    $rate = $this->getInsentifByEfficiency(
                                        $row->efficiency,
                                        $padInsentifFormula
                                    );

                                    $totalDeptInsentif += $rate * $row->piece;
                                }

                                /*
            |----------------------------------
            | DENOMINATOR (ALL OPERATOR)
            |----------------------------------
            */
                                $jumlahOperator = DB::table('employee_pad_assignments')
                                    ->where('dept', $dept)
                                    ->where('role', 'operator')
                                    ->where('period_id', $period->id)
                                    ->pluck('npk')
                                    ->unique()
                                    ->count();

                                if ($jumlahOperator == 0) {
                                    continue;
                                }

                                $amount += $this->calculateRolePadInsentif(
                                    $role,
                                    'pad',
                                    $totalDeptInsentif,
                                    $jumlahOperator
                                );
                            }
                        }
                    } else if ($component->code === 'cutting_insentif') {

                        $amount = 0;

                        /*
    |--------------------------------------------------------------------------
    | LOAD OVERTIME (ONLY ONCE)
    |--------------------------------------------------------------------------
    */
                        $overtimes = DB::table('overtimes')
                            ->where('NPK', $employee->NPK)
                            ->whereBetween('OVERTIME_DATE', [
                                $period->start_date,
                                $period->end_date
                            ])
                            ->get()
                            ->keyBy(fn($o) => $o->OVERTIME_DATE);


                        /*
    |--------------------------------------------------------------------------
    | VALIDATE OVERTIME
    |--------------------------------------------------------------------------
    */
                        $isValidOvertime = function ($date) use ($overtimes) {

                            // tidak ada record → tetap dihitung
                            if (!isset($overtimes[$date])) {
                                return true;
                            }

                            $lembur = $overtimes[$date]->JUMLAH_JAM_LEMBUR;

                            // NULL / kosong → tetap dihitung
                            if ($lembur === null || $lembur === '') {
                                return true;
                            }

                            // angka → tetap dihitung
                            if (is_numeric($lembur)) {
                                return true;
                            }

                            // MA / CT / BR / S1 dll → skip
                            return false;
                        };


                        /*
    |--------------------------------------------------------------------------
    | LOAD ASSIGNMENTS (NO JOIN)
    |--------------------------------------------------------------------------
    */
                        $assignments = DB::table('employee_cutting_assignments')
                            ->where('npk', $employee->NPK)
                            ->where('period_id', $period->id)
                            ->get();


                        /*
    |--------------------------------------------------------------------------
    | LOAD CUTTING EFFICIENCY
    |--------------------------------------------------------------------------
    */
                        $cuttingEfficiencies = DB::table('cutting_efficiencies')
                            ->where('period_id', $period->id)
                            ->whereBetween('date', [
                                $period->start_date,
                                $period->end_date
                            ])
                            ->get();


                        /*
    |--------------------------------------------------------------------------
    | CALCULATE INSENTIF
    |--------------------------------------------------------------------------
    */
                        foreach ($cuttingEfficiencies as $row) {

                            /*
        |----------------------------------
        | CHECK OVERTIME
        |----------------------------------
        */
                            if (!$isValidOvertime($row->date)) {
                                continue;
                            }

                            /*
        |----------------------------------
        | FIND ACTIVE ASSIGNMENT BY DATE
        |----------------------------------
        */
                            $assignment = $assignments->first(function ($a) use ($row) {

                                if ($row->date < $a->start_date) {
                                    return false;
                                }

                                if ($a->end_date && $row->date > $a->end_date) {
                                    return false;
                                }

                                return true;
                            });

                            // tidak ada role di tanggal tersebut
                            if (!$assignment) {
                                continue;
                            }

                            /*
                            |----------------------------------
                            | GET INSENTIF BY EFFICIENCY
                            |----------------------------------
                            */
                            $insentif = $this->getInsentifByEfficiency(
                                $row->efficiency,
                                $cuttingInsentifFormula
                            );

                            /*
                            |----------------------------------
                            | ADD AMOUNT BASED ROLE
                            |----------------------------------
                            */
                            $amount += $this->calculateRoleCuttingInsentif(
                                $assignment->role,
                                'cutting',
                                $insentif
                            );
                        }
                    } else {
                        $amount = $this->evaluateFormula($component->formula, $results, $inputVariables);
                    }
                }

                // 🔹 PERBAIKAN: bulatkan setiap komponen
                $amount = round((float) $amount, 0);
                $results[$component->code] = $amount;
                $results['late_minutes'] = $employee->late_minutes;

                // dd($results);

                if ($component->type === 'earning') {
                    $grandTotal += $amount;
                } else {
                    $grandTotal -= $amount;
                }
            }

            $grandTotal = round($grandTotal, 0);

            PayrollRunDetail::create([
                'run_id'        => $run->id,
                'employee_npk'  => $employee->NPK,
                'employee_name' => $employee->NAMA_KARYAWAN,
                'components'    => $results,
                'total_salary'  => $grandTotal
            ]);

            $totalPayroll += $grandTotal;

            // $payrollResults[] = [
            //     // 'run_id'        => $run->id,
            //     'absence_days'  => $employee->absence_days,
            //     'count_days'    => $count_days,
            //     'type' => $employee->type,
            //     'employee_npk'  => $employee->NPK,
            //     'employee_name' => $employee->NAMA_KARYAWAN,
            //     'components'    => $results,
            //     'total_salary'  => $grandTotal
            // ];
        }

        $run->update([
            'employee_count' => $employees->count(),
            'total_payroll'  => round($totalPayroll, 0)
        ]);


        /*
        |--------------------------------------------------------------------------
        | CREATE APPROVAL PAYROLL
        |--------------------------------------------------------------------------
        */

        $existsApprove = PayrollApprove::where('payroll_run_id', $run->id)->exists();

        if (!$existsApprove) {
            $settings = PayrollSetting::where('component', 'payroll')->get();

            if ($settings->count() > 0) {
                $approvals = $settings->pluck('approval')->toArray();

                $progress = collect($approvals)->map(function ($npk) {
                    $npkList = is_array($npk) ? $npk : json_decode($npk, true);
                    if (!is_array($npkList)) $npkList = [$npk];
                    $statusList = array_fill(0, count($npkList), 'waiting');
                    return [
                        'npk' => json_encode($npkList),
                        'status' => json_encode($statusList)
                    ];
                })->values();

                PayrollApprove::create([
                    'payroll_run_id' => $run->id,
                    'approval'       => $approvals,
                    'progress'       => $progress,
                    'approved_at'    => [],
                    'status'         => 'pending'
                ]);
            }
        }


        // return response()->json($payrollResults);

        Alert::success('Payroll generated successfully!');
        return redirect('payroll-process/index');
    }

    public function details($id)
    {
        /*
    |--------------------------------------------------------------------------
    | CHECK USER ROLE
    |--------------------------------------------------------------------------
    */

        $canSeeSalary = Auth::user()->hasRole(['Admin', 'Payroll']);


        /*
    |--------------------------------------------------------------------------
    | GET DATA
    |--------------------------------------------------------------------------
    */
        $data = DB::table('payroll_run_details')
            ->where('run_id', $id)
            ->select(
                'run_id',
                'employee_npk',
                'employee_name',
                'components',
                'total_salary'
            )
            ->orderBy('employee_npk')
            ->get();


        /*
    |--------------------------------------------------------------------------
    | TRANSFORM COMPONENTS
    |--------------------------------------------------------------------------
    */
        $data->transform(function ($item) use ($canSeeSalary) {

            $components = json_decode($item->components, true) ?? [];

            foreach ($components as $key => $value) {

                /*
            |--------------------------------------------------------------
            | HIDE NOMINAL IF NOT ADMIN / PAYROLL
            |--------------------------------------------------------------
            */
                $item->$key = $canSeeSalary
                    ? $value
                    : '***';
            }

            /*
        |--------------------------------------------------------------
        | TOTAL SALARY
        |--------------------------------------------------------------
        */
            $item->total_salary = $canSeeSalary
                ? $item->total_salary
                : '***';

            return $item;
        });


        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */
        return response()->json([
            'data' => $data
        ]);
    }

    public function destroy($period_id)
    {
        DB::beginTransaction();

        try {

            // ambil semua run id dari period
            $runIds = PayrollRun::where('id', $period_id)->pluck('id');
            if ($runIds->count() > 0) {

                // hapus detail payroll
                PayrollRunDetail::whereIn('run_id', $runIds)->delete();

                // hapus run payroll
                PayrollRun::whereIn('id', $runIds)->delete();
            }

            DB::commit();

            return redirect()->back()->with('success', 'Payroll deleted successfully');
        } catch (\Exception $e) {

            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function slip($run_id, $npk)
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

        $pdf = Pdf::loadView('payroll.slip', $data)
            ->setPaper('A4', 'portrait');

        return $pdf->download('slip-gaji-' . $employee->employee_npk . '.pdf');
    }

    public function export($run_id)
    {

        $export = PayrollExport::create([
            'run_id' => $run_id,
            'status' => 'processing',
            'progress' => 0
        ]);

        $type = 'process';

        GeneratePayrollExport::dispatch($export->id, $type);

        Alert::success('Sukses', 'Export payroll selesai diproses!');
        return redirect('payroll-process/index');
        // return response()->json([
        //     'message' => 'Export started',
        //     'export_id' => $export->id
        // ]);
    }

    public function progress($id)
    {

        $export = PayrollExport::findOrFail($id);

        return response()->json([
            'progress' => $export->progress,
            'status' => $export->status
        ]);
    }

    private function evaluateFormula($formula, $results, $inputVariables)
    {
        $variables = array_merge($inputVariables, $results);

        foreach ($variables as $key => $value) {
            $formula = preg_replace('/\b' . $key . '\b/', $value, $formula);
        }

        try {
            return eval("return $formula;");
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function getInsentifByEfficiency($efficiency, $rules)
    {
        krsort($rules);

        foreach ($rules as $threshold => $value) {
            if ($efficiency >= $threshold) {
                return $value;
            }
        }

        return 0;
    }

    private function calculateRoleSewingInsentif(
        $role,
        $dept,
        $totalLineInsentif,
        $jumlahLine
    ) {

        $jumlahLine = max($jumlahLine, 1);

        /*
    |--------------------------------------------------------------------------
    | GET FORMULA FROM DB (CACHE)
    |--------------------------------------------------------------------------
    */

        $formula = Cache::remember(
            "insentif_formula_{$dept}_{$role}",
            300,
            function () use ($role, $dept) {

                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', $dept)
                    ->value('formula');
            }
        );

        /*
    |--------------------------------------------------------------------------
    | DEFAULT FALLBACK
    |--------------------------------------------------------------------------
    */

        if (!$formula) {
            return $totalLineInsentif;
        }

        /*
    |--------------------------------------------------------------------------
    | VARIABLE REPLACEMENT
    |--------------------------------------------------------------------------
    */

        $variables = [
            'totalLineInsentif' => $totalLineInsentif,
            'jumlahLine'        => $jumlahLine,
        ];

        foreach ($variables as $key => $value) {
            $formula = str_replace($key, $value, $formula);
        }

        /*
    |--------------------------------------------------------------------------
    | SAFE EVALUATION
    |--------------------------------------------------------------------------
    */

        try {

            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {

            return $totalLineInsentif;
        }
    }

    private function calculateRolePadInsentif(
        $role,
        $dept,
        $totalDeptInsentif,
        $jumlahOperator
    ) {

        $jumlahOperator = max($jumlahOperator, 1);

        /*
    |--------------------------------------------------------------------------
    | GET FORMULA FROM DB (CACHE)
    |--------------------------------------------------------------------------
    */

        $formula = Cache::remember(
            "insentif_formula_{$dept}_{$role}",
            300,
            function () use ($role, $dept) {

                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', $dept)
                    ->value('formula');
            }
        );

        /*
    |--------------------------------------------------------------------------
    | DEFAULT FALLBACK
    |--------------------------------------------------------------------------
    */

        if (!$formula) {
            return $totalDeptInsentif;
        }

        /*
    |--------------------------------------------------------------------------
    | VARIABLE REPLACEMENT
    |--------------------------------------------------------------------------
    */

        $variables = [
            'totalDeptInsentif' => $totalDeptInsentif,
            'jumlahOperator'    => $jumlahOperator,
        ];

        foreach ($variables as $key => $value) {
            $formula = str_replace($key, $value, $formula);
        }

        /*
    |--------------------------------------------------------------------------
    | SAFE EVALUATION
    |--------------------------------------------------------------------------
    */

        try {

            // hanya izinkan karakter matematika
            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {

            return $totalDeptInsentif;
        }
    }

    private function calculateRoleCuttingInsentif(
        $role,
        $dept,
        $insentif
    ) {

        /*
    |--------------------------------------------------------------------------
    | GET FORMULA FROM DB (CACHE)
    |--------------------------------------------------------------------------
    */

        $formula = Cache::remember(
            "insentif_formula_{$dept}_{$role}",
            300,
            function () use ($role, $dept) {

                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', $dept)
                    ->value('formula');
            }
        );

        /*
    |--------------------------------------------------------------------------
    | DEFAULT FALLBACK
    |--------------------------------------------------------------------------
    */

        if (!$formula) {
            return $insentif;
        }

        /*
    |--------------------------------------------------------------------------
    | VARIABLE REPLACEMENT
    |--------------------------------------------------------------------------
    */

        $variables = [
            'insentif' => $insentif,
        ];

        foreach ($variables as $key => $value) {
            $formula = str_replace($key, $value, $formula);
        }

        /*
    |--------------------------------------------------------------------------
    | SAFE EVALUATION
    |--------------------------------------------------------------------------
    */

        try {

            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {

            return $insentif;
        }
    }
}
