<?php

namespace App\Http\Controllers;

use App\Models\AttendanceFinger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\AttendanceFingerExport;
use App\Exports\AttendanceNotFingerExport;
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

                // main query dari biodata
                $data = DB::connection('cii')->select("
                    SELECT
                        b.BARCODE AS pin,
                        b.NAMA_KARYAWAN AS nama,
                        b.NPK AS npk,
                        d.DEPARTEMENT AS bagian,
                        CONVERT(varchar, MIN(a.scan_date), 108) AS jam_masuk,
                        CONVERT(varchar, MAX(a.scan_date), 108) AS jam_pulang,
                        COUNT(a.scan_date) AS total_scan
                    FROM BIODATA b
                    LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT
                    JOIN att_log a ON CAST(a.pin AS VARCHAR) = CAST(b.BARCODE AS VARCHAR)
                    WHERE a.scan_date >= ? AND a.scan_date <= ?
                    GROUP BY
                        b.BARCODE,
                        b.NAMA_KARYAWAN,
                        b.NPK,
                        d.DEPARTEMENT
                    ORDER BY MIN(a.scan_date) ASC
                ", [$date . ' 00:00:00', $date . ' 23:59:59']);

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

        return Excel::download(new AttendanceFingerExport($request->date), 'attendance_finger_' . $request->date . '.xlsx');
    }

    /**
     * Datatable: employees who did NOT finger on the given date.
     */
    public function notFinger(Request $request)
    {
        if ($request->ajax()) {
            try {
                $date = $request->filled('date') ? $request->date : now()->toDateString();

                $data = DB::connection('cii')->select("
                    SELECT
                        b.BARCODE  AS pin,
                        b.NAMA_KARYAWAN AS nama,
                        b.NPK       AS npk,
                        d.DEPARTEMENT AS bagian,
                        b.STATUS    AS status
                    FROM BIODATA b
                    LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT
                    WHERE b.STATUS = 'A'
                      AND NOT EXISTS (
                          SELECT 1
                          FROM att_log a
                          WHERE CAST(a.pin AS VARCHAR) = CAST(b.BARCODE AS VARCHAR)
                            AND a.scan_date >= ? AND a.scan_date <= ?
                      )
                    ORDER BY b.NPK ASC
                ", [$date . ' 00:00:00', $date . ' 23:59:59']);

                return datatables()->of($data)->addIndexColumn()->make(true);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()]);
            }
        }

        return response()->json(['error' => 'Invalid request'], 400);
    }

    /**
     * Export: employees who did NOT finger on the given date.
     */
    public function exportNotFinger(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        return Excel::download(
            new AttendanceNotFingerExport($request->date),
            'not_finger_' . $request->date . '.xlsx'
        );
    }
}
