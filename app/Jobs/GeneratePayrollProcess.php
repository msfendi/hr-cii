<?php

namespace App\Jobs;

use App\Models\Employee6sAssignment;
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
use Illuminate\Support\Str;

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
        $this->processPayroll(false);
    }

    public function simulation()
    {
        return $this->processPayroll(true);
    }

    private function processPayroll($isCheck = false)
    {
        $payrollResults = [];
        if (!$isCheck) {
            $run = PayrollRun::findOrFail($this->runId);
            $period = PayrollPeriod::findOrFail($run->period_id);
        } else {
            $period = PayrollPeriod::findOrFail($this->runId);
        }

        //pindah ke controller copy dari sini
        $periodStart = Carbon::parse($period->start_date);
        $periodEnd   = Carbon::parse($period->end_date);
        $count_days  = Carbon::parse($periodStart)->diffInDays(Carbon::parse($periodEnd)) + 1;
        $absenceDays = 0;

        /*
        |--------------------------------------------------------------------------
        | BIODATA UNION (DITAMBAHKAN BARCODE)
        |--------------------------------------------------------------------------
        */

        if (!$isCheck) {
            $run->update([
                'status' => 'Unioning Biodata',
                'progress' => 5,
            ]);
        }

        $biodataUnion = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'), 'IS_EXPAT')
            ->unionAll(
                DB::connection('cii')
                    ->table('BIODATA_KELUAR')
                    ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'), 'IS_EXPAT')
            );

        // dd($biodataUnion->get());
        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE BASE + SHIFT
        |--------------------------------------------------------------------------
        */


        if (!$isCheck) {
            $run->update([
                'status' => 'Getting Employee Biodata',
                'progress' => 15,
            ]);
        }

        $employeeBase = DB::connection('cii')
            ->table('PKWT as p')

            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('p.NPK', '=', 'bio.NPK');
            })
            ->leftJoin('DEPT as d', 'bio.ID_DEPT', '=', 'd.ID_DEPT')
            ->where('p.TMK', '<=', $periodEnd)
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })

            ->where('p.NPK', '!=', 'C-00017')
            // ->where('p.NPK', '=', 'C-03094')

            ->select(
                'p.NPK',
                'bio.NAMA_KARYAWAN',
                DB::raw("CAST(bio.BARCODE AS VARCHAR(50)) AS BARCODE"),
                'p.TMK',
                'p.TKK',
                'bio.ID_DEPT',
                'd.DEPARTEMENT',
                'bio.SECTION',
                'bio.IS_STAFF',
                'd.IS_SEWING',
                'p.KETERANGAN',
                'p.TANGGUNGAN',
                'bio.IS_EXPAT'
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
            ->where('ec1.npk', '!=', 'C-00017')
            // ->where('ec1.npk', '=', 'C-03094')

            // ✅ contract harus masuk range periode
            ->whereDate('ec1.start_date', '<=', $periodEnd)
            ->whereDate('ec1.end_date', '>=', $periodStart)
            // ->where('ec1.status_contract', '=', 'AKTIF')

            // ✅ ambil contract terbaru
            ->whereRaw("
        ec1.id = (
            SELECT TOP 1 ec2.id
            FROM employees_contract ec2
            WHERE ec2.npk = ec1.npk
              AND ec2.start_date <= ?
              AND ec2.end_date >= ?
            ORDER BY ec2.contract_ke DESC,
                     ec2.start_date DESC
        )
    ", [$periodEnd, $periodStart]);

        // dd($employeeBase->get(), $latestContract);


        if (!$isCheck) {
            $run->update([
                'status' => 'Getting Employee Overtime Data',
                'progress' => 20,
            ]);
        }

        $overtimeSummary = DB::connection('cii')
            ->table('overtimes')
            ->leftJoinSub($latestContract, 'ec', function ($join) {
                $join->on('overtimes.NPK', '=', 'ec.npk');
            })
            ->whereBetween('OVERTIME_DATE', [$periodStart, $periodEnd])
            ->select(
                'overtimes.NPK',
                DB::raw("
                SUM(
                    CASE
                        WHEN UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) = 'H'
                            THEN 0.5

                        WHEN JUMLAH_JAM_LEMBUR IS NOT NULL
                            AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NULL
                            AND UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) IN ('MA','P1','BR','OUT')
                            THEN 1

                        ELSE 0
                    END
                ) as absence_days
            "),
                DB::raw("
                SUM(
                    CASE
                        WHEN JUMLAH_JAM_LEMBUR IS NOT NULL
                            AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NULL
                            AND UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) IN ('SD')
                            THEN 1

                        ELSE 0
                    END
                ) as sick_days
            "),
            )
            ->groupBy('overtimes.NPK');


        $overtimeDetails = DB::connection('cii')
            ->table('overtimes')
            ->leftJoinSub($latestContract, 'ec', function ($join) {
                $join->on('overtimes.NPK', '=', 'ec.npk');
            })
            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('overtimes.NPK', '=', 'bio.NPK');
            })
            ->leftJoin('DEPT as d', 'bio.ID_DEPT', '=', 'd.ID_DEPT')
            ->whereBetween('OVERTIME_DATE', [$periodStart, $periodEnd])
            ->select(
                'overtimes.NPK',
                'bio.NAMA_KARYAWAN',
                'd.DEPARTEMENT',
                'overtimes.OVERTIME_DATE',

                DB::raw("
            CASE
                WHEN DAY NOT IN ('Sabtu','Minggu','Saturday','Sunday')
                AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NOT NULL
                THEN TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT)
                ELSE 0
            END AS overtime_hours
        "),

                DB::raw("
            CASE
                WHEN DAY IN ('Sabtu','Minggu','Saturday','Sunday')
                AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NOT NULL
                THEN
                    CASE
                        WHEN
                            (
                                COALESCE(ec.salary,0)
                                + COALESCE(ec.allowance,0)
                            ) >= 3800000

                            OR

                            (
                                (COALESCE(ec.daily_salary,0) * {$count_days})
                                + COALESCE(ec.allowance,0)
                            ) >= 3800000

                        THEN
                            CASE
                                WHEN TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) > 8
                                THEN 8
                                ELSE TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT)
                            END

                        ELSE
                            TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT)
                    END

                ELSE 0
            END AS special_overtime_hours
        "),

                DB::raw("
            CASE
                WHEN UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) = 'H'
                    THEN 0.5

                WHEN JUMLAH_JAM_LEMBUR IS NOT NULL
                    AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NULL
                    AND UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) IN ('MA','P1','BR','OUT')
                    THEN 1

                ELSE 0
            END AS absence_days
        "),
                DB::raw("
            CASE
                WHEN UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) = 'H'
                    THEN JUMLAH_JAM_LEMBUR

                WHEN JUMLAH_JAM_LEMBUR IS NOT NULL
                    AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NULL
                    AND UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) IN ('MA','P1','H','BR','OUT','SD')
                    THEN JUMLAH_JAM_LEMBUR
            END AS absence_status
        ")
            )
            ->orderBy('overtimes.NPK')
            ->orderBy('overtimes.OVERTIME_DATE')
            ->get()
            ->groupBy('NPK');

        // CHECK PER DATE LATE

        $lateDetails =
            DB::connection('cii')
            ->query()

            /*
        |--------------------------------------------------------------------------
        | EMPLOYEE + CALENDAR
        |--------------------------------------------------------------------------
        */
            ->fromSub(function ($q) use ($employeeBase, $periodStart, $periodEnd) {

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
                        'emp.*',
                        DB::raw('cal.shift_date')
                    );
            }, 'emp')

            /*
        |--------------------------------------------------------------------------
        | SHIFT
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

            ->leftJoin('shifts as s', function ($join) {
                $join->on('es.shift_id', '=', 's.id')
                    ->whereNotNull('es.shift_id');
            })

            /*
        |--------------------------------------------------------------------------
        | ATT LOG (FIX: 1 ROW ONLY PER EMP-DATE)
        |--------------------------------------------------------------------------
        */
            ->leftJoinSub(
                DB::connection('cii')
                    ->table('att_log')
                    ->where('sn', '!=', '66208026030047')
                    ->selectRaw("
                    CAST(pin AS VARCHAR(50)) as pin,
                    CAST(scan_date AS DATE) as scan_day,
                    MIN(CAST(scan_date AS DATETIME)) as first_scan
                ")
                    ->groupBy(
                        DB::raw('CAST(pin AS VARCHAR(50))'),
                        DB::raw('CAST(scan_date AS DATE)')
                    ),
                'att',
                function ($join) {
                    $join->on(
                        DB::raw('CAST(emp.BARCODE AS VARCHAR(50))'),
                        '=',
                        'att.pin'
                    )
                        ->on(
                            DB::raw('CAST(emp.shift_date AS DATE)'),
                            '=',
                            'att.scan_day'
                        );
                }
            )

            /*
        |--------------------------------------------------------------------------
        | LATE COMPENSATION
        |--------------------------------------------------------------------------
        */
            ->leftJoin('late_compensations as lc', function ($join) {
                $join->on('emp.NPK', '=', 'lc.npk')
                    ->whereRaw("
                    CAST(lc.date AS DATE) = CAST(emp.shift_date AS DATE)
                ");
            })

            /*
        |--------------------------------------------------------------------------
        | SHIFT RESOLUTION
        |--------------------------------------------------------------------------
        */
            ->selectRaw("
            emp.NPK,
            emp.NAMA_KARYAWAN,
            emp.DEPARTEMENT,
            CAST(emp.BARCODE AS VARCHAR(50)) as pin,
            CAST(emp.shift_date AS DATE) as scan_day,

            COALESCE(CAST(s.work_start AS TIME), '08:00:00') as work_start,
            COALESCE(CAST(s.work_end AS TIME), '17:00:00') as work_end,

            att.first_scan
        ")

            /*
        |--------------------------------------------------------------------------
        | FINAL LATE CALC (NO NULL POSSIBILITY)
        |--------------------------------------------------------------------------
        */
            ->selectRaw("
            CASE
                WHEN lc.id IS NOT NULL THEN 0
                WHEN att.first_scan IS NULL THEN 0

                ELSE
                    CASE
                        WHEN att.first_scan >
                            DATEADD(
                                SECOND,
                                DATEDIFF(SECOND,'00:00:00',COALESCE(CAST(s.work_end AS TIME),'17:00:00')),
                                CAST(emp.shift_date AS DATETIME)
                            )
                        THEN 0

                        WHEN DATEDIFF(
                            MINUTE,
                            DATEADD(
                                MINUTE,5,
                                DATEADD(
                                    SECOND,
                                    DATEDIFF(SECOND,'00:00:00',COALESCE(CAST(s.work_start AS TIME),'08:00:00')),
                                    CAST(emp.shift_date AS DATETIME)
                                )
                            ),
                            att.first_scan
                        ) < 0 THEN 0

                        ELSE
                            DATEDIFF(
                                MINUTE,
                                DATEADD(
                                    MINUTE,5,
                                    DATEADD(
                                        SECOND,
                                        DATEDIFF(SECOND,'00:00:00',COALESCE(CAST(s.work_start AS TIME),'08:00:00')),
                                        CAST(emp.shift_date AS DATETIME)
                                    )
                                ),
                                att.first_scan
                            )
                    END
            END as late_minute
        ")

            /*
        |--------------------------------------------------------------------------
        | GROUP BY CLEAN
        |--------------------------------------------------------------------------
        */
            ->groupBy(
                'emp.NPK',
                'emp.NAMA_KARYAWAN',
                'emp.DEPARTEMENT',
                DB::raw('CAST(emp.BARCODE AS VARCHAR(50))'),
                DB::raw('CAST(emp.shift_date AS DATE)'),
                's.work_start',
                's.work_end',
                'att.first_scan',
                'lc.id'
            )

            ->whereBetween(
                DB::raw('CAST(emp.shift_date AS DATE)'),
                [$periodStart, $periodEnd]
            )

            ->orderBy(DB::raw('CAST(emp.shift_date AS DATE)'))
            ->get()
            ->groupBy('NPK');

        // SUMMARY LATE

        if (!$isCheck) {
            $run->update([
                'status' => 'Calculating Late Minutes',
                'progress' => 25,
            ]);
        }

        $lateSummary =
            DB::connection('cii')
            ->query()

            /*
        |--------------------------------------------------------------------------
        | SOURCE LATE ENGINE (ORIGINAL STRUCTURE - CLEANED)
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
            | DAILY SHIFT (ONLY EMPLOYEE SHIFT)
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

                    ->leftJoin('shifts as s', function ($join) {
                        $join->on('es.shift_id', '=', 's.id')
                            ->whereNotNull('es.shift_id');
                    })

                    /*
            |--------------------------------------------------------------------------
            | ATT LOG
            |--------------------------------------------------------------------------
            */
                    ->leftJoinSub(
                        DB::connection('cii')
                            ->table('att_log')
                            ->where('sn', '!=', '66208026030047')
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
                            CAST(att.scan_day AS DATE) = CAST(emp.shift_date AS DATE)
                        ")
                                        ->orWhereRaw("
                            (
                                es.shift_id IS NOT NULL
                                AND CAST(s.work_start AS TIME) > CAST(s.work_end AS TIME)
                                AND CAST(att.scan_day AS DATE) = DATEADD(DAY,1,CAST(emp.shift_date AS DATE))
                            )
                        ");
                                });
                        }
                    )

                    ->groupBy(
                        'emp.NPK',
                        'emp.BARCODE',
                        DB::raw('CAST(emp.shift_date AS DATE)'),
                        's.work_start',
                        's.work_end',
                        'es.shift_id'
                    )

                    /*
            |--------------------------------------------------------------------------
            | DAILY LATE RESULT (CLEANED - NO FS)
            |--------------------------------------------------------------------------
            */
                    ->selectRaw("
                emp.NPK,
                CAST(emp.BARCODE AS VARCHAR(50)) as pin,
                CAST(emp.shift_date AS DATE) as shift_date,

                MIN(att.scan_date) as first_scan,

                COALESCE(
                    CAST(s.work_end AS TIME),
                    '17:00:00'
                ) as work_end,

                CASE
                    WHEN MIN(att.scan_date) IS NULL THEN 0

                    WHEN
                        COALESCE(
                            CAST(s.work_start AS TIME),
                            '08:00:00'
                        )
                        >
                        COALESCE(
                            CAST(s.work_end AS TIME),
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
                    CAST(lc.date AS DATE) = CAST(daily.shift_date AS DATE)
                ");
            })

            /*
        |--------------------------------------------------------------------------
        | FINAL RESULT
        |--------------------------------------------------------------------------
        */
            ->selectRaw("
            daily.NPK as npk,
            daily.pin,

            SUM(
                CASE
                    WHEN lc.id IS NOT NULL THEN 0

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


        if (!$isCheck) {
            $run->update([
                'status' => 'Combining Employee Data',
                'progress' => 30,
            ]);
        }

        $assignment6s = Employee6sAssignment::where('period_id', $period->id);

        $bpjsException = DB::table('bpjs_exceptions')
            ->select(
                'npk',
                DB::raw("MAX(CASE WHEN component = 'bpjs_kesehatan' THEN percentage END) as percentkes"),
                DB::raw("MAX(CASE WHEN component = 'bpjs_ketenagakerjaan' THEN percentage END) as percentket")
            )
            ->groupBy('npk');

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
            ->whereBetween('tanggal', [$periodStart, $periodEnd])
            ->groupBy('npk');

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
            ->whereBetween('tanggal', [$periodStart, $periodEnd])
            ->orderBy('tanggal', 'asc')
            ->get()
            ->groupBy('npk');

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

            ->leftJoinSub($assignment6s, 'a6s', function ($join) use ($period) {
                $join->on('emp.NPK', '=', 'a6s.npk')
                    ->where('a6s.period_id', '=', $period->id);
            })

            ->leftJoin('DEPT as d', 'emp.ID_DEPT', '=', 'd.ID_DEPT')
            ->leftJoinSub($bpjsException, 'be', function ($join) {
                $join->on('emp.NPK', '=', 'be.npk');
            })
            ->leftJoinSub($ijinSummary, 'ij', function ($join) {
                $join->on('emp.NPK', '=', 'ij.npk');
            })
            // ->where('emp.NPK', '=', 'C-03094')

            ->select(
                'emp.NPK',
                'emp.NAMA_KARYAWAN',
                'emp.BARCODE',
                'emp.ID_DEPT',
                'd.DEPARTEMENT as DEPARTEMENT',
                'emp.SECTION as SECTION',
                'emp.TMK',
                'emp.TKK',
                'emp.IS_STAFF',
                'emp.IS_SEWING',
                'emp.IS_EXPAT',
                'emp.KETERANGAN',
                'emp.TANGGUNGAN',
                'a6s.percentage',
                'lt.late_minutes as total_telat',

                'ec.salary',
                'ec.allowance',
                'ec.pph21',
                'ec.type',
                'ec.daily_salary',
                'be.percentkes',
                'be.percentket',

                DB::raw('COALESCE(pa.adjusment,0) as adjusment'),
                // DB::raw('COALESCE(ot.overtime_hours,0) as overtime_hours'),
                // DB::raw('COALESCE(ot.special_overtime_hours,0) as special_overtime_hours'),
                DB::raw('COALESCE(ot.absence_days,0) as absence_days'),
                DB::raw('COALESCE(ot.sick_days,0) as sick_days'),
                DB::raw('COALESCE(lt.late_minutes,0) as late_minutes'),
                DB::raw('COALESCE(ij.total_ijin_minutes,0) as total_ijin_minutes'),
                DB::raw('COALESCE(ij.total_ijin_minutes,0) / 60 as total_ijin_hours'),

                DB::raw("DATEDIFF(YEAR, emp.TMK, '$periodEnd') as working_years")
            )
            ->orderBy('emp.ID_DEPT', 'asc')
            ->orderBy('emp.NPK', 'asc')
            ->get();


        // dd($employees);


        if (!$isCheck) {
            $run->update([
                'status' => 'Getting Payroll Components',
                'progress' => 35,
            ]);
        }

        $components = PayrollComponent::where('is_active', 1)
            ->where('code', '!=', 'thr')
            ->where('code', '!=', 'compensation')
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

        $sixsInsentifComponent = PayrollComponent::where('code', 'sixs_insentif')->first();
        $sixsInsentifFormula = $sixsInsentifComponent->formula;

        $BPJSKesehatanComponent = PayrollComponent::where('code', 'bpjs_kesehatan')->first();
        $BPJSKesehatanFormula = $BPJSKesehatanComponent->formula;

        $BPJSKetenagakerjaanComponent = PayrollComponent::where('code', 'bpjs_ketenagakerjaan')->first();
        $BPJSKetenagakerjaanFormula = $BPJSKetenagakerjaanComponent->formula;

        $totalPayroll = 0;

        if (!$isCheck) {
            $run->update([
                'status' => 'Starting Payroll Calculation',
                'progress' => 40,
            ]);
        }

        foreach ($employees as $employee) {
            $absenceDays = 0;

            /**
             * cek apakah TMK atau TKK terjadi dalam periode payroll
             */
            $tmk = $employee->TMK ? Carbon::parse($employee->TMK) : null;
            $tkk = $employee->TKK ? Carbon::parse($employee->TKK) : null;

            $isJoinOrResignInPeriod =
                ($tmk && $tmk->between($periodStart, $periodEnd)) ||
                ($tkk && $tkk->between($periodStart, $periodEnd));

            if ($isJoinOrResignInPeriod) {

                // hitung hari kerja (Senin–Jumat) dalam periode full bulan
                $cursor = $periodStart->copy();
                $workingDays = 0;

                while ($cursor->lte($periodEnd)) {
                    if (!$cursor->isWeekend()) {
                        $workingDays++;
                    }
                    $cursor->addDay();
                }

                // rumus: (hari kerja periode - 21) + absence lama
                $absenceDays = (21 - $workingDays) + $employee->absence_days;
            } else {
                $absenceDays = $employee->absence_days;
            }

            $inputVariables = [
                'basic_salary'   => (float) $employee->salary,
                'allowance'      => (float) $employee->allowance,
                'absence_days'   => (float) $absenceDays,
                'sick_days'   => (float) $employee->sick_days,
                'working_years'  => (float) $employee->working_years,
                'adjusment'      => (float) $employee->adjusment,
                'pph_21'         => (float) $employee->pph21,
                'daily_salary'   => (float) $employee->daily_salary,
                'count_days'     => (float) $count_days,
                'tanggungan'     => (float) $employee->TANGGUNGAN,
                'percentage'     => (float) $employee->percentage,
                'total_ijin'     => (float) $employee->total_ijin_minutes,
                'is_contract' => Str::ucfirst(Str::lower($employee->type)) === 'Contract' ? 1 : 0,
                'is_daily'    => Str::ucfirst(Str::lower($employee->type)) === 'Daily' ? 1 : 0,
                'late_minutes'     => $employee->IS_STAFF === '1' ? (float) $employee->late_minutes : 0,
                'is_staff'       => $employee->IS_STAFF == '1' ? 1 : 0,
                'is_sewing'       => $employee->IS_SEWING == '1' ? 1 : 0,
                'is_expat'       => $employee->IS_EXPAT == '1' ? 1 : 0,
                'bpjskesex' => $employee->percentkes === null ? 1 : (float) $employee->percentkes,
                'bpjsketex' => (float) $employee->percentket,
                // Basis BPJS
                'bpjs_base' => (
                    ($employee->IS_STAFF == '1' || $employee->IS_EXPAT == '1')
                    ? (
                        Str::ucfirst(Str::lower($employee->type)) === 'Contract'
                        ? (float) $employee->salary
                        : ((float) $employee->daily_salary * (float) $count_days)
                    )
                    : (
                        (
                            Str::ucfirst(Str::lower($employee->type)) === 'Contract'
                            ? (float) $employee->salary
                            : ((float) $employee->daily_salary * (float) $count_days)
                        )
                        + (float) $employee->allowance
                    )
                ),
                // JP hanya untuk non expat
                'bpjsjpex' => $employee->IS_EXPAT == '1' ? 0 : 1,
                // JHT selalu 2%
                'bpjsjhtex' => 2,
                // 'overtime_hours' => (float) $employee->overtime_hours,
                // 'special_overtime_hours' => (float) $employee->special_overtime_hours
            ];

            // dd($inputVariables);

            // dd($employee->late_minutes);

            $results = [];
            $grandTotal = 0;

            foreach ($components as $component) {
                if ($component->code === 'thr') continue;
                if ($component->code === 'compensation') continue;

                if ($component->calculation_method === 'fixed') {
                    $amount = $component->value;
                } else {

                    if (!$isCheck) {
                        $run->update([
                            'status' => 'Calculation for ' . $employee->NPK . ' - ' . $component->name,
                            'progress' => 60
                        ]);
                    }
                    if ($component->code === 'bpjs_kesehatan') {
                        if ($employee->TKK !== null) {
                            if (Carbon::parse($employee->TKK)->day <= 20) {
                                continue;
                            }
                        } else {
                            $totalBPJSKesehatan = 0;

                            $totalBPJSKesehatan += $this->evaluateFormula(
                                $BPJSKesehatanFormula,
                                $results,
                                $inputVariables
                            );

                            $amount = $totalBPJSKesehatan;
                        }
                    } else if ($component->code === 'bpjs_ketenagakerjaan') {
                        if ($employee->TKK !== null) {
                            continue;
                        } else {
                            $totalBPJSKetenagakerjaan = 0;

                            $totalBPJSKetenagakerjaan += $this->evaluateFormula(
                                $BPJSKetenagakerjaanFormula,
                                $results,
                                $inputVariables
                            );

                            $amount = $totalBPJSKetenagakerjaan;
                        }
                    } else if ($component->code === 'sixs_insentif') {
                        $total6sInsentif = 0;

                        $total6sInsentif += $this->evaluateFormula(
                            $sixsInsentifFormula,
                            $results,
                            $inputVariables
                        );

                        $amount = $total6sInsentif;
                        // dd($employee->percentage, $sixsInsentifFormula, $results, $inputVariables, $amount);
                    } else if ($component->code === 'overtime_pay') {
                        $employeeOvertimes = $overtimeDetails[$employee->NPK] ?? collect();

                        $totalOvertimePay = 0;

                        foreach ($employeeOvertimes as $ot) {

                            if ($ot->overtime_hours <= 0) {
                                continue;
                            }

                            $inputVariables['overtime_hours'] = $ot->overtime_hours;

                            $totalOvertimePay += $this->evaluateFormula(
                                $overtimeFormula,
                                $results,
                                $inputVariables
                            );
                        }

                        $amount = $totalOvertimePay;
                    } else if ($component->code === 'special_overtime_pay') {
                        if (!$isCheck) {
                            $run->update([
                                'status' => 'Calculation for ' . $employee->NPK . ' - ' . $component->name,
                                'progress' => 60
                            ]);
                        }
                        $employeeOvertimes = $overtimeDetails[$employee->NPK] ?? collect();

                        $totalSpecialOvertimePay = 0;

                        foreach ($employeeOvertimes as $ot) {

                            if ($ot->special_overtime_hours <= 0) {
                                continue;
                            }

                            $inputVariables['special_overtime_hours'] = $ot->special_overtime_hours;

                            $totalSpecialOvertimePay += $this->evaluateFormula(
                                $specialOvertimeFormula,
                                $results,
                                $inputVariables
                            );
                        }

                        $amount = $totalSpecialOvertimePay;
                    } else if ($component->code === 'sewing_insentif') {
                        $assignmentNpk = DB::table('employee_line_assignments as ela')
                            ->select('ela.npk', 'ela.role')
                            ->where('ela.period_id', $period->id)
                            ->where('ela.npk', $employee->NPK)
                            ->distinct()
                            ->get();

                        $tkkDate = !empty($employee->TKK)
                            ? Carbon::parse($employee->TKK)->format('Y-m-d')
                            : null;

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
                        $lineViolations = 0;
                        foreach ($assignmentNpk as $assignment) {
                            if (empty($assignment->role)) {
                                continue;
                            }
                            if ($assignment->role == 'operator' || $assignment->role == 'supervisor') {

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
                                $lineefficiencies = DB::table('employee_line_assignments as ela')
                                    ->leftJoin('line_efficiencies as le', function ($join) {
                                        $join->on('le.period_id', '=', 'ela.period_id')
                                            ->on('le.line_number', '=', 'ela.line_number')
                                            ->on('le.date', '=', 'ela.start_date');
                                    })

                                    ->leftJoinSub(
                                        DB::table('employee_line_assignments')
                                            ->select(
                                                'period_id',
                                                'line_number',
                                                'start_date',
                                                DB::raw('MAX(work_hours) as max_work_hours')
                                            )
                                            ->groupBy(
                                                'period_id',
                                                'line_number',
                                                'start_date'
                                            ),
                                        'max_wh',
                                        function ($join) {
                                            $join->on('max_wh.period_id', '=', 'ela.period_id')
                                                ->on('max_wh.line_number', '=', 'ela.line_number')
                                                ->on('max_wh.start_date', '=', 'ela.start_date');
                                        }
                                    )

                                    ->where('ela.period_id', $period->id)
                                    ->where('ela.npk', $employee->NPK)
                                    ->whereBetween('le.date', [$period->start_date, $period->end_date])

                                    ->select(
                                        'ela.npk',
                                        'le.line_number',
                                        'le.efficiency',
                                        'le.date',

                                        // work hours employee
                                        'ela.work_hours',

                                        // max work hours pada line & tanggal yang sama
                                        'max_wh.max_work_hours'
                                    )

                                    ->orderBy('le.date')
                                    ->get();

                                // dd($lineefficiencies);

                                if (strtolower($assignment->role) == 'operator') {

                                    $lineViolations = DB::table('sewing_violations')
                                        ->whereBetween('tanggal', [
                                            $period->start_date,
                                            $period->end_date
                                        ])
                                        ->where('id_dept', $employee->ID_DEPT)
                                        ->count();
                                } elseif (strtolower($assignment->role) == 'supervisor') {

                                    $leaderDept = DB::table('DEPT')
                                        ->where('ID_DEPT', $employee->ID_DEPT)
                                        ->value('DEPARTEMENT');

                                    $lineNumber = null;

                                    if (
                                        preg_match('/LINE\s+(\d+)/i', $leaderDept, $matches)
                                    ) {
                                        $lineNumber = $matches[1];
                                    }

                                    $lineDeptId = DB::table('DEPT')
                                        ->where('DEPARTEMENT', 'LINE ' . $lineNumber)
                                        ->value('ID_DEPT');

                                    $lineViolations = DB::table('sewing_violations')
                                        ->whereBetween('tanggal', [
                                            $period->start_date,
                                            $period->end_date
                                        ])
                                        ->where('id_dept', $lineDeptId)
                                        ->count();
                                } else {

                                    $lineViolations = 0;
                                }

                                // dd($employee, $lineViolations);

                                foreach ($lineefficiencies as $row) {

                                    /*
                |--------------------------------------------------------------------------
                | CHECK RESIGN (NEW)
                |--------------------------------------------------------------------------
                */
                                    if ($tkkDate && $row->date >= $tkkDate) {
                                        continue;
                                    }

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
                | CALCULATE INSENTIF
                |--------------------------------------------------------------------------
                */
                                    $lineInsentif =
                                        $this->getInsentifByEfficiency($row->efficiency, $sewingInsentifFormula) * $row->work_hours / $row->max_work_hours;

                                    $amount += $this->calculateRoleSewingInsentif(
                                        $assignment->role,
                                        'sewing',
                                        $lineInsentif,
                                        1, //karena hanya 1 line
                                        $lineViolations
                                    );
                                }
                            } else {

                                /*
            |--------------------------------------------------------------------------
            | CHIEF / MEKANIK / MEKANIK LEADER
            |--------------------------------------------------------------------------
            */
                                $validRoles = ['chief', 'mekanik', 'mekanik_leader'];

                                if (!in_array($assignment->role, $validRoles)) {
                                    // return $amount;
                                    continue;
                                }


                                $section = DB::table('sections')
                                    ->whereRaw('id = ?', [(int) $employee->SECTION])
                                    ->select('line_start', 'line_end')
                                    ->first();

                                // dd($employee->SECTION, $section);

                                if (!$section) {
                                    // return $amount;
                                    continue;
                                }

                                $lineStart = $section->line_start;
                                $lineEnd   = $section->line_end;

                                $grouped = DB::table('employee_line_assignments as ela')
                                    ->join('line_efficiencies as le', function ($join) {
                                        $join->on('le.period_id', '=', 'ela.period_id')
                                            ->on('le.date', '=', 'ela.start_date');
                                    })

                                    ->where('ela.npk', $employee->NPK)
                                    ->where('ela.period_id', $period->id)

                                    ->whereBetween('ela.start_date', [
                                        $period->start_date,
                                        $period->end_date
                                    ])

                                    ->whereBetween('le.line_number', [
                                        $lineStart,
                                        $lineEnd
                                    ])

                                    ->select(
                                        // 'le.line_number',
                                        'le.date'
                                    )

                                    ->groupBy(
                                        'le.date',
                                        // 'le.line_number'
                                    )

                                    ->orderBy('le.date')
                                    ->get();

                                // dd($grouped);


                                $lineViolations = DB::table('sewing_violations')
                                    ->leftJoin('DEPT as d', 'sewing_violations.id_dept', '=', 'd.ID_DEPT')
                                    ->whereBetween('sewing_violations.tanggal', [
                                        $period->start_date,
                                        $period->end_date
                                    ])
                                    ->where('d.DEPARTEMENT', 'like', 'LINE %')
                                    ->whereRaw("
                        CAST(REPLACE(d.DEPARTEMENT,'LINE ','') AS INT)
                        BETWEEN ? AND ?
                    ", [$lineStart, $lineEnd])
                                    ->count();

                                // dd($lineViolations);

                                $collectionDay = collect([]);
                                $collectionLines = collect([]);

                                $jumlahLine = DB::table('line_efficiencies')
                                    ->where('period_id', $period->id)
                                    ->whereBetween('date', [$period->start_date, $period->end_date])
                                    ->whereBetween('line_number', [$lineStart, $lineEnd])
                                    ->selectRaw('COUNT(DISTINCT line_number) as jumlah_line')
                                    ->get();

                                // dd($jumlahLine);
                                foreach ($grouped as $day) {

                                    /*
                |--------------------------------------------------------------------------
                | CHECK RESIGN (NEW)
                |--------------------------------------------------------------------------
                */
                                    if ($tkkDate && $day->date >= $tkkDate) {
                                        continue;
                                    }
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
                                        ->whereBetween('line_number', [$lineStart, $lineEnd])
                                        ->get();

                                    $totalLineInsentif = 0;

                                    foreach ($lines as $line) {

                                        $totalLineInsentif +=
                                            $this->getInsentifByEfficiency($line->efficiency, $sewingInsentifFormula);

                                        if ($totalLineInsentif <= 0) {
                                            continue;
                                        }

                                        $collectionLines->push($totalLineInsentif);

                                        // dd($grouped, $lines, $totalLineInsentif, $amount);
                                    }

                                    // dd($grouped, $collectionLines);

                                    $amount += $this->calculateRoleSewingInsentif(
                                        $assignment->role,
                                        'sewing',
                                        $totalLineInsentif,
                                        $jumlahLine->first()->jumlah_line,
                                        $lineViolations
                                    );

                                    $collectionDay->push($amount);
                                }
                                // dd($collectionDay->values()->toJson(), $collectionLines->values()->toJson());
                            }
                        }
                    } else if ($component->code === 'pad_insentif') {

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

                        // $run->update([
                        //     'status' => 'Calculation for ' . $employee->NPK . ' - ' . $component->name,
                        //     'progress' => 60
                        // ]);

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


                        // dd($assignments);


                        /*
            |--------------------------------------------------------------------------
            | OPERATOR
            |--------------------------------------------------------------------------
            */
                        foreach ($assignments as $assignment) {

                            if (empty($assignment->role)) {
                                continue;
                            }
                            if ($assignment->role === 'operator') {

                                // dd($rows);

                                if (!$isValidOvertime($assignment->npk, $assignment->date)) {
                                    continue;
                                }

                                $rate = $this->getInsentifByEfficiency(
                                    $assignment->efficiency,
                                    $padInsentifFormula
                                );

                                $amount += $rate * $assignment->piece;
                            }/*
        |--------------------------------------------------------------------------
        | NON OPERATOR (SPV / LEADER / HELPER)
        |--------------------------------------------------------------------------
        */ else {

                                $employeeDates = DB::table('pad_efficiencies')
                                    ->where('period_id', $period->id)
                                    ->where('npk', $employee->NPK)
                                    ->pluck('date')
                                    ->unique()
                                    ->toArray();
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
                                    ->where('role', '=', 'operator')
                                    ->whereBetween('date', [$period->start_date, $period->end_date])
                                    ->whereIn('date', $employeeDates)
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
                                    ->whereIn('pe.date', $employeeDates)
                                    ->where('pe.role', '=', 'operator')
                                    ->pluck('pe.npk')
                                    ->unique()
                                    ->count();

                                $amount += $this->calculateRolePadInsentif(
                                    $assignment->role,
                                    'pad',
                                    $totalDeptInsentif,
                                    $jumlahOperator
                                );
                            }
                        }
                    } else if ($component->code === 'cutting_insentif') {
                        $assignmentNpk = DB::table('employee_cutting_assignments as eca')
                            ->select('eca.npk', 'eca.role')
                            ->where('eca.period_id', $period->id)
                            ->where('eca.npk', $employee->NPK)
                            ->distinct()
                            ->get();
                        $tkkDate = !empty($employee->TKK)
                            ? Carbon::parse($employee->TKK)->format('Y-m-d')
                            : null;
                        // $run->update([
                        //     'status' => 'Calculation for ' . $employee->NPK . ' - ' . $component->name,
                        //     'progress' => 60
                        // ]);


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
                        $employeeDates = DB::table('employee_cutting_assignments')
                            ->where('period_id', $period->id)
                            ->where('npk', $employee->NPK)
                            ->pluck('start_date')
                            ->unique()
                            ->toArray();
                        // dd($employeeDates);
                        $cuttingEfficiencies = DB::table('cutting_efficiencies')
                            ->where('period_id', $period->id)
                            ->whereBetween('date', [
                                $period->start_date,
                                $period->end_date
                            ])
                            ->whereIn('date', $employeeDates)
                            ->get();

                        // dd($cuttingEfficiencies);


                        /*
    |--------------------------------------------------------------------------
    | CALCULATE INSENTIF
    |--------------------------------------------------------------------------
    */
                        foreach ($assignmentNpk as $assignment) {
                            // dd($assignmentNpk, $assignment->role);
                            if (empty($assignment->role)) {
                                continue;
                            }
                            foreach ($cuttingEfficiencies as $row) {
                                if ($tkkDate && $row->date >= $tkkDate) {
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
                        }
                    } else if ($component->code === 'heat_insentif') {

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
                        // $run->update([
                        //     'status' => 'Calculation for ' . $employee->NPK . ' - ' . $component->name,
                        //     'progress' => 60
                        // ]);
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

                        // dd($assignments);


                        /*
            |--------------------------------------------------------------------------
            | OPERATOR
            |--------------------------------------------------------------------------
            */
                        foreach ($assignments as $assignment) {

                            if (empty($assignment->role)) {
                                continue;
                            }
                            if ($assignment->role === 'operator') {

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

                            /*
                            |--------------------------------------------------------------------------
                            | NON OPERATOR (SPV / LEADER / HELPER)
                            |--------------------------------------------------------------------------
                            */ else {
                                $employeeDates = DB::table('heat_efficiencies')
                                    ->where('period_id', $period->id)
                                    ->where('npk', $employee->NPK)
                                    ->pluck('date')
                                    ->unique()
                                    ->toArray();
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
                                    ->where('role', '=', 'operator')
                                    ->whereBetween('date', [$period->start_date, $period->end_date])
                                    ->whereIn('date', $employeeDates)
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
                                $jumlahOperator = DB::table('heat_efficiencies as he')
                                    ->where('he.period_id', $period->id)
                                    ->whereIn('he.date', $employeeDates)
                                    ->where('he.role', '=', 'operator')
                                    ->pluck('he.npk')
                                    ->unique()
                                    ->count();

                                $amount += $this->calculateRoleHeatInsentif(
                                    $assignment->role,
                                    'heat',
                                    $totalDeptInsentif,
                                    $jumlahOperator
                                );
                            }
                        }
                    } else {
                        $amount = $this->evaluateFormula($component->formula, $results, $inputVariables);
                    }
                }

                // 🔹 PERBAIKAN: bulatkan setiap komponen
                $amount = round((float) $amount, 0);
                $results[$component->code] = $amount;
                // $results['late_minutes'] = $employee->late_minutes;

                // dd($results);

                if ($component->type === 'earning') {
                    $grandTotal += $amount;
                } else {
                    $grandTotal -= $amount;
                }
            }

            $grandTotal = round($grandTotal, 0);

            if (!$isCheck) {
                PayrollRunDetail::create([
                    'run_id'        => $run->id,
                    'employee_npk'  => $employee->NPK,
                    'employee_name' => $employee->NAMA_KARYAWAN,
                    'components'    => $results,
                    'total_salary'  => $grandTotal
                ]);
            }

            $totalPayroll += $grandTotal;

            if ($isCheck) {
                $payrollResults[] = [
                    // 'run_id'        => $run->id,
                    'absence_days_asli'   => (float) $employee->absence_days,
                    'absence_days'   => (float) $absenceDays,
                    'sick_days'   => (float) $employee->sick_days,
                    'count_days'    => $count_days,
                    'type' => Str::ucfirst(Str::lower($employee->type)),
                    'dept' => $employee->DEPARTEMENT,
                    'tmk' => $employee->TMK,
                    'period_start'  => $periodStart,
                    'tkk' => $employee->TKK,
                    'late_summary' => $employee->total_telat,
                    'total_ijin'     => (float) $employee->total_ijin_minutes,
                    'is_sewing'       => $employee->IS_SEWING == '1' ? 1 : 0,
                    'is_staff'       => $employee->IS_STAFF == '1' ? 1 : 0,
                    'is_expat'       => $employee->IS_EXPAT == '1' ? 1 : 0,
                    'bpjskesex' => $employee->percentkes === null ? 1 : (float) $employee->percentkes,
                    'bpjsketex' => (float) $employee->percentket,
                    'percentage'     => (float) $employee->percentage,
                    // Basis BPJS
                    'bpjs_base' => (
                        ($employee->IS_STAFF == '1' || $employee->IS_EXPAT == '1')
                        ? (
                            Str::ucfirst(Str::lower($employee->type)) === 'Contract'
                            ? (float) $employee->salary
                            : ((float) $employee->daily_salary * (float) $count_days)
                        )
                        : (
                            (
                                Str::ucfirst(Str::lower($employee->type)) === 'Contract'
                                ? (float) $employee->salary
                                : ((float) $employee->daily_salary * (float) $count_days)
                            )
                            + (float) $employee->allowance
                        )
                    ),
                    // JP hanya untuk non expat
                    'bpjsjpex' => $employee->IS_EXPAT == '1' ? 0 : 1,
                    // JHT selalu 2%
                    'bpjsjhtex' => 2,
                    // 'overtime_hours' => $employee->overtime_hours,
                    'employee_npk'  => $employee->NPK,
                    'employee_name' => $employee->NAMA_KARYAWAN,
                    'tanggungan' => $employee->TANGGUNGAN,
                    'keterangan' => $employee->KETERANGAN,
                    'components'    => $results,
                    'overtime_details' => ($overtimeDetails[$employee->NPK] ?? collect())
                        ->values(),
                    'late_details' => ($lateDetails[$employee->NPK] ?? collect())
                        ->values(),
                    'ijin_details' => ($ijinDetails[$employee->NPK] ?? collect())->values(),
                    'total_salary'  => $grandTotal
                ];
            }
        }

        if (!$isCheck) {
            $run->update([
                'employee_count' => $employees->count(),
                'total_payroll'  => round($totalPayroll, 0),
                'progress'       => 100,
                'status'         => 'Payroll calculation completed'
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE APPROVAL PAYROLL
        |--------------------------------------------------------------------------
        */

        if (!$isCheck) {
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
        }
        if ($isCheck) {
            return $payrollResults;
        }
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
        $jumlahLine,
        $violationsCount
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
            'violationsCount'   => $violationsCount ?? 0
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
