<?php

namespace App\Services\ApprovalWa;

use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Log;

class InboundApprovalProcessor
{
    /**
     * Parse inbound WhatsApp message.
     * Accepted formats:
     *   APPROVE <id>
     *   /approve <id>
     *   REJECT <id> <optional reason>
     *   /tolak <id> <optional reason>
     */
    public static function parse(string $message): ?array
    {
        $message = trim($message);

        // APPROVE / /approve
        if (preg_match('/^(?:APPROVE|\/approve)\s+#?(\d+)$/i', $message, $m)) {
            return [
                'decision' => 'approve',
                'id'       => (int) $m[1],
                'reason'   => null,
            ];
        }

        // REJECT / /tolak with optional reason
        if (preg_match('/^(?:REJECT|\/tolak)\s+#?(\d+)(?:\s+(.+))?$/i', $message, $m)) {
            return [
                'decision' => 'reject',
                'id'       => (int) $m[1],
                'reason'   => isset($m[2]) ? trim($m[2]) : null,
            ];
        }

        return null;
    }

    /**
     * Full inbound flow:
     * 1. Parse message
     * 2. Validate approver + request
     * 3. Update DB
     * 4. Return reply message
     */
    public static function handle(string $senderPhone, string $message): string
    {
        $parsed = self::parse($message);
        if (!$parsed) {
            return "⚠️ Format tidak dikenali.\n\nGunakan:\n- /approve <ID>\n- /tolak <ID> <alasan>";
        }

        $requestId = $parsed['id'];
        $decision  = $parsed['decision'];
        $reason    = $parsed['reason'];

        // Normalize phone
        $phone = PhoneFormatter::normalize($senderPhone);
        if (!PhoneFormatter::isValid($phone)) {
            Log::warning("[InboundApproval] Invalid phone format: $senderPhone");
            return "⛔ Nomor pengirim tidak valid: $senderPhone";
        }

        // Validate
        $validation = LeaveApprovalQuery::validateApprover($phone, $requestId);
        if (!$validation['valid']) {
            Log::warning("[InboundApproval] Unauthorized: $phone for #$requestId — {$validation['reason']}");
            return WaMessageFormatter::formatUnauthorized($requestId, $validation['reason']);
        }

        $approver = $validation['approver'];
        $approverName = $approver->NAMA ?? $approver->NPK;

        // Update DB
        LeaveApprovalQuery::updateDecision($requestId, $decision, $reason, $approver->NPK);

        // Apply efek approval yang sama dengan path web portal:
        //  - advance approval_progress ke level berikutnya, atau finalisasi di level terakhir
        //  - kalau reject → void sibling rows (chain berhenti)
        self::applyDecisionEffects($validation['request'], $decision);

        // Kalau disetujui, langsung kabari approver level berikutnya (kalau ada).
        // Level berikutnya hanya dikabari bila semua level sebelumnya sudah approved —
        // dicek di LeaveApprovalNotifier.
        if ($decision === 'approve') {
            try {
                $token = $validation['request']->token ?? null;
                if (!empty($token)) {
                    $notifyResult = LeaveApprovalNotifier::notifyNextLevel($token);
                    foreach ($notifyResult['skipped'] as $skipReason) {
                        Log::warning("[InboundApproval] token $token: $skipReason");
                    }
                }
            } catch (\Throwable $e) {
                Log::error("[InboundApproval] cascade notify: " . $e->getMessage());
            }
        }

        // Return confirmation
        return WaMessageFormatter::formatConfirmation($requestId, $decision, $approverName);
    }

    /**
     * Terapkan efek samping keputusan (advance progress / finalisasi / void sibling).
     *
     * Memakai LeaveRequest Eloquent model supaya LeaveApprovalEffects::advanceOrFinalize()
     * bekerja sama dengan path approve via web portal.
     */
    private function applyDecisionEffects(object $requestRow, string $decision): void
    {
        try {
            $leave = LeaveRequest::find($requestRow->id);
            if (!$leave) {
                Log::warning("[InboundApproval] LeaveRequest #{$requestRow->id} not found for effects");
                return;
            }

            if ($decision === 'reject') {
                LeaveApprovalEffects::voidSiblingRows($leave);
                Log::info("[InboundApproval] #$leave->id rejected → sibling rows voided");
                return;
            }

            LeaveApprovalEffects::advanceOrFinalize($leave);
            Log::info("[InboundApproval] #$leave->id approved → progress advanced / finalized");
        } catch (\Throwable $e) {
            Log::error("[InboundApproval] applyDecisionEffects failed: " . $e->getMessage());
        }
    }
}
