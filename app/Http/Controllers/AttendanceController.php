<?php

namespace App\Http\Controllers;

use App\Exports\AuditExport;
use App\Imports\AttendanceImport;
use App\Models\Attendance;
use App\Models\Audit;
use Dompdf\Dompdf;
use Barryvdh\Snappy\Facades\SnappyPdf;
// use Dompdf\Options;
// use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
// use Knp\Snappy\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\Facades\DataTables;

class AttendanceController extends Controller
{
    public function index()
    {
        // Use Cache Cache for employee group
        $employeeGroupChutex = DB::connection('audit')->table('AUDIT')
            ->select(DB::raw('MIN(SUBDIVISI) AS SUBDIVISI'), 'KODE_BAGIAN')
            ->groupBy('KODE_BAGIAN')
            ->orderBy('KODE_BAGIAN', 'ASC')
            ->get();

        return view('attendance.index', compact('employeeGroupChutex'));
    }

    public function showAttendance()
    {
        $query = DB::connection('audit')->table('AUDIT')
            ->select('id', 'NPK', 'NAMA_KARYAWAN', 'KODE_BAGIAN', 'SUBDIVISI', 'TANGGAL', 'JAM_PAGI', 'JAM_SIANG', 'JAM_MALAM', 'STATUS')
            ->orderBy('NPK', 'ASC')
            ->take(50)
            ->get();

        return DataTables::of($query)
            ->addIndexColumn()
            ->make(true);
    }

