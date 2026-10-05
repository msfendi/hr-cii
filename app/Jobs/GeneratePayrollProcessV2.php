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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Services\PayrollRoleFilterService;

class GeneratePayrollProcessV2 implements ShouldQueue
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
            // ->where('p.NPK', '=', 'C-00043')

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
            // ->where('ec1.npk', '=', 'C-00043')

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

        /*
        |--------------------------------------------------------------------------
        | ATTENDANCE CALC (SUMBER BARU UNTUK overtime_hours & special_overtime_hours
        | DAN JUGA DIPAKAI ULANG UNTUK late_minute)
        |--------------------------------------------------------------------------
        | absence_days, absence_status, sick_days TETAP dari table `overtimes`
        | (tidak berubah). Yang berubah sumbernya hanya overtime_hours,
        | special_overtime_hours (di bawah), dan late_minute (di section LATE).
        |
        | Langkah:
        | 1. pin_map  : BARCODE (BIODATA + BIODATA_KELUAR) -> NPK, dipakai untuk
        |               mencocokkan att_log.pin.
        | 2. shift_days : shift resmi karyawan per tanggal dari employee_shifts
        |               + shifts (work_start/work_end).
        | 3. scan_days  : tanggal kerja dari att_log untuk NPK yang TIDAK punya
        |               baris di employee_shifts pada tanggal tsb -> dipakai
        |               jam default 08:00-17:00.
        | 4. windows    : gabungan shift_days + scan_days (default), lalu hitung
        |               shift_start_dt & shift_end_dt sebagai DATETIME penuh.
        |               Untuk shift yang melewati tengah malam (work_end <=
        |               work_start, mis. CUTTING SHIFT 18:30 -> 03:30),
        |               shift_end_dt didorong ke hari berikutnya, tapi
        |               attendance_date tetap hari dia MULAI shift (sesuai
        |               instruksi: tanggal 4 jam 18:30 s/d tanggal 5 jam 03:30
        |               tetap terhitung presensi tanggal 4).
        | 5. OUTER APPLY mencari scan att_log TERDEKAT (dalam window +/-6 jam)
        |               dari shift_start_dt (jam masuk) dan shift_end_dt
        |               (jam pulang).
        |
        | CATATAN / ASUMSI yang perlu diverifikasi ke data asli:
        | - Window pencarian scan terdekat memakai +/-6 jam dari jam shift.
        |   Sesuaikan angka ini (lihat DATEADD(HOUR, -6/6, ...) di bawah) jika
        |   ada karyawan yang biasa datang/pulang lebih dari 6 jam dari jadwal.
        | - att_log diasumsikan berada di connection & database yang sama
        |   dengan employee_shifts/shifts/overtimes (connection 'cii'), sama
        |   seperti tabel-tabel lain yang sudah dipakai di file ini.
        | - Kolom yang dipakai dari att_log hanya `pin` dan `scan_date`.
        |--------------------------------------------------------------------------
        */

        $defaultWorkStart = '08:00:00';
        $defaultWorkEnd   = '17:00:00';
        $periodStartStr   = $periodStart->toDateString();
        $periodEndStr     = $periodEnd->toDateString();

        // NOTE: SQL Server tidak mengizinkan "WITH ... AS (...)" (CTE) dipakai
        // sebagai derived table di dalam subquery lain, mis. "FROM (WITH x AS
        // (...) SELECT ...) alias" -> "Incorrect syntax near 'WITH'". Karena
        // $attendanceCalcSql di bawah akan selalu dipakai sebagai subquery
        // (langsung, maupun bersarang lagi di dalam $overtimeMergedSql), maka
        // seluruh CTE ditulis ulang sebagai nested derived table biasa
        // (SELECT ... FROM (SELECT ...) alias), bukan WITH.

        $pinMapSql = "
            SELECT NPK, CAST(BARCODE AS VARCHAR(50)) AS BARCODE FROM BIODATA
            UNION ALL
            SELECT NPK, CAST(BARCODE AS VARCHAR(50)) AS BARCODE FROM BIODATA_KELUAR
        ";

        $shiftDaysSql = "
            SELECT
                es.npk AS NPK,
                CAST(es.shift_date AS DATE) AS attendance_date,
                CAST(s.work_start AS TIME) AS work_start,
                CAST(s.work_end AS TIME) AS work_end
            FROM employee_shifts es
            INNER JOIN shifts s ON s.id = es.shift_id
            WHERE CAST(es.shift_date AS DATE) BETWEEN '{$periodStartStr}' AND '{$periodEndStr}'
        ";

        $scanDaysSql = "
            SELECT DISTINCT
                pm.NPK,
                CAST(al.scan_date AS DATE) AS attendance_date
            FROM att_log al
            INNER JOIN ({$pinMapSql}) pm ON pm.BARCODE = CAST(al.pin AS VARCHAR(50))
            WHERE CAST(al.scan_date AS DATE) BETWEEN '{$periodStartStr}' AND '{$periodEndStr}'
        ";

        $attendanceDaysSql = "
            SELECT NPK, attendance_date, work_start, work_end
            FROM ({$shiftDaysSql}) shift_days
            UNION
            SELECT sd.NPK, sd.attendance_date,
                   CAST('{$defaultWorkStart}' AS TIME) AS work_start,
                   CAST('{$defaultWorkEnd}' AS TIME) AS work_end
            FROM ({$scanDaysSql}) sd
            WHERE NOT EXISTS (
                SELECT 1 FROM ({$shiftDaysSql}) x
                WHERE x.NPK = sd.NPK AND x.attendance_date = sd.attendance_date
            )
        ";

        $windowsSql = "
            SELECT
                ad.NPK,
                pm.BARCODE AS pin,
                ad.attendance_date,
                ad.work_start,
                ad.work_end,
                CAST(ad.attendance_date AS DATETIME) + CAST(ad.work_start AS DATETIME) AS shift_start_dt,
                CASE
                    WHEN ad.work_end <= ad.work_start
                        THEN DATEADD(DAY, 1, CAST(ad.attendance_date AS DATETIME)) + CAST(ad.work_end AS DATETIME)
                    ELSE CAST(ad.attendance_date AS DATETIME) + CAST(ad.work_end AS DATETIME)
                END AS shift_end_dt
            FROM ({$attendanceDaysSql}) ad
            INNER JOIN ({$pinMapSql}) pm ON pm.NPK = ad.NPK
        ";

        $attendanceCalcSql = "
            SELECT
                w.NPK,
                w.attendance_date,
                CASE WHEN DATEDIFF(DAY, '19000101', w.attendance_date) % 7 IN (5,6) THEN 1 ELSE 0 END AS is_weekend,
                w.work_start,
                w.work_end,
                ci.scan_date AS check_in,
                co.scan_date AS check_out,
                -- LATE: allowance 5 menit dari work_start
                CASE
                    WHEN ci.scan_date IS NULL THEN 0
                    WHEN DATEDIFF(MINUTE, DATEADD(MINUTE, 5, w.shift_start_dt), ci.scan_date) < 0 THEN 0
                    ELSE DATEDIFF(MINUTE, DATEADD(MINUTE, 5, w.shift_start_dt), ci.scan_date)
                END AS late_minute,
                -- OVERTIME: jeda 30 menit dari work_end, dibulatkan ke bawah per jam
                CASE
                    WHEN co.scan_date IS NULL THEN 0
                    WHEN DATEDIFF(MINUTE, DATEADD(MINUTE, 30, w.shift_end_dt), co.scan_date) <= 0 THEN 0
                    ELSE FLOOR(DATEDIFF(MINUTE, DATEADD(MINUTE, 30, w.shift_end_dt), co.scan_date) / 60.0)
                END AS overtime_hours,
                -- SPECIAL OVERTIME (weekend/holiday): total jam kerja - 1 jam istirahat, dibulatkan ke bawah
                CASE
                    WHEN ci.scan_date IS NULL OR co.scan_date IS NULL THEN 0
                    WHEN DATEDIFF(MINUTE, ci.scan_date, co.scan_date) - 60 <= 0 THEN 0
                    ELSE FLOOR((DATEDIFF(MINUTE, ci.scan_date, co.scan_date) - 60) / 60.0)
                END AS special_overtime_hours
            FROM ({$windowsSql}) w
            OUTER APPLY (
                SELECT TOP 1 al.scan_date
                FROM att_log al
                WHERE al.pin = w.pin
                  AND al.scan_date BETWEEN DATEADD(HOUR, -6, w.shift_start_dt) AND DATEADD(HOUR, 6, w.shift_start_dt)
                ORDER BY ABS(DATEDIFF(SECOND, al.scan_date, w.shift_start_dt))
            ) ci
            OUTER APPLY (
                SELECT TOP 1 al2.scan_date
                FROM att_log al2
                WHERE al2.pin = w.pin
                  AND al2.scan_date BETWEEN DATEADD(HOUR, -6, w.shift_end_dt) AND DATEADD(HOUR, 6, w.shift_end_dt)
                ORDER BY ABS(DATEDIFF(SECOND, al2.scan_date, w.shift_end_dt))
            ) co
        ";

        /*
        |--------------------------------------------------------------------------
        | OVERTIME MERGED (FULL JOIN attendance calc <-> overtimes)
        |--------------------------------------------------------------------------
        | FULL JOIN diperlukan karena:
        | - Hari dengan absensi (att_log ada) tapi TIDAK ada baris di `overtimes`
        |   -> overtime_hours/special_overtime_hours tetap harus muncul.
        | - Hari TANPA absensi tapi ADA baris `overtimes` berupa kode izin/sakit
        |   (H, MA, P1, BR, OUT, SD) -> absence_days/absence_status/sick_days
        |   tetap harus muncul walau tidak ada att_log di hari itu.
        |--------------------------------------------------------------------------
        */
        // NOTE: BR & OUT TIDAK LAGI dihitung dari `overtimes` di sini (dihitung
        // otomatis dari tanggal TMK/TKK di foreach($employees) memakai table
        // PKWT). H/MA/P1/SD hanya valid (dihitung) jika tanggalnya berada di
        // antara TMK - TKK karyawan (data di luar rentang itu, mis. MA/P1
        // setelah TKK atau sebelum TMK, dianggap tidak valid dan tidak
        // dihitung ke absence_days/sick_days -- tetap ditampilkan apa adanya
        // di absence_status sebagai referensi/audit trail).
        $pkwtActiveSql = "
            SELECT p.NPK, p.TMK, p.TKK
            FROM PKWT p
            WHERE p.TMK <= '{$periodEndStr}'
              AND (p.TKK IS NULL OR p.TKK >= '{$periodStartStr}')
              AND p.NPK <> 'C-00017'
        ";

        $overtimeMergedSql = <<<SQL
