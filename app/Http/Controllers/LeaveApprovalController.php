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
    public function __construct()
    {
        $this->middleware('auth')->except([
            'portalIndex',
            'portalApprove',
            'portalReject',
            'portalUpdateDecision',
        ]);
    }

    /**
     * Tampilkan data leave yang butuh di-approve (atau sudah di-approve)
     * oleh user/admin yang sedang login
     */
    public function index()
    {
        $user = Auth::user();
        $npk = $user ? $user->npk : null;

        if (!$npk) {
            Alert::error('Error', 'Akun Anda tidak memiliki NPK yang terdaftar.');
            return redirect()->back();
        }

        return $this->renderApprovalList($npk, false);
    }

    /**
     * Tampilkan data leave yang butuh di-approve untuk portal karyawan
     * (tanpa auth middleware, berbasis session cuti_employee_npk)
     */
    public function portalIndex()
    {
        $npk = session('cuti_employee_npk');

        if (!$npk) {
            Alert::error('Error', 'Silahkan login terlebih dahulu.');
            return redirect()->route('pengajuan-cuti.login');
        }

        $isApprover = \App\Models\ApprovalRule::where('approval_id', $npk)->exists();
        if (!$isApprover) {
            Alert::error('Akses Ditolak', 'Anda tidak terdaftar sebagai approver cuti.');
            return redirect()->route('pengajuan-cuti.form');
        }

        return $this->renderApprovalList($npk, true);
    }

    /**
     * Shared render method for both admin and portal approval views
     */
    private function renderApprovalList($npk, $isPortal = false)
    {
        // Cari data karyawan yang login sebagai approver (berdasarkan NPK)
        $employee = DB::connection('cii')
            ->table('BIODATA')
            ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->where('BIODATA.NPK', $npk)
            ->select('BIODATA.*', 'DEPT.DEPARTEMENT', 'DEPT.IS_SEWING')
            ->first();

        if (!$employee) {
            $employee = (object) [
                'NPK' => $npk,
                'NAMA_KARYAWAN' => $npk,
                'DEPARTEMENT' => '-',
                'IS_SEWING' => 0
            ];
        }

        $approverNpk = $employee->NPK;

        // Tampilkan permohonan yang levelnya sedang aktif (butuh aksi approver ini)
        // ATAU yang statusnya sudah pernah diputuskan (approved/rejected) --
        // supaya permohonan yang sudah diproses TIDAK hilang dari daftar approver ini
        // walaupun approval_progress sudah maju ke level berikutnya.
        $query = LeaveRequest::where('approval_id', $approverNpk)
            ->where(function ($q) {
                $q->whereColumn('approval_level', 'approval_progress')
                  ->orWhere('status', '!=', 'pending');
            });

        // Filter tanggal: tampilkan permohonan yang periode cutinya beririsan
        // dengan rentang tanggal yang dipilih (start_date/end_date dari request).
        if ($startDate = request('start_date')) {
            $query->whereDate('end_date', '>=', $startDate);
        }
        if ($endDate = request('end_date')) {
            $query->whereDate('start_date', '<=', $endDate);
        }
        if ($status = request('status')) {
            $query->where('status', $status);
        }

        $leaveRequestsQuery = $query
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'asc')
            ->get();

        $rows = [];
        foreach ($leaveRequestsQuery as $req) {
            $bioEmployee = DB::connection('cii')->table('BIODATA')
                ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->where('BIODATA.NPK', $req->NPK)
                ->select('BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
                ->first();

            $leaveType = LeaveTypes::find($req->leave_type_id);

            // Approver cuma boleh mengubah keputusan selama belum ada level
            // berikutnya yang sudah bertindak (biar workflow tetap konsisten).
            $laterLevelActed = LeaveRequest::where('token', $req->token)
                ->where('approval_level', '>', $req->approval_level)
                ->where('status', '!=', 'pending')
                ->where('void', '!=', 'true')
                ->exists();

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
                'comment'        => $req->comment,
                'created_at'     => Carbon::parse($req->created_at)->format('d M Y H:i'),
                'approval_level' => $req->approval_level,
                'can_update'     => $req->status !== 'pending' && !$laterLevelActed,
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
                    $detailBtn = '<button type="button" class="btn btn-sm btn-info btn-detail" data-id="'.$row['id'].'"><i class="fas fa-eye fa-sm"></i> Detail</button>';

                    if ($row['status'] === 'pending') {
                        return $detailBtn . ' ' .
                               '<button type="button" class="btn btn-sm btn-success btn-approve" data-id="'.$row['id'].'" data-nama="'.$row['nama'].'"><i class="fas fa-check fa-sm"></i> Approve</button> ' .
                               '<button type="button" class="btn btn-sm btn-danger btn-reject" data-id="'.$row['id'].'" data-nama="'.$row['nama'].'"><i class="fas fa-times fa-sm"></i> Reject</button>';
                    }

                    return $detailBtn;
                })
                ->rawColumns(['karyawan', 'status_badge', 'aksi'])
                ->make(true);
        }

        $ajaxUrl = $isPortal ? route('pengajuan-cuti.portal-approval') : route('pengajuan-cuti.approval');
        $actionBaseUrl = $isPortal ? url('pengajuan-cuti/portal-approval') : url('pengajuan-cuti/approval');

        return view('cuti.approval', compact('employee', 'isPortal', 'ajaxUrl', 'actionBaseUrl'));
    }

    /**
     * Portal: Approve cuti dari portal karyawan
     */
    public function portalApprove($id)
    {
        $npk = session('cuti_employee_npk');
        if (!$npk) {
            return response()->json(['success' => false, 'message' => 'Sesi login telah berakhir. Silahkan login kembali.'], 401);
        }

        $leave = LeaveRequest::findOrFail($id);
        if ($leave->approval_id !== $npk) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak untuk memproses permohonan ini.'], 403);
        }

        return $this->approve($id);
    }

    /**
     * Portal: Reject cuti dari portal karyawan
     */
    public function portalReject(Request $request, $id)
    {
        $npk = session('cuti_employee_npk');
        if (!$npk) {
            return response()->json(['success' => false, 'message' => 'Sesi login telah berakhir. Silahkan login kembali.'], 401);
        }

        $leave = LeaveRequest::findOrFail($id);
        if ($leave->approval_id !== $npk) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak untuk memproses permohonan ini.'], 403);
        }

        return $this->reject($request, $id);
    }

    /**
     * Portal: Ubah keputusan dari portal karyawan
     */
    public function portalUpdateDecision(Request $request, $id)
    {
        $npk = session('cuti_employee_npk');
        if (!$npk) {
            return response()->json(['success' => false, 'message' => 'Sesi login telah berakhir. Silahkan login kembali.'], 401);
        }

        $leave = LeaveRequest::findOrFail($id);
        if ($leave->approval_id !== $npk) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak untuk memproses permohonan ini.'], 403);
        }

        return $this->updateDecision($request, $id);
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
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Status sudah diproses sebelumnya']);
            }

            $leave->status = 'approved';
            $leave->approval_date = now();
            $leave->save();

            $this->advanceOrFinalize($leave);

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
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Status sudah diproses sebelumnya']);
            }

            $leave->status = 'rejected';
            $leave->comment = $request->comment;
            $leave->approval_date = now();
            $leave->save();

            $this->voidSiblingRows($leave);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Permohonan Cuti berhasil ditolak/cancel.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Ubah keputusan yang SUDAH diambil sebelumnya (approved <-> rejected),
     * dipanggil dari tombol "Ubah" pada permohonan yang statusnya bukan pending lagi.
     *
     * Guard: hanya boleh diubah selama belum ada level approval berikutnya yang
     * sudah bertindak (approve/reject), supaya workflow multi-level tetap konsisten.
     *
     * PENTING: method ini membalik/menerapkan ulang efek samping approve()
     * (potong/kembalikan leave_balances, buat/hapus record Overtime "CT").
     * Karena menyentuh data cuti & lembur yang mengalir ke payroll, disarankan
     * untuk ditest dulu di staging sebelum dipakai di production.
     */
    public function updateDecision(Request $request, $id)
    {
        $request->validate([
            'new_status' => 'required|in:approved,rejected',
        ]);

        try {
            DB::beginTransaction();

            $leave = LeaveRequest::findOrFail($id);

            if ($leave->status === 'pending') {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Gunakan tombol Approve/Reject untuk permohonan yang masih menunggu.']);
            }

            $laterLevelActed = LeaveRequest::where('token', $leave->token)
                ->where('approval_level', '>', $leave->approval_level)
                ->where('status', '!=', 'pending')
                ->where('void', '!=', 'true')
                ->exists();

            if ($laterLevelActed) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Tidak bisa diubah, proses approval sudah berjalan ke level berikutnya.']);
            }

            $oldStatus = $leave->status;
            $newStatus = $request->new_status;

            if ($oldStatus === $newStatus) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Status baru sama dengan status sebelumnya.']);
            }

            // Batalkan efek keputusan lama sebelum menerapkan yang baru
            if ($oldStatus === 'approved') {
                $isLastLevel = !LeaveRequest::where('token', $leave->token)
                    ->where('approval_level', '>', $leave->approval_level)
                    ->exists();

                if ($isLastLevel) {
                    $this->reverseFinalize($leave);
                } else {
                    // approval_progress sempat maju ke level berikutnya, tarik lagi ke level ini
                    LeaveRequest::where('token', $leave->token)
                        ->update(['approval_progress' => $leave->approval_level]);
                }
            } elseif ($oldStatus === 'rejected') {
                // Aktifkan lagi baris level lain yang ikut ke-void saat reject
                LeaveRequest::where('token', $leave->token)
                    ->where('id', '!=', $leave->id)
                    ->update(['status' => 'pending', 'void' => 'false']);
            }

            $leave->status = $newStatus;
            $leave->comment = $request->comment;
            $leave->approval_date = now();
            $leave->save();

            if ($newStatus === 'approved') {
                $this->advanceOrFinalize($leave);
            } else {
                $this->voidSiblingRows($leave);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Keputusan berhasil diubah.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Cek apakah ada proses level selanjutnya di token ini; kalau ada, majukan
     * approval_progress ke level itu. Kalau tidak ada (ini level terakhir),
     * finalisasi (potong leave balance + isi Overtime "CT").
     */
    private function advanceOrFinalize(LeaveRequest $leave)
    {
        $nextLevel = LeaveRequest::where('token', $leave->token)
            ->where('approval_level', '>', $leave->approval_level)
            ->orderBy('approval_level', 'asc')
            ->first();

        if ($nextLevel) {
            LeaveRequest::where('token', $leave->token)
                ->update(['approval_progress' => $nextLevel->approval_level]);
            return;
        }

        $this->finalizeApproval($leave);
    }

    /**
     * Efek final ketika cuti disetujui di level terakhir: potong leave_balances
     * dan isi record Overtime "CT" (cuti) untuk tiap hari kerja di periode cuti.
     */
    private function finalizeApproval(LeaveRequest $leave)
    {
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
                    'DEPT_GROUP' => '',
                ]
            );
        }
    }

    /**
     * Kebalikan dari finalizeApproval() -- dipakai saat keputusan "approved" di
     * level terakhir diubah jadi status lain: kembalikan leave_balances dan
     * hapus record Overtime "CT" yang sempat dibuat untuk periode cuti ini.
     */
    private function reverseFinalize(LeaveRequest $leave)
    {
        DB::table('leave_balances')
            ->where('NPK', $leave->NPK)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', date('Y'))
            ->update([
                'used_days' => DB::raw('used_days - ' . $leave->total_days),
                'remained_days' => DB::raw('remained_days + ' . $leave->total_days)
            ]);

        Overtime::where('NPK', $leave->NPK)
            ->whereBetween('OVERTIME_DATE', [$leave->start_date, $leave->end_date])
            ->where('JUMLAH_JAM_LEMBUR', 'CT')
            ->delete();
    }

    /**
     * Set baris-baris lain (level approval lain) di token yang sama jadi
     * rejected/void, dipakai saat salah satu level menolak permohonan.
     */
    private function voidSiblingRows(LeaveRequest $leave)
    {
        LeaveRequest::where('token', $leave->token)
            ->where('id', '!=', $leave->id)
            ->update(['status' => 'rejected', 'void' => 'true']);
    }
}