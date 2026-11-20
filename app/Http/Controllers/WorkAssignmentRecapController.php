<?php

namespace App\Http\Controllers;

use App\Models\WorkAssignmentRequest;
use App\Services\WorkAssignment\EmployeeLookupService;
use App\Services\WorkAssignment\WorkAssignmentApproval;
use App\Services\WorkAssignment\WorkAssignmentAttachment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Manage Work Assignment Request — halaman admin untuk oversee SEMUA pengajuan
 * Surat Ijin Dinas Luar (bukan cuma milik approver).
 *
 * Referensi: Leave Recap (`AdminLeaveRecapController`).
 *
 * Perbedaan dari approval page:
 *  - Melihat SEMUA pengajuan (semua pemohon), bukan hanya yang di chain-nya.
 *  - Untuk monitor & koreksi data, bukan untuk memutuskan approval.
 */
class WorkAssignmentRecapController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Halaman utama.
     */
    public function index()
    {
        return view('work_assignment.recap');
    }

    /**
     * Data untuk DataTables.
     */
    public function getData(Request $request)
    {
        $query = WorkAssignmentRequest::orderByDesc('id');

        // Filter bulan pengajuan.
        if ($request->filled('month_year')) {
            $parts = explode('-', (string) $request->input('month_year'));

            if (count($parts) === 2) {
                $query->whereYear('start_date', (int) $parts[0])
                      ->whereMonth('start_date', (int) $parts[1]);
            }
        }

        // Filter status overall.
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        // Filter keyword: nama / NPK / dinas ke.
        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->input('keyword'));
            $query->where(function ($q) use ($keyword) {
                $q->where('npk', 'like', "%{$keyword}%")
                  ->orWhere('dinas_ke', 'like', "%{$keyword}%")
                  ->orWhere('keperluan', 'like', "%{$keyword}%");
            });
        }

        $requests = $query->get();

        // Master karyawan untuk semua NPK yang muncul (1 query).
        $npkList = $requests->pluck('npk')->unique()->filter()->values()->all();

        $employees = [];

        if (!empty($npkList)) {
            $employees = DB::connection('cii')
                ->table('BIODATA')
                ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->whereIn('BIODATA.NPK', $npkList)
                ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
                ->get()
                ->keyBy('NPK');
        }

        $rows = [];

        foreach ($requests as $index => $req) {
            $progress = $req->progress();
            $emp      = $employees->get($req->npk);

            $rows[] = [
                'rowno'           => $index + 1,
                'id'              => $req->id,
                'token'           => $req->token,
                'npk'             => $req->npk,
                'nama'            => $emp->NAMA_KARYAWAN ?? $req->npk,
                'dept'            => $emp->DEPARTEMENT ?? '-',
                'duty_dates'      => $req->dates(),
                'start_date'      => $req->start_date?->format('Y-m-d'),
                'end_date'        => $req->end_date?->format('Y-m-d'),
                'total_days'      => $req->total_days,
                'dinas_ke'        => $req->dinas_ke,
                'keperluan'       => $req->keperluan,
                'jam_keluar'      => $req->jam_keluar ? substr((string) $req->jam_keluar, 0, 5) : null,
                'rencana_kembali' => $req->rencana_kembali ? substr((string) $req->rencana_kembali, 0, 5) : null,
                'jam_kembali'     => $req->jam_kembali ? substr((string) $req->jam_kembali, 0, 5) : null,
                'status'          => $progress['status'],
                'status_label'    => $progress['label'],
                'tone'            => $progress['tone'],
                'done'            => $progress['done'],
                'total'           => $progress['total'],
                'progress_done'   => $progress['done'],
                'current_level'   => (int) $req->current_level,
                'created_at'      => $req->created_at?->format('Y-m-d H:i:s'),
            ];
        }

        return response()->json([
            'data'            => collect($rows)->values(),
            'recordsTotal'    => count($rows),
            'recordsFiltered' => count($rows),
        ]);
    }

    /**
     * Detail pengajuan (timeline approval + isi surat).
     */
    public function detail($id)
    {
        $req = WorkAssignmentRequest::find($id);

        if (!$req) {
            return response()->json(['success' => false, 'message' => 'Pengajuan tidak ditemukan.'], 404);
        }

        $employee = EmployeeLookupService::lookup((string) $req->npk);
        $empData  = $employee['ok'] ? $employee['data'] : [];

        $timeline = [];

        foreach ($req->timeline() as $step) {
            $timeline[] = [
                'level'      => (int) $step['level'],
                'role_label' => $step['role_label'],
                'name'       => $step['name'],
                'jabatan'    => $step['jabatan'],
                'npk'        => $step['npk'],
                'decision'   => $step['decision'],
                'reason'     => $step['reason'],
                'decided_at' => $step['decided_at'],
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'              => $req->id,
                'token'           => $req->token,
                'npk'             => $req->npk,
                'nama'            => $empData['nama'] ?? $req->npk,
                'jabatan'         => $empData['jabatan'] ?? '-',
                'bagian'          => $empData['bagian'] ?? '-',
                'departemen'      => $empData['departemen'] ?? '-',
                'section'         => $empData['section'] ?? '-',
                'duty_dates'      => $req->dates(),
                'start_date'      => $req->start_date?->format('d-m-Y'),
                'end_date'        => $req->end_date?->format('d-m-Y'),
                'total_days'      => $req->total_days,
                'dinas_ke'        => $req->dinas_ke,
                'keperluan'       => $req->keperluan,
                'jam_keluar'      => $req->jam_keluar ? substr((string) $req->jam_keluar, 0, 5) : null,
                'rencana_kembali' => $req->rencana_kembali ? substr((string) $req->rencana_kembali, 0, 5) : null,
                'jam_kembali'     => $req->jam_kembali ? substr((string) $req->jam_kembali, 0, 5) : null,
                'attachments'     => WorkAssignmentAttachment::format($req->attach_files),
                'status'          => $req->status,
                'current_level'   => (int) $req->current_level,
                'created_at'      => $req->created_at?->format('d-m-Y H:i'),
                'timeline'        => $timeline,
            ],
        ]);
    }

    /**
     * Hapus satu pengajuan (hanya HR/admin).
     *
     * Pengajuan yang sudah disetujui → statusnya diubah jadi `cancelled`
     * dan jawaban approval ditandai, TIDAK dihapus fisik — supaya jejaknya
     * (siapa menyetujui kapan) tetap tercatat.
     *
     * Pengajuan yang belum selesai → dihapus permanen.
     */
    public function destroy(Request $request, $id)
    {
        $req = WorkAssignmentRequest::find($id);

        if (!$req) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan tidak ditemukan (mungkin sudah dihapus).',
            ], 404);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $progress = $req->progress();
        $isFinal  = in_array($progress['status'], ['approved', 'rejected'], true);

        try {
            DB::connection('cii')->transaction(function () use ($req, $isFinal, $request) {
                if ($isFinal) {
                    // Pengajuan sudah diputuskan → batalkan, jangan dihapus.
                    $answers = $req->answered();

                    foreach ($answers as $i => $answer) {
                        $answers[$i]['note'] = trim(
                            (string) ($answer['note'] ?? '')
                            . ' [dibatalkan oleh admin: '
                            . ($request->input('reason') ?: 'tanpa alasan') . ']'
                        );
                    }

                    $req->answers = $answers;
                    $req->status  = 'cancelled';
                    $req->void    = 'true';
                    $req->save();

                    return;
                }

                // Belum selesai → hapus permanen.
                WorkAssignmentRequest::where('id', $req->id)->delete();
            });
        } catch (\Throwable $e) {
            Log::error("[WorkAssignmentRecap] gagal hapus #{$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pengajuan: ' . $e->getMessage(),
            ], 500);
        }

        Log::info("[WorkAssignmentRecap] #{$id} dihapus/dibatalkan oleh admin");

        return response()->json([
            'success' => true,
            'message' => $isFinal
                ? 'Pengajuan dibatalkan. Jejak persetujuan tetap tersimpan.'
                : 'Pengajuan dihapus.',
            'mode'    => $isFinal ? 'cancelled' : 'deleted',
        ]);
    }

    /**
     * Unduh satu lampiran.
     *
     * Path diambil dari daftar lampiran pengajuan (bukan dari input mentah),
     * jadi tidak bisa dipakai untuk menebak file lain di disk.
     */
    public function downloadAttachment($id, $index)
    {
        $req = WorkAssignmentRequest::find($id);

        if (!$req) {
            abort(404, 'Pengajuan tidak ditemukan.');
        }

        $files = $req->attach_files ?? [];
        $path  = WorkAssignmentAttachment::pathOf($files[(int) $index] ?? null);

        if (!$path) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        if (!Storage::disk(WorkAssignmentAttachment::DISK)->exists($path)) {
            abort(404, 'File lampiran sudah tidak ada di storage.');
        }

        return Storage::disk(WorkAssignmentAttachment::DISK)->download($path);
    }

    /**
     * Hapus satu lampiran dari pengajuan (file + daftarnya).
     */
    public function deleteAttachment(Request $request, $id, $index)
    {
        $req = WorkAssignmentRequest::find($id);

        if (!$req) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan tidak ditemukan.',
            ], 404);
        }

        $files = $req->attach_files ?? [];
        $path  = WorkAssignmentAttachment::pathOf($files[(int) $index] ?? null);

        if (!$path) {
            return response()->json([
                'success' => false,
                'message' => 'Lampiran tidak ditemukan.',
            ], 404);
        }

        $remaining = WorkAssignmentAttachment::delete($files, $path);

        $req->attach_files = $remaining;
        $req->save();

        Log::info("[WorkAssignmentRecap] lampiran {$path} pada #{$id} dihapus");

        return response()->json([
            'success' => true,
            'message' => 'Lampiran dihapus.',
            'attachments' => WorkAssignmentAttachment::format($remaining),
        ]);
    }
}
