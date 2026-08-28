<?php

namespace App\Http\Controllers;

use App\Models\AttendanceFinger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\AttendanceExport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceFingerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            try {
                // main query dari att_log
                // $data = DB::connection('cii')->select("
                //     SELECT
                //         a.pin,
                //         COALESCE(b.NAMA_KARYAWAN, bk.NAMA_KARYAWAN) AS nama,
                //         COALESCE(b.NPK, bk.NPK) AS npk,
                //         COALESCE(db.DEPARTEMENT, dbk.DEPARTEMENT) AS bagian,
                //         CONVERT(varchar, MIN(a.scan_date), 108) AS jam_masuk,
                //         CONVERT(varchar, MAX(a.scan_date), 108) AS jam_pulang,
                //         COUNT(*) AS total_scan
                //     FROM att_log a
                //     LEFT JOIN BIODATA b ON CAST(b.BARCODE AS VARCHAR) = CAST(a.pin AS VARCHAR)
                //     LEFT JOIN BIODATA_KELUAR bk ON CAST(bk.BARCODE AS VARCHAR) = CAST(a.pin AS VARCHAR)
                //     LEFT JOIN DEPT db ON db.ID_DEPT = b.ID_DEPT
                //     LEFT JOIN DEPT dbk ON dbk.ID_DEPT = bk.ID_DEPT
                //     WHERE a.scan_date >= ? AND a.scan_date <= ?
                //     GROUP BY
                //         a.pin,
                //         b.NAMA_KARYAWAN, bk.NAMA_KARYAWAN,
                //         b.NPK, bk.NPK,
                //         db.DEPARTEMENT, dbk.DEPARTEMENT
                //     ORDER BY MIN(a.scan_date) ASC
                // ", [$date . ' 00:00:00', $date . ' 23:59:59']);





                // // main query dari biodata
                // $yesterday = Carbon::parse($date)->subDay()->format('Y-m-d');

                // $data = DB::connection('cii')->select("
                //     SELECT
                //         b.BARCODE AS pin,
                //         b.NAMA_KARYAWAN AS nama,
                //         b.NPK AS npk,
                //         d.DEPARTEMENT AS bagian,
                //         CONVERT(varchar, MIN(a.scan_date), 108) AS jam_masuk,
                //         CONVERT(varchar, MAX(a.scan_date), 108) AS jam_pulang,
                //         COUNT(a.scan_date) AS total_scan,
                //         COALESCE(s.name, 'Normal Shift') AS shift_name,
                //         CONVERT(varchar, COALESCE(s.work_start, '08:00:00'), 108) AS shift_start,
                //         CASE 
                //             WHEN CONVERT(varchar, MIN(a.scan_date), 108) > CONVERT(varchar, COALESCE(s.work_start, '08:00:00'), 108) THEN 1
                //             ELSE 0
                //         END as is_late
                //     FROM BIODATA b
                //     LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT
                //     LEFT JOIN employee_shifts es ON es.npk = b.NPK AND CAST(es.shift_date AS DATE) = CAST(? AS DATE)
                //     LEFT JOIN shifts s ON s.id = es.shift_id
                //     LEFT JOIN employee_shifts prev_es ON prev_es.npk = b.NPK AND CAST(prev_es.shift_date AS DATE) = CAST(? AS DATE)
                //     LEFT JOIN shifts prev_s ON prev_s.id = prev_es.shift_id
                //     JOIN att_log a ON CAST(a.pin AS VARCHAR) = CAST(b.BARCODE AS VARCHAR)
                //         AND a.scan_date >= DATEADD(hour, -4, CAST(? + ' ' + CONVERT(varchar, COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME))
                //         AND a.scan_date <= DATEADD(hour, 14, CAST(? + ' ' + CONVERT(varchar, COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME))
                //         AND a.scan_date > COALESCE(
                //             DATEADD(minute, 60,
                //                 CASE
                //                     WHEN prev_s.work_end < prev_s.work_start
                //                     THEN CAST(? + ' ' + CONVERT(varchar, prev_s.work_end, 108) AS DATETIME)
                //                     ELSE CAST(? + ' ' + CONVERT(varchar, prev_s.work_end, 108) AS DATETIME)
                //                 END
                //             ),
                //             CAST('1900-01-01' AS DATETIME)
                //         )
                //     GROUP BY
                //         b.BARCODE,
                //         b.NAMA_KARYAWAN,
                //         b.NPK,
                //         d.DEPARTEMENT,
                //         s.name,
                //         s.work_start
                //     ORDER BY MIN(a.scan_date) ASC
                // ", [$date, $yesterday, $date, $date, $date, $yesterday]);

                
                // (LAST WORK)
                // $yesterday = \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d');
                // $tomorrow  = \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d');

                // $data = DB::connection('cii')->select("
                //     WITH emp AS (
                //         SELECT
                //             b.BARCODE       AS pin,
                //             b.NAMA_KARYAWAN AS nama,
                //             b.NPK           AS npk,
                //             d.DEPARTEMENT   AS bagian,
                //             b.SECTION       AS section,
                //             b.BAG           AS jabatan,
                //             b.STATUS        AS status,
                //             COALESCE(s.name, 'Normal Shift')    AS shift_name,
                //             COALESCE(s.work_start, '08:00:00')  AS work_start,
                //             COALESCE(s.work_end, '17:00:00')    AS work_end,
                //             COALESCE(ps.work_start, '08:00:00') AS prev_work_start,
                //             COALESCE(ps.work_end, '17:00:00')   AS prev_work_end,
                //             COALESCE(ns.work_start, '08:00:00') AS next_work_start
                //         FROM BIODATA b
                //         LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT

                //         -- shift hari ini
                //         LEFT JOIN employee_shifts es
                //             ON es.npk = b.NPK
                //             AND CAST(es.shift_date AS DATE) = CAST(? AS DATE)
                //         LEFT JOIN shifts s ON s.id = es.shift_id

                //         -- shift kemarin (untuk exclusion window bawah)
                //         LEFT JOIN employee_shifts pes
                //             ON pes.npk = b.NPK
                //             AND CAST(pes.shift_date AS DATE) = CAST(? AS DATE)
                //         LEFT JOIN shifts ps ON ps.id = pes.shift_id

                //         -- shift besok (untuk exclusion window atas)
                //         LEFT JOIN employee_shifts nes
                //             ON nes.npk = b.NPK
                //             AND CAST(nes.shift_date AS DATE) = CAST(? AS DATE)
                //         LEFT JOIN shifts ns ON ns.id = nes.shift_id
                //     ),
                //     emp_bounds AS (
                //         SELECT
                //             e.*,
                //             CAST(? + ' ' + CONVERT(varchar(8), e.work_start, 108) AS DATETIME) AS shift_start_dt,
                //             CASE
                //                 WHEN e.work_end < e.work_start
                //                     THEN DATEADD(day, 1, CAST(? + ' ' + CONVERT(varchar(8), e.work_end, 108) AS DATETIME))
                //                 ELSE CAST(? + ' ' + CONVERT(varchar(8), e.work_end, 108) AS DATETIME)
                //             END AS shift_end_dt,
                //             CASE
                //                 WHEN e.prev_work_end < e.prev_work_start
                //                     THEN DATEADD(day, 1, CAST(? + ' ' + CONVERT(varchar(8), e.prev_work_end, 108) AS DATETIME))
                //                 ELSE CAST(? + ' ' + CONVERT(varchar(8), e.prev_work_end, 108) AS DATETIME)
                //             END AS prev_shift_end_dt,
                //             CAST(? + ' ' + CONVERT(varchar(8), e.next_work_start, 108) AS DATETIME) AS next_shift_start_dt
                //         FROM emp e
                //     ),
                //     emp_window AS (
                //         SELECT
                //             eb.*,
                //             -- batas atas window: mana yang lebih dekat, +6 jam dari shift_end
                //             -- ATAU 60 menit sebelum shift berikutnya mulai
                //             CASE
                //                 WHEN DATEADD(hour, 6, eb.shift_end_dt) < DATEADD(minute, -60, eb.next_shift_start_dt)
                //                     THEN DATEADD(hour, 6, eb.shift_end_dt)
                //                 ELSE DATEADD(minute, -60, eb.next_shift_start_dt)
                //             END AS scan_upper_bound
                //         FROM emp_bounds eb
                //     ),
                //     scans AS (
                //         SELECT
                //             ew.pin, ew.npk,
                //             a.scan_date,
                //             ew.shift_start_dt,
                //             ew.shift_end_dt
                //         FROM emp_window ew
                //         JOIN att_log a
                //             ON CAST(a.pin AS VARCHAR) = CAST(ew.pin AS VARCHAR)
                //             AND a.scan_date >= DATEADD(hour, -4, ew.shift_start_dt)
                //             AND a.scan_date <= ew.scan_upper_bound
                //             AND a.scan_date > DATEADD(minute, 60, ew.prev_shift_end_dt)
                //     ),
                //     scan_ranked AS (
                //         SELECT
                //             npk, scan_date,
                //             ABS(DATEDIFF(minute, scan_date, shift_start_dt)) AS dist_to_start,
                //             ABS(DATEDIFF(minute, scan_date, shift_end_dt))   AS dist_to_end,
                //             ROW_NUMBER() OVER (PARTITION BY npk ORDER BY ABS(DATEDIFF(minute, scan_date, shift_start_dt))) AS rn_masuk,
                //             ROW_NUMBER() OVER (PARTITION BY npk ORDER BY ABS(DATEDIFF(minute, scan_date, shift_end_dt)))   AS rn_pulang,
                //             COUNT(*) OVER (PARTITION BY npk) AS total_scan
                //         FROM scans
                //     )
                //     SELECT
                //         eb.pin, eb.nama, eb.npk, eb.bagian, eb.section, eb.jabatan, eb.status,

                //         CASE
                //             WHEN m.scan_date IS NULL THEN 'not scanned'
                //             WHEN m.total_scan = 1 AND m.dist_to_end < m.dist_to_start THEN 'not scanned'
                //             ELSE CONVERT(varchar(8), m.scan_date, 108)
                //         END AS jam_masuk,

                //         CASE
                //             WHEN p.scan_date IS NULL THEN 'not scanned'
                //             WHEN p.total_scan = 1 AND p.dist_to_start <= p.dist_to_end THEN 'not scanned'
                //             ELSE CONVERT(varchar(8), p.scan_date, 108)
                //         END AS jam_pulang,

                //         COALESCE(m.total_scan, 0) AS total_scan,
                //         eb.shift_name,
                //         CONVERT(varchar(8), eb.work_start, 108) AS shift_start,

                //         CASE
                //             WHEN m.scan_date IS NOT NULL
                //                 AND NOT (m.total_scan = 1 AND m.dist_to_end < m.dist_to_start)
                //                 AND m.scan_date > DATEADD(minute, 10, eb.shift_start_dt)
                //             THEN 1 ELSE 0
                //         END AS is_late

                //     FROM emp_window eb
                //     LEFT JOIN scan_ranked m ON m.npk = eb.npk AND m.rn_masuk = 1
                //     LEFT JOIN scan_ranked p ON p.npk = eb.npk AND p.rn_pulang = 1
                //     WHERE m.scan_date IS NOT NULL
                //     ORDER BY eb.bagian ASC, eb.npk ASC
                // ", [
                //     $date,          // emp: shift hari ini (es.shift_date)
                //     $yesterday,     // emp: shift kemarin (pes.shift_date)
                //     $tomorrow,      // emp: shift besok (nes.shift_date)
                //     $date,          // emp_bounds: shift_start_dt
                //     $date,          // emp_bounds: shift_end_dt (overnight)
                //     $date,          // emp_bounds: shift_end_dt (normal)
                //     $yesterday,     // emp_bounds: prev_shift_end_dt (overnight)
                //     $yesterday,     // emp_bounds: prev_shift_end_dt (normal)
                //     $tomorrow,      // emp_bounds: next_shift_start_dt
                // ]);


            $date      = $request->filled('date') ? $request->date : now()->toDateString();
            $yesterday = \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d');
            // $tomorrow dihapus — tidak diperlukan lagi

            $data = DB::connection('cii')->select("
                WITH emp AS (
                    SELECT
                        b.BARCODE       AS pin,
                        b.NAMA_KARYAWAN AS nama,
                        b.NPK           AS npk,
                        d.DEPARTEMENT   AS bagian,
                        b.SECTION       AS section,
                        b.BAG           AS jabatan,
                        b.STATUS        AS status,
                        COALESCE(s.name,  'Normal Shift') AS shift_name,
                        COALESCE(s.work_start, '08:00:00') AS work_start,
                        COALESCE(s.work_end,   '17:00:00') AS work_end,
                        COALESCE(ps.work_start,'08:00:00') AS prev_work_start,
                        COALESCE(ps.work_end,  '17:00:00') AS prev_work_end
                        -- DIHAPUS: next_work_start (tidak perlu lagi)
                    FROM BIODATA b
                    LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT

                    -- Shift hari ini
                    LEFT JOIN employee_shifts es
                        ON es.npk = b.NPK
                        AND CAST(es.shift_date AS DATE) = CAST(? AS DATE)      -- :date
                    LEFT JOIN shifts s ON s.id = es.shift_id

                    -- Shift kemarin: untuk hitung prev_shift_end_dt
                    LEFT JOIN employee_shifts pes
                        ON pes.npk = b.NPK
                        AND CAST(pes.shift_date AS DATE) = CAST(? AS DATE)     -- :yesterday
                    LEFT JOIN shifts ps ON ps.id = pes.shift_id

                    -- DIHAPUS: join nes/ns (next shift) tidak diperlukan lagi
                ),

                emp_bounds AS (
                    SELECT
                        e.*,

                        -- Jam mulai shift hari ini
                        CAST(? + ' ' + CONVERT(varchar(8), e.work_start, 108) AS DATETIME)
                            AS shift_start_dt,                                  -- :date

                        -- Jam selesai shift hari ini
                        -- Night shift (work_end < work_start): end = besok
                        -- Normal shift                        : end = hari ini
                        CASE
                            WHEN e.work_end < e.work_start
                                THEN DATEADD(day, 1, CAST(? + ' ' + CONVERT(varchar(8), e.work_end, 108) AS DATETIME))
                            ELSE CAST(? + ' ' + CONVERT(varchar(8), e.work_end, 108) AS DATETIME)
                        END AS shift_end_dt,                                    -- :date, :date

                        -- Jam selesai shift KEMARIN
                        -- Jika kemarin night shift: end-nya jatuh di $date (yesterday+1)
                        -- Jika kemarin normal shift: end-nya di $yesterday
                        CASE
                            WHEN e.prev_work_end < e.prev_work_start
                                THEN DATEADD(day, 1, CAST(? + ' ' + CONVERT(varchar(8), e.prev_work_end, 108) AS DATETIME))
                            ELSE CAST(? + ' ' + CONVERT(varchar(8), e.prev_work_end, 108) AS DATETIME)
                        END AS prev_shift_end_dt                                -- :yesterday, :yesterday

                        -- DIHAPUS: next_shift_start_dt
                    FROM emp e
                ),

                emp_window AS (
                    SELECT
                        eb.*,
                        /*
                         * FIX UTAMA:
                         * scan_upper_bound = shift_end_dt + 120 menit
                         *
                         * Sebelumnya: MIN(shift_end+6h, next_shift_start–60min)
                         *   → next_shift_start 08:00 - 60min = 07:00 ← terlalu sempit
                         *
                         * Sekarang: shift_end + 120min = 05:30 + 120min = 07:30
                         *   → scan 07:03 ≤ 07:30 ✓ masuk sebagai jam pulang 18/08
                         *
                         * Berlaku sama untuk normal shift:
                         *   shift_end 17:00 + 120min = 19:00 (cukup untuk lembur ringan)
                         */
                        DATEADD(minute, 120, eb.shift_end_dt) AS scan_upper_bound
                    FROM emp_bounds eb
                ),

                scans AS (
                    SELECT
                        ew.pin, ew.npk,
                        a.scan_date,
                        ew.shift_start_dt,
                        ew.shift_end_dt
                    FROM emp_window ew
                    JOIN att_log a
                        ON CAST(a.pin AS VARCHAR) = CAST(ew.pin AS VARCHAR)

                        -- Batas bawah: 4 jam sebelum shift mulai
                        AND a.scan_date >= DATEADD(hour, -4, ew.shift_start_dt)

                        -- Batas atas: shift_end + 120 menit (SAMA dengan scan_upper_bound)
                        AND a.scan_date <= ew.scan_upper_bound

                        /*
                         * FIX SIMETRI:
                         * Exclusion = prev_shift_end + 120 menit
                         *
                         * Sebelumnya: +60 menit → batas 06:30
                         *   → 07:03 > 06:30 = lolos ke 19/08 ← BUG
                         *
                         * Sekarang: +120 menit → batas 07:30
                         *   → 07:03 < 07:30 = diblokir dari 19/08 ✓
                         *
                         * Simetris dengan scan_upper_bound di atas:
                         *   batas atas  18/08 = shift_end      + 120min = 07:30
                         *   batas bawah 19/08 = prev_shift_end + 120min = 07:30
                         *   → tidak ada dead zone, tidak ada overlap ✓
                         */
                        AND a.scan_date > DATEADD(minute, 120, ew.prev_shift_end_dt)
                ),

                scan_ranked AS (
                    SELECT
                        npk, scan_date,
                        ABS(DATEDIFF(minute, scan_date, shift_start_dt)) AS dist_to_start,
                        ABS(DATEDIFF(minute, scan_date, shift_end_dt))   AS dist_to_end,
                        ROW_NUMBER() OVER (
                            PARTITION BY npk
                            ORDER BY ABS(DATEDIFF(minute, scan_date, shift_start_dt))
                        ) AS rn_masuk,
                        ROW_NUMBER() OVER (
                            PARTITION BY npk
                            ORDER BY ABS(DATEDIFF(minute, scan_date, shift_end_dt))
                        ) AS rn_pulang,
                        COUNT(*) OVER (PARTITION BY npk) AS total_scan
                    FROM scans
                )

                SELECT
                    eb.pin, eb.nama, eb.npk, eb.bagian, eb.section, eb.jabatan, eb.status,

                    CASE
                        WHEN m.scan_date IS NULL                                      THEN 'not scanned'
                        WHEN m.total_scan = 1 AND m.dist_to_end < m.dist_to_start    THEN 'not scanned'
                        ELSE CONVERT(varchar(8), m.scan_date, 108)
                    END AS jam_masuk,

                    CASE
                        WHEN p.scan_date IS NULL                                      THEN 'not scanned'
                        WHEN p.total_scan = 1 AND p.dist_to_start <= p.dist_to_end   THEN 'not scanned'
                        ELSE CONVERT(varchar(8), p.scan_date, 108)
                    END AS jam_pulang,

                    COALESCE(m.total_scan, 0)               AS total_scan,
                    eb.shift_name,
                    CONVERT(varchar(8), eb.work_start, 108) AS shift_start,

                    CASE
                        WHEN m.scan_date IS NOT NULL
                            AND NOT (m.total_scan = 1 AND m.dist_to_end < m.dist_to_start)
                            AND m.scan_date > DATEADD(minute, 10, eb.shift_start_dt)
                        THEN 1 ELSE 0
                    END AS is_late

                FROM emp_window eb
                LEFT JOIN scan_ranked m ON m.npk = eb.npk AND m.rn_masuk  = 1
                LEFT JOIN scan_ranked p ON p.npk = eb.npk AND p.rn_pulang = 1
                WHERE m.scan_date IS NOT NULL
                ORDER BY eb.bagian ASC, eb.npk ASC
            ", [
                $date,       // emp: es.shift_date  → shift hari ini
                $yesterday,  // emp: pes.shift_date → shift kemarin
                $date,       // emp_bounds: shift_start_dt
                $date,       // emp_bounds: shift_end_dt CASE night (DATEADD +1 day)
                $date,       // emp_bounds: shift_end_dt CASE normal
                $yesterday,  // emp_bounds: prev_shift_end_dt CASE night (DATEADD +1 day → jadi $date)
                $yesterday,  // emp_bounds: prev_shift_end_dt CASE normal
                // $tomorrow DIHAPUS
            ]);

                return datatables()->of($data)->addIndexColumn()->make(true);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()]);
            }
        }

        return view('attendance_finger.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(AttendanceFinger $attendanceFinger)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AttendanceFinger $attendanceFinger)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AttendanceFinger $attendanceFinger)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AttendanceFinger $attendanceFinger)
    {
        //
    }
    public function sync(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        try {
            $count = 0;

            DB::connection('fingerspot')
                ->table('att_log')
                ->whereDate('scan_date', $request->date)
                ->orderBy('scan_date') // chunk() requires an orderBy
                ->chunk(500, function ($sourceData) use (&$count) {
                    foreach ($sourceData as $data) {
                        DB::connection('cii')->table('att_log')->updateOrInsert(
                            [
                                'sn' => $data->sn,
                                'scan_date' => $data->scan_date,
                                'pin' => $data->pin,
                            ],
                            [
                                'verifymode' => $data->verifymode,
                                'inoutmode' => $data->inoutmode,
                                'reserved' => $data->reserved,
                                'work_code' => $data->work_code,
                                'att_id' => str_replace(['-', ' ', ':'], '', $data->scan_date . $data->sn . $data->pin),
                            ]
                        );
                        $count++;
                    }
                });

            if ($count === 0) {
                return response()->json(['message' => 'No data found for this date'], 404);
            }

            return response()->json(['message' => "Successfully synced $count records."]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function export(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        return Excel::download(new AttendanceExport($request->date), 'attendance_' . $request->date . '.xlsx');
    }

    /**
     * Datatable: employees who did NOT finger on the given date.
     */
    public function notFinger(Request $request)
    {
        if ($request->ajax()) {
            try {
                $date = $request->filled('date') ? $request->date : now()->toDateString();

                $yesterday = Carbon::parse($date)->subDay()->format('Y-m-d');
                $data = DB::connection('cii')->select("
                    SELECT
                        b.BARCODE       AS pin,
                        b.NAMA_KARYAWAN AS nama,
                        b.NPK           AS npk,
                        d.DEPARTEMENT   AS bagian,
                        b.STATUS        AS status
                    FROM BIODATA b
                    LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT

                    -- Shift hari ini
                    LEFT JOIN employee_shifts es
                        ON es.npk = b.NPK
                        AND CAST(es.shift_date AS DATE) = CAST(? AS DATE)
                    LEFT JOIN shifts s ON s.id = es.shift_id

                    -- Shift kemarin (untuk filter overflow night shift)
                    LEFT JOIN employee_shifts prev_es
                        ON prev_es.npk = b.NPK
                        AND CAST(prev_es.shift_date AS DATE) = CAST(? AS DATE)
                    LEFT JOIN shifts prev_s ON prev_s.id = prev_es.shift_id

                    WHERE b.STATUS = 'A'
                      AND NOT EXISTS (
                          SELECT 1
                          FROM att_log a
                          WHERE CAST(a.pin AS VARCHAR) = CAST(b.BARCODE AS VARCHAR)
                            AND a.scan_date >= DATEADD(
                                hour, -4,
                                CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
                            )
                            AND a.scan_date <= DATEADD(
                                hour, 14,
                                CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
                            )
                            AND a.scan_date > COALESCE(
                                DATEADD(minute, 60,
                                    CASE
                                        WHEN prev_s.work_end < prev_s.work_start
                                        THEN CAST(? + ' ' + CONVERT(varchar(8), prev_s.work_end, 108) AS DATETIME)
                                        ELSE CAST(? + ' ' + CONVERT(varchar(8), prev_s.work_end, 108) AS DATETIME)
                                    END
                                ),
                                CAST('1900-01-01 00:00:00' AS DATETIME)
                            )
                      )
                    ORDER BY b.NPK ASC
                ", [$date, $yesterday, $date, $date, $date, $yesterday]);

                return datatables()->of($data)->addIndexColumn()->make(true);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()]);
            }
        }

        return response()->json(['error' => 'Invalid request'], 400);
    }

    /**
     * Bulk-assign manual attendance records into att_log for employees who did not finger.
     */
    public function assignAttendance(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'pins' => 'required|array|min:1',
            'pins.*' => 'required|string',
        ]);

        $date        = $request->date;
        $pins        = $request->pins;
        $count       = 0;

        foreach ($pins as $pin) {
            // Generate random time between 07:50:00 and 07:55:00 (range of 300 seconds)
            $randomSeconds = rand(0, 500);
            $timeString = sprintf('07:%02d:%02d', 50 + floor($randomSeconds / 60), $randomSeconds % 60);
            $scanDatetime = $date . ' ' . $timeString;

            DB::connection('cii')->table('att_log')->updateOrInsert(
                [
                    'pin'       => $pin,
                    'scan_date' => $scanDatetime,
                    'sn'        => 'MANUAL',
                ],
                [
                    'verifymode' => 1,
                    'inoutmode'  => DB::connection('cii')->table('att_log')->where('pin', $pin)->whereDate('scan_date', $date)->count() + 1,
                    'reserved'   => 0,
                    'work_code'  => 0,
                    'att_id'     => str_replace(['-', ' ', ':'], '', $scanDatetime . 'MANUAL' . $pin),
                ]
            );
            $count++;
        }

        return response()->json(['message' => "Berhasil assign absensi untuk {$count} karyawan."]);
    }

    // function for download template export attendance manually
    public function downloadTemplateManual(Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m',
            'is_sewing' => 'required|in:0,1',
        ]);

        $dateParts = explode('-', $request->date);
        $year = $dateParts[0];
        $month = $dateParts[1];
        $is_sewing = $request->is_sewing;

        $sewingLabel = $is_sewing == '0' ? 'Sewing' : 'Non_Sewing';
        $fileName = "Template_Manual_Attendance_{$sewingLabel}_{$year}_{$month}.xlsx";

        return Excel::download(new \App\Exports\AttendanceManualTemplateExport($month, $year, $is_sewing), $fileName);
    }

    // ================================================================
    // BARU — Export Bulanan / Per Departemen
    // Terpisah TOTAL dari export() (harian) di atas. Tidak menyentuh
    // export()/AttendanceExport/AttendanceFingerExport yang sudah ada.
    // ================================================================

    /**
     * List departemen untuk dropdown di modal export bulanan.
     * GET /attendance-finger/departments
     */
    public function getDepartments()
    {
        try {
            $depts = DB::connection('cii')->table('DEPT')
                ->select('ID_DEPT as id_dept', 'DEPARTEMENT as departement')
                ->whereNotNull('DEPARTEMENT')
                ->orderBy('DEPARTEMENT')
                ->get();

            return response()->json($depts);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export attendance bulanan / rentang tanggal custom, difilter per
     * departemen (kosongkan dept_id untuk semua departemen).
     *
     * GET /attendance-finger/export-monthly?start_date=...&end_date=...&dept_id=...
     */
    public function exportMonthlyByDept(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'dept_id'    => 'nullable|string',
        ]);

        $deptId = $request->filled('dept_id') ? $request->dept_id : null;

        [$dates, $employees, $deptLabel] = $this->buildMonthlyPivotByDept(
            $request->start_date,
            $request->end_date,
            $deptId
        );

        $periodLabel = Carbon::parse($request->start_date)->translatedFormat('d M Y')
            . ' - ' . Carbon::parse($request->end_date)->translatedFormat('d M Y');

        $safeDept = preg_replace('/[^A-Za-z0-9]+/', '_', $deptLabel);
        $filename = "Attendance_{$safeDept}_{$request->start_date}_to_{$request->end_date}.xlsx";

        return Excel::download(
            new \App\Exports\AttendanceMonthlyByDeptExport($dates, $employees, $deptLabel, $periodLabel),
            $filename
        );
    }

    /**
     * Bangun [dates[], employees[], deptLabel] untuk rentang tanggal +
     * filter departemen opsional.
     *
     * @return array{0: array<int,string>, 1: array<int,array>, 2: string}
     */
    private function buildMonthlyPivotByDept(string $startDate, string $endDate, ?string $deptId): array
    {
        $bindings = [$startDate, $endDate]; // DateSeries

        if ($deptId) {
            $bindings[] = $deptId; // filter dept di CTE emp
        }

        $bindings = array_merge($bindings, [
            $startDate, $endDate, // att_log_window
            $startDate, $endDate, // candidate (klip ke rentang display)
        ]);

        $rows = DB::connection('cii')->select($this->pivotSqlMonthly($deptId), $bindings);

        $dates = [];
        foreach (CarbonPeriod::create($startDate, $endDate) as $day) {
            $dates[] = $day->format('Y-m-d');
        }

        $employees = [];
        $deptLabel = null;

        foreach ($rows as $row) {
            $npk = $row->npk;

            if (!isset($employees[$npk])) {
                $employees[$npk] = [
                    'npk'        => $row->npk,
                    'nama'       => $row->nama,
                    'bagian'     => $row->bagian,
                    'attendance' => [],
                ];
            }

            if ($deptId && $deptLabel === null) {
                $deptLabel = $row->bagian;
            }

            $workDate = Carbon::parse($row->work_date)->format('Y-m-d');

            $employees[$npk]['attendance'][$workDate] = [
                'masuk'   => $row->jam_masuk,
                'pulang'  => $row->jam_pulang,
                'is_late' => (bool) $row->is_late,
            ];
        }

        foreach ($employees as &$emp) {
            foreach ($dates as $d) {
                if (!isset($emp['attendance'][$d])) {
                    $emp['attendance'][$d] = [
                        'masuk'   => 'not scanned',
                        'pulang'  => 'not scanned',
                        'is_late' => false,
                    ];
                }
            }
            ksort($emp['attendance']);
        }
        unset($emp);

        $employees = array_values($employees);
        usort($employees, fn ($a, $b) => [$a['bagian'], $a['npk']] <=> [$b['bagian'], $b['npk']]);

        if ($deptLabel === null) {
            $deptLabel = 'Semua Departemen';
        }

        return [$dates, $employees, $deptLabel];
    }

    /**
     * Query pivot bulanan/range, opsional filter departemen.
     * Adaptasi dari query single-day di index() (shift hari ini + kemarin,
     * window ±120 menit simetris) — TANPA join shift besok, sama seperti
     * index() versi final kamu.
     *
     * Strategi performa "candidate": perhitungan shift (join
     * employee_shifts/shifts) hanya dijalankan untuk (npk, tanggal) yang
     * benar-benar dekat dengan scan di att_log (candidate/cand_emp), bukan
     * untuk semua kombinasi karyawan x tanggal — supaya tidak berat untuk
     * karyawan yang banyak "not scanned"-nya (lihat CTE "grid" di bagian
     * akhir untuk tampilan penuh semua tanggal, yang murah/tanpa join berat).
     *
     * Params (urut): start_date, end_date, [dept_id jika difilter],
     *                start_date, end_date, start_date, end_date
     */
    private function pivotSqlMonthly(?string $deptId): string
    {
        $deptFilter = $deptId ? 'WHERE d.ID_DEPT = ?' : '';

        return "
            ;WITH DateSeries AS (
                SELECT CAST(? AS DATE) AS work_date
                UNION ALL
                SELECT DATEADD(DAY, 1, work_date)
                FROM DateSeries
                WHERE work_date < CAST(? AS DATE)
            ),
            emp AS (
                SELECT
                    b.BARCODE       AS pin,
                    b.NPK           AS npk,
                    b.NAMA_KARYAWAN AS nama,
                    d.DEPARTEMENT   AS bagian
                FROM BIODATA b
                LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT
                {$deptFilter}
            ),
            att_log_window AS (
                SELECT pin, scan_date
                FROM att_log
                WHERE scan_date >= DATEADD(DAY, -1, CAST(? AS DATETIME))
                  AND scan_date <  DATEADD(DAY, 2, CAST(? AS DATETIME))
            ),
            candidate AS (
                SELECT DISTINCT npk, work_date FROM (
                    SELECT e.npk, CAST(a.scan_date AS DATE) AS work_date
                    FROM emp e JOIN att_log_window a ON CAST(a.pin AS VARCHAR) = CAST(e.pin AS VARCHAR)
                    UNION ALL
                    SELECT e.npk, DATEADD(DAY, -1, CAST(a.scan_date AS DATE))
                    FROM emp e JOIN att_log_window a ON CAST(a.pin AS VARCHAR) = CAST(e.pin AS VARCHAR)
                    UNION ALL
                    SELECT e.npk, DATEADD(DAY, 1, CAST(a.scan_date AS DATE))
                    FROM emp e JOIN att_log_window a ON CAST(a.pin AS VARCHAR) = CAST(e.pin AS VARCHAR)
                ) x
                WHERE work_date BETWEEN CAST(? AS DATE) AND CAST(? AS DATE)
            ),
            cand_emp AS (
                SELECT e.pin, e.npk, e.nama, e.bagian, c.work_date
                FROM candidate c
                JOIN emp e ON e.npk = c.npk
            ),
            emp_shift AS (
                SELECT
                    ce.*,
                    COALESCE(s.work_start, '08:00:00')  AS work_start,
                    COALESCE(s.work_end, '17:00:00')    AS work_end,
                    COALESCE(ps.work_start, '08:00:00') AS prev_work_start,
                    COALESCE(ps.work_end, '17:00:00')   AS prev_work_end
                FROM cand_emp ce
                LEFT JOIN employee_shifts es
                    ON es.npk = ce.npk AND CAST(es.shift_date AS DATE) = ce.work_date
                LEFT JOIN shifts s ON s.id = es.shift_id
                LEFT JOIN employee_shifts pes
                    ON pes.npk = ce.npk AND CAST(pes.shift_date AS DATE) = DATEADD(DAY, -1, ce.work_date)
                LEFT JOIN shifts ps ON ps.id = pes.shift_id
            ),
            emp_bounds AS (
                SELECT
                    es.*,
                    CAST(CONVERT(VARCHAR(10), es.work_date, 120) + ' ' + CONVERT(VARCHAR(8), es.work_start, 108) AS DATETIME) AS shift_start_dt,
                    CASE
                        WHEN es.work_end < es.work_start
                            THEN DATEADD(DAY, 1, CAST(CONVERT(VARCHAR(10), es.work_date, 120) + ' ' + CONVERT(VARCHAR(8), es.work_end, 108) AS DATETIME))
                        ELSE CAST(CONVERT(VARCHAR(10), es.work_date, 120) + ' ' + CONVERT(VARCHAR(8), es.work_end, 108) AS DATETIME)
                    END AS shift_end_dt,
                    CASE
                        WHEN es.prev_work_end < es.prev_work_start
                            THEN DATEADD(DAY, 1, CAST(CONVERT(VARCHAR(10), DATEADD(DAY, -1, es.work_date), 120) + ' ' + CONVERT(VARCHAR(8), es.prev_work_end, 108) AS DATETIME))
                        ELSE CAST(CONVERT(VARCHAR(10), DATEADD(DAY, -1, es.work_date), 120) + ' ' + CONVERT(VARCHAR(8), es.prev_work_end, 108) AS DATETIME)
                    END AS prev_shift_end_dt
                FROM emp_shift es
            ),
            emp_window AS (
                SELECT
                    eb.*,
                    DATEADD(MINUTE, 120, eb.shift_end_dt) AS scan_upper_bound
                FROM emp_bounds eb
            ),
            scans AS (
                SELECT
                    ew.pin, ew.npk, ew.work_date,
                    a.scan_date,
                    ew.shift_start_dt,
                    ew.shift_end_dt
                FROM emp_window ew
                JOIN att_log_window a
                    ON CAST(a.pin AS VARCHAR) = CAST(ew.pin AS VARCHAR)
                    AND a.scan_date >= DATEADD(HOUR, -4, ew.shift_start_dt)
                    AND a.scan_date <= ew.scan_upper_bound
                    AND a.scan_date > DATEADD(MINUTE, 120, ew.prev_shift_end_dt)
            ),
            scan_ranked AS (
                SELECT
                    npk, work_date, scan_date, shift_start_dt,
                    ABS(DATEDIFF(MINUTE, scan_date, shift_start_dt)) AS dist_to_start,
                    ABS(DATEDIFF(MINUTE, scan_date, shift_end_dt))   AS dist_to_end,
                    ROW_NUMBER() OVER (PARTITION BY npk, work_date ORDER BY ABS(DATEDIFF(MINUTE, scan_date, shift_start_dt))) AS rn_masuk,
                    ROW_NUMBER() OVER (PARTITION BY npk, work_date ORDER BY ABS(DATEDIFF(MINUTE, scan_date, shift_end_dt)))   AS rn_pulang,
                    COUNT(*) OVER (PARTITION BY npk, work_date) AS total_scan
                FROM scans
            ),
            grid AS (
                SELECT e.pin, e.npk, e.nama, e.bagian, ds.work_date
                FROM emp e
                CROSS JOIN DateSeries ds
            )
            SELECT
                g.npk, g.nama, g.bagian, g.work_date,

                CASE
                    WHEN m.scan_date IS NULL THEN 'not scanned'
                    WHEN m.total_scan = 1 AND m.dist_to_end < m.dist_to_start THEN 'not scanned'
                    ELSE CONVERT(VARCHAR(8), m.scan_date, 108)
                END AS jam_masuk,

                CASE
                    WHEN p.scan_date IS NULL THEN 'not scanned'
                    WHEN p.total_scan = 1 AND p.dist_to_start <= p.dist_to_end THEN 'not scanned'
                    ELSE CONVERT(VARCHAR(8), p.scan_date, 108)
                END AS jam_pulang,

                CASE
                    WHEN m.scan_date IS NOT NULL
                        AND NOT (m.total_scan = 1 AND m.dist_to_end < m.dist_to_start)
                        AND m.scan_date > DATEADD(MINUTE, 10, m.shift_start_dt)
                    THEN 1 ELSE 0
                END AS is_late

            FROM grid g
            LEFT JOIN scan_ranked m ON m.npk = g.npk AND m.work_date = g.work_date AND m.rn_masuk = 1
            LEFT JOIN scan_ranked p ON p.npk = g.npk AND p.work_date = g.work_date AND p.rn_pulang = 1
            ORDER BY g.bagian ASC, g.npk ASC, g.work_date ASC
            OPTION (MAXRECURSION 400)
        ";
    }
}