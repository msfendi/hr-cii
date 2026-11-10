<?php

namespace App\Services\ApprovalWa;

use Illuminate\Support\Facades\Log;

class InboundApprovalProcessor
{
    /**
     * Parse inbound WhatsApp message.
     * Expected format:
     *   APPROVE <id>
     *   REJECT <id> <optional reason>
     *
     * Returns null if message doesn't match expected pattern.
     */
    public static function parse(string $message): ?array
    {
        $message = trim($message);

        // APPROVE 123
        if (preg_match('/^APPROVE\s+#?(\d+)$/i', $message, $m)) {
            return [
                'decision' => 'approve',
                'id'       => (int) $m[1],
                'reason'   => null,
            ];
        }

        // REJECT 123 optional reason
        if (preg_match('/^REJECT\s+#?(\d+)(?:\s+(.+))?$/i', $message, $m)) {
            return [
                'decision' => 'reject',
                'id'       => (int) $m[1],
                'reason'   => $m[2] ?? null,
            ];
        }

        return null;
    }

    /**
     * Full inbound flow:
     *  1. Parse message
     *  2. Find approver by phone number (lookup PKWT.HP → NPK → approval_rules)
     *  3. Validate approver has right to decide on this request
     *  4. Update DB
     *  5. Return response message
     *
     * @param string $senderPhone  Normalized phone number of the sender (628xxx)
     * @param string $message      Raw WhatsApp message text
     * @return string              Reply message to send back
     */
    public static function handle(string $senderPhone, string $message): string
    {
        // 1. Parse
        $parsed = self::parse($message);
        if (!$parsed) {
            return "⚠️ Format tidak dikenali.\n\nGunakan:\n- APPROVE <ID>\n- REJECT <ID> <alasan>";
        }

        $requestId = $parsed['id'];
        $decision  = $parsed['decision'];
        $reason    = $parsed['reason'];

        // 2. Find approver NPK by phone number
        $approver = self::findApproverByPhone($senderPhone);
        if (!$approver) {
            Log::warning("[InboundApproval] Unknown sender phone: $senderPhone");
            return "⛔ Nomor Anda ($senderPhone) tidak terdaftar sebagai approver di sistem.";
        }

        $approverNpk  = $approver->NPK;
        $approverName = $approver->NAMA ?? $approverNpk;

        // 3. Validate
        $validation = LeaveApprovalQuery::validateApproverForRequest($requestId, $approverNpk);
        if (!$validation['valid']) {
            return WaMessageFormatter::formatUnauthorized($requestId, $validation['reason']);
        }

        // 4. Update DB
        LeaveApprovalQuery::updateDecision($requestId, $decision, $reason, $approverNpk);

        // 5. Return confirmation
        return WaMessageFormatter::formatConfirmation($requestId, $decision, $approverName);
    }

    /**
     * Find approver by their phone number.
     * Looks up PKWT by HP field (normalized), then checks if that NPK is in approval_rules.
     */
    private static function findApproverByPhone(string $senderPhone): ?object
    {
        // Get all approval_rules NPKs with their phone numbers from PKWT
        // We normalize on the fly in PHP since SQL Server doesn't have easy regex
        $rows = \Illuminate\Support\Facades\DB::connection('cii')->select("
            SELECT DISTINCT ar.approval_id AS NPK, p.NAMA, p.HP
            FROM approval_rules ar
            JOIN PKWT p ON p.NPK = ar.approval_id
            WHERE p.HP IS NOT NULL AND LEN(p.HP) > 5
        ");

        foreach ($rows as $row) {
            $normalized = PhoneFormatter::normalize($row->HP);
            if ($normalized === $senderPhone) {
                return $row;
            }
        }

        return null;
    }
}
