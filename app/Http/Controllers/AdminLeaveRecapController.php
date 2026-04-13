<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class AdminLeaveRecapController extends Controller
{
    public function index()
    {
        return view('leave-recap.index');
    }

    public function getData(Request $request)
    {
        $query = LeaveRequest::with('leaveType')
            ->whereColumn('approval_level', 'approval_progress')
            ->select('leave_requests.*');

        if ($request->has('month_year') && $request->month_year != '') {
            $parts = explode('-', $request->month_year);
            if (count($parts) == 2) {
                $query->whereYear('start_date', $parts[0])
                      ->whereMonth('start_date', $parts[1]);
            }
        }

        $activeRequests = $query->get()->unique('token');

        $npkList = $activeRequests->pluck('NPK')->unique()->toArray();
        
        $employees = DB::connection('cii')->table('BIODATA')
            ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->whereIn('BIODATA.NPK', $npkList)
            ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
            ->get()
            ->keyBy('NPK');

        $rows = [];
        foreach ($activeRequests as $activeRow) {
            $employee = $employees->get($activeRow->NPK);

            if ($activeRow->status === 'rejected') {
                $overallStatus = 'rejected';
            } elseif ($activeRow->status === 'approved') {
                $overallStatus = 'approved';
            } else {
                $overallStatus = $activeRow->approval_level > 1 ? 'partial' : 'pending';
            }

            $rows[] = [
                'token'          => $activeRow->token,
                'npk'            => $activeRow->NPK,
                'nama'           => $employee ? $employee->NAMA_KARYAWAN : $activeRow->NPK,
                'dept'           => $employee ? $employee->DEPARTEMENT : '-',
                'leave_type'     => $activeRow->leaveType->name ?? '-',
                'start_date'     => $activeRow->start_date,
                'end_date'       => $activeRow->end_date,
                'total_days'     => $activeRow->total_days,
                'reason'         => $activeRow->reason,
                'overall_status' => $overallStatus,
                'created_at'     => $activeRow->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $collection = collect($rows);

        if ($request->has('status') && $request->status != '') {
            $collection = $collection->where('overall_status', $request->status);
        }

        return DataTables::of($collection)
            ->addIndexColumn()
            ->addColumn('karyawan', function($row) {
                return '<strong>'.$row['nama'].'</strong><br><small class="text-muted">'.$row['npk'].' &middot; '.$row['dept'].'</small>';
            })
            ->addColumn('periode', function($row) {
                $start = Carbon::parse($row['start_date'])->format('d M y');
                $end   = Carbon::parse($row['end_date'])->format('d M y');
                return $start . ' – ' . $end;
            })
            ->addColumn('hari', function($row) {
                return $row['total_days'] . ' hari';
            })
            ->addColumn('status_badge', function($row) {
                if($row['overall_status'] === 'approved') return '<span class="badge badge-success">Disetujui</span>';
                elseif($row['overall_status'] === 'rejected') return '<span class="badge badge-danger">Ditolak</span>';
                elseif($row['overall_status'] === 'partial') return '<span class="badge badge-warning text-white">Parsial</span>';
                return '<span class="badge badge-secondary">Menunggu</span>';
            })
            ->addColumn('action', function($row) {
                return '<button class="btn btn-sm btn-info btn-detail" data-token="'.$row['token'].'"><i class="fas fa-eye"></i> Detail</button>';
            })
            ->rawColumns(['karyawan', 'status_badge', 'action'])
            ->make(true);
    }

    public function getDetail($token)
    {
        $requests = LeaveRequest::with('leaveType')
            ->where('token', $token)
            ->orderBy('approval_level', 'asc')
            ->get();

        if ($requests->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Data not found']);
        }

        $firstReq = $requests->first();
        $employee = DB::connection('cii')->table('BIODATA')
            ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->where('BIODATA.NPK', $firstReq->NPK)
            ->select('BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
            ->first();

        $approverNpks = $requests->pluck('approval_id')->unique()->toArray();
        $approvers = DB::connection('cii')->table('BIODATA')
            ->whereIn('NPK', $approverNpks)
            ->pluck('NAMA_KARYAWAN', 'NPK');

        $detailPaths = $requests->map(function($req) use ($approvers) {
            return [
                'level' => $req->approval_level,
                'approver_name' => $approvers->get($req->approval_id, $req->approval_id),
                'status' => $req->status,
                'date' => $req->approval_date ? Carbon::parse($req->approval_date)->format('d M Y') : '-',
                'comment' => $req->comment,
            ];
        });

        $data = [
            'npk' => $firstReq->NPK,
            'nama' => $employee ? $employee->NAMA_KARYAWAN : $firstReq->NPK,
            'dept' => $employee ? $employee->DEPARTEMENT : '-',
            'leave_type' => $firstReq->leaveType->name ?? '-',
            'start_date' => Carbon::parse($firstReq->start_date)->format('d M Y'),
            'end_date' => Carbon::parse($firstReq->end_date)->format('d M Y'),
            'total_days' => $firstReq->total_days,
            'reason' => $firstReq->reason,
            'approvals' => $detailPaths,
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }
}