    public function import(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|mimes:xls,xlsx'
        ]);

        $file = $request->file('file');
        $nama_file = $file->hashName();
        $path = $file->storeAs('public/excel/', $nama_file);
        $import = Excel::import(new AttendanceImport(), storage_path('app/public/excel/' . $nama_file));
        Storage::delete($path);

        if ($import) {
            Alert::success('Import Successfully!', 'Attendance data successfully imported!');
            return redirect()->intended('attendance/index')->with(['success' => 'Data Berhasil Diimport!']);
        } else {
            return redirect()->intended('attendance/index')->with(['error' => 'Data Gagal Diimport!']);
        }
    }

    public function export(Request $request)
    {
        $dates = explode(',', $request->holiday_date);
        $days = array_map(function ($dateStr) {
            $parts = explode('/', trim($dateStr));
            return $parts[1];
        }, $dates);

        return response()->json(['success' => true, 'days' => $days]);
    }

    public function report(Request $request)
    {
        $employeeGroupChutex = DB::connection('audit')->table('AUDIT')->select('NPK', 'KODE_BAGIAN', 'SUBDIVISI')->distinct('NPK', 'KODE_BAGIAN', 'SUBDIVISI')->whereIn('KODE_BAGIAN', $request->department);
        $employeeGroup = $employeeGroupChutex->orderBy('KODE_BAGIAN', 'ASC')->orderBy('NPK', 'ASC')->get();

        $employeesChutex = DB::connection('audit')->table('AUDIT')->select('NPK', 'NAMA_KARYAWAN', 'KODE_BAGIAN', 'SUBDIVISI', 'TANGGAL', 'JAM_PAGI', 'JAM_SIANG', 'JAM_MALAM', 'STATUS AS KETERANGAN')->whereIn('KODE_BAGIAN', $request->department)->whereBetween('TANGGAL', [$request->fromdate, $request->todate]);
        $employees = $employeesChutex->orderBy('KODE_BAGIAN', 'ASC')->orderBy('NPK', 'ASC')->orderBy('TANGGAL', 'ASC')->get();

        $days = $request->days;
        return view('template.report-final', compact('employees', 'employeeGroup', 'days'));
    }

    public function export_view(Request $request)
    {
        return Excel::download(
            new AuditExport(
                $request->fromdate,
                $request->todate,
                $request->department,
                $request->days
            ),
            'audit.xlsx'
        );
    }

    public function deleteAll(Request $request)
    {
        $deleteAllData = DB::connection('audit')->table('AUDIT')->truncate();

        if ($deleteAllData) {
            Alert::success('Delete Successfully!', 'All attendance data successfully deleted!');
            return redirect()->intended('attendance/index')->with(['success' => 'All Attendance Data Successfully Deleted!']);
        } else {
            return redirect()->intended('attendance/index')->with(['error' => 'Failed to Delete Attendance Data!']);
        }
    }


    public function checkMasterData()
    {
        $checkDatas = DB::connection('audit')->table('AUDIT')
            ->select(DB::raw('COUNT(NPK) AS COUNT'), 'NPK', 'TANGGAL')
            ->groupBy('NPK', 'TANGGAL')
            ->having(DB::raw('COUNT(NPK)'), '>=', 2)
            ->orderByDesc('COUNT')
            ->get();
        return view('attendance.check-master-data', compact('checkDatas'));
    }

    public function edit($id)
    {
        $employee = DB::connection('audit')->table('AUDIT')->where('id', $id)->first();
        return view('attendance.update', compact('employee'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'jam_pagi' => 'nullable|date_format:H:i',
            'jam_siang' => 'nullable|date_format:H:i',
            'jam_malam' => 'nullable|date_format:H:i',
        ]);

        $update = DB::connection('audit')->table('AUDIT')->where('id', $id)->update([
            'JAM_PAGI' => $request->jam_pagi,
            'JAM_SIANG' => $request->jam_siang,
            'JAM_MALAM' => $request->jam_malam,
            'updated_at' => now(),
        ]);

        if ($update) {
            Alert::success('Update Successfully!', 'Attendance data successfully updated!');
            return redirect()->intended('attendance/index')->with(['success' => 'Attendance Data Successfully Updated!']);
        } else {
            return redirect()->intended('attendance/index')->with(['error' => 'Failed to Update Attendance Data!']);
        }
    }

    // =========================================================================================================================================


    // public function export(Request $request)
    // {
    //     // dd($request->all());
    //     if ($request->department === 'sewing') {
    //         $employeeGroupChutex = DB::connection('sqlsrv')->table('AUDIT')->select('NPK', 'KODE_BAGIAN', 'SUBDIVISI')->distinct('NPK', 'KODE_BAGIAN', 'SUBDIVISI')->where('SUBDIVISI', 'LIKE', "%LINE%");
    //         $employeeGroup = $employeeGroupChutex->orderBy('KODE_BAGIAN', 'ASC')->orderBy('NPK', 'ASC')->get();

    //         $employeesChutex = DB::connection('sqlsrv')->table('AUDIT')->select('NPK', 'NAMA_KARYAWAN', 'KODE_BAGIAN', 'SUBDIVISI', 'TANGGAL', 'JAM_PAGI', 'JAM_SIANG', 'JAM_MALAM', 'STATUS AS KETERANGAN')->where('SUBDIVISI', 'LIKE', "%LINE%")->where('TANGGAL', '>=', $request->fromdate)->where('TANGGAL', '<=', $request->todate);
    //         $employees = $employeesChutex->orderBy('KODE_BAGIAN', 'ASC')->orderBy('NPK', 'ASC')->orderBy('TANGGAL', 'ASC')->get();
    //     } else {
    //         $employeeGroupChutex = DB::connection('sqlsrv')->table('AUDIT')->select('NPK', 'KODE_BAGIAN', 'SUBDIVISI')->distinct('NPK', 'KODE_BAGIAN', 'SUBDIVISI')->where('SUBDIVISI', 'NOT LIKE', "%LINE%");
    //         $employeeGroup = $employeeGroupChutex->orderBy('KODE_BAGIAN', 'ASC')->orderBy('NPK', 'ASC')->get();

    //         $employeesChutex = DB::connection('sqlsrv')->table('AUDIT')->select('NPK', 'NAMA_KARYAWAN', 'KODE_BAGIAN', 'SUBDIVISI', 'TANGGAL', 'JAM_PAGI', 'JAM_SIANG', 'JAM_MALAM', 'STATUS AS KETERANGAN')->where('SUBDIVISI', 'NOT LIKE', "%LINE%")->where('TANGGAL', '>=', $request->fromdate)->where('TANGGAL', '<=', $request->todate);
    //         $employees = $employeesChutex->orderBy('KODE_BAGIAN', 'ASC')->orderBy('NPK', 'ASC')->orderBy('TANGGAL', 'ASC')->get();
    //     }

    //     // dd($employees);
    //     // $pdf = dompdf::loadView('template.report2', compact(['employees', 'employeeGroup']));
    //     $pdf = PDF::loadView('template.report2', compact(['employees', 'employeeGroup']));
    //     $pdf->setPaper('A4', 'landscape');
    //     $pdf->render();

    //     return $pdf->download('Data Absen.pdf');
    // }


    // public function auditsewing()
    // {
    //     $employeeGroupChutex = DB::connection('sqlsrv')->table('AUDIT')->select('NPK', 'KODE_BAGIAN', 'SUBDIVISI')->distinct('NPK', 'KODE_BAGIAN', 'SUBDIVISI')->where('SUBDIVISI', 'LIKE', "%LINE%");
    //     $employeeGroup = $employeeGroupChutex->orderBy('KODE_BAGIAN', 'ASC')->orderBy('NPK', 'ASC')->get();

    //     $employeesChutex = DB::connection('sqlsrv')->table('AUDIT')->select('NPK', 'NAMA_KARYAWAN', 'KODE_BAGIAN', 'SUBDIVISI', 'TANGGAL', 'JAM_PAGI', 'JAM_SIANG', 'JAM_MALAM', 'STATUS AS KETERANGAN')->where('SUBDIVISI', 'LIKE', "%LINE%");
    //     $employees = $employeesChutex->orderBy('KODE_BAGIAN', 'ASC')->orderBy('NPK', 'ASC')->orderBy('TANGGAL', 'ASC')->get();

    //     return view('template.report2', compact('employees', 'employeeGroup'));
    // }

    // public function auditnonsewing(Request $request)
    // {
    //     $employeeGroupChutex = DB::connection('sqlsrv')->table('AUDIT')->select('NPK', 'KODE_BAGIAN', 'SUBDIVISI')->distinct('NPK', 'KODE_BAGIAN', 'SUBDIVISI')->where('SUBDIVISI', 'NOT LIKE', "%LINE%");
    //     $employeeGroup = $employeeGroupChutex->orderBy('KODE_BAGIAN', 'ASC')->orderBy('NPK', 'ASC')->get();

    //     $employeesChutex = DB::connection('sqlsrv')->table('AUDIT')->select('NPK', 'NAMA_KARYAWAN', 'KODE_BAGIAN', 'SUBDIVISI', 'TANGGAL', 'JAM_PAGI', 'JAM_SIANG', 'JAM_MALAM', 'STATUS AS KETERANGAN')->where('SUBDIVISI', 'NOT LIKE', "%LINE%");
    //     $employees = $employeesChutex->orderBy('KODE_BAGIAN', 'ASC')->orderBy('NPK', 'ASC')->orderBy('TANGGAL', 'ASC')->get();

    //     return view('template.report2', compact('employees', 'employeeGroup'));
    // }
}
