<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveTypes;
use App\Models\Overtime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class LeaveApprovalController extends Controller
{
    /**
     * Tampilkan data leave yang butuh di-approve (atau sudah di-approve)
     * oleh user/admin yang sedang login
     */
    public function index()
    {
        $npk = session('cuti_employee_npk');
        if (!$npk) {
            return redirect()->route('pengajuan-cuti.login');
        }

        // Cari data karyawan yang login sebagai approver (berdasarkan sesi NPK)
        $employee = DB::connection('cii')
            ->table('BIODATA')
            ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->where('BIODATA.NPK', $npk)
            ->select('BIODATA.*', 'DEPT.DEPARTEMENT', 'DEPT.IS_SEWING')
            ->first();

        if (!$employee) {
            return redirect()->route('pengajuan-cuti.login');
        }

        $approverNpk = $employee->NPK;

        // Ambil SEMUA permohonan yg menunjuk NPK ini dan levelnya sedang aktif (approval_level = approval_progress)
        $leaveRequestsQuery = LeaveRequest::where('approval_id', $approverNpk)
            ->whereColumn('approval_level', 'approval_progress')
            ->orderBy('created_at', 'desc')
            ->get();

        $rows = [];
        foreach ($leaveRequestsQuery as $req) {
            $bioEmployee = DB::connection('cii')->table('BIODATA')
                ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->where('BIODATA.NPK', $req->NPK)
                ->select('BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
                ->first();

            $leaveType = LeaveTypes::find($req->leave_type_id);

            $rows[] = [
                'id'             => $req->id,
                'token'          => $req->token,
                'npk'            => $req->NPK,
                'nama'           => $bioEmployee ? $bioEmployee->NAMA_KARYAWAN : $req->NPK,
                'dept'           => $bioEmployee ? $bioEmployee->DEPARTEMENT : '-',
                'leave_type'     => $leaveType ? $leaveType->name : '-',
                'start_date'     => $req->start_date,
                'end_date'       => $req->end_date,
                'total_days'     => $req->total_days,
                'reason'         => $req->reason,
                'status'         => $req->status,
                'created_at'     => $req->created_at,
                'approval_level' => $req->approval_level,
            ];
        }

        if (request()->ajax()) {
            return \Yajra\DataTables\Facades\DataTables::of(collect($rows))
                ->addIndexColumn()
                ->addColumn('karyawan', function($row) {
                    return '<strong>'.$row['nama'].'</strong><br><small class="text-muted">'.$row['npk'].' &middot; '.$row['dept'].'</small>';
                })
                ->addColumn('periode', function($row) {
                    $start = Carbon::parse($row['start_date'])->format('d M Y');
                    $end   = Carbon::parse($row['end_date'])->format('d M Y');
                    return $start . ' – ' . $end;
                })
                ->addColumn('hari', function($row) {
                    return $row['total_days'] . ' hari';
                })
                ->addColumn('alasan', function($row) {
                    return $row['reason'] ?: '-';
                })
                ->addColumn('status_badge', function($row) {
                    if($row['status'] === 'approved') return '<span class="badge badge-success">Disetujui</span>';
                    elseif($row['status'] === 'rejected') return '<span class="badge badge-danger">Ditolak</span>';
                    return '<span class="badge badge-warning text-white">Menunggu</span>';
                })
                ->addColumn('aksi', function($row) {
                    if($row['status'] === 'pending') {
                        return '<button type="button" class="btn btn-sm btn-success btn-approve" data-id="'.$row['id'].'" data-nama="'.$row['nama'].'"><i class="fas fa-check fa-sm"></i> Approve</button> ' .
                               '<button type="button" class="btn btn-sm btn-danger btn-reject" data-id="'.$row['id'].'" data-nama="'.$row['nama'].'"><i class="fas fa-times fa-sm"></i> Reject</button>';
                    }
                    return '<span class="text-muted small">Sudah diproses</span>';
                })
                ->rawColumns(['karyawan', 'status_badge', 'aksi'])
                ->make(true);
        }

        return view('cuti.approval', compact('employee'));
    }

    /**
     * Proses approve dari form Admin
     */
    public function approve($id)
    {
        try {
            DB::beginTransaction();

            $leave = LeaveRequest::findOrFail($id);
            if ($leave->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Status sudah diproses sebelumnya']);
            }

            // Update status ini jadi approved
            $leave->status = 'approved';
            $leave->approval_date = now();
            $leave->save();

            // Cek apakah ada proses level selanjutnya di token ini?
            $nextLevel = LeaveRequest::where('token', $leave->token)
                ->where('approval_level', '>', $leave->approval_level)
                ->orderBy('approval_level', 'asc')
                ->first();

            if ($nextLevel) {
                LeaveRequest::where('token', $leave->token)
                    ->update(['approval_progress' => $nextLevel->approval_level]);
            } else {
                $lastApprove = LeaveRequest::where('token', $leave->token)
                    ->where('approval_level', $leave->approval_level)
                    ->where('status', 'approved')
                    ->latest()
                    ->get();

                DB::table('leave_balances')
                    ->where('NPK', $leave->NPK)
                    ->where('leave_type_id', $leave->leave_type_id)
                    ->where('year', date('Y'))
                    ->update([
                        'used_days' => DB::raw('used_days + ' . $leave->total_days),
                        'remained_days' => DB::raw('remained_days - ' . $leave->total_days)
                    ]);
                
                $karyawan = DB::connection('cii')->table('BIODATA')
                    ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                    ->where('BIODATA.NPK', $leave->NPK)
                    ->select('BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT', 'BIODATA.ID_DEPT', 'BIODATA.IS_STAFF', 'DEPT.IS_SEWING')
                    ->first();

                $holidays = json_decode(file_get_contents(storage_path('app/calendar.json')), true);
                $holidays = array_filter($holidays, function($item) {
                    return isset($item['holiday']) && $item['holiday'] === true;
                });
                $holidays = array_keys($holidays);

                $startDate = Carbon::parse($leave->start_date);
                $endDate = Carbon::parse($leave->end_date);

                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                    $dayOfWeek = $date->dayOfWeek; // 0 = Minggu, 6 = Sabtu
                    $dateString = $date->format('Y-m-d');

                    // Lewati hari Sabtu, Minggu, atau Hari Libur Nasional
                    if ($dayOfWeek == 0 || $dayOfWeek == 6 || in_array($dateString, $holidays)) {
                        continue;
                    }

                    // Suntikan data ke tabel Overtime
                    Overtime::updateOrCreate(
                        [
                            'NPK' => $leave->NPK,
                            'OVERTIME_DATE' => $dateString,
                        ],
                        [
                            'NAMA_KARYAWAN' => $karyawan ? $karyawan->NAMA_KARYAWAN : $leave->NPK,
                            'BAGIAN' => $karyawan ? $karyawan->DEPARTEMENT : '-',
                            'DAY' => $date->translatedFormat('l'),
                            'JUMLAH_JAM_LEMBUR' => 'CT',
                            // cek dept group dari biodata dan dept
                            'DEPT_GROUP' => '',
                        ]
                    );
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Permohonan Cuti berhasil disetujui.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Proses cancel (reject) dari form Admin
     */
    public function reject(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $leave = LeaveRequest::findOrFail($id);
            if ($leave->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Status sudah diproses sebelumnya']);
            }

            // Set ke rejected
            $leave->status = 'rejected';
            $leave->comment = $request->comment;
            $leave->approval_date = now();
            $leave->save();

            // Sisa token lainnya kita biarkan saja approval_progress nya tertahan atau kita set status jadi tertolak juga
            LeaveRequest::where('token', $leave->token)
                ->where('id', '!=', $leave->id)
                ->update(['status' => 'rejected', 'void' => 'true']);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Permohonan Cuti berhasil ditolak/cancel.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
