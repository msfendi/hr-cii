<?php

namespace App\Http\Controllers;

use App\Models\WorkAssignmentRequest;
use App\Services\ApprovalWa\ApprovalCommandParser;
use App\Services\ApprovalWa\ApprovalWebhookTrigger;
use App\Services\WorkAssignment\EmployeeLookupService;
use App\Services\WorkAssignment\WorkAssignmentApproval;
use App\Services\WorkAssignment\WorkAssignmentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Halaman approval admin Work Assignment Request (Surat Ijin Dinas Luar).
 *
 * Referensi: pengajuan cuti (`LeaveApprovalController`) — daftar pengajuan
 * yang menunggu keputusan approver, tombol Setujui / Tolak, modal detail.
 *
 * BEDA dari cuti:
 *  - Data pengajuan disimpan SATU row dengan chain + answers dalam JSON.
 *  - Otorisasi berdasarkan NPK yang ada di `approval_chain` pada level tertentu.
 *  - Level hanya boleh diputuskan sesuai urutan (level sebelumnya harus
 *    sudah approved).
 */
class WorkAssignmentApprovalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Halaman utama approval — daftar pengajuan milik approver yang login.
     */
    public function index()
    {
        $npk = $this->approverNpk();

        if ($npk === null) {
            Alert::error('Error', 'Akun Anda tidak memiliki NPK yang terdaftar.');
            return redirect()->route('home');
        }

        $employee = EmployeeLookupService::lookup($npk);

        return view('work_assignment.approval', [
            'approver' => $employee['ok'] ? $employee['data'] : ['npk' => $npk, 'nama' => $npk],
            'approverNpk' => $npk,
        ]);
    }

    /**
     * Data untuk DataTables (server-side).
     */
    public function getData(Request $request)
    {
        $npk = $this->approverNpk();

        if ($npk === null) {
            return response()->json(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]);
        }

        $requests = WorkAssignmentApproval::forApprover($npk);

        $status = (string) $request->input('status', 'pending');

        $rows = [];

        foreach ($requests as $index => $req) {
            $timeline = $req->timeline();
            $progress = $req->progress();
            $myLevel  = WorkAssignmentApproval::levelOf($req, $npk);

            $myDecision = 'none';

            foreach ($timeline as $step) {
                if ((int) $step['level'] === (int) $myLevel) {
                    $myDecision = $step['decision'];
                    break;
                }
            }

            $isWaitingPrevious = $myLevel !== null
                && $myLevel > (int) $req->current_level
                && $myDecision === 'waiting';

            $isActionable = $myLevel !== null
                && WorkAssignmentApproval::isActionable($req, $myLevel);

            // Timeline ringkas per level: nama approver + status terkini.
            // Dipakai di kolom "Approval Progress" supaya approver langsung
            // tahu siapa yang sudah menyetujui dan siapa giliran berikutnya.
            $steps = [];

            foreach ($timeline as $step) {
                $steps[] = [
                    'level'      => (int) $step['level'],
                    'name'       => $step['name'],
                    'role_label' => $step['role_label'],
                    'npk'        => $step['npk'],
                    'decision'   => $step['decision'],
                    'decided_at' => $step['decided_at'],
                ];
            }

            // Approver yang sedang giliran.
            $activeStep = null;

            foreach ($steps as $step) {
                if ($step['decision'] === 'waiting') {
                    $activeStep = $step;
                    break;
                }
            }

            // Filter status (default: yang masih perlu keputusan)
            if ($status === 'actionable') {
                if (!$isActionable) {
                    continue;
                }
            } elseif ($status === 'waiting_previous') {
                if (!$isWaitingPrevious) {
                    continue;
                }
            } elseif ($status === 'decided') {
                if (!in_array($myDecision, ['approved', 'rejected'], true)) {
                    continue;
                }
            } elseif ($status !== 'all' && $status !== '') {
                // Filter per status overall pengajuan
                if ($progress['status'] !== $status) {
                    continue;
                }
            }

            $employee = EmployeeLookupService::lookup((string) $req->npk);
            $empData  = $employee['ok'] ? $employee['data'] : [];

            $rows[] = [
                'rowno'           => $index + 1,
                'id'               => $req->id,
                'token'            => $req->token,
                'npk'              => $req->npk,
                'nama'             => $empData['nama'] ?? $req->npk,
                'jabatan'          => $empData['jabatan'] ?? '-',
                'bagian'           => $empData['bagian'] ?? '-',
                'departemen'       => $empData['departemen'] ?? '-',
                'section'          => $empData['section'] ?? '-',
                'duty_dates'       => $req->dates(),
                'start_date'       => $req->start_date?->format('Y-m-d'),
                'end_date'         => $req->end_date?->format('Y-m-d'),
                'total_days'       => $req->total_days,
                'dinas_ke'         => $req->dinas_ke,
                'keperluan'        => $req->keperluan,
                'jam_keluar'       => $req->jam_keluar ? substr((string) $req->jam_keluar, 0, 5) : null,
                'rencana_kembali'  => $req->rencana_kembali ? substr((string) $req->rencana_kembali, 0, 5) : null,
                'jam_kembali'      => $req->jam_kembali ? substr((string) $req->jam_kembali, 0, 5) : null,
                'overall_status'   => $progress['status'],
                'status_label'     => $progress['label'],
                'progress_done'    => $progress['done'],
                'progress_total'   => $progress['total'],
                'current_level'    => (int) $req->current_level,
                'steps'            => $steps,
                'active_step'      => $activeStep,
                'my_level'         => $myLevel,
                'my_decision'      => $myDecision,
                'is_actionable'    => $isActionable,
                'is_waiting_prev'  => $isWaitingPrevious,
                'created_at'       => $req->created_at?->format('Y-m-d H:i:s'),
                'submitted_by'     => $req->submitted_by,
            ];
        }

        $collection = collect($rows);

        return response()->json([
            'data'            => $collection->values(),
            'recordsTotal'    => $collection->count(),
            'recordsFiltered' => $collection->count(),
        ]);
    }

    /**
     * Detail satu pengajuan (timeline approval per level).
     */
    public function detail($id)
    {
        $npk = $this->approverNpk();

        if ($npk === null) {
            return response()->json(['success' => false, 'message' => 'NPK tidak ditemukan.'], 403);
        }

        $request = WorkAssignmentRequest::find($id);

        if (!$request) {
            return response()->json(['success' => false, 'message' => 'Pengajuan tidak ditemukan.'], 404);
        }

        $myLevel = WorkAssignmentApproval::levelOf($request, $npk);

        if ($myLevel === null) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki level pada pengajuan ini.',
            ], 403);
        }

        $employee = EmployeeLookupService::lookup((string) $request->npk);
        $empData  = $employee['ok'] ? $employee['data'] : [];

        $timeline = [];

        foreach ($request->timeline() as $step) {
            $timeline[] = [
                'level'      => (int) $step['level'],
                'role_label' => $step['role_label'],
                'name'       => $step['name'],
                'jabatan'    => $step['jabatan'],
                'npk'        => $step['npk'],
                'decision'   => $step['decision'],
                'reason'     => $step['reason'],
                'decided_at' => $step['decided_at'],
                'is_mine'    => (int) $step['level'] === $myLevel,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'              => $request->id,
                'npk'             => $request->npk,
                'nama'            => $empData['nama'] ?? $request->npk,
                'jabatan'         => $empData['jabatan'] ?? '-',
                'bagian'          => $empData['bagian'] ?? '-',
                'departemen'      => $empData['departemen'] ?? '-',
                'section'         => $empData['section'] ?? '-',
                'duty_dates'      => $request->dates(),
                'start_date'      => $request->start_date?->format('d-m-Y'),
                'end_date'        => $request->end_date?->format('d-m-Y'),
                'total_days'      => $request->total_days,
                'dinas_ke'        => $request->dinas_ke,
                'keperluan'       => $request->keperluan,
                'jam_keluar'      => $request->jam_keluar ? substr((string) $request->jam_keluar, 0, 5) : null,
                'rencana_kembali' => $request->rencana_kembali ? substr((string) $request->rencana_kembali, 0, 5) : null,
                'jam_kembali'     => $request->jam_kembali ? substr((string) $request->jam_kembali, 0, 5) : null,
                'attachments'     => WorkAssignmentAttachment::format($request->attach_files),
                'status'          => $request->status,
                'current_level'   => (int) $request->current_level,
                'my_level'        => $myLevel,
                'is_actionable'   => WorkAssignmentApproval::isActionable($request, $myLevel),
                'created_at'      => $request->created_at?->format('d-m-Y H:i'),
                'timeline'        => $timeline,
            ],
        ]);
    }

    /**
     * Setujui level milik approver ini.
     */
    public function approve(Request $request, $id)
    {
        $npk = $this->approverNpk();

        if ($npk === null) {
            return response()->json(['success' => false, 'message' => 'NPK tidak ditemukan.'], 403);
        }

        $request->validate([
            'comment' => 'nullable|string|max:1000',
        ]);

        $workAssignment = WorkAssignmentRequest::find($id);

        if (!$workAssignment) {
            return response()->json(['success' => false, 'message' => 'Pengajuan tidak ditemukan.'], 404);
        }

        $level = WorkAssignmentApproval::levelOf($workAssignment, $npk);

        if ($level === null) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki level pada pengajuan ini.',
            ], 403);
        }

        $result = WorkAssignmentApproval::approve(
            $workAssignment,
            $npk,
            $level,
            $request->input('comment')
        );

        if ($result['ok']) {
            Log::info("[WorkAssignment] #{$id} disetujui oleh {$npk} pada level {$level}");

            $next = WorkAssignmentApproval::describeNext(
                WorkAssignmentRequest::find($id)
            );

            if ($next) {
                Log::info("[WorkAssignment] #{$id} lanjut ke level {$next['level']} — {$next['name']} ({$next['npk']})");
            }

            // Kabari approver level berikutnya lewat WhatsApp. Kegagalan kirim
            // tidak membatalkan keputusan yang sudah tersimpan.
            try {
                ApprovalWebhookTrigger::notify(
                    (string) $workAssignment->token,
                    'approve',
                    ApprovalCommandParser::DOC_IZIN_DINAS
                );
            } catch (\Throwable $e) {
                Log::error("[WorkAssignment] #{$id} gagal trigger notifikasi: " . $e->getMessage());
            }
        }

        // Service memakai key `ok`; samakan ke `success` supaya konsisten
        // dengan endpoint lain dan tidak salah dibaca frontend sebagai gagal.
        return response()->json([
            'success'    => (bool) $result['ok'],
            'message'    => $result['message'],
            'status'     => $result['status'] ?? null,
            'next_level' => $result['next_level'] ?? null,
        ]);
    }

    /**
     * Tolak level milik approver ini. Alasan wajib.
     */
    public function reject(Request $request, $id)
    {
        $npk = $this->approverNpk();

        if ($npk === null) {
            return response()->json(['success' => false, 'message' => 'NPK tidak ditemukan.'], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $workAssignment = WorkAssignmentRequest::find($id);

        if (!$workAssignment) {
            return response()->json(['success' => false, 'message' => 'Pengajuan tidak ditemukan.'], 404);
        }

        $level = WorkAssignmentApproval::levelOf($workAssignment, $npk);

        if ($level === null) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki level pada pengajuan ini.',
            ], 403);
        }

        $result = WorkAssignmentApproval::reject(
            $workAssignment,
            $npk,
            $level,
            $request->input('reason')
        );

        if ($result['ok']) {
            Log::info("[WorkAssignment] #{$id} ditolak oleh {$npk} pada level {$level}: " . $request->input('reason'));
        }

        // Samakan key `ok` → `success` (lihat approve()).
        return response()->json([
            'success' => (bool) $result['ok'],
            'message' => $result['message'],
            'status'  => $result['status'] ?? null,
        ]);
    }

    /**
     * Ubah keputusan yang sudah diambil.
     */
    public function updateDecision(Request $request, $id)
    {
        $npk = $this->approverNpk();

        if ($npk === null) {
            return response()->json(['success' => false, 'message' => 'NPK tidak ditemukan.'], 403);
        }

        $request->validate([
            'new_status' => 'required|in:approved,rejected',
            'reason'     => 'nullable|string|max:1000',
        ]);

        $workAssignment = WorkAssignmentRequest::find($id);

        if (!$workAssignment) {
            return response()->json(['success' => false, 'message' => 'Pengajuan tidak ditemukan.'], 404);
        }

        $result = WorkAssignmentApproval::updateDecision(
            $workAssignment,
            $npk,
            $request->input('new_status'),
            $request->input('reason')
        );

        if ($result['ok']) {
            Log::info("[WorkAssignment] #{$id} keputusan diubah oleh {$npk} → " . $request->input('new_status'));
        }

        // Samakan key `ok` → `success` (lihat approve()).
        return response()->json([
            'success' => (bool) $result['ok'],
            'message' => $result['message'],
        ]);
    }

    /**
     * NPK approver dari user yang login.
     */
    private function approverNpk(): ?string
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        $npk = trim((string) ($user->npk ?? ''));

        return $npk === '' ? null : $npk;
    }
}
