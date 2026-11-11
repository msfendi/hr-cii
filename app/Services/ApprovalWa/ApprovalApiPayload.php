<?php

namespace App\Services\ApprovalWa;

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
     * @return array
     */
    public static function pendingPayload(): array
    {
        $requests = LeaveApprovalQuery::getPendingForNotification();
        $items    = [];

        foreach ($requests as $request) {
            $item = self::buildItem($request);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return [
            'generated_at' => now()->setTimezone('Asia/Jakarta')->toIso8601String(),
            'count'        => count($items),
            'items'        => $items,
        ];
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

        $message = WaMessageFormatter::formatApprovalRequest($request, $approverInfo);
        $id      = (int) $request->id;

        return [
            'request_id'      => $id,
            'token'           => $request->token,
            'level'           => (int) $request->approval_level,
            'total_levels'    => LeaveApprovalQuery::countTokenLevels($request->token),
            'phone'           => $phone,
            'raw_phone'       => $approverInfo->HP,
            'approver_npk'    => $request->approval_id,
            'approver_name'   => $approverInfo->NAMA ?? $approverInfo->NPK,
            'employee_npk'    => $request->NPK,
            'start_date'      => $request->start_date,
            'end_date'        => $request->end_date,
            'total_days'      => $request->total_days,
            'reason'          => $request->reason,
            'message'         => $message,
            'approve_command' => "/approve $id",
            'reject_command'  => "/tolak $id <alasan>",
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