SELECT
    COALESCE(ac.NPK, o.NPK) AS NPK,
    COALESCE(ac.attendance_date, CAST(o.OVERTIME_DATE AS DATE)) AS OVERTIME_DATE,
    ac.is_weekend,
    ac.work_start,
    ac.work_end,
    ac.check_in,
    ac.check_out,
    ac.late_minute,
    ac.overtime_hours,
    ac.special_overtime_hours,
    CASE
        WHEN UPPER(LTRIM(RTRIM(o.JUMLAH_JAM_LEMBUR))) = 'H'
            AND CAST(o.OVERTIME_DATE AS DATE) >= CAST(pk.TMK AS DATE)
            AND (pk.TKK IS NULL OR CAST(o.OVERTIME_DATE AS DATE) <= CAST(pk.TKK AS DATE))
            THEN 0.5
        WHEN o.JUMLAH_JAM_LEMBUR IS NOT NULL
            AND TRY_CAST(o.JUMLAH_JAM_LEMBUR AS FLOAT) IS NULL
            AND UPPER(LTRIM(RTRIM(o.JUMLAH_JAM_LEMBUR))) IN ('MA','P1','HD')
            AND CAST(o.OVERTIME_DATE AS DATE) >= CAST(pk.TMK AS DATE)
            AND (pk.TKK IS NULL OR CAST(o.OVERTIME_DATE AS DATE) <= CAST(pk.TKK AS DATE))
            THEN 1
        ELSE 0
    END AS absence_days,
    CASE
        WHEN UPPER(LTRIM(RTRIM(o.JUMLAH_JAM_LEMBUR))) = 'H' THEN o.JUMLAH_JAM_LEMBUR
        WHEN o.JUMLAH_JAM_LEMBUR IS NOT NULL
            AND TRY_CAST(o.JUMLAH_JAM_LEMBUR AS FLOAT) IS NULL
            AND UPPER(LTRIM(RTRIM(o.JUMLAH_JAM_LEMBUR))) IN ('MA','P1','HD','H','BR','OUT','SD')
            THEN o.JUMLAH_JAM_LEMBUR
    END AS absence_status,
    CASE
        WHEN o.JUMLAH_JAM_LEMBUR IS NOT NULL
            AND TRY_CAST(o.JUMLAH_JAM_LEMBUR AS FLOAT) IS NULL
            AND UPPER(LTRIM(RTRIM(o.JUMLAH_JAM_LEMBUR))) = 'SD'
            AND CAST(o.OVERTIME_DATE AS DATE) >= CAST(pk.TMK AS DATE)
            AND (pk.TKK IS NULL OR CAST(o.OVERTIME_DATE AS DATE) <= CAST(pk.TKK AS DATE))
            THEN 1
        ELSE 0
    END AS sick_days
FROM ({$attendanceCalcSql}) ac
FULL OUTER JOIN (
    SELECT * FROM overtimes
    WHERE OVERTIME_DATE BETWEEN '{$periodStartStr}' AND '{$periodEndStr}'
) o
    ON o.NPK = ac.NPK
   AND CAST(o.OVERTIME_DATE AS DATE) = ac.attendance_date
LEFT JOIN ({$pkwtActiveSql}) pk
    ON pk.NPK = COALESCE(ac.NPK, o.NPK)
