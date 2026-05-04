<?php

namespace App\Http\Controllers;

use App\Models\AttendanceFinger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\AttendanceExport;
use Carbon\Carbon;
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
                $date = $request->filled('date') ? $request->date : now()->toDateString();

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





                // // main query dari biodata (LAST WORK)
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



                $yesterday = \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d');
                $data = DB::connection('cii')->select("
                    SELECT
                        b.BARCODE               AS pin,
                        b.NAMA_KARYAWAN         AS nama,
                        b.NPK                   AS npk,
                        d.DEPARTEMENT           AS bagian,
                        b.SECTION               AS section,
                        b.BAG                   AS jabatan,
                        b.STATUS                AS status,

                        -- jam_masuk: scan pertama jika > 1, atau single scan di paruh awal shift
                        CASE
                            WHEN COUNT(a.scan_date) > 1
                                THEN CONVERT(varchar(8), MIN(a.scan_date), 108)
                            WHEN MIN(a.scan_date) <= DATEADD(
                                hour, 4,
                                CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
                            )
                                THEN CONVERT(varchar(8), MIN(a.scan_date), 108)
                            ELSE 'not scanned'
                        END                                             AS jam_masuk,

                        -- jam_pulang: scan terakhir jika > 1, atau single scan di paruh akhir shift
                        CASE
                            WHEN COUNT(a.scan_date) > 1
                                THEN CONVERT(varchar(8), MAX(a.scan_date), 108)
                            WHEN MIN(a.scan_date) > DATEADD(
                                hour, 4,
                                CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
                            )
                                THEN CONVERT(varchar(8), MIN(a.scan_date), 108)
                            ELSE 'not scanned'
                        END                                             AS jam_pulang,

                        COUNT(a.scan_date)                              AS total_scan,
                        COALESCE(s.name, 'Normal Shift')                AS shift_name,
                        CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS shift_start,

                        -- is_late: toleransi 10 menit, hanya jika ada scan masuk
                        CASE
                            WHEN (
                                COUNT(a.scan_date) > 1
                                OR MIN(a.scan_date) <= DATEADD(
                                    hour, 4,
                                    CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
                                )
                            )
                            AND MIN(a.scan_date) > DATEADD(
                                minute, 10,
                                CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
                            )
                            THEN 1
                            ELSE 0
                        END AS is_late

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

                    JOIN att_log a
                        ON CAST(a.pin AS VARCHAR) = CAST(b.BARCODE AS VARCHAR)

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
                                    ELSE
                                        CAST(? + ' ' + CONVERT(varchar(8), prev_s.work_end, 108) AS DATETIME)
                                END
                            ),
                            CAST('1900-01-01 00:00:00' AS DATETIME)
                        )

                    GROUP BY
                        b.BARCODE,
                        b.NAMA_KARYAWAN,
                        b.NPK,
                        d.DEPARTEMENT,
                        b.SECTION,
                        b.BAG,
                        b.STATUS,
                        s.name,
                        s.work_start
                    ORDER BY d.DEPARTEMENT ASC, b.NPK ASC
                ", [
                    $date,          // jam_masuk midpoint
                    $date,          // jam_pulang midpoint
                    $date,          // is_late masuk check
                    $date,          // is_late 10 min tolerance
                    $date,          // es.shift_date
                    $yesterday,     // prev_es.shift_date
                    $date,          // scan_date lower bound
                    $date,          // scan_date upper bound
                    $date,          // prev night shift work_end
                    $yesterday,     // prev normal shift work_end
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

}
