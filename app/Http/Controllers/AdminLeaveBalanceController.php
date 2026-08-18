<?php

namespace App\Http\Controllers;

use App\Models\LeaveBalances;
use App\Models\LeaveTypes;
use App\Services\LeaveBalanceGenerationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminLeaveBalanceController extends Controller
{
    public function index(Request $request)
    {
        $departments = DB::connection('cii')->table('DEPT')->select('ID_DEPT', 'DEPARTEMENT')->where('SECTION', 'CHUTEX')->get();
        return view('leave-balance.index', compact('departments'));
    }

    public function getData(Request $request)
    {

        $npkWithBalances = LeaveBalances::where('year', $request->year)
            ->pluck('NPK')
            ->unique()
            ->toArray();

        $query = DB::connection('cii')
            ->table('BIODATA')
            ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
            ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->whereIn('BIODATA.NPK', $npkWithBalances);

        if ($request->has('department_id') && $request->department_id != '') {
            $query->where('BIODATA.ID_DEPT', $request->department_id);
        }

        $biodatas = $query->get();

        return response()->json([
            'data' => $biodatas,
        ]);
    }

    public function show(Request $request, $NPK)
    {
        $year = $request->input('year', date('Y'));
        
        $employee = DB::connection('cii')->table('BIODATA')
            ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
            ->where('NPK', $NPK)->first();

        if (!$employee) {
            abort(404, 'Employee not found');
        }

        $balances = LeaveBalances::where('NPK', $NPK)
            ->where('year', $year)
            ->join('leave_types', 'leave_balances.leave_type_id', '=', 'leave_types.id')
            ->select('leave_balances.*', 'leave_types.name', 'leave_types.code')
            ->get();

        $balances_with_calc = $balances->map(function($bal) {
            // remained_days = sisa aktual (bisa berkurang karena penggunaan)
            // available dihitung dari remained_days langsung (sudah final)
            return $bal;
        });

        $leaveTypes = LeaveTypes::where('is_active', true)->get();
        
        $details = $leaveTypes->map(function($type) use ($balances_with_calc) {
            $rec = $balances_with_calc->firstWhere('leave_type_id', $type->id);
            return (object) [
                'balance_id'    => $rec ? $rec->id : null,
                'leave_type_id' => $type->id,
                'type_name'     => $type->name,
                'code'          => $type->code,
                'default_days'  => $type->default_days,           // Jatah resmi dari leave_types
                'used_days'     => $rec ? $rec->used_days : 0,    // Hari terpakai
                'remained_days' => $rec ? $rec->remained_days : 0, // Sisa aktual dari leave_balances
            ];
        });

        $requestsQuery = \App\Models\LeaveRequest::with('leaveType')
            ->where('NPK', $NPK)
            ->whereYear('start_date', $year)
            ->whereColumn('approval_level', 'approval_progress')
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('token');

        $leaveHistory = [];
        foreach ($requestsQuery as $req) {
            if ($req->status === 'rejected') {
                $status = 'Ditolak';
                $badge = 'danger';
            } elseif ($req->status === 'approved') {
                $status = 'Disetujui';
                $badge = 'success';
            } else {
                $status = $req->approval_level > 1 ? 'Parsial' : 'Menunggu';
                $badge = $req->approval_level > 1 ? 'warning text-white' : 'secondary';
            }
            $leaveHistory[] = (object) [
                'leave_type' => $req->leaveType->name ?? '-',
                'start_date' => Carbon::parse($req->start_date)->format('d M Y'),
                'end_date'   => Carbon::parse($req->end_date)->format('d M Y'),
                'total_days' => $req->total_days,
                'reason'     => $req->reason,
                'status'     => $status,
                'badge'      => $badge,
                'comment'    => $req->comment,
            ];
        }

        return view('leave-balance.show', compact('employee', 'year', 'details', 'leaveTypes', 'leaveHistory'));
    }

    public function storeBalance(Request $request)
    {
        $request->validate([
            'NPK' => 'required',
            'leave_type_id' => 'required',
            'year' => 'required|numeric',
            'remained_days' => 'required|numeric',
            'used_days' => 'required|numeric',
        ]);

        $balance = LeaveBalances::updateOrCreate(
            [
                'NPK' => $request->NPK,
                'leave_type_id' => $request->leave_type_id,
                'year' => $request->year,
            ],
            [
                'remained_days' => $request->remained_days,
                'used_days' => $request->used_days,
            ]
        );

        return response()->json(['status' => 'success', 'data' => $balance]);
    }

    public function updateBalance(Request $request, $id)
    {
        $balance = LeaveBalances::find($id);
        if (!$balance) {
            return response()->json(['status' => 'error', 'message' => 'Data not found'], 404);
        }

        $balance->update($request->only(['remained_days', 'used_days']));

        return response()->json(['status' => 'success', 'data' => $balance]);
    }

    public function destroyBalance($id)
    {
        $balance = LeaveBalances::find($id);
        if (!$balance) {
            return response()->json(['status' => 'error', 'message' => 'Data not found'], 404);
        }

        $balance->delete();
        return response()->json(['status' => 'success']);
    }

    /**
     * Generate jatah cuti tahunan untuk seluruh karyawan tetap.
     * Logika sebenarnya ada di LeaveBalanceGenerationService (lihat app/Services),
     * supaya bisa dites & dipanggil dari tempat lain (mis. command/scheduler) tanpa
     * bergantung pada HTTP request.
     */
    public function generateYearlyBalance(Request $request, LeaveBalanceGenerationService $leaveBalanceGenerationService)
    {
        $year = (int) $request->input('year', now()->year);

        $result = $leaveBalanceGenerationService->generate($year);

        if (!$result['success']) {
            return response()->json(['status' => 'error', 'message' => $result['message']], 400);
        }

        return response()->json([
            'status'          => 'success',
            'message'         => "Generate Balance selesai untuk tahun $year",
            'year'            => $year,
            'employees_count' => $result['employees_count'],
            'created'         => $result['created'],
            'skipped'         => $result['skipped'],
            'skipped_gender'  => $result['skipped_gender'],
        ]);
    }
}