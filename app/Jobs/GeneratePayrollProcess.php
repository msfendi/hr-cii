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
use Illuminate\Support\Facades\Auth;
use App\Services\PayrollRoleFilterService;

class GeneratePayrollProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $runId;

    /**
     * NPK spesifik untuk mode "per NPK" (single employee).
     * - null  -> mode ALL (semua karyawan, dipakai untuk generate payroll
     *            sungguhan / check payroll biasa).
     * - 'xxx' -> mode PER NPK (hanya 1 karyawan, dipakai untuk live slip
     *            preview) — query dibatasi dari awal, TIDAK compute
     *            seluruh karyawan lalu difilter belakangan.
     */
    public $npk;

    /**
     * Throttle timestamp for progress updates (avoid 1 UPDATE query per
     * employee x component, which was previously hammering the DB).
     */
    private $lastProgressUpdate = 0;

    public function __construct($runId, $npk = null)
    {
        $this->runId = $runId;
        $this->npk   = $npk;
    }

    public function handle()
    {
        $this->processPayroll(false);
    }

    /**
     * @param  string|null $npk  Override NPK per-panggilan. Jika null,
     *                           pakai $this->npk dari constructor (bisa
     *                           juga null -> mode ALL).
     */
    public function simulation($npk = null)
    {
        return $this->processPayroll(true, $npk ?? $this->npk);
    }

    /**
     * Throttled wrapper around $run->update() for progress/status text.
     * Same end-result for the user (status text + progress bar), just not
     * fired on every single component x employee iteration.
     */
    private function updateProgress($run, $isCheck, $status, $progress, $minIntervalSeconds = 1)
    {
        if ($isCheck) {
            return;
        }

        $now = microtime(true);
        if (($now - $this->lastProgressUpdate) < $minIntervalSeconds) {
            return;
        }

        $run->update([
            'status'   => $status,
            'progress' => $progress,
        ]);

        $this->lastProgressUpdate = $now;
    }

    private function processPayroll($isCheck = false, $npk = null)
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
            ->where('p.TMK', '<=', $periodEnd)
            ->where(function ($q) use ($periodStart) {
                // Karyawan dianggap masih aktif di periode ini selama TKK
                // belum lewat SEBELUM periode dimulai. TKK yang jatuh SETELAH
                // periodEnd (mis. resign 1 Juli sedangkan periode payroll
                // adalah Juni) tetap harus dianggap aktif penuh di periode
                // Juni — jadi batas atas (periodEnd) TIDAK boleh dipakai
                // di sini, cukup batas bawah (periodStart).
                $q->whereNull('p.TKK')
                    ->orWhere('p.TKK', '>=', $periodStart);
            })

            ->where('p.NPK', '!=', 'C-00017')
            // ->where('p.NPK', '=', 'C-04477')

            /*
            |----------------------------------------------------------------
            | MODE PER NPK
            |----------------------------------------------------------------
            | Kalau $npk diisi (live slip preview), batasi dari root query
            | paling awal supaya seluruh subquery turunan & loop di bawah
            | hanya memproses 1 karyawan — bukan compute semua karyawan
            | dulu baru difilter belakangan (lambat).
            |----------------------------------------------------------------
            */
            ->when($npk, function ($q) use ($npk) {
                $q->where('p.NPK', $npk);
            })

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

        /*
        |--------------------------------------------------------------------------
        | PKWT AKTIF (NPK, TMK, TKK) -- DIPAKAI UNTUK:
        | 1. Membatasi absence_days (H/MA/P1/SD) dari `overtimes` agar hanya
        |    valid jika tanggalnya berada di antara TMK - TKK karyawan.
        | 2. Menjadi acuan tanggal untuk perhitungan BR (sebelum TMK) dan OUT
        |    (setelah/mulai TKK) yang sekarang dihitung otomatis dari tanggal,
        |    bukan dari teks 'BR'/'OUT' di table overtimes lagi.
        |--------------------------------------------------------------------------
        */
        $pkwtActive = DB::connection('cii')
            ->table('PKWT as p')
            ->select('p.NPK', 'p.TMK', 'p.TKK')
            ->where('p.TMK', '<=', $periodEnd)
            ->where(function ($q) use ($periodStart) {
                $q->whereNull('p.TKK')
                    ->orWhere('p.TKK', '>=', $periodStart);
            })
            ->where('p.NPK', '!=', 'C-00017');

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
            // ->where('ec1.npk', '=', 'C-04477')

            // ✅ contract harus masuk range periode
            ->whereDate('ec1.start_date', '<=', $periodEnd)
            ->whereDate('ec1.end_date', '>=', $periodStart)

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
            ->leftJoinSub($pkwtActive, 'pk', function ($join) {
                $join->on('overtimes.NPK', '=', 'pk.NPK');
            })
            ->leftJoin('holidays as h', function ($join) {
                $join->on(
                    DB::raw('CAST(overtimes.OVERTIME_DATE AS DATE)'),
                    '=',
                    DB::raw('CAST(h.holiday_date AS DATE)')
                );
            })
            ->whereBetween('OVERTIME_DATE', [$periodStart, $periodEnd])
            ->select(
                'overtimes.NPK',

                // BR & OUT TIDAK LAGI DIAMBIL DARI OVERTIMES (dihitung otomatis
                // dari TMK/TKK di PKWT, lihat foreach($employees) di bawah).
                // H/MA/P1 hanya valid (dihitung) jika tanggalnya berada di
                // antara TMK - TKK karyawan (data di luar rentang itu dianggap
                // tidak valid, mis. MA/P1 setelah TKK atau sebelum TMK).
                DB::raw("
            SUM(
                CASE
                    WHEN UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) = 'H'
                        AND CAST(overtimes.OVERTIME_DATE AS DATE) >= CAST(pk.TMK AS DATE)
                        AND (pk.TKK IS NULL OR CAST(overtimes.OVERTIME_DATE AS DATE) <= CAST(pk.TKK AS DATE))
                        THEN 0.5

                    WHEN JUMLAH_JAM_LEMBUR IS NOT NULL
                        AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NULL
                        AND UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) IN ('MA','P1')
                        AND CAST(overtimes.OVERTIME_DATE AS DATE) >= CAST(pk.TMK AS DATE)
                        AND (pk.TKK IS NULL OR CAST(overtimes.OVERTIME_DATE AS DATE) <= CAST(pk.TKK AS DATE))
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
                        AND UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) = 'SD'
                        AND CAST(overtimes.OVERTIME_DATE AS DATE) >= CAST(pk.TMK AS DATE)
                        AND (pk.TKK IS NULL OR CAST(overtimes.OVERTIME_DATE AS DATE) <= CAST(pk.TKK AS DATE))
                        THEN 1

                    ELSE 0
                END
            ) as sick_days
        ")
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
            ->leftJoinSub($pkwtActive, 'pk', function ($join) {
                $join->on('overtimes.NPK', '=', 'pk.NPK');
            })
            ->leftJoin('DEPT as d', 'bio.ID_DEPT', '=', 'd.ID_DEPT')
            ->leftJoin('holidays as h', function ($join) {
                $join->on(
                    DB::raw('CAST(overtimes.OVERTIME_DATE AS DATE)'),
                    '=',
                    DB::raw('CAST(h.holiday_date AS DATE)')
                );
            })
            ->whereBetween('OVERTIME_DATE', [$periodStart, $periodEnd])
            ->select(
                'overtimes.NPK',
                'bio.NAMA_KARYAWAN',
                'd.DEPARTEMENT',
                'overtimes.OVERTIME_DATE',
                'h.name as holiday_name',
                'h.is_national',

                DB::raw("
                CASE
                    WHEN
                        DAY NOT IN ('Sabtu','Minggu','Saturday','Sunday')
                        AND h.holiday_date IS NULL
                        AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NOT NULL
                    THEN TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT)

                    ELSE 0
                END AS overtime_hours
                "),

                DB::raw("
CASE
    WHEN
    (
        DAY IN ('Sabtu','Minggu','Saturday','Sunday')
        OR h.holiday_date IS NOT NULL
    )
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

                // BR & OUT tidak lagi dihitung dari overtimes (lihat auto BR/OUT
                // dari TMK/TKK di foreach($employees)). H/MA/P1 hanya valid jika
                // tanggalnya di antara TMK - TKK karyawan.
                DB::raw("
            CASE
                WHEN UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) = 'H'
                    AND CAST(overtimes.OVERTIME_DATE AS DATE) >= CAST(pk.TMK AS DATE)
                    AND (pk.TKK IS NULL OR CAST(overtimes.OVERTIME_DATE AS DATE) <= CAST(pk.TKK AS DATE))
                    THEN 0.5

                WHEN JUMLAH_JAM_LEMBUR IS NOT NULL
                    AND TRY_CAST(JUMLAH_JAM_LEMBUR AS FLOAT) IS NULL
                    AND UPPER(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR))) IN ('MA','P1')
                    AND CAST(overtimes.OVERTIME_DATE AS DATE) >= CAST(pk.TMK AS DATE)
                    AND (pk.TKK IS NULL OR CAST(overtimes.OVERTIME_DATE AS DATE) <= CAST(pk.TKK AS DATE))
                    THEN 1

                ELSE 0
            END AS absence_days
        "),
                // absence_status TETAP menampilkan kode aslinya (H, MA, P1, BR,
                // OUT, SD) apa adanya sebagai referensi/audit trail, meskipun
                // entry tsb di luar TMK-TKK (tidak valid) sehingga TIDAK ikut
                // dihitung di absence_days di atas.
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

        /*
|--------------------------------------------------------------------------
| LAPISAN TERLUAR: filter hanya baris dengan rn = 1 (scan paling relevan)
|--------------------------------------------------------------------------
*/
        $lateDetails = DB::connection('cii')
            ->table('employee_lates as el')

            ->leftJoinSub($employeeBase, 'emp', function ($join) {
                $join->on('el.npk', '=', 'emp.NPK');
            })

            ->leftJoin('employee_shifts as es', function ($join) {
                $join->on('el.npk', '=', 'es.npk')
                    ->on(
                        DB::raw('CAST(el.date AS DATE)'),
                        '=',
                        DB::raw('CAST(es.shift_date AS DATE)')
                    );
            })

            ->leftJoin('shifts as s', function ($join) {
                $join->on('es.shift_id', '=', 's.id')
                    ->whereNotNull('es.shift_id');
            })

            ->leftJoin('late_compensations as lc', function ($join) {
                $join->on('el.npk', '=', 'lc.npk')
                    ->whereRaw('CAST(lc.date AS DATE) = CAST(el.date AS DATE)');
            })

            ->whereBetween(DB::raw('CAST(el.date AS DATE)'), [$periodStart, $periodEnd])

            ->selectRaw("
                el.npk as NPK,
                emp.NAMA_KARYAWAN,
                emp.DEPARTEMENT,
                CAST(emp.BARCODE AS VARCHAR(50)) as pin,
                CAST(el.date AS DATE) as scan_day,
                COALESCE(CAST(s.work_start AS TIME), '08:00:00') as work_start,
                COALESCE(CAST(s.work_end AS TIME), '17:00:00') as work_end,
                el.arrival_time as first_scan,
                el.reason,
                CASE
                    WHEN lc.id IS NOT NULL THEN 0
                    WHEN DATEDIFF(
                        MINUTE,
                        DATEADD(MINUTE, 5, COALESCE(CAST(s.work_start AS TIME), '08:00:00')),
                        el.arrival_time
                    ) < 0 THEN 0
                    ELSE DATEDIFF(
                        MINUTE,
                        DATEADD(MINUTE, 5, COALESCE(CAST(s.work_start AS TIME), '08:00:00')),
                        el.arrival_time
                    )
                END as late_actual
            ");

        /*
|--------------------------------------------------------------------------
| LAPISAN PEMBUNGKUS TERAKHIR
|--------------------------------------------------------------------------
*/
        $lateDetails = DB::connection('cii')
            ->query()
            ->fromSub($lateDetails, 'calc')
            ->selectRaw("
        calc.NPK,
        calc.NAMA_KARYAWAN,
        calc.DEPARTEMENT,
        calc.pin,
        calc.scan_day,
        calc.work_start,
        calc.work_end,
        calc.first_scan,
        calc.reason,
        calc.late_actual,
        CASE
            WHEN calc.late_actual > 240 THEN calc.late_actual
            ELSE calc.late_actual
        END as late_minute
    ")
            ->orderBy(DB::raw('CAST(calc.scan_day AS DATE)'))
            ->get()
            ->groupBy('NPK');

        // SUMMARY LATE

        if (!$isCheck) {
            $run->update([
                'status' => 'Calculating Late Minutes',
                'progress' => 25,
            ]);
        }

        /*
|--------------------------------------------------------------------------
| LATE SUMMARY (SUMBER BARU: employee_lates, JOIN by NPK)
|--------------------------------------------------------------------------
| Direkap per NPK: late_minutes (sudah dicap maksimal 240 menit/hari,
| dijumlahkan sepanjang periode) dan late_actual (jumlah mentah tanpa cap).
| Query ini dipakai sebagai leftJoinSub ke $employees berdasarkan
| emp.NPK = lt.npk (tidak lagi lewat BARCODE/pin seperti versi att_log).
|--------------------------------------------------------------------------
*/
        $lateSummary =
            DB::connection('cii')
            ->table('employee_lates as el')

            ->leftJoin('employee_shifts as es', function ($join) {
                $join->on('el.npk', '=', 'es.npk')
                    ->on(
                        DB::raw('CAST(el.date AS DATE)'),
                        '=',
                        DB::raw('CAST(es.shift_date AS DATE)')
                    );
            })

            ->leftJoin('shifts as s', function ($join) {
                $join->on('es.shift_id', '=', 's.id')
                    ->whereNotNull('es.shift_id');
            })

            ->leftJoin('late_compensations as lc', function ($join) {
                $join->on('el.npk', '=', 'lc.npk')
                    ->whereRaw('CAST(lc.date AS DATE) = CAST(el.date AS DATE)');
            })

            ->whereBetween(DB::raw('CAST(el.date AS DATE)'), [$periodStart, $periodEnd])

            ->selectRaw("
                el.npk,
                CASE
                    WHEN lc.id IS NOT NULL THEN 0
                    WHEN DATEDIFF(
                        MINUTE,
                        DATEADD(MINUTE, 5, COALESCE(CAST(s.work_start AS TIME), '08:00:00')),
                        el.arrival_time
                    ) < 0 THEN 0
                    ELSE DATEDIFF(
                        MINUTE,
                        DATEADD(MINUTE, 5, COALESCE(CAST(s.work_start AS TIME), '08:00:00')),
                        el.arrival_time
                    )
                END as raw_late_minute
            ");

        /*
|--------------------------------------------------------------------------
| LAPISAN PEMBUNGKUS TERAKHIR (rekap per pegawai)
|--------------------------------------------------------------------------
*/
        $lateSummary = DB::connection('cii')
            ->query()
            ->fromSub($lateSummary, 'calc')
            ->selectRaw("
        calc.npk,
        SUM(
            CASE
                WHEN calc.raw_late_minute > 240 THEN calc.raw_late_minute
                ELSE calc.raw_late_minute
            END
        ) as late_minutes,
        SUM(calc.raw_late_minute) as late_actual
    ")
            ->groupBy('calc.npk');

        // NOTE: removed redundant `$lateSummary->get();` call here.
        // The exact same query is already consumed below as a subquery via
        // leftJoinSub() when building $employees — calling ->get() on it
        // separately executed this expensive window-function query twice
        // and discarded the result. No behavior change, pure waste removed.

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
                DB::raw("MAX(CASE WHEN component = 'bpjs_ketenagakerjaan' THEN percentage END) as percentket"),
                DB::raw("
            CASE
                WHEN MAX(CASE WHEN component = 'bpjs_kesehatan' THEN percentage END) IS NOT NULL
                THEN 1
                ELSE 0
            END as is_excepkes
        "),
                DB::raw("
            CASE
                WHEN MAX(CASE WHEN component = 'bpjs_ketenagakerjaan' THEN percentage END) IS NOT NULL
                THEN 1
                ELSE 0
            END as is_exceptk
        ")
            )
            ->groupBy('npk');

        $ijinSummary = DB::table('ijin_meninggalkan_pekerjaans as imp')
            ->leftJoin('BIODATA as b', 'b.NPK', '=', 'imp.npk')
            ->leftJoin('DEPT as db', 'db.ID_DEPT', '=', 'b.ID_DEPT')
            ->leftJoin('break_masters as bm', 'bm.id', '=', 'imp.id_break')
            ->selectRaw("
        imp.npk,

        SUM(
            CASE
                WHEN imp.jam_kembali IS NULL THEN 0
                ELSE
                    DATEDIFF(MINUTE, imp.jam_keluar, imp.jam_kembali)
                    -
                    CASE
                        WHEN imp.jam_keluar < bm.time_end
                         AND imp.jam_kembali > bm.time_start
                        THEN
                            DATEDIFF(
                                MINUTE,
                                CASE
                                    WHEN imp.jam_keluar > bm.time_start
                                        THEN imp.jam_keluar
                                    ELSE bm.time_start
                                END,
                                CASE
                                    WHEN imp.jam_kembali < bm.time_end
                                        THEN imp.jam_kembali
                                    ELSE bm.time_end
                                END
                            )
                        ELSE 0
                    END
            END
        ) as total_ijin_minutes
    ")
            ->whereBetween('imp.tanggal', [$periodStart, $periodEnd])
            ->where('imp.is_deduction', true)
            ->groupBy('imp.npk');

        // dd($ijinSummary->get());

        $ijinDetails = DB::table('ijin_meninggalkan_pekerjaans as imp')
            ->leftJoin('BIODATA as b', 'b.NPK', '=', 'imp.npk')
            ->leftJoin('DEPT as db', 'db.ID_DEPT', '=', 'b.ID_DEPT')
            ->leftJoin('break_masters as bm', 'bm.id', '=', 'imp.id_break')
            ->selectRaw("
        imp.npk,
        b.NAMA_KARYAWAN,
        imp.tanggal,
        imp.jam_keluar,
        imp.rencana_kembali,
        imp.jam_kembali,
        imp.reason,
        bm.time_start,
        bm.time_end,
        db.DEPARTEMENT,

        CASE
            WHEN imp.jam_kembali IS NULL THEN 0
            ELSE
                DATEDIFF(MINUTE, imp.jam_keluar, imp.jam_kembali)
                -
                CASE
                    WHEN imp.jam_keluar < bm.time_end
                     AND imp.jam_kembali > bm.time_start
                    THEN
                        DATEDIFF(
                            MINUTE,
                            CASE
                                WHEN imp.jam_keluar > bm.time_start
                                    THEN imp.jam_keluar
                                ELSE bm.time_start
                            END,
                            CASE
                                WHEN imp.jam_kembali < bm.time_end
                                    THEN imp.jam_kembali
                                ELSE bm.time_end
                            END
                        )
                    ELSE 0
                END
        END AS ijin_minutes
    ")
            ->whereBetween('imp.tanggal', [$periodStart, $periodEnd])
            ->where('imp.is_deduction', true)
            ->orderBy('imp.tanggal', 'asc')
            ->get()
            ->groupBy('npk');

        $payrollAdjustmentSummary = DB::table('payroll_adjusments')
            ->select(
                'npk',
                'period_id',
                DB::raw('SUM(adjusment) as total_adjusment')
            )
            ->where('period_id', $period->id)
            ->groupBy('npk', 'period_id');

        $payrollAdjustmentDetails = DB::table('payroll_adjusments as pa')
            ->leftJoinSub($employeeBase, 'emp', function ($join) {
                $join->on('pa.npk', '=', 'emp.NPK');
            })
            ->leftJoin(DB::connection('cii')->raw('DEPT as d'), 'emp.ID_DEPT', '=', 'd.ID_DEPT')
            ->where('pa.period_id', $period->id)
            ->select(
                'pa.*',
                'emp.NAMA_KARYAWAN',
                'emp.ID_DEPT',
                'd.DEPARTEMENT'
            )
            ->orderBy('emp.ID_DEPT')
            ->orderBy('pa.npk')
            ->orderBy('pa.id')
            ->get()
            ->groupBy('npk');

        $nextMutation = DB::table('employee_mutations as em1')
            ->select(
                'em1.npk',
                'em1.from_dept',
                'em1.to_dept',
                'em1.date'
            )
            ->where('em1.date', '>', $periodEnd)
            ->whereRaw('em1.id = (
        SELECT MIN(em2.id)
        FROM employee_mutations em2
        WHERE em2.npk = em1.npk
        AND em2.date > ?
    )', [$periodEnd]);

        $employeeViolationSummary = DB::table('employee_violations')
            ->select(
                'npk',
                DB::raw('SUM(percentage) as violation_percentage')
            )
            ->where('period_id', $period->id)
            ->groupBy('npk');

        /*
        |--------------------------------------------------------------------------
        | NIGHT SHIFT SUMMARY (untuk komponen Night Shift Compensation)
        |--------------------------------------------------------------------------
        | Dihitung dari jumlah baris employee_shifts (per NPK) dalam periode
        | payroll berjalan, dimana shift yang bersangkutan (shifts.work_start)
        | dimulai LEBIH dari jam 18:00. Shift dengan nama mengandung kata
        | "Security" DIKECUALIKAN dari perhitungan ini — pengecekan nama
        | dilakukan case-insensitive (LOWER) supaya tidak lolos hanya karena
        | perbedaan huruf besar/kecil, mis. "security", "SECURITY", "Security".
        |--------------------------------------------------------------------------
        */
        $nightShiftSummary = DB::connection('cii')
            ->table('employee_shifts as es')
            ->join('shifts as s', 'es.shift_id', '=', 's.id')
            ->leftJoin('overtimes as o', function ($join) {
                $join->on('o.NPK', '=', 'es.npk')
                    ->on(DB::raw('CAST(o.OVERTIME_DATE AS DATE)'), '=', DB::raw('CAST(es.shift_date AS DATE)'));
            })
            ->whereBetween(DB::raw('CAST(es.shift_date AS DATE)'), [$periodStart, $periodEnd])
            ->where(DB::raw('CAST(s.work_start AS TIME)'), '>=', '15:00:00')
            ->whereRaw("LOWER(LTRIM(RTRIM(s.name))) NOT LIKE '%security%'")
            ->where(function ($q) {
                $q->whereNull('o.JUMLAH_JAM_LEMBUR')
                    ->orWhereRaw("TRY_CAST(o.JUMLAH_JAM_LEMBUR AS DECIMAL(10,2)) IS NOT NULL");
            })
            ->select(
                'es.npk',
                DB::raw('COUNT(*) as night_shift_count')
            )
            ->groupBy('es.npk');

        /*
        |--------------------------------------------------------------------------
        | NIGHT SHIFT DETAILS (untuk modal detail Night Shift Compensation)
        |--------------------------------------------------------------------------
        | Rincian per-baris shift malam (per NPK) yang menjadi dasar
        | $nightShiftSummary di atas -- dipakai untuk menampilkan modal
        | detail di halaman Payroll Processing / Payroll Approve.
        |--------------------------------------------------------------------------
        */
        $nightShiftDetails = DB::connection('cii')
            ->table('employee_shifts as es')
            ->join('shifts as s', 'es.shift_id', '=', 's.id')
            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('es.npk', '=', 'bio.NPK');
            })
            ->leftJoin('DEPT as d', 'bio.ID_DEPT', '=', 'd.ID_DEPT')
            ->leftJoin('overtimes as o', function ($join) {
                $join->on('o.NPK', '=', 'es.npk')
                    ->on(DB::raw('CAST(o.OVERTIME_DATE AS DATE)'), '=', DB::raw('CAST(es.shift_date AS DATE)'));
            })
            ->whereBetween(DB::raw('CAST(es.shift_date AS DATE)'), [$periodStart, $periodEnd])
            ->where(DB::raw('CAST(s.work_start AS TIME)'), '>=', '15:00:00')
            ->whereRaw("LOWER(LTRIM(RTRIM(s.name))) NOT LIKE '%security%'")
            ->where(function ($q) {
                $q->whereNull('o.JUMLAH_JAM_LEMBUR')
                    ->orWhereRaw("TRY_CAST(o.JUMLAH_JAM_LEMBUR AS DECIMAL(10,2)) IS NOT NULL");
            })
            ->select(
                'es.npk as NPK',
                'bio.NAMA_KARYAWAN',
                'd.DEPARTEMENT',
                DB::raw('CAST(es.shift_date AS DATE) as shift_date'),
                's.name as shift_name',
                DB::raw('CAST(s.work_start AS TIME) as work_start'),
                DB::raw('CAST(s.work_end AS TIME) as work_end')
            )
            ->orderBy('es.npk')
            ->orderBy('es.shift_date')
            ->get()
            ->groupBy('NPK');

        $employeesQuery = DB::connection('cii')
            ->query()
            ->fromSub($employeeBase, 'emp')

            ->leftJoinSub($overtimeSummary, 'ot', function ($join) {
                $join->on('emp.NPK', '=', 'ot.NPK');
            })

            ->leftJoinSub($lateSummary, 'lt', function ($join) {
                $join->on(
                    DB::raw('CAST(emp.NPK AS VARCHAR(50))'),
                    '=',
                    DB::raw('CAST(lt.npk AS VARCHAR(50))')
                );
            })

            ->leftJoinSub($latestContract, 'ec', function ($join) {
                $join->on('emp.NPK', '=', 'ec.npk');
            })

            ->leftJoinSub($payrollAdjustmentSummary, 'pa', function ($join) {
                $join->on('emp.NPK', '=', 'pa.npk');
            })
            ->leftJoinSub($employeeViolationSummary, 'ev', function ($join) {
                $join->on('emp.NPK', '=', 'ev.npk');
            })

            ->leftJoinSub($assignment6s, 'a6s', function ($join) use ($period) {
                $join->on('emp.NPK', '=', 'a6s.npk')
                    ->where('a6s.period_id', '=', $period->id);
            })
            ->leftJoinSub($nextMutation, 'em', function ($join) {
                $join->on('emp.NPK', '=', 'em.npk');
            })

            ->leftJoin('DEPT as d', function ($join) {
                $join->on(
                    'd.ID_DEPT',
                    '=',
                    DB::raw("
            CASE
                WHEN em.from_dept IS NOT NULL
                THEN em.from_dept
                ELSE emp.ID_DEPT
            END
        ")
                );
            })
            ->leftJoinSub($bpjsException, 'be', function ($join) {
                $join->on('emp.NPK', '=', 'be.npk');
            })
            ->leftJoinSub($ijinSummary, 'ij', function ($join) {
                $join->on(
                    DB::raw('CAST(emp.NPK AS VARCHAR(50))'),
                    '=',
                    DB::raw('CAST(ij.npk AS VARCHAR(50))')
                );
            })

            ->leftJoinSub($nightShiftSummary, 'ns', function ($join) {
                $join->on(
                    DB::raw('CAST(emp.NPK AS VARCHAR(50))'),
                    '=',
                    DB::raw('CAST(ns.npk AS VARCHAR(50))')
                );
            })

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
                'be.is_excepkes',
                'be.is_exceptk',

                DB::raw("
                CASE
                    WHEN em.from_dept IS NOT NULL
                    THEN em.from_dept
                    ELSE emp.ID_DEPT
                END as payroll_dept
                "),

                DB::raw('COALESCE(ev.violation_percentage, 0) as violation_percentage'),
                DB::raw('COALESCE(pa.total_adjusment,0) as adjusment'),
                DB::raw('COALESCE(ot.absence_days,0) as absence_days'),
                DB::raw('COALESCE(ot.sick_days,0) as sick_days'),
                DB::raw('COALESCE(lt.late_minutes,0) as late_minutes'),
                DB::raw('COALESCE(ij.total_ijin_minutes,0) as total_ijin_minutes'),
                DB::raw('COALESCE(ij.total_ijin_minutes,0) / 60 as total_ijin_hours'),
                DB::raw('COALESCE(ns.night_shift_count,0) as night_shift_count'),

                DB::raw("DATEDIFF(YEAR, emp.TMK, '$periodEnd') as working_years")
            );
        /*
        |--------------------------------------------------------------------------
        | FILTER BY payroll_role -- HANYA UNTUK MODE CHECK/SIMULATION
        |--------------------------------------------------------------------------
        | $isCheck = false  -> generate payroll SUNGGUHAN, dijalankan di queue
        |                      worker (tanpa session user), dan HARUS mencakup
        |                      SEMUA karyawan tanpa kecuali. TIDAK BOLEH difilter.
        |
        | $isCheck = true   -> dipanggil SINKRON dari
        |                      PayrollProcessController::checkPayroll(), pada
        |                      request yang sama dengan user yang login, sehingga
        |                      Auth::user() valid. Di sinilah kita batasi hasil
        |                      simulasi sesuai role_payrolls user (Admin bypass).
        |--------------------------------------------------------------------------
        */
        if ($isCheck) {
            $checkUser    = Auth::user();
            $checkRole    = PayrollRoleFilterService::getRole($checkUser);
            $checkIsAdmin = $checkUser ? $checkUser->hasRole('Admin') : false;

            if (!$checkIsAdmin) {
                PayrollRoleFilterService::applyToQuery(
                    $employeesQuery,
                    $checkRole,
                    'emp.IS_STAFF',
                    'd.IS_SEWING'
                );
            }
        }

        $employees = $employeesQuery
            ->when($npk, function ($q) use ($npk) {
                // Safety-net: employeeBase sudah difilter di root query,
                // tapi tetap dijaga di sini juga supaya query akhir yang
                // benar-benar dieksekusi pasti hanya 1 NPK.
                $q->where('emp.NPK', $npk);
            })
            ->orderBy('emp.ID_DEPT', 'asc')
            ->orderBy('emp.NPK', 'asc')
            ->get();

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

        $componentTypeMap = $components->pluck('type', 'code')->toArray();

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

        /*
        |--------------------------------------------------------------------------
        | PRE-FETCH DATA YANG SEBELUMNYA DI-QUERY ULANG PER EMPLOYEE (N+1 FIX)
        |--------------------------------------------------------------------------
        | Semua data berikut sebelumnya di-query di dalam foreach($employees)
        | -> per employee -> per component, sehingga jumlah query DB bisa
        | mencapai ribuan untuk payroll dengan banyak karyawan.
        |
        | Di sini kita ambil SEMUA data yang relevan untuk periode berjalan
        | dalam satu query per tabel, lalu kita group/keyBy di memori (PHP)
        | supaya bisa dipakai sebagai lookup table per NPK / per dept /
        | per tanggal. Filter yang tadinya di klausa SQL `WHERE npk = ?`
        | sekarang menjadi `$collection->get($npk)` — hasilnya identik,
        | karena filternya sama persis, hanya dipindah ke level aplikasi.
        |--------------------------------------------------------------------------
        */

        // Overtime (dipakai berulang di sewing/pad/cutting/heat insentif untuk
        // validasi "apakah hari ini valid dihitung insentif")
        $allOvertimesForInsentif = DB::table('overtimes')
            ->whereBetween('OVERTIME_DATE', [$period->start_date, $period->end_date])
            ->get()
            ->groupBy('NPK')
            ->map(function ($rows) {
                return $rows->keyBy('OVERTIME_DATE');
            });

        $isValidOvertimeFor = function ($npk, $date) use ($allOvertimesForInsentif) {
            $row = $allOvertimesForInsentif[$npk][$date] ?? null;

            if (!$row) {
                return true; // tidak ada overtime → tetap dihitung
            }

            $lembur = $row->JUMLAH_JAM_LEMBUR;

            if ($lembur === null || $lembur === '') {
                return true;
            }

            if (is_numeric($lembur)) {
                return true;
            }

            // karakter (MA, CT, BR, S1, dll)
            return false;
        };

        // Sewing violations untuk operator (per id_dept) dan untuk supervisor
        // (per range line). Kita ambil sekali, sudah join ke DEPT supaya bisa
        // dipakai untuk perhitungan range "LINE n" pada cabang supervisor.
        $allSewingViolationsRaw = DB::table('sewing_violations')
            ->leftJoin('DEPT as d', 'sewing_violations.id_dept', '=', 'd.ID_DEPT')
            ->whereBetween('sewing_violations.tanggal', [$period->start_date, $period->end_date])
            ->select('sewing_violations.id_dept', 'd.DEPARTEMENT')
            ->get();

        // by id_dept langsung -> dipakai untuk role operator
        $sewingViolationsByDept = $allSewingViolationsRaw->groupBy('id_dept');

        // by nomor line (hasil parse "LINE n") -> dipakai untuk role supervisor
        $sewingViolationsByLineNumber = $allSewingViolationsRaw
            ->filter(function ($row) {
                return $row->DEPARTEMENT && stripos($row->DEPARTEMENT, 'LINE ') === 0;
            })
            ->groupBy(function ($row) {
                return (int) str_ireplace('LINE ', '', $row->DEPARTEMENT);
            });

        // cutting violations dipakai untuk range line (supervisor sewing,
        // dihitung dari DEPT yang formatnya "LINE n")
        $countSewingViolationsForLineRange = function ($lineStart, $lineEnd) use ($sewingViolationsByLineNumber) {
            $count = 0;
            foreach ($sewingViolationsByLineNumber as $lineNumber => $rows) {
                if ($lineNumber >= $lineStart && $lineNumber <= $lineEnd) {
                    $count += $rows->count();
                }
            }
            return $count;
        };

        if (!$isCheck) {
            $run->update([
                'status' => 'Payroll Calculation In Progress',
                'progress' => 45,
            ]);
        }

        // Untuk batch insert PayrollRunDetail di akhir, alih-alih create()
        // satu per satu di dalam loop.
        $payrollRunDetailRows = [];
        $now = Carbon::now();

        foreach ($employees as $employee) {
            $absenceDays = 0;

            /**
             * cek apakah TMK atau TKK terjadi dalam periode payroll
             */
            $tmk = $employee->TMK ? Carbon::parse($employee->TMK) : null;
            $tkk = $employee->TKK ? Carbon::parse($employee->TKK) : null;

            /**
             * BR & OUT SEKARANG DIHITUNG OTOMATIS DARI TANGGAL TMK/TKK (PKWT),
             * BUKAN dari teks literal 'BR'/'OUT' di table overtimes.
             * - BR  : hari kerja (Senin-Jumat) SEBELUM tanggal TMK, dimulai
             *         dari awal periode payroll berjalan.
             *         Contoh: TMK 7 Juli -> BR diambil dari tanggal 1-6 Juli,
             *         Sabtu/Minggu dilewati.
             * - OUT : hari kerja (Senin-Jumat) MULAI tanggal TKK s/d akhir
             *         periode payroll berjalan.
             *         Contoh: TKK 28 Juli -> OUT diambil dari tanggal 28-31
             *         Juli, Sabtu/Minggu dilewati.
             */
            $autoBrDays = 0;
            if ($tmk && $tmk->between($periodStart, $periodEnd)) {
                $cursor = $periodStart->copy();
                $limit  = $tmk->copy()->subDay();

                while ($cursor->lte($limit)) {
                    if (!$cursor->isWeekend()) {
                        $autoBrDays++;
                    }
                    $cursor->addDay();
                }
            }

            $autoOutDays = 0;
            if ($tkk && $tkk->between($periodStart, $periodEnd)) {
                $cursor = $tkk->copy();

                while ($cursor->lte($periodEnd)) {
                    if (!$cursor->isWeekend()) {
                        $autoOutDays++;
                    }
                    $cursor->addDay();
                }
            }

            // $employee->absence_days sekarang HANYA berisi H/MA/P1 yang valid
            // dari overtimes (sudah difilter di rentang TMK-TKK pada query
            // $overtimeSummary). BR & OUT otomatis ditambahkan di sini.
            $absenceDaysRaw = (float) $employee->absence_days + $autoBrDays + $autoOutDays;

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

                // rumus: (21 - hari kerja periode) + absence karyawan
                if ($absenceDaysRaw <= ($workingDays - 21)) {
                    $absenceDays = 0;
                } else {

                    // $absenceDays = (21 - $workingDays) + $absenceDaysRaw;
                    $absenceDays = (21 - $workingDays) + $absenceDaysRaw;
                }
            } else {
                $absenceDays = $absenceDaysRaw;
            }

            $isJoinOrResignInPeriod =
                ($tmk && $tmk->between($periodStart, $periodEnd)) ||
                ($tkk && $tkk->between($periodStart, $periodEnd));

            // tambahan: khusus untuk cek TKK apakah jatuh di periode berjalan
            $isTkkInPeriod = ($tkk && $tkk->between($periodStart, $periodEnd)) ? 1 : 0;

            $inputVariables = [
                'basic_salary'   => (float) $employee->salary,
                'allowance'      => (float) $employee->allowance,
                'absence_days_asli'   => (float) $absenceDaysRaw,
                'absence_days'   => (float) $absenceDays,
                'sick_days'   => (float) $employee->sick_days,
                'working_years'  => (float) $employee->working_years,
                'adjusment'      => (float) $employee->adjusment,
                'pph_21'         => (float) $employee->pph21,
                'daily_salary'   => (float) $employee->daily_salary,
                'count_days'     => (float) $count_days,
                'tanggungan'     => (float) $employee->TANGGUNGAN,
                'percentage'     => (float) $employee->percentage,

                'violation_percentage' => (float) $employee->violation_percentage,
                'total_ijin'     => (float) $employee->total_ijin_minutes,
                'night_shift_count' => (float) $employee->night_shift_count,
                'is_contract' => Str::ucfirst(Str::lower($employee->type)) === 'Contract' ? 1 : 0,
                'is_daily'    => Str::ucfirst(Str::lower($employee->type)) === 'Daily' ? 1 : 0,
                'late_minutes'     => (float) $employee->late_minutes,
                'is_staff'       => $employee->IS_STAFF == '1' ? 1 : 0,
                'is_sewing'       => $employee->IS_SEWING == '1' ? 1 : 0,
                'is_expat'       => $employee->IS_EXPAT == '1' ? 1 : 0,
                'bpjskesex' => $employee->percentkes === null ? 1 : (float) $employee->percentkes,
                'bpjsketex' => (float) $employee->percentket,
                'is_excepkes'  => $employee->is_excepkes === null ? 0 : (float) $employee->is_excepkes,
                'is_exceptk'  => $employee->is_exceptk === null ? 0 : (float) $employee->is_exceptk,
                'bpjs_base' => (
                    ($employee->IS_STAFF == '1' || $employee->IS_EXPAT == '1')
                    ? (
                        Str::ucfirst(Str::lower($employee->type)) === 'Contract'
                        ? (float) $employee->salary
                        : ((float) $employee->salary)
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
                'bpjsjpex' => $employee->IS_EXPAT == '1' ? 0 : 1,
                'bpjsjhtex' => 2,
                // BARU — dipakai formula bpjs_kesehatan untuk aturan cut-off tanggal 20
                'tkk_in_period' => $isTkkInPeriod,
                'tkk_day'        => $tkk ? (float) $tkk->day : 0,
                'has_tkk'        => $tkk ? 1 : 0,
            ];

            /*
            |--------------------------------------------------------------------------
            | SALARY DAILY CONTRACT (EXPAT + DAILY)
            |--------------------------------------------------------------------------
            | Untuk karyawan expat berstatus Daily, basic_salary yang tampil di
            | payroll bukan hasil kalkulasi formula seperti biasa, melainkan
            | diambil langsung dari kolom `salary` pada employees_contract
            | (sudah tersedia di $employee->salary via $latestContract join).
            | Ini murni penanda/nilai tambahan — tidak mengubah $inputVariables
            | ataupun proses evaluateFormula di bawah.
            |--------------------------------------------------------------------------
*/
            $isExpatDaily = $inputVariables['is_expat'] == 1 && $inputVariables['is_daily'] == 1;

            $salaryDailyContract = $isExpatDaily
                ? (float) $employee->salary
                : null;

            $results = [];
            $grandTotal = 0;

            foreach ($components as $component) {
                if ($component->code === 'thr') continue;
                if ($component->code === 'compensation') continue;

                if ($component->calculation_method === 'fixed') {
                    $amount = $component->value;
                } else {

                    $this->updateProgress(
                        $run ?? null,
                        $isCheck,
                        'Calculation for ' . $employee->NPK . ' - ' . $component->name,
                        60
                    );

                    if ($component->code === 'bpjs_kesehatan') {
                        $totalBPJSKesehatan = 0;

                        $totalBPJSKesehatan += $this->evaluateFormula(
                            $BPJSKesehatanFormula,
                            $results,
                            $inputVariables
                        );

                        $amount = $totalBPJSKesehatan;
                    } else if ($component->code === 'bpjs_ketenagakerjaan') {
                        $totalBPJSKetenagakerjaan = 0;

                        $totalBPJSKetenagakerjaan += $this->evaluateFormula(
                            $BPJSKetenagakerjaanFormula,
                            $results,
                            $inputVariables
                        );

                        $amount = $totalBPJSKetenagakerjaan;
                    } else if ($component->code === 'sixs_insentif') {
                        $total6sInsentif = 0;

                        $total6sInsentif += $this->evaluateFormula(
                            $sixsInsentifFormula,
                            $results,
                            $inputVariables
                        );

                        $amount = $total6sInsentif;
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

                        $this->updateProgress(
                            $run ?? null,
                            $isCheck,
                            'Calculation for ' . $employee->NPK . ' - ' . $component->name,
                            60
                        );

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

                        /*
                        |----------------------------------------------------
                        | LOAD THRESHOLD
                        |----------------------------------------------------
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

                        // NOTE: $mutations (employee_mutations) yang sebelumnya
                        // di-query di sini dihapus karena hasilnya tidak pernah
                        // digunakan di logic manapun pada blok ini (dead code).

                        // Validasi overtime sekarang pakai data yang sudah
                        // di-prefetch sebelum loop (lihat $isValidOvertimeFor).
                        $isValidOvertime = function ($date) use ($employee, $isValidOvertimeFor) {
                            return $isValidOvertimeFor($employee->NPK, $date);
                        };

                        /*
                        |----------------------------------------------------
                        | OPERATOR
                        |----------------------------------------------------
                        */
                        $lineViolations = 0;
                        foreach ($assignmentNpk as $assignment) {
                            if (empty($assignment->role)) {
                                continue;
                            }
                            if ($assignment->role == 'operator' || $assignment->role == 'supervisor') {

                                preg_match('/\d+/', $employee->DEPARTEMENT, $matches);
                                $defaultLine = $matches[0] ?? null;

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
                                        'ela.work_hours',
                                        'max_wh.max_work_hours'
                                    )

                                    ->orderBy('le.date')
                                    ->get();

                                if (strtolower($assignment->role) == 'operator') {

                                    // Sebelumnya: query sewing_violations per employee.
                                    // Sekarang: lookup dari hasil prefetch by id_dept.
                                    $lineViolations = ($sewingViolationsByDept[$employee->ID_DEPT] ?? collect())->count();
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

                                    // Sebelumnya: query sewing_violations per employee.
                                    // Sekarang: lookup dari hasil prefetch by id_dept.
                                    $lineViolations = ($sewingViolationsByDept[$lineDeptId] ?? collect())->count();
                                } else {

                                    $lineViolations = 0;
                                }

                                foreach ($lineefficiencies as $row) {

                                    if ($tkkDate && $row->date >= $tkkDate) {
                                        continue;
                                    }

                                    if (!$isValidOvertime($row->date)) {
                                        continue;
                                    }

                                    $lineInsentif =
                                        $this->getInsentifByEfficiency($row->efficiency, $sewingInsentifFormula) * $row->work_hours / $row->max_work_hours;

                                    $amount += $this->calculateRoleSewingInsentif(
                                        $assignment->role,
                                        'sewing',
                                        $lineInsentif,
                                        1,
                                        $lineViolations,
                                        $employee->violation_percentage
                                    );
                                }
                            } else {

                                /*
                                |--------------------------------------------
                                | CHIEF / MEKANIK / MEKANIK LEADER
                                |--------------------------------------------
                                */
                                $validRoles = ['chief', 'mekanik', 'mekanik_leader'];

                                if (!in_array($assignment->role, $validRoles)) {
                                    continue;
                                }

                                $section = DB::table('sections')
                                    ->whereRaw('id = ?', [(int) $employee->SECTION])
                                    ->select('line_start', 'line_end')
                                    ->first();

                                if (!$section) {
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
                                        'le.date'
                                    )

                                    ->groupBy(
                                        'le.date'
                                    )

                                    ->orderBy('le.date')
                                    ->get();

                                // Sebelumnya: query sewing_violations per employee
                                // dengan filter range "LINE n" pada DEPARTEMENT.
                                // Sekarang: hitung dari hasil prefetch by line number.
                                $lineViolations = $countSewingViolationsForLineRange($lineStart, $lineEnd);

                                $collectionDay = collect([]);
                                $collectionLines = collect([]);

                                $jumlahLine = DB::table('line_efficiencies')
                                    ->where('period_id', $period->id)
                                    ->whereBetween('date', [$period->start_date, $period->end_date])
                                    ->whereBetween('line_number', [$lineStart, $lineEnd])
                                    ->selectRaw('COUNT(DISTINCT line_number) as jumlah_line')
                                    ->get();

                                foreach ($grouped as $day) {

                                    if ($tkkDate && $day->date >= $tkkDate) {
                                        continue;
                                    }

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
                                    }

                                    $amount += $this->calculateRoleSewingInsentif(
                                        $assignment->role,
                                        'sewing',
                                        $totalLineInsentif,
                                        $jumlahLine->first()->jumlah_line,
                                        $lineViolations,
                                        $employee->violation_percentage
                                    );

                                    $collectionDay->push($amount);
                                }
                            }
                        }
                    } else if ($component->code === 'pad_insentif') {

                        $query = DB::table('pad_efficiencies')
                            ->where('npk', $employee->NPK)
                            ->where('period_id', $period->id)
                            ->whereBetween('date', [$period->start_date, $period->end_date]);

                        $isOperator = (clone $query)->value('role') === 'operator';

                        $assignments = $isOperator
                            ? $query->get()
                            : $query->limit(1)->get();

                        $amount = 0;

                        // NOTE: $mutations (employee_mutations) dihapus — dead code,
                        // tidak pernah dipakai di blok ini.

                        $isValidOvertime = function ($npk, $date) use ($isValidOvertimeFor) {
                            return $isValidOvertimeFor($npk, $date);
                        };

                        foreach ($assignments as $assignment) {

                            if (empty($assignment->role)) {
                                continue;
                            }
                            if ($assignment->role === 'operator') {

                                if (!$isValidOvertime($assignment->npk, $assignment->date)) {
                                    continue;
                                }

                                $rate = $this->getInsentifByEfficiency(
                                    $assignment->efficiency,
                                    $padInsentifFormula
                                );

                                $amount += $rate * $assignment->piece;
                            } else {

                                $employeeDates = DB::table('pad_efficiencies')
                                    ->where('period_id', $period->id)
                                    ->where('npk', $employee->NPK)
                                    ->pluck('date')
                                    ->unique()
                                    ->toArray();

                                $totalDeptInsentif = 0;

                                $operators = DB::table('pad_efficiencies')
                                    ->where('period_id', $period->id)
                                    ->where('role', '=', 'operator')
                                    ->whereBetween('date', [$period->start_date, $period->end_date])
                                    ->whereIn('date', $employeeDates)
                                    ->get();

                                foreach ($operators as $operator) {
                                    if (!$isValidOvertime($operator->npk, $operator->date)) {
                                        continue;
                                    }

                                    $rate = $this->getInsentifByEfficiency(
                                        $operator->efficiency,
                                        $padInsentifFormula
                                    );

                                    $totalDeptInsentif += $rate * $operator->piece;
                                }

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

                        $amount = 0;

                        // NOTE: $mutations dihapus — dead code, tidak dipakai.

                        $isValidOvertime = function ($date) use ($employee, $isValidOvertimeFor) {
                            return $isValidOvertimeFor($employee->NPK, $date);
                        };

                        $employeeDates = DB::table('employee_cutting_assignments')
                            ->where('period_id', $period->id)
                            ->where('npk', $employee->NPK)
                            ->pluck('start_date')
                            ->unique()
                            ->toArray();

                        $cuttingEfficiencies = DB::table('cutting_efficiencies')
                            ->where('period_id', $period->id)
                            ->whereBetween('date', [
                                $period->start_date,
                                $period->end_date
                            ])
                            ->whereIn('date', $employeeDates)
                            ->get();

                        foreach ($assignmentNpk as $assignment) {
                            if (empty($assignment->role)) {
                                continue;
                            }
                            foreach ($cuttingEfficiencies as $row) {
                                if ($tkkDate && $row->date >= $tkkDate) {
                                    continue;
                                }

                                if (!$isValidOvertime($row->date)) {
                                    continue;
                                }

                                $insentif = $this->getInsentifByEfficiency(
                                    $row->efficiency,
                                    $cuttingInsentifFormula
                                );

                                $amount += $this->calculateRoleCuttingInsentif(
                                    $assignment->role,
                                    'cutting',
                                    $insentif
                                );
                            }
                        }
                    } else if ($component->code === 'heat_insentif') {

                        $query = DB::table('heat_efficiencies')
                            ->where('npk', $employee->NPK)
                            ->where('period_id', $period->id)
                            ->whereBetween('date', [$period->start_date, $period->end_date]);

                        $isOperator = (clone $query)->value('role') === 'operator';

                        $assignments = $isOperator
                            ? $query->get()
                            : $query->limit(1)->get();

                        $amount = 0;

                        // NOTE: $mutations dihapus — dead code, tidak dipakai.

                        $isValidOvertime = function ($npk, $date) use ($isValidOvertimeFor) {
                            return $isValidOvertimeFor($npk, $date);
                        };

                        foreach ($assignments as $assignment) {

                            if (empty($assignment->role)) {
                                continue;
                            }
                            if ($assignment->role === 'operator') {

                                if (!$isValidOvertime($assignment->npk, $assignment->date)) {
                                    continue;
                                }

                                $rate = $this->getInsentifByEfficiency(
                                    $assignment->efficiency,
                                    $heatInsentifFormula
                                );

                                $amount += $rate * $assignment->piece;
                            } else {
                                $employeeDates = DB::table('heat_efficiencies')
                                    ->where('period_id', $period->id)
                                    ->where('npk', $employee->NPK)
                                    ->where('role', $assignment->role)
                                    ->pluck('date')
                                    ->unique()
                                    ->toArray();
                                $totalDeptInsentif = 0;

                                $operators = DB::table('heat_efficiencies')
                                    ->where('period_id', $period->id)
                                    ->where('role', '=', 'operator')
                                    ->whereBetween('date', [$period->start_date, $period->end_date])
                                    ->whereIn('date', $employeeDates) // sekarang hanya tanggal saat NPK ini jadi $role, bukan semua tanggal NPK
                                    ->get();

                                foreach ($operators as $operator) {
                                    if ($tkkDate && $operator->date >= $tkkDate) {
                                        continue;
                                    }
                                    // FILTER HANYA NUMERATOR
                                    if (!$isValidOvertime($operator->npk, $operator->date)) {
                                        continue;
                                    }

                                    $rate = $this->getInsentifByEfficiency(
                                        $operator->efficiency,
                                        $heatInsentifFormula
                                    );

                                    $totalDeptInsentif += $rate * $operator->piece;
                                }
                                $jumlahOperator = DB::table('heat_efficiencies as he')
                                    ->where('he.period_id', $period->id)
                                    ->whereIn('he.date', $employeeDates) // ikut pakai $employeeDates yang sudah difilter role
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

                /*
                |--------------------------------------------------------------------------
                | GUARD: absence_deduction TIDAK BOLEH NEGATIF
                |--------------------------------------------------------------------------
                | absence_deduction adalah komponen bertipe "deduction" yang secara
                | semantik selalu diperlakukan sebagai nilai yang DIKURANGKAN dari
                | grandTotal (lihat blok "if earning / else deduction" di bawah:
                | $grandTotal -= $amount).
                |
                | Kalau formula-nya menghasilkan angka NEGATIF (mis. -500000), maka
                | $grandTotal -= (-500000) akan menjadi $grandTotal + 500000 —
                | tanda minus ketemu minus jadi PLUS, sehingga gaji malah naik alih-
                | alih dipotong (mis. 1000000 - (-500000) = 1500000, padahal
                | seharusnya tetap 1000000 / tidak dipotong).
                |
                | Fix: kalau absence_deduction hasilnya negatif, jangan dihitung —
                | anggap 0, supaya tidak terbalik jadi penambahan.
                |--------------------------------------------------------------------------
                */
                // if ($component->code === 'absence_deduction' && $amount < 0) {
                //     $amount = 0;
                // }

                $results[$component->code] = $amount;

                if ($component->type === 'earning') {
                    $grandTotal += $amount;
                } else {
                    $grandTotal -= $amount;
                }
            }

            $grandTotal = round($grandTotal, 0);

            $componentsWithType = [];
            foreach ($results as $code => $amount) {
                $componentsWithType[$code] = [
                    'amount' => $amount,
                    'type'   => $componentTypeMap[$code] ?? null,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | OVERRIDE BASIC_SALARY UNTUK EXPAT + DAILY
            |--------------------------------------------------------------------------
            | Jika karyawan expat & daily, nilai yang disimpan ke components
            | (dan otomatis ikut ke payroll_run_details.components) untuk
            | basic_salary diganti pakai salary_daily_contract, bukan hasil
            | formula/basic_salary hasil kalkulasi biasa. total_salary /
            | $grandTotal TIDAK diubah — hanya representasi nilai basic_salary
            | yang disimpan di kolom components.
            |--------------------------------------------------------------------------
            */
            if ($salaryDailyContract !== null && isset($componentsWithType['basic_salary'])) {
                $componentsWithType['basic_salary']['amount'] = $salaryDailyContract;
            }

            if (!$isCheck) {
                $batchSize = 250;
                $payrollRunDetailRows[] = [
                    'run_id'        => $run->id,
                    'employee_npk'  => $employee->NPK,
                    'employee_name' => $employee->NAMA_KARYAWAN,
                    'employee_dept' => $employee->payroll_dept,
                    'components' => json_encode($componentsWithType),
                    'total_salary'  => $grandTotal,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];

                // Flush batch insert tiap 500 baris supaya tidak terlalu besar
                // dalam satu query, sambil tetap jauh lebih sedikit query
                // dibanding insert satu-satu seperti sebelumnya.
                if (count($payrollRunDetailRows) >= $batchSize) {
                    PayrollRunDetail::insert($payrollRunDetailRows);
                    $payrollRunDetailRows = [];
                }
            }

            $totalPayroll += $grandTotal;

            if ($isCheck) {
                $payrollResults[] = [
                    'is_contract' => Str::ucfirst(Str::lower($employee->type)) === 'Contract' ? 1 : 0,
                    'is_daily'    => Str::ucfirst(Str::lower($employee->type)) === 'Daily' ? 1 : 0,
                    'is_expat'       => $employee->IS_EXPAT == '1' ? 1 : 0,
                    'salary_daily_contract' => $salaryDailyContract,
                    'absence_days_asli'   => (float) $absenceDaysRaw,
                    'absence_days'   => (float) $absenceDays,
                    'br_days_auto'   => (float) $autoBrDays,
                    'out_days_auto'  => (float) $autoOutDays,
                    'sick_days'   => (float) $employee->sick_days,
                    'count_days'    => $count_days,
                    'type' => Str::ucfirst(Str::lower($employee->type)),
                    'dept' => $employee->DEPARTEMENT,
                    'id_dept' => $employee->ID_DEPT,
                    'employee_dept' => $employee->payroll_dept,
                    'tmk' => $employee->TMK,
                    'period_start'  => $periodStart,
                    'period_end'  => $periodEnd,
                    'tkk' => $employee->TKK,
                    'late_summary' => $employee->total_telat,
                    'violation_percentage' => (float) $employee->violation_percentage,
                    'total_ijin'     => (float) $employee->total_ijin_minutes,
                    'night_shift_count' => (float) $employee->night_shift_count,
                    'is_sewing'       => $employee->IS_SEWING == '1' ? 1 : 0,
                    'is_staff'       => $employee->IS_STAFF == '1' ? 1 : 0,
                    'bpjskesex' => $employee->percentkes === null ? 1 : (float) $employee->percentkes,
                    'bpjsketex' => (float) $employee->percentket,
                    'is_excepkes'  => $employee->is_excepkes === null ? 0 : (float) $employee->is_excepkes,
                    'is_exceptk'  => $employee->is_exceptk === null ? 0 : (float) $employee->is_exceptk,
                    'percentage'     => (float) $employee->percentage,
                    'bpjs_base' => (
                        ($employee->IS_STAFF == '1' || $employee->IS_EXPAT == '1')
                        ? (
                            Str::ucfirst(Str::lower($employee->type)) === 'Contract'
                            ? (float) $employee->salary
                            : ((float) $employee->salary)
                            // Str::ucfirst(Str::lower($employee->type)) === 'Contract'
                            // ? (float) $employee->salary
                            // : ((float) $employee->daily_salary * (float) $count_days)
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
                    'bpjsjpex' => $employee->IS_EXPAT == '1' ? 0 : 1,
                    'bpjsjhtex' => 2,
                    // BARU — dipakai formula bpjs_kesehatan untuk aturan cut-off tanggal 20
                    'tkk_in_period' => $isTkkInPeriod,
                    'tkk_day'        => $tkk ? (float) $tkk->day : 0,
                    'has_tkk'        => $tkk ? 1 : 0,
                    'employee_npk'  => $employee->NPK,
                    'employee_name' => $employee->NAMA_KARYAWAN,
                    'tanggungan' => $employee->TANGGUNGAN,
                    'keterangan' => $employee->KETERANGAN,
                    'components' => $componentsWithType,
                    'payroll_adjustment_details' => ($payrollAdjustmentDetails[$employee->NPK] ?? collect())
                        ->values(),

                    'payroll_adjustment_total' => (float) $employee->adjusment,
                    'overtime_details' => ($overtimeDetails[$employee->NPK] ?? collect())
                        ->values(),
                    'late_details' => ($lateDetails[$employee->NPK] ?? collect())
                        ->values(),
                    'ijin_details' => ($ijinDetails[$employee->NPK] ?? collect())->values(),
                    'night_shift_details' => ($nightShiftDetails[$employee->NPK] ?? collect())
                        ->values(),
                    'total_salary'  => $grandTotal
                ];
            }
        }

        // Flush sisa baris yang belum mencapai batch size 500
        if (!$isCheck && !empty($payrollRunDetailRows)) {
            PayrollRunDetail::insert($payrollRunDetailRows);
            $payrollRunDetailRows = [];
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

            DB::transaction(function () use ($periodStart, $periodEnd) {

                // Hapus data payroll lembur pada periode yang akan digenerate
                DB::table('overtimes_payroll')
                    ->whereBetween('OVERTIME_DATE', [$periodStart, $periodEnd])
                    ->delete();

                // Insert ulang dari tabel overtimes
                DB::table('overtimes_payroll')->insertUsing(
                    [
                        'NPK',
                        'NAMA_KARYAWAN',
                        'BAGIAN',
                        'OVERTIME_DATE',
                        'JUMLAH_JAM_LEMBUR',
                        'created_at',
                        'updated_at',
                        'DAY',
                        'DEPT_GROUP',
                    ],
                    DB::table('overtimes')
                        ->select(
                            'NPK',
                            'NAMA_KARYAWAN',
                            'BAGIAN',
                            'OVERTIME_DATE',
                            'JUMLAH_JAM_LEMBUR',
                            'created_at',
                            'updated_at',
                            'DAY',
                            'DEPT_GROUP',
                        )
                        ->whereBetween('OVERTIME_DATE', [$periodStart, $periodEnd])
                );
            });
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
        $violationsCount,
        $employeeViolations
    ) {

        $jumlahLine = max($jumlahLine, 1);

        $formula = Cache::remember(
            "insentif_formula_{$dept}_{$role}",
            300,
            function () use ($role, $dept) {

                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', $dept)
                    ->value('formula');
            }
        );

        if (!$formula) {
            return $totalLineInsentif;
        }

        $variables = [
            'totalLineInsentif' => $totalLineInsentif,
            'jumlahLine'        => $jumlahLine,
            'violationsCount'   => $violationsCount ?? 0,
            'violation_percentage' => $employeeViolations ?? 0
        ];

        foreach ($variables as $key => $value) {
            $formula = str_replace($key, $value, $formula);
        }

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

        $formula = Cache::remember(
            "insentif_formula_{$dept}_{$role}",
            300,
            function () use ($role, $dept) {

                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', $dept)
                    ->value('formula');
            }
        );

        if (!$formula) {
            return $totalDeptInsentif;
        }

        $variables = [
            'totalDeptInsentif' => $totalDeptInsentif,
            'jumlahOperator'    => $jumlahOperator,
        ];

        foreach ($variables as $key => $value) {
            $formula = str_replace($key, $value, $formula);
        }

        try {

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

        $formula = Cache::remember(
            "insentif_formula_{$dept}_{$role}",
            300,
            function () use ($role, $dept) {

                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', $dept)
                    ->value('formula');
            }
        );

        if (!$formula) {
            return $totalDeptInsentif;
        }

        $variables = [
            'totalDeptInsentif' => $totalDeptInsentif,
            'jumlahOperator'    => $jumlahOperator,
        ];

        foreach ($variables as $key => $value) {
            $formula = str_replace($key, $value, $formula);
        }

        try {

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

        $formula = Cache::remember(
            "insentif_formula_{$dept}_{$role}",
            300,
            function () use ($role, $dept) {

                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', $dept)
                    ->value('formula');
            }
        );

        if (!$formula) {
            return $insentif;
        }

        $variables = [
            'insentif' => $insentif,
        ];

        foreach ($variables as $key => $value) {
            $formula = str_replace($key, $value, $formula);
        }

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
