<?php

namespace App\Services\ApprovalWa;

use App\Models\IjinMeninggalkanRequest;
use App\Models\WorkAssignmentRequest;
use Illuminate\Support\Facades\Log;

/**
 * Build payload JSON untuk messaging layer (Hermes) — Opsi C decoupling.
 *
 * Laravel = business logic + data. Hermes = messaging layer.
 * Laravel tidak pernah mengirim pesan WA sendiri; dia hanya menyiapkan
 * payload siap-kirim supaya messaging layer bisa eksekusi.
 *
 * Payload pending:
 *   {
 *     "generated_at": "2026-11-11T09:00:00+07:00",
 *     "count": 2,
 *     "items": [
 *       {
 *         "request_id": 849,
 *         "token": "abc123",
 *         "level": 2,
 *         "total_levels": 4,
 *         "phone": "6281390207272",
 *         "approver_npk": "C-04861",
 *         "approver_name": "RAKHMAT BUDIYONO",
 *         "employee_npk": "C-00825",
 *         "start_date": "2026-10-29",
 *         "end_date": "2026-10-30",
 *         "total_days": 2,
 *         "reason": "Acara Gereja",
 *         "message": "...",
 *         "approve_command": "/approve 849",
 *         "reject_command": "/tolak 849 <alasan>"
 *       }
 *     ]
 *   }
 */
class ApprovalApiPayload
{
    /**
     * Build list pending pengajuan yang siap dikabari (sudah lewat filter sequential).
     *
     * @param  string  $doc  doctype: cuti | izin-dinas
     * @return array
     */
    public static function pendingPayload(string $doc = ApprovalCommandParser::DOC_CUTI): array
    {
        $items = match ($doc) {
            ApprovalCommandParser::DOC_IZIN_DINAS        => self::izinDinasItems(),
            ApprovalCommandParser::DOC_IZIN_MENINGGALKAN => self::izinMeninggalkanItems(),
            default                                      => self::cutiItems(),
        };

        return [
            'generated_at' => now()->setTimezone('Asia/Jakarta')->toIso8601String(),
            'doc'          => $doc,
            'count'        => count($items),
            'items'        => $items,
        ];
    }

    /**
     * Item pending untuk pengajuan cuti.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function cutiItems(): array
    {
        $requests = LeaveApprovalQuery::getPendingForNotification();
        $items    = [];

        foreach ($requests as $request) {
            $item = self::buildItem($request);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Item pending untuk Izin Dinas.
     *
     * Rantai approval Izin Dinas berupa JSON, jadi "level yang giliran" tidak
     * bisa diambil dengan query SQL sederhana seperti cuti. Penyaringan
     * diserahkan ke IzinDinasNotifier (yang membaca approval_chain + answers).
     *
     * @return array<int, array<string, mixed>>
     */
    private static function izinDinasItems(): array
    {
        $requests = WorkAssignmentRequest::query()
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('void')->orWhereNotIn('void', ['true', '1']);
            })
            ->orderBy('id')
            ->get(['id', 'token']);

        $items = [];

        foreach ($requests as $request) {
            $item = IzinDinasNotifier::buildPayloadForToken((string) $request->token);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Item pending untuk Izin Meninggalkan Tugas/Pekerjaan.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function izinMeninggalkanItems(): array
    {
        $requests = IjinMeninggalkanRequest::query()
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('void')->orWhereNotIn('void', ['true', '1']);
            })
            ->orderBy('id')
            ->get(['id', 'token']);

        $items = [];

        foreach ($requests as $request) {
            $item = IzinMeninggalkanNotifier::buildPayloadForToken((string) $request->token);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Build satu item payload, atau null kalau approver tidak punya nomor valid.
     */
    private static function buildItem(object $request): ?array
    {
        $approverInfo = LeaveApprovalQuery::getApproverInfo($request->approval_id);
        if (!$approverInfo) {
            Log::warning("[ApprovalApi] approver {$request->approval_id} not found in PKWT (request #{$request->id})");
            return null;
        }

        $phone = PhoneFormatter::normalize($approverInfo->HP ?? null);
        if (!PhoneFormatter::isValid($phone)) {
            Log::warning("[ApprovalApi] invalid phone for {$request->approval_id} (request #{$request->id}): '{$approverInfo->HP}'");
            return null;
        }

        $message = LeaveMessageFormatter::formatApprovalRequest($request, $approverInfo);
        $id      = (int) $request->id;

        return [
            'doc'             => ApprovalCommandParser::DOC_CUTI,
            'request_id'      => $id,
            'token'           => $request->token,
            'level'           => (int) $request->approval_level,
            'total_levels'    => LeaveApprovalQuery::countTokenLevels($request->token),
            'phone'           => $phone,
            'raw_phone'       => $approverInfo->HP,
            'approver_npk'    => $request->approval_id,
            'approver_name'   => $approverInfo->NAMA ?? $approverInfo->NPK,
            'employee_npk'    => $request->NPK,
            'employee_name'   => LeaveApprovalQuery::getEmployeeName((string) $request->NPK),
            'leave_type_id'   => (int) ($request->leave_type_id ?? 0),
            'leave_type'      => LeaveApprovalQuery::getLeaveTypeName((int) ($request->leave_type_id ?? 0)),
            'submitted_at'    => $request->created_at ?? null,
            'start_date'      => $request->start_date,
            'end_date'        => $request->end_date,
            'duration_days'   => (int) $request->total_days,
            'reason'          => $request->reason,
            'message'         => $message,
            'approve_command' => "/approve-cuti$id",
            'reject_command'  => "/tolak-cuti$id <alasan>",
        ];
    }

    /**
     * Build response payload untuk hasil decision.
     */
    public static function decisionResult(int $requestId, string $status, ?string $reply, array $extra = []): array
    {
        return array_merge([
            'request_id'  => $requestId,
            'status'      => $status,
            'reply'       => $reply,
            'processed_at' => now()->setTimezone('Asia/Jakarta')->toIso8601String(),
        ], $extra);
    }
}
