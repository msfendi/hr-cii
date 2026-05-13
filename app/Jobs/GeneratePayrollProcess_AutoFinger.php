<?php

namespace App\Jobs;

use App\Models\InsentifRoleFormula;
use App\Models\PayrollApprove;
use App\Models\PayrollComponent;
use App\Models\PayrollExport;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use App\Models\PayrollSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GeneratePayrollProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $runId;

    public function __construct($runId)
    {
        $this->runId = $runId;
    }

    public function handle()
    {

        $run = PayrollRun::findOrFail($this->runId);
        $period = PayrollPeriod::findOrFail($run->period_id);

        $periodStart = $period->start_date;
        $periodEnd   = $period->end_date;
        $count_days  = Carbon::parse($periodStart)->diffInDays(Carbon::parse($periodEnd)) + 1;

        /*
        |--------------------------------------------------------------------------
        | BIODATA UNION (DITAMBAHKAN BARCODE)
        |--------------------------------------------------------------------------
        */

        $run->update([
            'status' => 'Unioning Biodata',
            'progress' => 5,
        ]);

        $biodataUnion = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            ->unionAll(
                DB::connection('cii')
                    ->table('BIODATA_KELUAR')
                    ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            );

        // dd($biodataUnion->get());
        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE BASE + SHIFT
        |--------------------------------------------------------------------------
        */


        $run->update([
            'status' => 'Getting Employee Biodata',
            'progress' => 15,
        ]);

        $employeeBase = DB::connection('cii')
            ->table('PKWT as p')

            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('p.NPK', '=', 'bio.NPK');
            })

            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })

            // ->where('p.NPK', '=', 'C-00216')

            ->select(
                'p.NPK',
                'bio.NAMA_KARYAWAN',
                DB::raw("CAST(bio.BARCODE AS VARCHAR(50)) AS BARCODE"),
                'p.TMK',
                'p.TKK',
                'bio.ID_DEPT',
                'bio.SECTION',
            )
            ->distinct();

        // dd($employeeBase->get());



        $latestContract = DB::table('employees_contract as ec1')
            ->select(
                'ec1.npk',
                'ec1.salary',
                'ec1.allowance',
                'ec1.pph21',
                'ec1.type',
                'ec1.daily_salary'
            )
            ->whereRaw('ec1.id = (
            SELECT TOP 1 ec2.id
            FROM employees_contract ec2
            WHERE ec2.npk = ec1.npk
            ORDER BY ec2.contract_ke DESC, ec2.start_date DESC
        )');


        $run->update([
            'status' => 'Getting Employee Overtime Data',
            'progress' => 20,
        ]);

        $dailyAttendance = DB::connection('cii')

            ->table(DB::raw("({$employeeBase->toSql()}) as emp"))
            ->mergeBindings($employeeBase)

            /*
    |--------------------------------------------------------------------------
    | ATT LOG
    |--------------------------------------------------------------------------
    */
            ->leftJoin('att_log as al', function ($join) use ($periodStart, $periodEnd) {

                $join->on(
                    DB::raw('CAST(emp.BARCODE AS VARCHAR(50))'),
                    '=',
                    DB::raw('CAST(al.pin AS VARCHAR(50))')
                )
                    ->whereRaw("
            TRY_CONVERT(datetime, al.scan_date)
            BETWEEN ? AND ?
        ", [$periodStart, $periodEnd]);
            })

            /*
    |--------------------------------------------------------------------------
    | SHIFT PER TANGGAL (FIX)
    |--------------------------------------------------------------------------
    */
            ->leftJoin('employee_shifts as es', function ($join) {

                $join->on('emp.NPK', '=', 'es.npk')
                    ->whereRaw("
                es.shift_date =
                TRY_CONVERT(date, al.scan_date)
            ");
            })

            ->leftJoin('shifts as s', 'es.shift_id', '=', 's.id')

            ->select(

                'emp.NPK',

                /*
        |--------------------------------------------------------------------------
        | WORK DATE
        |--------------------------------------------------------------------------
        */
                DB::raw("
            TRY_CONVERT(date, al.scan_date) as work_date
        "),

                /*
        |--------------------------------------------------------------------------
        | WORK DAY
        |--------------------------------------------------------------------------
        */
                DB::raw("
            CASE DATENAME(WEEKDAY, TRY_CONVERT(datetime, al.scan_date))
                WHEN 'Monday' THEN 'Senin'
                WHEN 'Tuesday' THEN 'Selasa'
                WHEN 'Wednesday' THEN 'Rabu'
                WHEN 'Thursday' THEN 'Kamis'
                WHEN 'Friday' THEN 'Jumat'
                WHEN 'Saturday' THEN 'Sabtu'
                WHEN 'Sunday' THEN 'Minggu'
            END as work_day
        "),

                /*
        |--------------------------------------------------------------------------
        | SHIFT START (PER DATE)
        |--------------------------------------------------------------------------
        */
                DB::raw("
            COALESCE(
                TRY_CONVERT(time,s.work_start),
                '08:00:00'
            ) as shift_start
        "),

                /*
        |--------------------------------------------------------------------------
        | SHIFT END (PER DATE)
        |--------------------------------------------------------------------------
        */
                DB::raw("
            COALESCE(
                TRY_CONVERT(time,s.work_end),
                '17:00:00'
            ) as shift_end
        "),

                /*
        |--------------------------------------------------------------------------
        | SCAN IN PROTECTION
        |--------------------------------------------------------------------------
        */
                DB::raw("
        CASE
            WHEN COUNT(al.scan_date)=1
            THEN
                CASE
                    WHEN
                        DATEDIFF(MINUTE,
                            DATEADD(SECOND,
                                DATEDIFF(SECOND,'00:00:00',
                                    COALESCE(TRY_CONVERT(time,s.work_start),'08:00:00')
                                ),
                                CAST(TRY_CONVERT(date,al.scan_date) as datetime)
                            ),
                            MIN(TRY_CONVERT(datetime,al.scan_date))
                        )
                        <
                        DATEDIFF(MINUTE,
                            MIN(TRY_CONVERT(datetime,al.scan_date)),
                            DATEADD(SECOND,
                                DATEDIFF(SECOND,'00:00:00',
                                    COALESCE(TRY_CONVERT(time,s.work_end),'17:00:00')
                                ),
                                CAST(TRY_CONVERT(date,al.scan_date) as datetime)
                            )
                        )
                    THEN MIN(TRY_CONVERT(datetime,al.scan_date))
                    ELSE
                        DATEADD(SECOND,
                            DATEDIFF(SECOND,'00:00:00',
                                COALESCE(TRY_CONVERT(time,s.work_start),'08:00:00')
                            ),
                            CAST(TRY_CONVERT(date,al.scan_date) as datetime)
                        )
                END
            ELSE MIN(TRY_CONVERT(datetime,al.scan_date))
        END as scan_in
        "),

                /*
        |--------------------------------------------------------------------------
        | SCAN OUT PROTECTION
        |--------------------------------------------------------------------------
        */
                DB::raw("
        CASE
            WHEN COUNT(al.scan_date)=1
            THEN
                CASE
                    WHEN
                        DATEDIFF(MINUTE,
                            DATEADD(SECOND,
                                DATEDIFF(SECOND,'00:00:00',
                                    COALESCE(TRY_CONVERT(time,s.work_start),'08:00:00')
                                ),
                                CAST(TRY_CONVERT(date,al.scan_date) as datetime)
                            ),
                            MAX(TRY_CONVERT(datetime,al.scan_date))
                        )
                        <
                        DATEDIFF(MINUTE,
                            MAX(TRY_CONVERT(datetime,al.scan_date)),
                            DATEADD(SECOND,
                                DATEDIFF(SECOND,'00:00:00',
                                    COALESCE(TRY_CONVERT(time,s.work_end),'17:00:00')
                                ),
                                CAST(TRY_CONVERT(date,al.scan_date) as datetime)
                            )
                        )
                    THEN
                        DATEADD(SECOND,
                            DATEDIFF(SECOND,'00:00:00',
                                COALESCE(TRY_CONVERT(time,s.work_end),'17:00:00')
                            ),
                            CAST(TRY_CONVERT(date,al.scan_date) as datetime)
                        )
                    ELSE MAX(TRY_CONVERT(datetime,al.scan_date))
                END
            ELSE MAX(TRY_CONVERT(datetime,al.scan_date))
        END as scan_out
        ")
            )

            ->groupBy(
                'emp.NPK',
                DB::raw("TRY_CONVERT(date, al.scan_date)"),
                DB::raw("
            CASE DATENAME(WEEKDAY, TRY_CONVERT(datetime, al.scan_date))
                WHEN 'Monday' THEN 'Senin'
                WHEN 'Tuesday' THEN 'Selasa'
                WHEN 'Wednesday' THEN 'Rabu'
                WHEN 'Thursday' THEN 'Kamis'
                WHEN 'Friday' THEN 'Jumat'
                WHEN 'Saturday' THEN 'Sabtu'
                WHEN 'Sunday' THEN 'Minggu'
            END
        "),
                DB::raw("COALESCE(TRY_CONVERT(time,s.work_start),'08:00:00')"),
                DB::raw("COALESCE(TRY_CONVERT(time,s.work_end),'17:00:00')")
            );
        // return response()->json($dailyAttendance->get());

        $overtimeSummary = DB::connection('cii')

            ->table(DB::raw("({$dailyAttendance->toSql()}) as d"))
            ->mergeBindings($dailyAttendance)

            ->leftJoinSub($latestContract, 'ec', function ($join) {
                $join->on('d.NPK', '=', 'ec.npk');
            })

            ->select(
                'd.NPK',

                /*
        |--------------------------------------------------------------------------
        | WEEKDAY OVERTIME
        |--------------------------------------------------------------------------
        */
                DB::raw("
        SUM(
            CASE
                WHEN DATENAME(WEEKDAY,d.work_date) NOT IN ('Saturday','Sunday')
                AND d.scan_out IS NOT NULL
                AND d.scan_in <> d.scan_out

                THEN
                    CASE
                        WHEN
                            (
                                COALESCE(ec.salary,0)
                                + COALESCE(ec.allowance,0)
                            ) >= 3800000
                            OR
                            (
                                (COALESCE(ec.daily_salary,0)*{$count_days})
                                + COALESCE(ec.allowance,0)
                            ) >= 3800000
                        THEN 0

                        ELSE
                            CASE
                                WHEN DATEDIFF(
                                    MINUTE,
                                    CAST(d.work_date as datetime)
                                        + CAST(d.shift_end as datetime),
                                    d.scan_out
                                ) <= 0
                                THEN 0

                                ELSE FLOOR(
                                    DATEDIFF(
                                        MINUTE,
                                        CAST(d.work_date as datetime)
                                            + CAST(d.shift_end as datetime),
                                        d.scan_out
                                    ) / 60.0
                                )
                            END
                    END
                ELSE 0
            END
        ) as overtime_hours
        "),

                /*
        |--------------------------------------------------------------------------
        | WEEKEND OVERTIME (FIX NEGATIVE BUG)
        |--------------------------------------------------------------------------
        */
                DB::raw("
        SUM(
            CASE
                WHEN DATENAME(WEEKDAY,d.work_date) IN ('Saturday','Sunday')
                AND d.scan_out IS NOT NULL
                AND d.scan_in <> d.scan_out

                THEN
                    CASE
                        WHEN
                            (
                                COALESCE(ec.salary,0)
                                + COALESCE(ec.allowance,0)
                            ) >= 3800000
                            OR
                            (
                                (COALESCE(ec.daily_salary,0)*{$count_days})
                                + COALESCE(ec.allowance,0)
                            ) >= 3800000
                        THEN
                            CASE
                                WHEN DATEDIFF(
                                    MINUTE,
                                    CAST(d.work_date as datetime)
                                        + CAST(d.shift_end as datetime),
                                    d.scan_out
                                ) <= 0
                                THEN 0

                                WHEN FLOOR(
                                    DATEDIFF(
                                        MINUTE,
                                        CAST(d.work_date as datetime)
                                            + CAST(d.shift_end as datetime),
                                        d.scan_out
                                    ) /60.0
                                ) > 8
                                THEN 8

                                ELSE FLOOR(
                                    DATEDIFF(
                                        MINUTE,
                                        CAST(d.work_date as datetime)
                                            + CAST(d.shift_end as datetime),
                                        d.scan_out
                                    ) /60.0
                                )
                            END

                        ELSE
                            CASE
                                WHEN DATEDIFF(
                                    MINUTE,
                                    CAST(d.work_date as datetime)
                                        + CAST(d.shift_end as datetime),
                                    d.scan_out
                                ) <= 0
                                THEN 0

                                ELSE FLOOR(
                                    DATEDIFF(
                                        MINUTE,
                                        CAST(d.work_date as datetime)
                                            + CAST(d.shift_end as datetime),
                                        d.scan_out
                                    ) /60.0
                                )
                            END
                    END
                ELSE 0
            END
        ) as special_overtime_hours
        "),

                /*
        |--------------------------------------------------------------------------
        | ABSENCE (MA)
        |--------------------------------------------------------------------------
        */
                DB::raw("
        SUM(
            CASE
                WHEN d.scan_in IS NULL
                 AND d.scan_out IS NULL
                THEN 1
                ELSE 0
            END
        ) as absence_days
        ")
            )

            ->groupBy('d.NPK');
        // return response()->json($overtimeSummary->get());

        // CHECK PER DATE LATE

        //         $lateSummary =
        //             DB::connection('cii')
        //             ->query()

        //             /*
        // |--------------------------------------------------------------------------
        // | EMPLOYEE + CALENDAR
        // |--------------------------------------------------------------------------
        // */
        //             ->fromSub(function ($q) use ($employeeBase, $periodStart, $periodEnd) {

        //                 $q->fromSub($employeeBase, 'emp')

        //                     ->crossJoinSub(

        //                         DB::connection('cii')
        //                             ->query()
        //                             ->selectRaw("
        //                     DATEADD(
        //                         DAY,
        //                         v.number,
        //                         CAST(? AS DATE)
        //                     ) as shift_date
        //                 ", [$periodStart])

        //                             ->from(DB::raw('master..spt_values v'))
        //                             ->where('v.type', 'P')
        //                             ->whereRaw("
        //                     v.number <= DATEDIFF(
        //                         DAY,
        //                         CAST(? AS DATE),
        //                         CAST(? AS DATE)
        //                     )
        //                 ", [$periodStart, $periodEnd]),

        //                         'cal'
        //                     )

        //                     ->select(
        //                         'emp.*',
        //                         DB::raw('cal.shift_date')
        //                     );
        //             }, 'emp')


        //             /*
        // |--------------------------------------------------------------------------
        // | DAILY SHIFT
        // |--------------------------------------------------------------------------
        // */
        //             ->leftJoin('employee_shifts as es', function ($join) {

        //                 $join->on('emp.NPK', '=', 'es.npk')
        //                     ->on(
        //                         DB::raw('CAST(emp.shift_date AS DATE)'),
        //                         '=',
        //                         DB::raw('CAST(es.shift_date AS DATE)')
        //                     );
        //             })

        //             ->leftJoin('shifts as s', 'es.shift_id', '=', 's.id')


        //             /*
        // |--------------------------------------------------------------------------
        // | ATT LOG
        // |--------------------------------------------------------------------------
        // */
        //             ->leftJoinSub(

        //                 DB::connection('cii')
        //                     ->table('att_log')
        //                     ->whereBetween(
        //                         DB::raw('CAST(scan_date AS DATE)'),
        //                         [$periodStart, $periodEnd]
        //                     )
        //                     ->select(
        //                         DB::raw('CAST(pin AS VARCHAR(50)) as pin'),
        //                         DB::raw('CAST(scan_date AS DATE) as scan_day'),
        //                         'scan_date'
        //                     ),

        //                 'att',

        //                 function ($join) {

        //                     $join->on(
        //                         DB::raw('CAST(emp.BARCODE AS VARCHAR(50))'),
        //                         '=',
        //                         'att.pin'
        //                     )

        //                         ->whereRaw("
        //             CAST(att.scan_day AS DATE)
        //             =
        //             CAST(emp.shift_date AS DATE)
        //         ");
        //                 }
        //             )


        //             /*
        // |--------------------------------------------------------------------------
        // | FALLBACK SHIFT
        // |--------------------------------------------------------------------------
        // */
        //             ->leftJoin('shifts as fs', function ($join) {

        //                 $join->whereRaw("
        //         es.shift_id IS NULL
        //         AND CAST(att.scan_date AS DATE)
        //             BETWEEN fs.start_date
        //             AND COALESCE(fs.end_date, CAST(att.scan_date AS DATE))
        //     ");
        //             })


        //             /*
        // |--------------------------------------------------------------------------
        // | LATE COMPENSATION
        // |--------------------------------------------------------------------------
        // */
        //             ->leftJoin('late_compensations as lc', function ($join) {

        //                 $join->on('emp.NPK', '=', 'lc.npk')
        //                     ->whereRaw("
        //             CAST(lc.date AS DATE)
        //             =
        //             CAST(emp.shift_date AS DATE)
        //         ");
        //             })


        //             /*
        // |--------------------------------------------------------------------------
        // | SHIFT RESOLUTION
        // |--------------------------------------------------------------------------
        // */
        //             ->selectRaw("
        //     emp.NPK,
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


        //             /*
        // |--------------------------------------------------------------------------
        // | FIRST SCAN
        // |--------------------------------------------------------------------------
        // */
        //             ->selectRaw("
        //     MIN(att.scan_date) as first_scan
        // ")


        //             /*
        // |--------------------------------------------------------------------------
        // | ⭐ LATE ENGINE (LOGIC TIDAK DIUBAH)
        // |--------------------------------------------------------------------------
        // */
        //             ->selectRaw("
        // CASE

        //     -- ADA KOMPENSASI
        //     WHEN lc.id IS NOT NULL THEN 0

        //     -- TIDAK ADA ABSEN
        //     WHEN MIN(att.scan_date) IS NULL THEN 0

        //     -- ⭐ FIX: SCAN SETELAH JAM PULANG (FINGER PULANG SAJA)
        //     WHEN MIN(att.scan_date) >
        //         DATEADD(
        //             SECOND,
        //             DATEDIFF(
        //                 SECOND,'00:00:00',
        //                 COALESCE(
        //                     CAST(s.work_end AS TIME),
        //                     CAST(fs.work_end AS TIME),
        //                     '17:00:00'
        //                 )
        //             ),
        //             CAST(emp.shift_date AS DATETIME)
        //         )
        //     THEN 0

        //     -- GRACE PERIOD
        //     WHEN DATEDIFF(
        //         MINUTE,
        //         DATEADD(
        //             MINUTE,5,
        //             DATEADD(
        //                 SECOND,
        //                 DATEDIFF(
        //                     SECOND,'00:00:00',
        //                     COALESCE(
        //                         CAST(s.work_start AS TIME),
        //                         CAST(fs.work_start AS TIME),
        //                         '08:00:00'
        //                     )
        //                 ),
        //                 CAST(emp.shift_date AS DATETIME)
        //             )
        //         ),
        //         MIN(att.scan_date)
        //     ) < 0 THEN 0

        //     ELSE
        //     DATEDIFF(
        //         MINUTE,
        //         DATEADD(
        //             MINUTE,5,
        //             DATEADD(
        //                 SECOND,
        //                 DATEDIFF(
        //                     SECOND,'00:00:00',
        //                     COALESCE(
        //                         CAST(s.work_start AS TIME),
        //                         CAST(fs.work_start AS TIME),
        //                         '08:00:00'
        //                     )
        //                 ),
        //                 CAST(emp.shift_date AS DATETIME)
        //             )
        //         ),
        //         MIN(att.scan_date)
        //     )

        // END as late_minute
        // ")


        //             ->groupBy(
        //                 'emp.NPK',
        //                 DB::raw('CAST(emp.BARCODE AS VARCHAR(50))'),
        //                 DB::raw('CAST(emp.shift_date AS DATE)'),
        //                 's.work_start',
        //                 's.work_end',
        //                 'fs.work_start',
        //                 'fs.work_end',
        //                 'lc.id'
        //             )

        //             ->whereBetween(
        //                 DB::raw('CAST(emp.shift_date AS DATE)'),
        //                 [$periodStart, $periodEnd]
        //             )

        //             ->orderBy(DB::raw('CAST(emp.shift_date AS DATE)'));

        // SUMMARY LATE

        $run->update([
            'status' => 'Calculating Late Minutes',
            'progress' => 25,
        ]);

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
| FALLBACK SHIFT
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
| DAILY LATE RESULT (100% ORIGINAL)
|--------------------------------------------------------------------------
*/
                    ->selectRaw("
        emp.NPK,
        CAST(emp.BARCODE AS VARCHAR(50)) as pin,
        CAST(emp.shift_date AS DATE) as shift_date,

        MIN(att.scan_date) as first_scan,

        COALESCE(
            CAST(s.work_end AS TIME),
            CAST(fs.work_end AS TIME),
            '17:00:00'
        ) as work_end,

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
| LATE COMPENSATION
|--------------------------------------------------------------------------
*/
            ->leftJoin('late_compensations as lc', function ($join) {

                $join->on('daily.NPK', '=', 'lc.npk')
                    ->whereRaw("
            CAST(lc.date AS DATE)
            =
            CAST(daily.shift_date AS DATE)
        ");
            })


            /*
|--------------------------------------------------------------------------
| FINAL RESULT (WINDOW FILTER ADDED)
|--------------------------------------------------------------------------
*/
            ->selectRaw("
    daily.NPK as npk,
    daily.pin,

    SUM(
        CASE
            WHEN lc.id IS NOT NULL THEN 0

            -- ⭐ WINDOW: SCAN SETELAH JAM PULANG
            WHEN daily.first_scan >
                DATEADD(
                    SECOND,
                    DATEDIFF(SECOND,'00:00:00',daily.work_end),
                    CAST(daily.shift_date AS DATETIME)
                )
            THEN 0

            ELSE daily.late_minute
        END
    ) as late_minutes
")

            ->groupBy('daily.NPK', 'daily.pin');

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


        $run->update([
            'status' => 'Combining Employee Data',
            'progress' => 30,
        ]);

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

            // ->leftJoin('payroll_masters as pm',   'emp.NPK', '=', 'pm.npk')
            ->leftJoinSub($latestContract, 'ec', function ($join) {
                $join->on('emp.NPK', '=', 'ec.npk');
            })

            ->leftJoin('payroll_adjusments as pa', function ($join) use ($period) {
                $join->on('emp.NPK', '=', 'pa.npk')
                    ->where('pa.period_id', '=', $period->id);
            })

            ->leftJoin('DEPT as d', 'emp.ID_DEPT', '=', 'd.ID_DEPT')

            ->select(
                'emp.NPK',
                'emp.NAMA_KARYAWAN',
                'emp.BARCODE',
                'emp.ID_DEPT',
                'd.DEPARTEMENT as DEPARTEMENT',
                'emp.SECTION as SECTION',

                'ec.salary',
                'ec.allowance',
                'ec.pph21',
                'ec.type',
                'ec.daily_salary',

                DB::raw('COALESCE(pa.adjusment,0) as adjusment'),
                DB::raw('COALESCE(ot.overtime_hours,0) as overtime_hours'),
                DB::raw('COALESCE(ot.special_overtime_hours,0) as special_overtime_hours'),
                DB::raw('COALESCE(ot.absence_days,0) as absence_days'),
                DB::raw('COALESCE(lt.late_minutes,0) as late_minutes'),

                DB::raw("DATEDIFF(YEAR, emp.TMK, '$periodEnd') as working_years")
            )
            ->get();


        // dd($employees);


        $run->update([
            'status' => 'Getting Payroll Components',
            'progress' => 35,
        ]);

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

        $heatInsentifComponent = PayrollComponent::where('code', 'heat_insentif')->first();
        $heatInsentifFormula = json_decode($heatInsentifComponent->formula, true);

        $totalPayroll = 0;

        $run->update([
            'status' => 'Starting Payroll Calculation',
            'progress' => 40,
        ]);
        foreach ($employees as $employee) {

            $roleData = DB::table('dept_insentif_role as lir')
                ->join('insentif_role_formulas as irf', 'lir.role', '=', 'irf.id')
                ->where('lir.id_dept', $employee->ID_DEPT)
                ->where('irf.is_active', 1)
                ->select(
                    'irf.id',
                    'irf.role',
                    'irf.dept',
                    'irf.formula'
                )
                ->first();

            /*
    |--------------------------------------------------------------------------
    | SET ROLE KE EMPLOYEE OBJECT
    |--------------------------------------------------------------------------
    */

            $employee->role = $roleData->role ?? null;

            // dd($employee->role);

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
                'overtime_hours' => (float) $employee->overtime_hours,
                'special_overtime_hours' => (float) $employee->special_overtime_hours
            ];

            // dd($employee->late_minutes);

            $results = [];
            $grandTotal = 0;

            foreach ($components as $component) {
                if ($component->code === 'thr') continue;
                if ($component->code === 'compensation') continue;

                if ($component->calculation_method === 'fixed') {
                    $amount = $component->value;
                } else {

                    $run->update([
                        'status' => 'Calculation for ' . $employee->NPK . ' - ' . $component->name,
                        'progress' => 60
                    ]);

                    if ($component->code === 'overtime_pay') {
                        $amount = $this->evaluateFormula(
                            $overtimeFormula,
                            $results,
                            $inputVariables
                        );
                    } else if ($component->code === 'special_overtime_pay') {


                        $run->update([
                            'status' => 'Calculation for ' . $employee->NPK . ' - ' . $component->name,
                            'progress' => 60
                        ]);
                        $amount = $this->evaluateFormula(
                            $specialOvertimeFormula,
                            $results,
                            $inputVariables
                        );
                    } else if ($component->code === 'sewing_insentif') {

                        if (empty($employee->role)) {
                            continue;
                        }

                        $run->update([
                            'status' => 'Calculation for ' . $employee->NPK . ' - ' . $component->name,
                            'progress' => 60
                        ]);

                        $amount = 0;

                        $collectionLinesTest = collect([]);
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
        | GET MUTATIONS EMPLOYEE
        |--------------------------------------------------------------------------
        */
                        $mutations = DB::table('employee_mutations')
                            ->leftJoin('DEPT as d', 'employee_mutations.to_dept', '=', 'd.ID_DEPT')
                            ->where('npk', $employee->NPK)
                            ->orderBy('date')
                            ->get();

                        // dd($mutations);


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
        | OPERATOR
        |--------------------------------------------------------------------------
        */
                        if ($employee->role == 'operator' || $employee->role == 'supervisor') {

                            /*
            |--------------------------------------------------------------------------
            | GET INITIAL LINE
            |--------------------------------------------------------------------------
            */
                            preg_match('/\d+/', $employee->DEPARTEMENT, $matches);
                            $defaultLine = $matches[0] ?? null;

                            /*
            |--------------------------------------------------------------------------
            | GET ALL LINE EFFICIENCIES
            |--------------------------------------------------------------------------
            */
                            $lineefficiencies = DB::table('line_efficiencies as le')
                                ->where('le.period_id', $period->id)
                                ->whereBetween('le.date', [$period->start_date, $period->end_date])
                                ->select(
                                    'le.line_number',
                                    'le.efficiency',
                                    'le.date'
                                )
                                ->orderBy('le.date')
                                ->get();

                            foreach ($lineefficiencies as $row) {

                                /*
                |--------------------------------------------------------------------------
                | CHECK OVERTIME
                |--------------------------------------------------------------------------
                */
                                if (!$isValidOvertime($row->date)) {
                                    continue;
                                }

                                /*
                |--------------------------------------------------------------------------
                | DETERMINE EMPLOYEE LINE BY DATE
                |--------------------------------------------------------------------------
                */
                                $employeeLine = $defaultLine;

                                foreach ($mutations as $mutation) {
                                    if ($mutation->date <= $row->date) {
                                        preg_match('/\d+/', $mutation->DEPARTEMENT, $m);
                                        // dd($employeeLine, $m[0]);
                                        $employeeLine = $m[0] ?? $employeeLine;
                                    }
                                }

                                /*
                |--------------------------------------------------------------------------
                | SKIP IF NOT EMPLOYEE LINE
                |--------------------------------------------------------------------------
                */

                                $collectionLinesTest->push($row->date . '-' . $row->line_number . '-' . $employeeLine);
                                // dd($row->line_number, $employeeLine);
                                if ($row->line_number != $employeeLine) {
                                    continue;
                                }

                                /*
                |--------------------------------------------------------------------------
                | CALCULATE INSENTIF
                |--------------------------------------------------------------------------
                */
                                $lineInsentif =
                                    $this->getInsentifByEfficiency($row->efficiency, $sewingInsentifFormula);

                                $amount += $this->calculateRoleSewingInsentif(
                                    $employee->role,
                                    'sewing',
                                    $lineInsentif,
                                    1 //karena hanya 1 line
                                );
                            }
                        } else {

                            /*
            |--------------------------------------------------------------------------
            | CHIEF / MEKANIK / MEKANIK LEADER
            |--------------------------------------------------------------------------
            */
                            $validRoles = ['chief', 'mekanik', 'mekanik_leader'];

                            if (!in_array($employee->role, $validRoles)) {
                                // return $amount;
                                continue;
                            }


                            $section = DB::table('sections')
                                ->whereRaw('id = ?', [(int) $employee->SECTION])
                                ->select('line_start', 'line_end')
                                ->first();

                            if (!$section) {
                                // return $amount;
                                continue;
                            }

                            $lineStart = $section->line_start;
                            $lineEnd   = $section->line_end;

                            $grouped = DB::table('line_efficiencies')
                                ->where('period_id', $period->id)
                                ->whereBetween('date', [$period->start_date, $period->end_date])
                                ->whereBetween('line_number', [$lineStart, $lineEnd]) // ✅ FILTER SECTION
                                ->select(
                                    'date',
                                )
                                ->groupBy('date')
                                ->get();

                            // dd($grouped);

                            $jumlahLine = DB::table('line_efficiencies')
                                ->where('period_id', $period->id)
                                ->whereBetween('date', [$period->start_date, $period->end_date])
                                ->whereBetween('line_number', [$lineStart, $lineEnd])
                                ->selectRaw('COUNT(DISTINCT line_number) as jumlah_line')
                                ->get();

                            // dd($jumlahLine);

                            $collectionDay = collect([]);
                            $collectionLines = collect([]);
                            foreach ($grouped as $day) {
                                /*
                |----------------------------------
                | CHECK OVERTIME
                |----------------------------------
                */
                                if (!$isValidOvertime($day->date)) {
                                    continue;
                                }

                                $lines = DB::table('line_efficiencies')
                                    ->where('period_id', $period->id)
                                    ->where('date', $day->date)
                                    ->get();

                                $totalLineInsentif = 0;

                                foreach ($lines as $line) {

                                    $totalLineInsentif +=
                                        $this->getInsentifByEfficiency($line->efficiency, $sewingInsentifFormula);

                                    if ($totalLineInsentif <= 0) {
                                        continue;
                                    }

                                    $collectionLines->push($totalLineInsentif);

                                    // dd($grouped, $line, $totalLineInsentif, $day->jumlah_line, $amount);
                                }

                                // dd($collectionDay, $collectionLines);

                                $amount += $this->calculateRoleSewingInsentif(
                                    $employee->role,
                                    'sewing',
                                    $totalLineInsentif,
                                    $jumlahLine->first()->jumlah_line
                                );

                                $collectionDay->push($amount);
                            }
                            // dd($collectionDay->values()->toJson());
                        }
                    } else if ($component->code === 'pad_insentif') {
                        if (empty($employee->role)) {
                            continue;
                        }
                        $run->update([
                            'status' => 'Calculation for ' . $employee->NPK . ' - ' . $component->name,
                            'progress' => 60
                        ]);

                        $amount = 0;

                        /*
        |--------------------------------------------------------------------------
        | GET MUTATIONS EMPLOYEE
        |--------------------------------------------------------------------------
        */
                        $mutations = DB::table('employee_mutations')
                            ->leftJoin('DEPT as d', 'employee_mutations.to_dept', '=', 'd.ID_DEPT')
                            ->where('npk', $employee->NPK)
                            ->orderBy('date')
                            ->get();

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
    | LOAD ASSIGNMENT
    |--------------------------------------------------------------------------
    */
                        $assignments = DB::table('pad_efficiencies')
                            ->where('npk', $employee->NPK)
                            ->where('period_id', $period->id)
                            ->whereBetween('date', [$period->start_date, $period->end_date])
                            ->get();

                        // dd($assignments);


                        /*
            |--------------------------------------------------------------------------
            | OPERATOR
            |--------------------------------------------------------------------------
            */
                        if ($employee->role === 'operator') {
                            foreach ($assignments as $assignment) {

                                // dd($rows);

                                if (!$isValidOvertime($assignment->npk, $assignment->date)) {
                                    continue;
                                }

                                $rate = $this->getInsentifByEfficiency(
                                    $assignment->efficiency,
                                    $padInsentifFormula
                                );

                                $amount += $rate * $assignment->piece;
                            }
                        }

                        /*
        |--------------------------------------------------------------------------
        | NON OPERATOR (SPV / LEADER / HELPER)
        |--------------------------------------------------------------------------
        */ else {

                            // dd($employee);

                            /*
            |----------------------------------
            | TOTAL DEPT INSENTIF
            | ONLY VALID OPERATOR
            |----------------------------------
            */
                            $totalDeptInsentif = 0;


                            $operators = DB::table('pad_efficiencies')
                                ->where('period_id', $period->id)
                                ->whereBetween('date', [$period->start_date, $period->end_date])
                                ->get();

                            // dd($operators);


                            foreach ($operators as $operator) {
                                // FILTER HANYA NUMERATOR
                                if (!$isValidOvertime($operator->npk, $operator->date)) {
                                    continue;
                                }

                                $rate = $this->getInsentifByEfficiency(
                                    $operator->efficiency,
                                    $padInsentifFormula
                                );

                                $totalDeptInsentif += $rate * $operator->piece;

                                // dd($totalDeptInsentif);
                            }

                            /*
                |----------------------------------
                | DENOMINATOR (ALL OPERATOR)
                |----------------------------------
                */
                            $jumlahOperator = DB::table('pad_efficiencies as pe')
                                ->where('pe.period_id', $period->id)
                                ->pluck('pe.npk')
                                ->unique()
                                ->count();

                            $amount += $this->calculateRolePadInsentif(
                                $employee->role,
                                'pad',
                                $totalDeptInsentif,
                                $jumlahOperator
                            );
                        }
                    } else if ($component->code === 'cutting_insentif') {

                        if (empty($employee->role)) {
                            continue;
                        }
                        $run->update([
                            'status' => 'Calculation for ' . $employee->NPK . ' - ' . $component->name,
                            'progress' => 60
                        ]);


                        $amount = 0;

                        /*
        |--------------------------------------------------------------------------
        | GET MUTATIONS EMPLOYEE
        |--------------------------------------------------------------------------
        */
                        $mutations = DB::table('employee_mutations')
                            ->leftJoin('DEPT as d', 'employee_mutations.to_dept', '=', 'd.ID_DEPT')
                            ->where('npk', $employee->NPK)
                            ->orderBy('date')
                            ->get();

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

                        // dd($cuttingEfficiencies);


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
                                $employee->role,
                                'cutting',
                                $insentif
                            );
                        }
                    } else if ($component->code === 'heat_insentif') {

                        if (empty($employee->role)) {
                            continue;
                        }
                        $run->update([
                            'status' => 'Calculation for ' . $employee->NPK . ' - ' . $component->name,
                            'progress' => 60
                        ]);
                        $amount = 0;

                        /*
        |--------------------------------------------------------------------------
        | GET MUTATIONS EMPLOYEE
        |--------------------------------------------------------------------------
        */
                        $mutations = DB::table('employee_mutations')
                            ->leftJoin('DEPT as d', 'employee_mutations.to_dept', '=', 'd.ID_DEPT')
                            ->where('npk', $employee->NPK)
                            ->orderBy('date')
                            ->get();

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
    | LOAD ASSIGNMENT
    |--------------------------------------------------------------------------
    */
                        $assignments = DB::table('heat_efficiencies')
                            ->where('npk', $employee->NPK)
                            ->where('period_id', $period->id)
                            ->whereBetween('date', [$period->start_date, $period->end_date])
                            ->get();

                        // dd($assignments);


                        /*
            |--------------------------------------------------------------------------
            | OPERATOR
            |--------------------------------------------------------------------------
            */
                        if ($employee->role === 'operator') {
                            foreach ($assignments as $assignment) {

                                // dd($rows);

                                if (!$isValidOvertime($assignment->npk, $assignment->date)) {
                                    continue;
                                }

                                $rate = $this->getInsentifByEfficiency(
                                    $assignment->efficiency,
                                    $heatInsentifFormula
                                );

                                $amount += $rate * $assignment->piece;
                            }
                        }

                        /*
        |--------------------------------------------------------------------------
        | NON OPERATOR (SPV / LEADER / HELPER)
        |--------------------------------------------------------------------------
        */ else {

                            // dd($employee);

                            /*
            |----------------------------------
            | TOTAL DEPT INSENTIF
            | ONLY VALID OPERATOR
            |----------------------------------
            */
                            $totalDeptInsentif = 0;


                            $operators = DB::table('heat_efficiencies')
                                ->where('period_id', $period->id)
                                ->whereBetween('date', [$period->start_date, $period->end_date])
                                ->get();

                            // dd($operators);


                            foreach ($operators as $operator) {
                                // FILTER HANYA NUMERATOR
                                if (!$isValidOvertime($operator->npk, $operator->date)) {
                                    continue;
                                }

                                $rate = $this->getInsentifByEfficiency(
                                    $operator->efficiency,
                                    $heatInsentifFormula
                                );

                                $totalDeptInsentif += $rate * $operator->piece;

                                // dd($totalDeptInsentif);
                            }

                            /*
                |----------------------------------
                | DENOMINATOR (ALL OPERATOR)
                |----------------------------------
                */
                            $jumlahOperator = DB::table('heat_efficiencies as pe')
                                ->where('pe.period_id', $period->id)
                                ->pluck('pe.npk')
                                ->unique()
                                ->count();

                            $amount += $this->calculateRoleHeatInsentif(
                                $employee->role,
                                'heat',
                                $totalDeptInsentif,
                                $jumlahOperator
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
            'total_payroll'  => round($totalPayroll, 0),
            'progress'       => 100,
            'status'         => 'Payroll calculation completed'
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

    private function calculateRoleHeatInsentif(
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