SQL;

        $overtimeBase = DB::connection('cii')
            ->table(DB::raw("({$overtimeMergedSql}) as base"));

        $overtimeSummary = (clone $overtimeBase)
            ->select(
                'base.NPK',
                DB::raw('SUM(base.absence_days) as absence_days'),
                DB::raw('SUM(base.sick_days) as sick_days')
            )
            ->groupBy('base.NPK');

        $overtimeDetails = (clone $overtimeBase)
            ->leftJoinSub($latestContract, 'ec', function ($join) {
                $join->on('base.NPK', '=', 'ec.npk');
            })
            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('base.NPK', '=', 'bio.NPK');
            })
            ->leftJoin('DEPT as d', 'bio.ID_DEPT', '=', 'd.ID_DEPT')
            ->leftJoin('holidays as h', function ($join) {
                $join->on(
                    DB::raw('CAST(base.OVERTIME_DATE AS DATE)'),
                    '=',
                    DB::raw('CAST(h.holiday_date AS DATE)')
                );
            })
            ->select(
                'base.NPK',
                'bio.NAMA_KARYAWAN',
                'd.DEPARTEMENT',
                'base.OVERTIME_DATE',
                'h.name as holiday_name',
                'h.is_national',
                'base.check_in',
                'base.check_out',
                'base.work_start',
                'base.work_end',

                DB::raw("
            CASE
                WHEN base.is_weekend = 0 AND h.holiday_date IS NULL
                THEN COALESCE(base.overtime_hours, 0)
                ELSE 0
            END AS overtime_hours
        "),

                DB::raw("
CASE
    WHEN base.is_weekend = 1 OR h.holiday_date IS NOT NULL
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
                    WHEN COALESCE(base.special_overtime_hours,0) > 8
                    THEN 8
                    ELSE COALESCE(base.special_overtime_hours,0)
                END

            ELSE
                COALESCE(base.special_overtime_hours,0)

        END

    ELSE 0
END AS special_overtime_hours
"),

                'base.absence_days',
                'base.absence_status'
            )
            ->orderBy('base.NPK')
            ->orderBy('base.OVERTIME_DATE')
            ->get()
            ->groupBy('NPK');

        /*
        |--------------------------------------------------------------------------
        | NOLKAN overtime_hours / special_overtime_hours JIKA ADA IJIN YANG BELUM
        | KEMBALI (ijin_meninggalkan_pekerjaans.jam_kembali = NULL) DI NPK &
        | TANGGAL YANG SAMA
        |--------------------------------------------------------------------------
        | Di V2, overtime_hours & special_overtime_hours dihitung dari selisih
        | check-in/check-out di att_log (bukan langsung dari JUMLAH_JAM_LEMBUR
        | yang numerik seperti di V1). Tapi kalau pada NPK & tanggal yang sama
        | karyawan tsb punya baris ijin_meninggalkan_pekerjaans dengan
        | jam_kembali masih NULL (belum kembali dari ijin), maka jam kerja hasil
        | scan di tanggal itu tidak bisa dipercaya untuk dihitung sebagai lembur
        | -> overtime_hours & special_overtime_hours dinolkan di sini
        | (post-process di PHP karena ijin_meninggalkan_pekerjaans ada di
        | connection DB yang berbeda dari `overtimes`/`att_log`/`cii`).
        |--------------------------------------------------------------------------
        */
        $openIjinDates = DB::table('ijin_meninggalkan_pekerjaans')
            ->whereBetween('tanggal', [$periodStart, $periodEnd])
            ->whereNull('jam_kembali')
            ->select('npk', DB::raw('CAST(tanggal AS DATE) as tanggal'))
            ->get()
            ->map(function ($row) {
                return $row->npk . '|' . Carbon::parse($row->tanggal)->format('Y-m-d');
            })
            ->flip();

        $overtimeDetails = $overtimeDetails->map(function ($rows) use ($openIjinDates) {
            return $rows->map(function ($ot) use ($openIjinDates) {
                $key = $ot->NPK . '|' . Carbon::parse($ot->OVERTIME_DATE)->format('Y-m-d');

                if (isset($openIjinDates[$key])) {
                    $ot->overtime_hours = 0;
                    $ot->special_overtime_hours = 0;
                }

                return $ot;
            });
        });

        // SUMMARY LATE

        if (!$isCheck) {
            $run->update([
                'status' => 'Calculating Late Minutes',
                'progress' => 25,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | LATE DETAILS & SUMMARY (SUMBER BARU: att_log, konsep sama seperti
        | overtime di atas -- pakai $attendanceCalcSql yang sama)
        |--------------------------------------------------------------------------
        | Late dihitung dari check_in (att_log) terhadap work_start shift
        | masing-masing (allowance 5 menit), BUKAN lagi dari employee_lates.
        | late_compensations tetap dihormati (jika ada kompensasi utk
        | NPK+tanggal tsb, late dianggap 0), sama seperti versi sebelumnya.
        |
        | CATATAN: kolom `reason` sebelumnya berasal dari employee_lates.reason.
        | att_log tidak punya kolom alasan keterlambatan, jadi kolom ini
        | sekarang selalu NULL. Jika masih dibutuhkan di UI, perlu sumber lain.
        |--------------------------------------------------------------------------
        */
        $lateDetails = DB::connection('cii')
            ->table(DB::raw("({$attendanceCalcSql}) as ac"))

            ->leftJoinSub($employeeBase, 'emp', function ($join) {
                $join->on('ac.NPK', '=', 'emp.NPK');
            })

            ->leftJoin('late_compensations as lc', function ($join) {
                $join->on('ac.NPK', '=', 'lc.npk')
                    ->whereRaw('CAST(lc.date AS DATE) = ac.attendance_date');
            })

            ->selectRaw("
                ac.NPK as NPK,
                emp.NAMA_KARYAWAN,
                emp.DEPARTEMENT,
                CAST(emp.BARCODE AS VARCHAR(50)) as pin,
                ac.attendance_date as scan_day,
                ac.work_start,
                ac.work_end,
                ac.check_in as first_scan,
                CAST(NULL AS VARCHAR(255)) as reason,
                CASE
                    WHEN lc.id IS NOT NULL THEN 0
                    ELSE COALESCE(ac.late_minute, 0)
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
        calc.late_actual as late_minute
    ")
            ->orderBy(DB::raw('CAST(calc.scan_day AS DATE)'))
            ->get()
            ->groupBy('NPK');

        $lateSummary =
            DB::connection('cii')
            ->table(DB::raw("({$attendanceCalcSql}) as ac"))

            ->leftJoin('late_compensations as lc', function ($join) {
                $join->on('ac.NPK', '=', 'lc.npk')
                    ->whereRaw('CAST(lc.date AS DATE) = ac.attendance_date');
            })

            ->selectRaw("
                ac.NPK as npk,
                CASE
                    WHEN lc.id IS NOT NULL THEN 0
                    ELSE COALESCE(ac.late_minute, 0)
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
        SUM(calc.raw_late_minute) as late_minutes,
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
            ->leftJoin('employee_shifts as ijes', function ($join) {
                $join->on('ijes.npk', '=', 'imp.npk')
                    ->on(
                        DB::raw('CAST(ijes.shift_date AS DATE)'),
                        '=',
                        DB::raw('CAST(imp.tanggal AS DATE)')
                    );
            })
            ->leftJoin('shifts as ijs', function ($join) {
                $join->on('ijes.shift_id', '=', 'ijs.id')
                    ->whereNotNull('ijes.shift_id');
            })
            ->selectRaw("
        imp.npk,

        SUM(
            DATEDIFF(
                MINUTE,
                imp.jam_keluar,
                COALESCE(imp.jam_kembali, CAST(ijs.work_end AS TIME), '17:00:00')
            )
            -
            CASE
                WHEN imp.jam_keluar < bm.time_end
                 AND COALESCE(imp.jam_kembali, CAST(ijs.work_end AS TIME), '17:00:00') > bm.time_start
                THEN
                    DATEDIFF(
                        MINUTE,
                        CASE
                            WHEN imp.jam_keluar > bm.time_start
                                THEN imp.jam_keluar
                            ELSE bm.time_start
                        END,
                        CASE
                            WHEN COALESCE(imp.jam_kembali, CAST(ijs.work_end AS TIME), '17:00:00') < bm.time_end
                                THEN COALESCE(imp.jam_kembali, CAST(ijs.work_end AS TIME), '17:00:00')
                            ELSE bm.time_end
                        END
                    )
                ELSE 0
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
            ->leftJoin('employee_shifts as ijdes', function ($join) {
                $join->on('ijdes.npk', '=', 'imp.npk')
                    ->on(
                        DB::raw('CAST(ijdes.shift_date AS DATE)'),
                        '=',
                        DB::raw('CAST(imp.tanggal AS DATE)')
                    );
            })
            ->leftJoin('shifts as ijds', function ($join) {
                $join->on('ijdes.shift_id', '=', 'ijds.id')
                    ->whereNotNull('ijdes.shift_id');
            })
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

        DATEDIFF(
            MINUTE,
            imp.jam_keluar,
            COALESCE(imp.jam_kembali, CAST(ijds.work_end AS TIME), '17:00:00')
        )
        -
        CASE
            WHEN imp.jam_keluar < bm.time_end
             AND COALESCE(imp.jam_kembali, CAST(ijds.work_end AS TIME), '17:00:00') > bm.time_start
            THEN
                DATEDIFF(
                    MINUTE,
                    CASE
                        WHEN imp.jam_keluar > bm.time_start
                            THEN imp.jam_keluar
                        ELSE bm.time_start
                    END,
                    CASE
                        WHEN COALESCE(imp.jam_kembali, CAST(ijds.work_end AS TIME), '17:00:00') < bm.time_end
                            THEN COALESCE(imp.jam_kembali, CAST(ijds.work_end AS TIME), '17:00:00')
                        ELSE bm.time_end
                    END
                )
            ELSE 0
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

        $qcInsentifComponent = PayrollComponent::where('code', 'qc_insentif')->first();
        $qcInsentifFormula = json_decode($qcInsentifComponent->formula, true);

        // Total potongan QC violation untuk periode berjalan (tabel qc_violations,
        // kolom percentage). Dikirim ke formula role QC sebagai variable
        // `qc_violation`, mis. 50 => ((100-qc_violation)/100) = 0.5.
        // Beberapa baris pada periode yang sama dijumlahkan, dibatasi 0-100.
        $qcViolationPercentage = min(100, max(0, (float) DB::table('qc_violations')
            ->where('period_id', $period->id)
            ->sum('percentage')));

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
            // dari overtimes (sudah difilter di rentang TMK-TKK pada
            // $overtimeMergedSql). BR & OUT otomatis ditambahkan di sini.
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

                                // jumlahLine = jumlah line DISTINCT (dalam range section) yang muncul pada
                                // tanggal penugasan karyawan di employee_line_assignments (hanya tanggal
                                // yang benar-benar dihitung: lolos TKK & validasi overtime), BUKAN seluruh
                                // line di section selama satu periode. Dengan begitu chief/mekanik/
                                // mekanik_leader/section_head yang hanya bertugas sebagian tanggal dihitung
                                // proporsional sesuai tanggal assignment-nya.
                                $assignedValidDates = [];
                                foreach ($grouped as $assignedDay) {
                                    if ($tkkDate && $assignedDay->date >= $tkkDate) {
                                        continue;
                                    }
                                    if (!$isValidOvertime($assignedDay->date)) {
                                        continue;
                                    }
                                    $assignedValidDates[] = $assignedDay->date;
                                }

                                $jumlahLineCount = empty($assignedValidDates)
                                    ? 0
                                    : (int) DB::table('line_efficiencies')
                                        ->where('period_id', $period->id)
                                        ->whereIn('date', $assignedValidDates)
                                        ->whereBetween('line_number', [$lineStart, $lineEnd])
                                        ->distinct()
                                        ->count('line_number');

                                // dibungkus agar pemakaian $jumlahLine->first()->jumlah_line di bawah tetap valid
                                $jumlahLine = collect([(object) ['jumlah_line' => $jumlahLineCount]]);

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
                    } else if ($component->code === 'qc_insentif') {
                        $assignmentNpk = DB::table('employee_qc_assignments as ela')
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
                            ->where('insentif_type', 'QC')
                            ->where('type', 'Percentage')
                            ->pluck('minimum', 'days');

                        $getMinEfficiency = function ($dayIndex) use ($thresholds) {

                            if (isset($thresholds[$dayIndex])) {
                                return $thresholds[$dayIndex];
                            }

                            return $thresholds->max();
                        };

                        // Validasi overtime pakai data yang sudah di-prefetch
                        // sebelum loop (lihat $isValidOvertimeFor).
                        $isValidOvertime = function ($date) use ($employee, $isValidOvertimeFor) {
                            return $isValidOvertimeFor($employee->NPK, $date);
                        };

                        /*
                        |----------------------------------------------------
                        | OPERATOR (INLINE / ENDLINE / FQC)
                        |----------------------------------------------------
                        | SPV dipindahkan ke cabang CHIEF/QA (section-based)
                        | karena formula QC SPV = (Total QC Incentive 1 Section
                        | / Total Line) * 50%, bukan per-line seperti Operator.
                        |----------------------------------------------------
                        */
                        $lineViolations = 0;
                        foreach ($assignmentNpk as $assignment) {
                            if (empty($assignment->role)) {
                                continue;
                            }
                            if (in_array(strtolower((string) $assignment->role), ['inline', 'endline', 'fqc'], true)) {

                                preg_match('/\d+/', $employee->DEPARTEMENT, $matches);
                                $defaultLine = $matches[0] ?? null;

                                $lineefficiencies = DB::table('employee_qc_assignments as ela')
                                    ->leftJoin('qc_efficiencies as le', function ($join) {
                                        $join->on('le.period_id', '=', 'ela.period_id')
                                            ->on('le.line_number', '=', 'ela.line_number')
                                            ->on('le.date', '=', 'ela.start_date')
                                            ->where('le.dept', '=', 'qc');
                                    })

                                    ->leftJoinSub(
                                        DB::table('employee_qc_assignments')
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
                                    // FIX ROLE GANDA: hanya assignment milik role ini
                                    ->where('ela.role', $assignment->role)
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

                                // NOTE: reuses sewing_violations (sama seperti Line Insentif)
                                // karena belum ada tabel violations khusus QC.
                                // Cabang ini hanya menangani role operator QC
                                // (inline, endline, fqc). SPV ada di cabang CHIEF/QA di bawah.
                                $lineViolations = ($sewingViolationsByDept[$employee->ID_DEPT] ?? collect())->count();

                                foreach ($lineefficiencies as $row) {

                                    if ($tkkDate && $row->date >= $tkkDate) {
                                        continue;
                                    }

                                    if (!$isValidOvertime($row->date)) {
                                        continue;
                                    }

                                    // Cutoff di luar tier tertinggi sudah ditangani oleh
                                    // getInsentifByDefectRate() sendiri (return 0 jika
                                    // efficiency melebihi threshold terbesar), sama
                                    // seperti QcInsentifMasterController::calculateQc().
                                    $lineInsentif =
                                        $this->getInsentifByDefectRate($row->efficiency, $qcInsentifFormula) * $row->work_hours / $row->max_work_hours;

                                    $amount += $this->calculateRoleQcInsentif(
                                        $assignment->role,
                                        'qc',
                                        $lineInsentif,
                                        1,
                                        $lineViolations,
                                        $employee->violation_percentage,
                                        $qcViolationPercentage
                                    );
                                }
                            } else {

                                /*
                                |--------------------------------------------
                                | CHIEF / QA / SPV (SECTION-BASED)
                                |--------------------------------------------
                                | SPV masuk ke sini karena formula QC SPV
                                | berbasis Total QC Incentive dalam 1 Section
                                | dibagi Total Line (sama seperti Chief & QA).
                                |--------------------------------------------
                                */
                                $validRoles = ['chief', 'qa', 'qa_leader', 'spv'];

                                if (!in_array($assignment->role, $validRoles)) {
                                    continue;
                                }

                                if (in_array($assignment->role, ['qa', 'qa_leader'], true)) {

                                    /*
                                    |--------------------------------------------------------------------------
                                    | QA / QA LEADER (sama dengan QcInsentifMasterController)
                                    |--------------------------------------------------------------------------
                                    | Sumber: employee_qc_assignments dengan line_number NULL, DISTINCT per
                                    | tanggal + buyer + third_party.
                                    | - third_party terisi : qc_efficiencies tanggal + buyer + third_party tsb.
                                    | - third_party kosong : qc_efficiencies tanggal + buyer saja.
                                    |     * NPK yang memegang third_party di buyer yang sama => assignment
                                    |       kosong ikut third_party tsb (tidak dobel).
                                    |     * Buyer yang punya baris third party (MUJI): hanya baris line_number
                                    |       NULL (PQC + TENTAC).
                                    |     * Buyer tanpa third party (GAP, SUKO): baris buyer di-distinct per
                                    |       tanggal (1 defect rate per buyer).
                                    | Nominal per entry = TOTAL insentif baris distinct x faktor role
                                    | (qa 5/10, qa_leader 7/10) x potongan qc_violation PER BUYER.
                                    | QA LEADER dibagi jumlah third_party di tanggal tsb.
                                    |--------------------------------------------------------------------------
                                    */
                                    $qaRawAll = DB::table('employee_qc_assignments')
                                        ->where('npk', $employee->NPK)
                                        ->where('period_id', $period->id)
                                        ->where('role', $assignment->role)
                                        ->whereNull('line_number') // QA / QA leader: hanya assignment tanpa line_number
                                        ->whereBetween('start_date', [
                                            $period->start_date,
                                            $period->end_date
                                        ])
                                        ->select('start_date as date', 'buyer', 'third_party')
                                        ->orderBy('start_date')
                                        ->get()
                                        ->map(function ($row) {
                                            $thirdParty = trim((string) ($row->third_party ?? ''));
                                            $row->date        = substr((string) $row->date, 0, 10);
                                            $row->third_party = $thirdParty !== '' ? $thirdParty : null;
                                            $row->buyer       = trim((string) ($row->buyer ?? ''));

                                            return $row;
                                        })
                                        ->values();

                                    // FIX BUYER KOSONG: assignment tanpa buyer = berlaku untuk SEMUA buyer yang punya
                                    // data qc_efficiencies (dept qa) pada tanggal tsb (termasuk SUKO, GAP, dst).
                                    // Insentif tiap buyer dihitung terpisah lalu diakumulasi. Untuk buyer yang BUKAN
                                    // assignment eksplisit NPK ini, nominal dibagi jumlah staf QA/QA leader yang
                                    // memegang buyer itu pada tanggal tsb (staf lain + NPK ini).
                                    $qaBlank = $qaRawAll->filter(fn($row) => $row->buyer === '')->values();
                                    $qaRaw   = $qaRawAll->filter(fn($row) => $row->buyer !== '')->values();
                                    $qaShareByDateBuyer = [];

                                    if ($qaBlank->isNotEmpty()) {
                                        $qaBuyersByDate = DB::table('qc_efficiencies')
                                            ->where('period_id', $period->id)
                                            ->where('dept', 'qa')
                                            ->whereIn('date', $qaBlank->pluck('date')->unique()->values()->all())
                                            ->whereNotNull('buyer')
                                            ->where('buyer', '!=', '')
                                            ->select('date', 'buyer')
                                            ->get()
                                            ->groupBy(fn($r) => substr((string) $r->date, 0, 10))
                                            ->map(fn($rows) => $rows->pluck('buyer')
                                                ->map(fn($b) => trim((string) $b))
                                                ->unique(fn($b) => strtoupper($b))
                                                ->values());

                                        $qaOtherStaff = DB::table('employee_qc_assignments')
                                            ->where('period_id', $period->id)
                                            ->whereIn('role', ['qa', 'qa_leader'])
                                            ->where('npk', '!=', $employee->NPK)
                                            ->whereNull('line_number')
                                            ->whereNotNull('buyer')
                                            ->where('buyer', '!=', '')
                                            ->whereBetween('start_date', [$period->start_date, $period->end_date])
                                            ->select('npk', 'buyer', 'start_date')
                                            ->get()
                                            ->groupBy(fn($r) => substr((string) $r->start_date, 0, 10) . '|' . strtoupper(trim((string) $r->buyer)))
                                            ->map(fn($rows) => $rows->pluck('npk')->unique()->count());

                                        $qaThirdPartyCountByDateBuyer = DB::table('qc_efficiencies')
                                            ->where('period_id', $period->id)
                                            ->where('dept', 'qa')
                                            ->whereIn('date', $qaBlank->pluck('date')->unique()->values()->all())
                                            ->whereNotNull('third_party')
                                            ->where('third_party', '!=', '')
                                            ->select('date', 'buyer', 'third_party')
                                            ->get()
                                            ->groupBy(fn($r) => substr((string) $r->date, 0, 10) . '|' . strtoupper(trim((string) $r->buyer)))
                                            ->map(fn($rows) => $rows->pluck('third_party')
                                                ->map(fn($t) => strtoupper(trim((string) $t)))
                                                ->unique()
                                                ->count());

                                        $qaExpanded = collect();

                                        foreach ($qaBlank as $blankRow) {
                                            foreach ($qaBuyersByDate->get($blankRow->date, collect()) as $blankBuyer) {
                                                $shareKey = $blankRow->date . '|' . strtoupper($blankBuyer);

                                                $qaExpanded->push((object) [
                                                    'date'        => $blankRow->date,
                                                    'buyer'       => $blankBuyer,
                                                    'third_party' => null,
                                                    'expanded'    => true,
                                                ]);

                                                // QA LEADER memegang SELURUH buyer; buyer yang pecah >= 2 third_party pada tanggal tsb (MUJI: PQC + TENTAC)
                                                // sudah dibagi per third_party oleh staf QA masing-masing, jadi leader TIDAK dibagi jumlah staf.
                                                // Pembagi staf hanya untuk buyer tanpa pecahan third_party (SUKO, GAP, dst).
                                                $qaShareByDateBuyer[$shareKey] = ($assignment->role === 'qa_leader' && $qaThirdPartyCountByDateBuyer->get($shareKey, 0) >= 2)
                                                    ? 1
                                                    : $qaOtherStaff->get($shareKey, 0) + 1;
                                            }
                                        }

                                        // Assignment eksplisit ditaruh DULU supaya unique() mempertahankan baris eksplisit
                                        // (tidak dibagi) kalau buyer+tanggal yang sama juga muncul dari expand buyer kosong.
                                        $qaRaw = $qaRaw->concat($qaExpanded)->values();
                                    }


                                    // third_party yang dipegang NPK ini per buyer.
                                    $qaThirdPartyByBuyer = $qaRaw
                                        ->filter(fn($row) => $row->third_party !== null)
                                        ->groupBy(fn($row) => strtoupper($row->buyer))
                                        ->map(fn($rows) => $rows->pluck('third_party')
                                            ->unique(fn($tp) => strtoupper($tp))
                                            ->values());

                                    // Assignment kosong ikut third_party NPK (jika ada), lalu DISTINCT.
                                    $qaAssignments = $qaRaw
                                        ->flatMap(function ($row) use ($qaThirdPartyByBuyer) {
                                            if ($row->third_party !== null) {
                                                return [$row];
                                            }

                                            $tps = $qaThirdPartyByBuyer->get(strtoupper($row->buyer));

                                            if ($tps && $tps->isNotEmpty()) {
                                                return $tps->map(fn($tp) => (object) [
                                                    'date'        => $row->date,
                                                    'buyer'       => $row->buyer,
                                                    'third_party' => $tp,
                                                    'expanded'    => $row->expanded ?? false,
                                                ])->all();
                                            }

                                            return [$row];
                                        })
                                        ->unique(fn($row) => $row->date . '|' . strtoupper($row->buyer) . '|' . strtoupper((string) $row->third_party))
                                        ->values();

                                    if ($qaAssignments->isEmpty()) {
                                        continue;
                                    }

                                    // Semua qc_efficiencies yang dibutuhkan, sekali query.
                                    $qaEffByKey = DB::table('qc_efficiencies')
                                        ->where('period_id', $period->id)
                                        ->where('dept', 'qa')
                                        ->whereIn('date', $qaAssignments->pluck('date')->unique()->all())
                                        ->whereIn('buyer', $qaAssignments->pluck('buyer')->unique()->all())
                                        ->get()
                                        ->groupBy(fn($row) => substr((string) $row->date, 0, 10) . '|' . strtoupper(trim((string) $row->buyer)));

                                    // Buyer yang punya baris third party di periode ini (mis. MUJI).
                                    $qaBuyerHasThirdParty = DB::table('qc_efficiencies')
                                        ->where('period_id', $period->id)
                                        ->where('dept', 'qa')
                                        ->whereIn('buyer', $qaAssignments->pluck('buyer')->unique()->all())
                                        ->whereNotNull('third_party')
                                        ->where('third_party', '!=', '')
                                        ->pluck('buyer')
                                        ->mapWithKeys(fn($buyer) => [strtoupper(trim((string) $buyer)) => true]);

                                    $qaByDate = collect([]);
                                    $allLineNumbers = collect([]);

                                    foreach ($qaAssignments as $qaAssignment) {

                                        $buyerHasThirdParty = $qaBuyerHasThirdParty->has(strtoupper($qaAssignment->buyer));

                                        $effRows = $qaEffByKey->get(
                                            $qaAssignment->date . '|' . strtoupper($qaAssignment->buyer),
                                            collect()
                                        );

                                        $linesOfDay = $effRows
                                            ->filter(function ($row) use ($qaAssignment, $buyerHasThirdParty) {
                                                // third_party terisi => baris buyer + third_party tsb
                                                if ($qaAssignment->third_party !== null) {
                                                    return strcasecmp(trim((string) ($row->third_party ?? '')), $qaAssignment->third_party) === 0;
                                                }

                                                // third_party kosong, buyer punya third party (MUJI) => hanya line_number NULL
                                                if ($buyerHasThirdParty) {
                                                    return $row->line_number === null || $row->line_number === '';
                                                }

                                                // third_party kosong, buyer tanpa third party (GAP, SUKO) => semua baris buyer
                                                return true;
                                            })
                                            // DISTINCT per tanggal (+ third_party): 1 defect rate = 1 baris.
                                            ->unique(fn($row) => strtoupper(trim((string) ($row->third_party ?? ''))) . '|' . (float) $row->efficiency)
                                            ->values();

                                        if ($linesOfDay->isEmpty()) {
                                            continue;
                                        }

                                        $qaByDate->push((object) [
                                            'date'        => $qaAssignment->date,
                                            'buyer'       => $qaAssignment->buyer,
                                            'third_party' => $qaAssignment->third_party,
                                            'lines'       => $linesOfDay,
                                            'share'       => ($qaAssignment->expanded ?? false)
                                                ? ($qaShareByDateBuyer[$qaAssignment->date . '|' . strtoupper($qaAssignment->buyer)] ?? 1)
                                                : 1,
                                        ]);

                                        $allLineNumbers = $allLineNumbers->merge($linesOfDay->pluck('line_number')->filter(fn($n) => $n !== null));
                                    }

                                    if ($qaByDate->isEmpty()) {
                                        continue;
                                    }

                                    $allLineNumbers = $allLineNumbers->unique()->values()->all();

                                    // Jumlah third_party per tanggal (pembagi QA LEADER).
                                    $qaThirdPartyCountByDate = [];

                                    foreach ($qaByDate as $row) {
                                        if ($row->third_party !== null) {
                                            $qaThirdPartyCountByDate[(string) $row->date . '|' . strtoupper($row->buyer)][strtoupper($row->third_party)] = true;
                                        }
                                    }

                                    $collectionDay = collect([]);

                                    // Proses PER TANGGAL: 1 tanggal bisa punya >1 entry (beda third_party).
                                    foreach ($qaByDate->groupBy(fn($entry) => (string) $entry->date) as $entries) {

                                        $date = $entries->first()->date;

                                        if ($tkkDate && $date >= $tkkDate) {
                                            continue;
                                        }

                                        if (!$isValidOvertime($date)) {
                                            continue;
                                        }

                                        $dayAmount = 0;

                                        foreach ($entries as $entry) {

                                            $totalLineInsentif = 0;

                                            foreach ($entry->lines as $line) {
                                                // Cutoff di luar tier tertinggi sudah ditangani getInsentifByDefectRate().
                                                $totalLineInsentif +=
                                                    $this->getInsentifByDefectRate($line->efficiency, $qcInsentifFormula);
                                            }

                                            $entryAmount = $this->calculateQaQcInsentif(
                                                $assignment->role,
                                                $totalLineInsentif,
                                                $this->qcViolationPercentageByBuyer($period, $entry->buyer)
                                            );

                                            // FIX MULTI BUYER: QA LEADER dibagi jumlah third_party pada tanggal + buyer ENTRY ini
                                            // (bukan seluruh third_party di tanggal tsb), lalu hasil tiap buyer diakumulasi.
                                            if ($assignment->role === 'qa_leader') {
                                                $jumlahThirdParty = max(count($qaThirdPartyCountByDate[(string) $date . '|' . strtoupper($entry->buyer)] ?? []), 1);
                                                $entryAmount = $entryAmount / $jumlahThirdParty;
                                            }

                                            // FIX BUYER KOSONG: buyer hasil expand dibagi jumlah staf QA di buyer tsb
                                            $entryAmount = $entryAmount / max((int) ($entry->share ?? 1), 1);

                                            $dayAmount += $entryAmount;
                                        }


                                        $amount += $dayAmount;

                                        $collectionDay->push($amount);
                                    }
                                } else {

                                    $section = DB::table('sections')
                                        ->whereRaw('id = ?', [(int) $employee->SECTION])
                                        ->select('line_start', 'line_end', 'blank_line')
                                        ->first();

                                    if (!$section) {
                                        continue;
                                    }

                                    $lineStart = $section->line_start;
                                    $lineEnd   = $section->line_end;

                                    $grouped = DB::table('employee_qc_assignments as ela')
                                        ->join('qc_efficiencies as le', function ($join) {
                                            $join->on('le.period_id', '=', 'ela.period_id')
                                                ->on('le.date', '=', 'ela.start_date')
                                                ->where('le.dept', '=', 'qc');
                                        })

                                        ->where('ela.npk', $employee->NPK)
                                        ->where('ela.period_id', $period->id)
                                        // FIX ROLE GANDA: hanya tanggal assignment role ini (chief/spv)
                                        ->where('ela.role', $assignment->role)

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

                                    // NOTE: reuses sewing_violations (sama seperti Line Insentif)
                                    // karena belum ada tabel violations khusus QC.
                                    $lineViolations = $countSewingViolationsForLineRange($lineStart, $lineEnd);

                                    $collectionDay = collect([]);
                                    $collectionLines = collect([]);

                                    // jumlahLine = panjang range section (lineEnd -
                                    // lineStart + 1), sama seperti
                                    // QcInsentifMasterController::calculateQc() —
                                    // BUKAN COUNT(DISTINCT line_number) dari
                                    // qc_efficiencies (yang bisa lebih kecil dari
                                    // panjang range kalau ada line tanpa data).
                                    // Pembagi = jumlah line di range section dikurangi sections.blank_line
                                    // (mis. 67-78 => 12; blank 72 => 11; blank 72,74 => 10), sama seperti QcInsentifMasterController.
                                    $jumlahLine = $this->qcSectionLineCount($lineStart, $lineEnd, $section->blank_line ?? null);

                                    foreach ($grouped as $day) {

                                        if ($tkkDate && $day->date >= $tkkDate) {
                                            continue;
                                        }

                                        if (!$isValidOvertime($day->date)) {
                                            continue;
                                        }

                                        $lines = DB::table('qc_efficiencies')
                                            ->where('period_id', $period->id)
                                            ->where('dept', 'qc')
                                            ->where('date', $day->date)
                                            ->whereBetween('line_number', [$lineStart, $lineEnd])
                                            ->get();

                                        $totalLineInsentif = 0;

                                        foreach ($lines as $line) {

                                            // Cutoff di luar tier tertinggi sudah
                                            // ditangani oleh getInsentifByDefectRate()
                                            // sendiri, sama seperti controller.
                                            $totalLineInsentif +=
                                                $this->getInsentifByDefectRate($line->efficiency, $qcInsentifFormula);

                                            if ($totalLineInsentif <= 0) {
                                                continue;
                                            }

                                            $collectionLines->push($totalLineInsentif);
                                        }

                                        // CHIEF / SPV: (total insentif semua line section / jumlah line) x faktor role
                                        // (chief 7/10, spv 5/10), lalu dipotong qc_violation. Sama dengan controller.
                                        $amount += $this->calculateChiefSpvQcInsentif(
                                            $assignment->role,
                                            $totalLineInsentif,
                                            $jumlahLine,
                                            $qcViolationPercentage
                                        );

                                        $collectionDay->push($amount);
                                    }
                                }
                            }
                        }
                    } else if ($component->code === 'pad_insentif') {
                        $assignments = DB::table('pad_efficiencies')
                            ->where('npk', $employee->NPK)
                            ->where('period_id', $period->id)
                            ->whereBetween('date', [$period->start_date, $period->end_date])
                            ->get();

                        $amount = 0;

                        // NOTE: $mutations (employee_mutations) dihapus — dead code,
                        // tidak pernah dipakai di blok ini.

                        $tkkDate = !empty($employee->TKK)
                            ? Carbon::parse($employee->TKK)->format('Y-m-d')
                            : null;

                        $isValidOvertime = function ($npk, $date) use ($isValidOvertimeFor) {
                            return $isValidOvertimeFor($npk, $date);
                        };

                        /*
                        |----------------------------------------------------------------
                        | OPERATOR DAYS
                        |----------------------------------------------------------------
                        */
                        foreach ($assignments->where('role', 'operator') as $assignment) {

                            if ($tkkDate && $assignment->date >= $tkkDate) {
                                continue;
                            }

                            if (!$isValidOvertime($assignment->npk, $assignment->date)) {
                                continue;
                            }

                            $rate = $this->getInsentifByEfficiency(
                                $assignment->efficiency,
                                $padInsentifFormula
                            );

                            $amount += $rate * $assignment->piece;
                        }

                        /*
                        |----------------------------------------------------------------
                        | NON OPERATOR DAYS (SPV / LEADER / HELPER)
                        |----------------------------------------------------------------
                        | Dikelompokkan per role dulu (kalau role berubah di tengah
                        | periode), lalu per tanggal — supaya tanggal saat karyawan
                        | masih jadi operator TIDAK ikut masuk ke perhitungan non
                        | operator. Operator pembanding diambil PER TANGGAL, mengikuti
                        | kolom "tim" milik employee non operator itu sendiri pada
                        | tanggal tsb:
                        |   - tim = 1  -> hanya operator dengan tim = 1
                        |   - tim = 2  -> hanya operator dengan tim = 2
                        |   - tim null -> semua operator (tanpa filter tim)
                        |----------------------------------------------------------------
                        */
                        $nonOperatorAssignments = $assignments->filter(
                            fn($a) => !empty($a->role) && $a->role !== 'operator'
                        );

                        foreach ($nonOperatorAssignments->groupBy('role') as $role => $roleAssignments) {

                            $employeeAssignmentsByDate = $roleAssignments->groupBy('date');

                            $totalDeptInsentif = 0;
                            $operatorNpks = [];

                            foreach ($employeeAssignmentsByDate as $date => $rowsForDate) {

                                if ($tkkDate && $date >= $tkkDate) {
                                    continue;
                                }

                                $tim = $rowsForDate
                                    ->pluck('tim')
                                    ->filter(fn($t) => $t !== null && $t !== '')
                                    ->first();

                                $operatorQuery = DB::table('pad_efficiencies')
                                    ->where('period_id', $period->id)
                                    ->where('role', '=', 'operator')
                                    ->where('date', $date);

                                if (!is_null($tim)) {
                                    $operatorQuery->where('tim', $tim);
                                }

                                $operatorsForDate = $operatorQuery->get();

                                foreach ($operatorsForDate as $operator) {
                                    if (!$isValidOvertime($operator->npk, $operator->date)) {
                                        continue;
                                    }

                                    $rate = $this->getInsentifByEfficiency(
                                        $operator->efficiency,
                                        $padInsentifFormula
                                    );

                                    $totalDeptInsentif += $rate * $operator->piece;
                                    $operatorNpks[] = $operator->npk;
                                }
                            }

                            $jumlahOperator = collect($operatorNpks)->unique()->count();

                            $amount += $this->calculateRolePadInsentif(
                                $role,
                                'pad',
                                $totalDeptInsentif,
                                $jumlahOperator
                            );
                        }
                    } else if ($component->code === 'cutting_insentif') {
                        // Disamakan dengan CuttingInsentifMasterController::check()+
                        // calculateCutting(): dipanggil SEKALI PER role yang benar-benar
                        // dimiliki NPK ini di employee_cutting_assignments periode ini,
                        // lalu dijumlah — bukan setiap baris efisiensi dikali setiap role
                        // (bug lama: kalau NPK punya >1 role, tiap efisiensi ikut dihitung
                        // ulang untuk role lain juga sehingga amount jadi berlipat).
                        // Disamakan dengan CuttingInsentifMasterController::check():
                        // 1) Hanya karyawan dengan TKK kosong atau TKK di dalam periode
                        //    (controller: whereNull(TKK) OR TKK BETWEEN start & end).
                        // 2) Hanya role yang terdaftar di insentif_role_formulas
                        //    (controller: joinSub insentif_role_formulas).
                        // 3) Dibulatkan per role sebelum dijumlah
                        //    (controller: round per baris, lalu mergeInsentifByNpk).
                        $amount = 0;

                        $cuttingTkkOk = empty($employee->TKK)
                            || (
                                Carbon::parse($employee->TKK)->format('Y-m-d') >= Carbon::parse($period->start_date)->format('Y-m-d')
                                && Carbon::parse($employee->TKK)->format('Y-m-d') <= Carbon::parse($period->end_date)->format('Y-m-d')
                            );

                        if ($cuttingTkkOk) {
                            $rolesForNpk = DB::table('employee_cutting_assignments')
                                ->where('period_id', $period->id)
                                ->where('npk', $employee->NPK)
                                ->whereIn('role', function ($q) {
                                    $q->select('role')->from('insentif_role_formulas');
                                })
                                ->pluck('role')
                                ->filter()
                                // Case/trailing-space insensitive, sama seperti DISTINCT +
                                // collation SQL Server di controller. Tanpa ini 'fushing' &
                                // 'FUSHING' dianggap 2 role berbeda oleh PHP sehingga
                                // dihitung 2x (double count).
                                ->unique(fn($r) => mb_strtolower(trim($r)));

                            foreach ($rolesForNpk as $roleForNpk) {
                                $roleAmount = round((float) $this->calculateCuttingFromController(
                                    $employee,
                                    $period,
                                    $cuttingInsentifFormula,
                                    $roleForNpk
                                ), 0);

                                if ($roleAmount <= 0) continue;

                                $amount += $roleAmount;
                            }
                        }
                    } else if ($component->code === 'heat_insentif') {

                        // Disamakan dengan HeatInsentifMasterController::check()+
                        // calculateHeat(): dipanggil SEKALI PER role yang benar-benar
                        // dimiliki NPK ini di heat_efficiencies periode ini, lalu
                        // dijumlah (setara mergeInsentifByNpk di controller). Sebelumnya
                        // job hanya melihat $employee->role (role payroll umum) untuk
                        // menentukan cabang operator/non-operator dan menjalankan
                        // SEMUA baris assignment (termasuk baris role lain, mis. repair)
                        // lewat cabang itu — sekarang tiap role dihitung terpisah
                        // dengan formula yang sesuai role-nya masing-masing.
                        $rolesForNpk = DB::table('heat_efficiencies')
                            ->where('npk', $employee->NPK)
                            ->where('period_id', $period->id)
                            ->whereBetween('date', [$period->start_date, $period->end_date])
                            ->pluck('role')
                            ->filter()
                            ->unique();

                        $amount = 0;

                        foreach ($rolesForNpk as $roleForNpk) {
                            $amount += $this->calculateHeatFromController(
                                $employee,
                                $period,
                                $heatInsentifFormula,
                                $roleForNpk
                            );
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

            DB::transaction(function () use ($periodStart, $periodEnd, $overtimeDetails) {

                /*
                |----------------------------------------------------------------
                | SYNC overtimes_payroll (UPDATE OR CREATE)
                |----------------------------------------------------------------
                | Sebelumnya JUMLAH_JAM_LEMBUR & OVERTIME_DATE di-copy langsung
                | dari tabel `overtimes` (delete lalu insertUsing). Sekarang
                | overtime_hours & special_overtime_hours sudah TIDAK lagi
                | berasal dari tabel `overtimes`, melainkan hasil kalkulasi
                | attendance (att_log) yang sudah tersedia di $overtimeDetails
                | (lihat $overtimeMergedSql di atas).
                |
                | Kolom identitas (NAMA_KARYAWAN, BAGIAN, DAY, DEPT_GROUP) tetap
                | bersumber dari tabel `overtimes` seperti sebelumnya. Kolom
                | JUMLAH_JAM_LEMBUR asli dari `overtimes` HANYA dipakai sebagai
                | penentu boleh/tidaknya baris ini di-update:
                | - null ATAU angka -> boleh di-update, nilainya diganti dengan
                |   (overtime_hours + special_overtime_hours) dari $overtimeDetails.
                | - string kode (MA, CT, BR, OUT, P1, H, SD, dll) -> SKIP, baris
                |   tidak disentuh sama sekali (data manual tetap dipertahankan).
                |----------------------------------------------------------------
                */

                // Index $overtimeDetails (grouped by NPK) menjadi lookup
                // "NPK|Y-m-d" -> row, supaya pencarian per source row O(1).
                $overtimeDetailsByKey = [];
                foreach ($overtimeDetails as $npk => $rows) {
                    foreach ($rows as $ot) {
                        $key = $npk . '|' . Carbon::parse($ot->OVERTIME_DATE)->toDateString();
                        $overtimeDetailsByKey[$key] = $ot;
                    }
                }

                $sourceOvertimes = DB::table('overtimes')
                    ->select(
                        'NPK',
                        'NAMA_KARYAWAN',
                        'BAGIAN',
                        'OVERTIME_DATE',
                        'JUMLAH_JAM_LEMBUR',
                        'DAY',
                        'DEPT_GROUP',
                    )
                    ->whereBetween('OVERTIME_DATE', [$periodStart, $periodEnd])
                    ->get();

                $now = Carbon::now();

                foreach ($sourceOvertimes as $source) {

                    $originalLembur = $source->JUMLAH_JAM_LEMBUR;

                    // String kode (MA, CT, BR, OUT, P1, H, SD, dll) -> skip,
                    // jangan update baris ini sama sekali.
                    if ($originalLembur !== null && $originalLembur !== '' && !is_numeric($originalLembur)) {
                        continue;
                    }

                    $dateKey = $source->NPK . '|' . Carbon::parse($source->OVERTIME_DATE)->toDateString();
                    $detail  = $overtimeDetailsByKey[$dateKey] ?? null;

                    $jumlahJamLembur = $detail
                        ? ((float) ($detail->overtime_hours ?? 0) + (float) ($detail->special_overtime_hours ?? 0))
                        : 0;

                    $existing = DB::table('overtimes_payroll')
                        ->where('NPK', $source->NPK)
                        ->where('OVERTIME_DATE', $source->OVERTIME_DATE)
                        ->first();

                    if ($existing) {
                        DB::table('overtimes_payroll')
                            ->where('NPK', $source->NPK)
                            ->where('OVERTIME_DATE', $source->OVERTIME_DATE)
                            ->update([
                                'NAMA_KARYAWAN'     => $source->NAMA_KARYAWAN,
                                'BAGIAN'            => $source->BAGIAN,
                                'JUMLAH_JAM_LEMBUR' => $jumlahJamLembur,
                                'DAY'               => $source->DAY,
                                'DEPT_GROUP'        => $source->DEPT_GROUP,
                                'updated_at'        => $now,
                            ]);
                    } else {
                        DB::table('overtimes_payroll')->insert([
                            'NPK'               => $source->NPK,
                            'NAMA_KARYAWAN'     => $source->NAMA_KARYAWAN,
                            'BAGIAN'            => $source->BAGIAN,
                            'OVERTIME_DATE'     => $source->OVERTIME_DATE,
                            'JUMLAH_JAM_LEMBUR' => $jumlahJamLembur,
                            'created_at'        => $now,
                            'updated_at'        => $now,
                            'DAY'               => $source->DAY,
                            'DEPT_GROUP'        => $source->DEPT_GROUP,
                        ]);
                    }
                }
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

    /*
    |--------------------------------------------------------------------------
    | QC: arah tier terbalik dari sewing — makin KECIL defect rate makin
    | BESAR insentif. Formula qc_insentif dibaca sebagai upper-bound tiap
    | tier, jadi harus ascending + "<=".
    |--------------------------------------------------------------------------
    */
    private function getInsentifByDefectRate($efficiency, $rules)
    {
        ksort($rules);

        foreach ($rules as $threshold => $value) {
            if ($efficiency <= $threshold) {
                return $value;
            }
        }

        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | QC VIOLATION PER BUYER (qc_violations) - sama dengan QcInsentifMasterController
    |--------------------------------------------------------------------------
    | Jika tabel qc_violations punya kolom `buyer`, baris dengan buyer kosong
    | berlaku untuk semua buyer dan baris ber-buyer hanya untuk buyer tsb.
    | Tanpa kolom `buyer` => total percentage periode.
    */
    private array $qcViolationBuyerCache = [];

    private function qcViolationPercentageByBuyer($period, $buyer = null): float
    {
        $periodId = is_object($period) ? $period->id : $period;
        $buyerKey = strtoupper(trim((string) $buyer));
        $cacheKey = $periodId . '|' . $buyerKey;

        if (isset($this->qcViolationBuyerCache[$cacheKey])) {
            return $this->qcViolationBuyerCache[$cacheKey];
        }

        $query = DB::table('qc_violations')->where('period_id', $periodId);

        if (Schema::hasColumn('qc_violations', 'buyer')) {
            $query->where(function ($q) use ($buyerKey) {
                $q->whereNull('buyer')
                    ->orWhere('buyer', '')
                    ->orWhereRaw('UPPER(buyer) = ?', [$buyerKey]);
            });
        }

        return $this->qcViolationBuyerCache[$cacheKey] = min(100, max(0, (float) $query->sum('percentage')));
    }

    /*
    |--------------------------------------------------------------------------
    | JUMLAH LINE SECTION (pembagi CHIEF / SPV QC)
    |--------------------------------------------------------------------------
    | Jumlah line = (line_end - line_start + 1) dikurangi line yang tercantum di
    | sections.blank_line (satu atau beberapa nilai, dipisah koma / spasi / titik koma).
    | Hanya blank line yang berada di dalam range section yang dihitung (distinct).
    | Contoh: 67-78 => 12; blank 72 => 11; blank 72,74 => 10.
    | Sama seperti QcInsentifMasterController.
    */
    private array $qcSectionLineCountCache = [];

    private function qcSectionLineCount($lineStart, $lineEnd, $blankLine = null): int
    {
        $lineStart = (int) $lineStart;
        $lineEnd   = (int) $lineEnd;
        $blankKey  = trim((string) $blankLine);
        $cacheKey  = $lineStart . '-' . $lineEnd . '|' . $blankKey;

        if (isset($this->qcSectionLineCountCache[$cacheKey])) {
            return $this->qcSectionLineCountCache[$cacheKey];
        }

        $blanks = [];
        if ($blankKey !== '') {
            foreach (preg_split('/[\s,;]+/', $blankKey, -1, PREG_SPLIT_NO_EMPTY) as $b) {
                if (is_numeric($b)) {
                    $n = (int) $b;
                    if ($n >= $lineStart && $n <= $lineEnd) {
                        $blanks[$n] = true;
                    }
                }
            }
        }

        $count = max(($lineEnd - $lineStart + 1) - count($blanks), 1);

        return $this->qcSectionLineCountCache[$cacheKey] = $count;
    }

    /*
    |--------------------------------------------------------------------------
    | INSENTIF QA / QA LEADER QC
    |--------------------------------------------------------------------------
    | qa        : (total * 5 / 10) * ((100 - qc_violation) / 100)
    | qa_leader : (total * 7 / 10) * ((100 - qc_violation) / 100)
    */
    private function calculateQaQcInsentif($role, $totalLineInsentif, $qcViolation = 0)
    {
        return $this->evaluateQcRoleFormula($role, $totalLineInsentif, 1, $qcViolation, fn() => $totalLineInsentif
            * ($role === 'qa_leader' ? 0.7 : 0.5)
            * ((100 - (float) ($qcViolation ?? 0)) / 100));
    }

    /*
    |--------------------------------------------------------------------------
    | INSENTIF CHIEF / SPV QC
    |--------------------------------------------------------------------------
    | chief : ((total / jumlahLine) * 7 / 10) * ((100 - qc_violation) / 100)
    | spv   : ((total / jumlahLine) * 5 / 10) * ((100 - qc_violation) / 100)
    */
    private function calculateChiefSpvQcInsentif($role, $totalLineInsentif, $jumlahLine, $qcViolation = 0)
    {
        $jumlahLine = max((int) $jumlahLine, 1);

        return $this->evaluateQcRoleFormula($role, $totalLineInsentif, $jumlahLine, $qcViolation, fn() => ($totalLineInsentif / $jumlahLine)
            * ($role === 'chief' ? 0.7 : 0.5)
            * ((100 - (float) ($qcViolation ?? 0)) / 100));
    }

    private function evaluateQcRoleFormula($role, $totalLineInsentif, $jumlahLine, $qcViolation, callable $fallback)
    {
        $formula = Cache::remember(
            "insentif_formula_qc_{$role}",
            300,
            function () use ($role) {
                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', 'qc')
                    ->value('formula');
            }
        );

        if (!$formula) {
            return $fallback();
        }

        $formula = strtr($formula, array_map(fn($v) => (string) $v, [
            'totalLineInsentif' => $totalLineInsentif,
            'jumlahLine'        => $jumlahLine,
            'qc_violation'      => (float) ($qcViolation ?? 0),
            'insentif'          => $totalLineInsentif,
        ]));

        try {
            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {
            return $fallback();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE QC OPERATOR (inline / endline / fqc)
    |--------------------------------------------------------------------------
    | Disalin 1:1 dari QcInsentifMasterController::calculateRoleQcInsentif().
    | Rumus role dept 'qc' di insentif_role_formulas dievaluasi; jika tidak ada
    | rumus, nominal dikembalikan apa adanya (tanpa potongan qc_violation).
    */
    private function calculateRoleQcInsentif(
        $role,
        $dept,
        $totalLineInsentif,
        $jumlahLine,
        $violationsCount,
        $employeeViolations,
        $qcViolation = 0,
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
            'violation_percentage' => $employeeViolations ?? 0,
            'qc_violation'      => $qcViolation ?? 0,
            'insentif'          => $totalLineInsentif,
        ];

        $formula = strtr($formula, array_map(fn($v) => (string) $v, $variables));

        try {

            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {

            return $totalLineInsentif;
        }
    }

    private function calculateRoleSewingInsentif(
        $role,
        $dept,
        $totalLineInsentif,
        $jumlahLine,
        $violationsCount,
        $employeeViolations,
        $qcViolation = 0
    ) {

        $jumlahLine = max($jumlahLine, 1);

        // QC: nominal dasar langsung dipotong qc_violations (persen), rumus role
        // di insentif_role_formulas (dept 'qc') tidak dievaluasi.
        if ($dept === 'qc') {
            return $totalLineInsentif * ((100 - ($qcViolation ?? 0)) / 100);
        }

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
            'violation_percentage' => $employeeViolations ?? 0,
            // Variable khusus formula role QC (dept 'qc'):
            // - qc_violation : persentase potongan dari tabel qc_violations
            // - insentif     : alias totalLineInsentif (dipakai role operator QC)
            'qc_violation'      => $qcViolation ?? 0,
            'insentif'          => $totalLineInsentif,
        ];

        // strtr() mengganti dari key terpanjang dan tidak me-scan ulang hasil
        // penggantian, jadi 'totalLineInsentif' tidak bentrok dengan 'insentif'.
        $formula = strtr($formula, array_map(fn($v) => (string) $v, $variables));

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

    /*
    |--------------------------------------------------------------------------
    | HEAT SEAL INSENTIF (DIPORT VERBATIM DARI HeatInsentifMasterController::
    | calculateHeat, supaya job selalu identik dengan hasil controller/live
    | check. Dipanggil SEKALI PER (npk, role) — sama seperti controller yang
    | memanggilnya sekali per baris $employees (yang sudah 1 row = 1 role
    | hasil resolve dari heat_efficiencies.role), bukan sekali untuk seluruh
    | employee. Satu NPK dengan >1 role di heat_efficiencies pada periode yang
    | sama akan di-loop & dijumlah oleh caller di bawah (mirip
    | mergeInsentifByNpk di controller).
    |--------------------------------------------------------------------------
    */
    private function calculateHeatFromController($employee, $period, $formula, $role)
    {
        $amount = 0;

        $tkkDate = !empty($employee->TKK)
            ? Carbon::parse($employee->TKK)->format('Y-m-d')
            : null;

        $overtimes = DB::table('overtimes')
            ->where('NPK', $employee->NPK)
            ->whereBetween('OVERTIME_DATE', [
                $period->start_date,
                $period->end_date
            ])
            ->get()
            ->keyBy(fn($o) => $o->OVERTIME_DATE);

        // NOTE: signature closure ini SENGAJA dibuat sama persis dengan
        // controller (termasuk bug parameter-nya: closure cuma terima 1
        // parameter $date, tapi dipanggil dengan 2 argumen npk+date di
        // bawah). Efeknya validasi overtime jadi selalu TRUE (tidak pernah
        // exclude hari apapun) — ini reproduksi 1:1 dari perilaku controller
        // saat ini, BUKAN perbaikan. Lihat catatan di akhir pesan.
        $isValidOvertime = function ($date) use ($overtimes) {
            if (!isset($overtimes[$date])) {
                return true;
            }
            $lembur = $overtimes[$date]->JUMLAH_JAM_LEMBUR;
            if ($lembur === null || $lembur === '') {
                return true;
            }
            if (is_numeric($lembur)) {
                return true;
            }
            return false;
        };

        // 🔹 PERBAIKAN: sebelumnya $assignments ditentukan lewat $isOperator
        // yang diambil dari ->value('role') TANPA orderBy — baris mana yang
        // "duluan" dikembalikan MySQL untuk NPK ini tidak terjamin urutannya,
        // jadi untuk NPK yang punya >1 role (mis. operator + repair) dalam
        // periode yang sama, bisa saja baris pertama yang terambil justru
        // role 'repair', membuat $isOperator = false dan $assignments
        // dipotong jadi cuma 1 baris (->limit(1)) — akibatnya hari-hari
        // operator lain ikut hilang dari perhitungan. Sekarang untuk role
        // 'operator', assignment SELALU diambil dengan filter role='operator'
        // secara eksplisit, jadi hasilnya deterministik dan tidak bergantung
        // urutan baris dari database.
        if ($role === 'operator') {
            $assignments = DB::table('heat_efficiencies')
                ->where('npk', $employee->NPK)
                ->where('period_id', $period->id)
                ->where('role', 'operator')
                ->whereBetween('date', [$period->start_date, $period->end_date])
                ->get();

            foreach ($assignments as $assignment) {
                if ($tkkDate && $assignment->date >= $tkkDate) {
                    continue;
                }
                if (!$isValidOvertime($assignment->npk, $assignment->date)) {
                    continue;
                }
                $rate = $this->getInsentifByEfficiency($assignment->efficiency, $formula);
                $amount += $rate * $assignment->piece;
            }
        } else {
            $employeeDates = DB::table('heat_efficiencies')
                ->where('period_id', $period->id)
                ->where('npk', $employee->NPK)
                ->where('role', $role)
                ->pluck('date')
                ->unique()
                ->toArray();

            $totalDeptInsentif = 0;

            $operators = DB::table('heat_efficiencies')
                ->where('period_id', $period->id)
                ->where('role', '=', 'operator')
                ->whereBetween('date', [$period->start_date, $period->end_date])
                ->whereIn('date', $employeeDates)
                ->get();

            foreach ($operators as $operator) {
                if ($tkkDate && $operator->date >= $tkkDate) {
                    continue;
                }
                if (!$isValidOvertime($operator->npk, $operator->date)) {
                    continue;
                }
                $rate = $this->getInsentifByEfficiency($operator->efficiency, $formula);
                $totalDeptInsentif += $rate * $operator->piece;
            }

            $jumlahOperator = DB::table('heat_efficiencies as he')
                ->where('he.period_id', $period->id)
                ->whereIn('he.date', $employeeDates)
                ->where('he.role', '=', 'operator')
                ->pluck('he.npk')
                ->unique()
                ->count();

            $amount += $this->calculateRoleHeatInsentif($role, 'heat', $totalDeptInsentif, $jumlahOperator);
        }

        return $amount;
    }

    /*
    |--------------------------------------------------------------------------
    | CUTTING INSENTIF (DIPORT VERBATIM DARI CuttingInsentifMasterController::
    | calculateCutting). Sama seperti Heat, dipanggil SEKALI PER (npk, role).
    |--------------------------------------------------------------------------
    */
    private function calculateCuttingFromController($employee, $period, $formula, $role)
    {
        $amount = 0;

        $tkkDate = !empty($employee->TKK)
            ? Carbon::parse($employee->TKK)->format('Y-m-d')
            : null;

        $overtimes = DB::table('overtimes')
            ->where('NPK', $employee->NPK)
            ->whereBetween('OVERTIME_DATE', [
                $period->start_date,
                $period->end_date
            ])
            ->get()
            ->keyBy(fn($o) => $o->OVERTIME_DATE);

        // Sama seperti di calculateHeatFromController: closure ini sengaja
        // dibuat identik dengan controller (1 parameter, dipanggil dengan 1
        // argumen di sini — untuk cutting controller memang konsisten
        // memanggilnya dengan 1 argumen $row->date, jadi TIDAK ada bug
        // parameter di versi cutting).
        $isValidOvertime = function ($date) use ($overtimes) {
            if (!isset($overtimes[$date])) {
                return true;
            }
            $lembur = $overtimes[$date]->JUMLAH_JAM_LEMBUR;
            if ($lembur === null || $lembur === '') {
                return true;
            }
            if (is_numeric($lembur)) {
                return true;
            }
            return false;
        };

        $employeeDates = DB::table('employee_cutting_assignments')
            ->where('period_id', $period->id)
            ->where('npk', $employee->NPK)
            ->pluck('start_date')
            ->unique()
            ->toArray();

        $cuttingEfficiencies = DB::table('cutting_efficiencies')
            ->where('period_id', $period->id)
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->whereIn('date', $employeeDates)
            ->get();

        foreach ($cuttingEfficiencies as $row) {
            if ($tkkDate && $row->date >= $tkkDate) {
                continue;
            }
            if (!$isValidOvertime($row->date)) {
                continue;
            }
            $insentif = $this->getInsentifByEfficiency($row->efficiency, $formula);
            $amount += $this->calculateRoleCuttingInsentif($role, 'cutting', $insentif);
        }

        return $amount;
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
