<?php

namespace App\Http\Controllers;

use App\Models\IjinMeninggalkanRequest;
use App\Services\ApprovalWa\ApprovalCommandParser;
use App\Services\ApprovalWa\ApprovalWebhookTrigger;
use App\Services\IzinMeninggalkan\IjinMeninggalkanApproval;
use App\Services\WorkAssignment\EmployeeLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Halaman approval Izin Meninggalkan Tugas / Pekerjaan (admin).
 *
 * Berbeda dari halaman pemohon (session NPK), halaman ini memakai auth
 * Laravel: NPK user yang login adalah identitas approver.
 *
 * Approver hanya melihat pengajuan yang rantainya memuat dirinya, dan hanya
 * boleh memutuskan level miliknya saat giliran tiba — aturan itu ditegakkan
 * IjinMeninggalkanApproval, bukan di controller.
 */
class IjinMeninggalkanApprovalController extends Controller
{
    /** NPK user yang sedang login (approver). */
    private function approverNpk(): ?string
    {
        $npk = trim((string) (Auth::user()->npk ?? ''));

        return $npk === '' ? null : $npk;
    }

    public function index()
    {
        $npk = $this->approverNpk();

        $name = null;

        if ($npk !== null) {
            $names = EmployeeLookupService::namesFor([$npk]);
            $name  = $names[$npk] ?? null;
        }

        return view('izin_meninggalkan.approval', [
            'approverNpk' => $npk,
            'approverName' => $name ?? $npk ?? '-',
        ]);
    }

