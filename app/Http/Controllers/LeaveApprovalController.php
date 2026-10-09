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

        // Ambil semua permohonan di mana user ini adalah approver
        // Termasuk yang sedang menunggu giliran (approval_progress == approval_level),
        // yang sudah diputuskan (approved/rejected), MAUPUN yang masih menunggu approval
        // level sebelumnya (approval_progress < approval_level).
        $query = LeaveRequest::where('approval_id', $approverNpk);

        // Filter tanggal: tampilkan permohonan yang periode cutinya beririsan
        // dengan rentang tanggal yang dipilih (start_date/end_date dari request).
        if ($startDate = request('start_date')) {
            $query->whereDate('end_date', '>=', $startDate);
        }
        if ($endDate = request('end_date')) {
            $query->whereDate('start_date', '<=', $endDate);
        }

        // Filter status: default tampilkan yang statusnya pending / waiting
        $status = request('status', 'pending');
        if (empty($status)) {
            $status = 'pending';
        }

        if ($status !== 'all') {
            if ($status === 'waiting_previous') {
                $query->where('status', 'pending')
                      ->whereColumn('approval_progress', '<', 'approval_level');
            } elseif ($status === 'pending_active') {
                $query->where('status', 'pending')
                      ->whereColumn('approval_progress', '=', 'approval_level');
            } else {
                $query->where('status', $status);
            }
        }

        // Urutkan dan pastikan 1 row per pengajuan (token):
        // 1. Yang butuh tindakan aktif approver ini (pending & approval_progress == approval_level)
        // 2. Yang masih menunggu approval sebelumnya (pending & approval_progress < approval_level)
        // 3. Yang sudah diputuskan (approved / rejected)
        $leaveRequestsQuery = $query
            ->orderByRaw("CASE 
                WHEN status = 'pending' AND approval_progress = approval_level THEN 0 
                WHEN status = 'pending' THEN 1 
                ELSE 2 
            END")
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('token');

        $rows = [];
        foreach ($leaveRequestsQuery as $req) {
            $bioEmployee = DB::connection('cii')->table('BIODATA')
                ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->where('BIODATA.NPK', $req->NPK)
                ->select('BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
                ->first();

            $leaveType = LeaveTypes::find($req->leave_type_id);

            // Ambil sisa balance cuti karyawan untuk jenis cuti yang dipilih
            $leaveBalance = DB::table('leave_balances')
                ->where('NPK', $req->NPK)
                ->where('leave_type_id', $req->leave_type_id)
                ->where('year', date('Y'))
                ->first();

            // Ambil daftar semua approver untuk token ini (semua level)
            $allApprovers = LeaveRequest::where('token', $req->token)
                ->where('void', '!=', 'true')
                ->orderBy('approval_level', 'asc')
                ->get();

            $approversList = [];
            foreach ($allApprovers as $approverReq) {
                $approverBio = DB::connection('cii')->table('BIODATA')
                    ->where('NPK', $approverReq->approval_id)
                    ->select('NAMA_KARYAWAN')
                    ->first();

                $approversList[] = [
                    'npk'    => $approverReq->approval_id,
                    'nama'   => $approverBio ? $approverBio->NAMA_KARYAWAN : $approverReq->approval_id,
                    'level'  => $approverReq->approval_level,
                    'status' => $approverReq->status,
                    'void'   => $approverReq->void,
                ];
            }

            // Cek apakah masih menunggu approval level sebelumnya
            $isWaitingPrevious = ($req->status === 'pending' && (int)$req->approval_progress < (int)$req->approval_level);

            // Approver cuma boleh mengubah keputusan selama belum ada level
            // berikutnya yang sudah bertindak (biar workflow tetap konsisten).
            $laterLevelActed = LeaveRequest::where('token', $req->token)
                ->where('approval_level', '>', $req->approval_level)
                ->where('status', '!=', 'pending')
                ->where('void', '!=', 'true')
                ->exists();

            // Format lampiran file
            $formattedFiles = [];
            if (!empty($req->attach_files)) {
                $files = is_array($req->attach_files) ? $req->attach_files : json_decode($req->attach_files, true);
                if (is_array($files)) {
                    foreach ($files as $filePath) {
                        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                        $formattedFiles[] = [
                            'path'     => $filePath,
                            'url'      => asset('storage/' . $filePath),
                            'name'     => basename($filePath),
                            'ext'      => $ext,
                            'is_image' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp']),
                            'is_pdf'   => $ext === 'pdf',
                            'is_word'  => in_array($ext, ['doc', 'docx']),
                        ];
                    }
                }
            }

            $rows[] = [
                'id'                  => $req->id,
                'token'               => $req->token,
                'npk'                 => $req->NPK,
                'nama'                => $bioEmployee ? $bioEmployee->NAMA_KARYAWAN : $req->NPK,
                'dept'                => $bioEmployee ? $bioEmployee->DEPARTEMENT : '-',
                'leave_type'          => $leaveType ? $leaveType->name : '-',
                'leave_balance'       => $leaveBalance ? $leaveBalance->remained_days : '-',
                'leave_used'          => $leaveBalance ? $leaveBalance->used_days : '-',
                'start_date'          => $req->start_date,
                'end_date'            => $req->end_date,
                'start_date_formatted'=> Carbon::parse($req->start_date)->format('d M Y'),
                'end_date_formatted'  => Carbon::parse($req->end_date)->format('d M Y'),
                'total_days'          => $req->total_days,
                'reason'              => $req->reason,
                'status'              => $req->status,
                'comment'             => $req->comment,
                'created_at'          => Carbon::parse($req->created_at)->format('d M Y H:i'),
                'approval_level'      => $req->approval_level,
                'approval_progress'   => $req->approval_progress,
                'is_waiting_previous' => $isWaitingPrevious,
                'can_update'          => $req->status !== 'pending' && !$laterLevelActed,
                'attach_files'        => $formattedFiles,
                'approvers'           => $approversList,
            ];
        }

        if (request()->ajax()) {
            return \Yajra\DataTables\Facades\DataTables::of(collect($rows))
                ->addIndexColumn()
                ->addColumn('karyawan', function($row) {
                    return '<div style="line-height:1.3;">' .
                           '  <strong style="font-size:0.95rem; color:#2e59d9;">'.e($row['nama']).'</strong>' .
                           '  <div class="text-muted" style="font-size:0.85rem; margin-top:2px;">' .
                           '    <span class="font-weight-bold">'.e($row['npk']).'</span> &middot; '.e($row['dept']) .
                           '  </div>' .
                           '</div>';
                })
                ->addColumn('periode', function($row) {
                    $start = $row['start_date_formatted'];
                    $end   = $row['end_date_formatted'];
                    return '<div style="font-size:0.9rem; font-weight:600; white-space:nowrap;">' . $start . '</div>' .
                           '<div class="text-muted" style="font-size:0.8rem; text-align:center;">s/d</div>' .
                           '<div style="font-size:0.9rem; font-weight:600; white-space:nowrap;">' . $end . '</div>';
                })
                ->addColumn('hari', function($row) {
                    return '<span class="badge badge-light border text-dark font-weight-bold" style="font-size:0.92rem; padding:5px 8px;">'.$row['total_days'].' hari</span>';
                })
                ->addColumn('alasan', function($row) {
                    return '<div style="font-size:0.88rem; line-height:1.4;">' . (e($row['reason']) ?: '-') . '</div>';
                })
                ->addColumn('sisa_cuti', function($row) {
                    if ($row['leave_balance'] === '-') {
                        return '<span class="text-muted" style="font-size:0.9rem;">-</span>';
                    }
                    $sisa = (int)$row['leave_balance'];
                    $used = (int)$row['leave_used'];
                    $color = $sisa <= 2 ? 'danger' : ($sisa <= 5 ? 'warning' : 'success');
                    return '<div class="font-weight-bold text-'.$color.'" style="font-size:1.05rem;">'.$sisa.' hari</div>' .
                           '<div class="text-muted font-weight-bold" style="font-size:0.8rem; margin-top:2px;">Terpakai: '.$used.' hr</div>';
                })
                ->addColumn('status_approver', function($row) {
                    // Daftar approver beserta status approval masing-masing
                    if (empty($row['approvers'])) {
                        return '<span class="text-muted">-</span>';
                    }

                    $html = '<div style="font-size:0.86rem; text-align:left;">';
                    foreach ($row['approvers'] as $index => $approver) {
                        $statusBadge = '';
                        if ($approver['status'] === 'approved') {
                            $statusBadge = '<span class="badge badge-success" style="font-size:0.75rem; padding:3px 7px;"><i class="fas fa-check fa-xs mr-1"></i>Approved</span>';
                        } elseif ($approver['status'] === 'rejected') {
                            $statusBadge = '<span class="badge badge-danger" style="font-size:0.75rem; padding:3px 7px;"><i class="fas fa-times fa-xs mr-1"></i>Rejected</span>';
                        } else {
                            $statusBadge = '<span class="badge badge-warning text-white" style="font-size:0.75rem; padding:3px 7px;"><i class="fas fa-clock fa-xs mr-1"></i>Pending</span>';
                        }

                        $borderBottom = ($index < count($row['approvers']) - 1) ? 'border-bottom:1px dashed #e3e6f0;' : '';

                        $html .= '<div class="d-flex align-items-center justify-content-between py-1" style="' . $borderBottom . ' gap:6px;">';
                        $html .= '  <div class="d-flex align-items-center text-truncate" style="flex:1; min-width:0;" title="['.$approver['npk'].'] '.e($approver['nama']).'">';
                        $html .= '    <span class="badge badge-light border text-muted mr-1" style="font-size:0.72rem;">'.$approver['npk'].'</span>';
                        $html .= '    <span class="font-weight-bold text-dark text-truncate" style="font-size:0.85rem;">'.e($approver['nama']).'</span>';
                        $html .= '  </div>';
                        $html .= '  <div style="flex-shrink:0;">'.$statusBadge.'</div>';
                        $html .= '</div>';
                    }
                    $html .= '</div>';

                    return $html;
                })
                ->addColumn('status_utama', function($row) {
                    // Status utama pengajuan cuti (sebelum kolom aksi)
                    if ($row['status'] === 'approved') {
                        return '<span class="badge badge-success px-2 py-1" style="font-size:0.85rem;"><i class="fas fa-check mr-1"></i>Disetujui</span>';
                    } elseif ($row['status'] === 'rejected') {
                        return '<span class="badge badge-danger px-2 py-1" style="font-size:0.85rem;"><i class="fas fa-times mr-1"></i>Ditolak</span>';
                    } elseif (!empty($row['is_waiting_previous'])) {
                        return '<span class="badge badge-secondary px-2 py-1" style="font-size:0.82rem;" title="Menunggu approval level sebelumnya"><i class="fas fa-clock mr-1"></i>Menunggu Level Sebelumnya</span>';
                    }
                    return '<span class="badge badge-warning text-white px-2 py-1" style="font-size:0.85rem;"><i class="fas fa-hourglass-half mr-1"></i>Menunggu</span>';
                })
                ->addColumn('aksi', function($row) {
                    $detailBtn = '<button type="button" class="btn btn-sm btn-info btn-detail btn-block text-nowrap" style="font-size:0.82rem; padding:4px 6px;" data-id="'.$row['id'].'"><i class="fas fa-eye fa-sm"></i> Detail</button>';

                    if ($row['status'] === 'pending') {
                        if (!empty($row['is_waiting_previous'])) {
                            return $detailBtn;
                        }

                        return '<div class="d-flex flex-column" style="gap:4px;">' .
                               $detailBtn .
                               '<button type="button" class="btn btn-sm btn-success btn-approve btn-block text-nowrap" style="font-size:0.82rem; padding:4px 6px;" data-id="'.$row['id'].'" data-nama="'.e($row['nama']).'"><i class="fas fa-check fa-sm"></i> Approve</button>' .
                               '<button type="button" class="btn btn-sm btn-danger btn-reject btn-block text-nowrap" style="font-size:0.82rem; padding:4px 6px;" data-id="'.$row['id'].'" data-nama="'.e($row['nama']).'"><i class="fas fa-times fa-sm"></i> Reject</button>' .
                               '</div>';
                    }

                    return $detailBtn;
                })
                ->rawColumns(['karyawan', 'periode', 'hari', 'alasan', 'sisa_cuti', 'status_approver', 'status_utama', 'aksi'])
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

            if ((int)$leave->approval_progress < (int)$leave->approval_level) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Permohonan ini masih menunggu persetujuan pada level sebelumnya.']);
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

            if ((int)$leave->approval_progress < (int)$leave->approval_level) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Permohonan ini masih menunggu persetujuan pada level sebelumnya.']);
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
     * dan isi record Overtime "CT" atau "CU" (khusus tipe 'pribadi'/id 14) untuk tiap hari kerja di periode cuti.
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

        // Khusus tipe cuti 'pribadi' (atau ID 14), kode JUMLAH_JAM_LEMBUR adalah 'CU', selain itu 'CT'
        $leaveType = $leave->leaveType ?? LeaveTypes::find($leave->leave_type_id);
        $overtimeCode = ($leaveType && $leaveType->code === 'pribadi') ? 'CU' : 'CT';

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
                    'JUMLAH_JAM_LEMBUR' => $overtimeCode,
                    'DEPT_GROUP' => '',
                    'is_request' => 'true',
                ]
            );
        }
    }

    /**
     * Kebalikan dari finalizeApproval() -- dipakai saat keputusan "approved" di
     * level terakhir diubah jadi status lain: kembalikan leave_balances dan
     * hapus record Overtime "CT"/"CU" yang sempat dibuat untuk periode cuti ini.
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
            ->whereIn('JUMLAH_JAM_LEMBUR', ['CT', 'CU'])
            ->update([
                'JUMLAH_JAM_LEMBUR' => null,
                'is_request' => 'false',
            ]);
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