<?php

namespace App\Http\Controllers;

use App\Models\AttendanceFinger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\AttendanceFingerExport;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceFingerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $attendanceFingers = DB::connection('audit')->table('att_log')->orderBy('scan_date', 'desc')->limit(10)->get();
        return view('attendance_finger.index', compact('attendanceFingers'));
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
            $sourceData = DB::connection('fingerspot')
                ->table('att_log')
                ->whereDate('scan_date', $request->date)
                ->get();

            if ($sourceData->isEmpty()) {
                return response()->json(['message' => 'No data found for this date'], 404);
            }

            $count = 0;
            foreach ($sourceData as $data) {
                DB::connection('audit')->table('att_log')->updateOrInsert(
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
}