    /**
     * Data untuk DataTables.
     */
    public function getData(Request $request)
    {
        $npk = $this->approverNpk();

        if ($npk === null) {
            return response()->json(['data' => [], 'recordsTotal' => 0, 'recordsFiltered' => 0]);
        }

        $status = (string) $request->input('status', 'pending');

        $requests = IjinMeninggalkanRequest::orderByDesc('id')->get()
            ->filter(fn (IjinMeninggalkanRequest $r) => IjinMeninggalkanApproval::levelOf($r, $npk) !== null);

        $rows = [];

        foreach ($requests as $req) {
            $myLevel = IjinMeninggalkanApproval::levelOf($req, $npk);
            $canAct  = $myLevel !== null && IjinMeninggalkanApproval::isActionable($req, $myLevel);
            $progress = $req->progress();

            // Tab: pending = masih menunggu keputusan berikutnya (termasuk
            // yang belum giliran approver ini), approved/rejected final.
            if ($status !== 'all' && $progress['status'] !== $status) {
                continue;
            }

            $rows[] = [
                'id'             => (int) $req->id,
                'npk'            => (string) $req->npk,
                'nama'           => null, // diisi massal di bawah
                'tanggal'        => $req->tanggal?->format('d-m-Y'),
                'jam_keluar'     => $req->jam_keluar ? substr((string) $req->jam_keluar, 0, 5) : '-',
                'rencana_kembali' => $req->rencana_kembali ? substr((string) $req->rencana_kembali, 0, 5) : '-',
                'jam_kembali'    => $req->jam_kembali ? substr((string) $req->jam_kembali, 0, 5) : null,
                'alasan'         => (string) ($req->alasan ?? ''),
                'status'         => $progress['label'],
                'status_tone'    => $progress['tone'],
                'done'           => $progress['done'],
                'total'          => $progress['total'],
                'my_level'       => $myLevel,
                'current_level'  => (int) $req->current_level,
                'can_act'        => $canAct,
                'created_at'     => $req->created_at?->format('d-m-Y H:i'),
            ];
        }

        // Nama pemohon diambil sekaligus supaya tidak query per baris.
        $names = EmployeeLookupService::namesFor(array_values(array_unique(array_column($rows, 'npk'))));

        foreach ($rows as &$row) {
            $row['nama'] = $names[$row['npk']] ?? $row['npk'];
        }
        unset($row);

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'data'            => $rows,
            'recordsTotal'    => count($rows),
            'recordsFiltered' => count($rows),
        ]);
    }

    /**
     * Detail satu pengajuan (modal).
     */
    public function detail($id)
    {
        $npk = $this->approverNpk();

        if ($npk === null) {
            return response()->json(['success' => false, 'message' => 'NPK tidak ditemukan.'], 403);
        }

        $req = IjinMeninggalkanRequest::find($id);

        if (!$req) {
            return response()->json(['success' => false, 'message' => 'Pengajuan tidak ditemukan.'], 404);
        }

        $myLevel = IjinMeninggalkanApproval::levelOf($req, $npk);

        if ($myLevel === null) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak tercantum pada rantai approval pengajuan ini.',
            ], 403);
        }

        $names = EmployeeLookupService::namesFor([(string) $req->npk]);
        $progress = $req->progress();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'              => (int) $req->id,
                'npk'             => (string) $req->npk,
                'nama'            => $names[(string) $req->npk] ?? (string) $req->npk,
                'tanggal'         => $req->tanggal?->format('d-m-Y'),
                'jam_keluar'      => $req->jam_keluar ? substr((string) $req->jam_keluar, 0, 5) : '-',
                'rencana_kembali' => $req->rencana_kembali ? substr((string) $req->rencana_kembali, 0, 5) : null,
                'jam_kembali'     => $req->jam_kembali ? substr((string) $req->jam_kembali, 0, 5) : null,
                'alasan'          => (string) ($req->alasan ?? ''),
                'status'          => $progress['label'],
                'status_tone'     => $progress['tone'],
                'created_at'      => $req->created_at?->format('d-m-Y H:i'),
                'my_level'        => $myLevel,
                'current_level'   => (int) $req->current_level,
                'can_act'         => IjinMeninggalkanApproval::isActionable($req, $myLevel),
                'steps'           => $req->timeline(),
            ],
        ]);
    }

    public function approve(Request $request, $id)
    {
        return $this->decide($request, (int) $id, 'approve');
    }

    public function reject(Request $request, $id)
    {
        return $this->decide($request, (int) $id, 'reject');
    }

    /**
     * Proses keputusan (approve/reject).
     */
    private function decide(Request $request, int $id, string $decision): \Illuminate\Http\JsonResponse
    {
        $npk = $this->approverNpk();

        if ($npk === null) {
            return response()->json(['success' => false, 'message' => 'NPK tidak ditemukan.'], 403);
        }

        $request->validate([
            'comment' => 'nullable|string|max:1000',
        ]);

        $req = IjinMeninggalkanRequest::find($id);

        if (!$req) {
            return response()->json(['success' => false, 'message' => 'Pengajuan tidak ditemukan.'], 404);
        }

        $level = IjinMeninggalkanApproval::levelOf($req, $npk);

        if ($level === null) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki level pada pengajuan ini.',
            ], 403);
        }

        $result = $decision === 'approve'
            ? IjinMeninggalkanApproval::approve($req, $npk, $level, $request->input('comment'))
            : IjinMeninggalkanApproval::reject($req, $npk, $level, $request->input('comment'));

        if ($result['ok']) {
            Log::info("[IzinMeninggalkan] #{$id} {$decision} oleh {$npk} pada level {$level}");

            if ($decision === 'approve') {
                try {
                    ApprovalWebhookTrigger::notify(
                        (string) $req->token,
                        'approve',
                        ApprovalCommandParser::DOC_IZIN_MENINGGALKAN
                    );
                } catch (\Throwable $e) {
                    Log::error("[IzinMeninggalkan] gagal cascade notifikasi #{$id}: " . $e->getMessage());
                }
            }
        } else {
            Log::warning("[IzinMeninggalkan] #{$id} {$decision} gagal oleh {$npk}: {$result['message']}");
        }

        return response()->json([
            'success'    => (bool) $result['ok'],
            'message'    => $result['message'],
            'status'     => $result['status'] ?? null,
            'next_level' => $result['next_level'] ?? null,
        ]);
    }
}