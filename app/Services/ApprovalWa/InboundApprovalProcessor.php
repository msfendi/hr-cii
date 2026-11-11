<?php

namespace App\Services\ApprovalWa;

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

        // Return confirmation
        return WaMessageFormatter::formatConfirmation($requestId, $decision, $approverName);
    }
}
