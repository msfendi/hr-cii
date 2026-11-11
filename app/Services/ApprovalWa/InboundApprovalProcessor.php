<?php

namespace App\Services\ApprovalWa;

use App\Models\LeaveRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InboundApprovalProcessor
{
    /**
     * Parse inbound WhatsApp message.
     * Accepted formats:
     *   APPROVE <id>            /approve <id>
     *   REJECT <id> <reason>    /tolak <id> <reason>
     *   BATAL <id> <reason>     /batal <id> <reason>
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

        // BATAL / /batal — membatalkan keputusan yang sudah approved
        if (preg_match('/^(?:BATAL|\/batal)\s+#?(\d+)(?:\s+(.+))?$/i', $message, $m)) {
            return [
                'decision' => 'revert',
                'id'       => (int) $m[1],
                'reason'   => isset($m[2]) ? trim($m[2]) : 'Dibatalkan via WhatsApp',
            ];
        }

        return null;
    }

    /**
     * Full inbound flow:
     * 1. Parse message
     * 2. Normalize + validate approver
     * 3. Conditional UPDATE (atomic — mencegah double potong saldo)
     * 4. Apply efek samping dalam transaksi
     * 5. Cascade notify level berikutnya (setelah commit)
     * 6. Return reply message
     */
    public static function handle(string $senderPhone, string $message): string
    {
        $parsed = self::parse($message);
        if (!$parsed) {
            return "⚠️ Format tidak dikenali.\n\nGunakan:\n- /approve <ID>\n- /tolak <ID> <alasan>\n- /batal <ID> <alasan>";
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

        // ── REVERT (batal approval yang sudah jadi) ──
        if ($decision === 'revert') {
            return self::handleRevert($phone, $requestId, $reason);
        }

        // ── APPROVE / REJECT ──
        $validation = LeaveApprovalQuery::validateApprover($phone, $requestId);
        if (!$validation['valid']) {
            Log::warning("[InboundApproval] Unauthorized: $phone for #$requestId — {$validation['reason']}");
            return WaMessageFormatter::formatUnauthorized($requestId, $validation['reason']);
        }

        $approver     = $validation['approver'];
        $approverName = $approver->NAMA ?? $approver->NPK;

        // ── Transaksi: conditional UPDATE + efek samping atomik ──
        $conn = DB::connection('cii');

        try {
            $conn->beginTransaction();

            // Conditional UPDATE: WHERE status = 'pending'
            // Affected = 0 → request sudah diproses (race / double-tap), abort.
            $affected = LeaveApprovalQuery::updateDecision($requestId, $decision, $reason, $approver->NPK);

            if ($affected === 0) {
                $conn->rollBack();
                Log::warning("[InboundApproval] #$requestId: affected=0 (already processed), aborted");
                return WaMessageFormatter::formatUnauthorized(
                    $requestId,
                    'Pengajuan ini sudah diproses sebelumnya. Tidak ada perubahan.'
                );
            }

            // Efek samping: advance progress / finalisasi / void sibling
            self::applyDecisionEffects($validation['request'], $decision);

            $conn->commit();
        } catch (\Throwable $e) {
            try {
                $conn->rollBack();
            } catch (\Throwable $ignored) {
                // sudah rollback / tidak ada transaksi aktif
            }
            Log::error("[InboundApproval] #$requestId transaction failed: " . $e->getMessage());
            return "⛔ Terjadi kesalahan saat memproses pengajuan #$requestId. Perubahan tidak disimpan.";
        }

        // ── Cascade notify level berikutnya (setelah commit, supaya tidak buka lock) ──
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

        return WaMessageFormatter::formatConfirmation($requestId, $decision, $approverName);
    }

    /**
     * Batalkan keputusan yang sudah approved (approved → rejected).
     *
     * Mengembalikan saldo cuti (reverseFinalize) kalau level terakhir sudah
     * memotong saldo, atau menarik approval_progress kalau belum final.
     */
    private static function handleRevert(string $phone, int $requestId, ?string $reason): string
    {
        // Cari approver dari nomor HP
        $approver = LeaveApprovalQuery::findApproverByPhone($phone);
        if (!$approver) {
            Log::warning("[InboundApproval] Unknown sender phone: $phone");
            return "⛔ Nomor Anda ($phone) tidak terdaftar sebagai approver di sistem.";
        }

        $revert = LeaveApprovalQuery::revertDecision($requestId, $approver->NPK);
        if (!$revert['ok']) {
            Log::warning("[InboundApproval] Revert denied for #$requestId by {$approver->NPK} — {$revert['reason']}");
            return WaMessageFormatter::formatUnauthorized($requestId, $revert['reason']);
        }

        $req = $revert['request'];
        $conn = DB::connection('cii');

        try {
            $conn->beginTransaction();

            // Conditional UPDATE: WHERE status = 'approved'
            $affected = LeaveApprovalQuery::applyRevert($requestId, $reason);

            if ($affected === 0) {
                $conn->rollBack();
                return WaMessageFormatter::formatUnauthorized(
                    $requestId,
                    'Pengajuan ini sudah diproses sebelumnya. Tidak ada perubahan.'
                );
            }

            $leave = LeaveRequest::find($requestId);
            if ($leave) {
                // Kalau ini level terakhir, saldo sudah terpotong → kembalikan.
                // Kalau belum, tarik progress ke level ini.
                $isLastLevel = !LeaveRequest::where('token', $leave->token)
                    ->where('approval_level', '>', $leave->approval_level)
                    ->exists();

                if ($isLastLevel) {
                    LeaveApprovalEffects::reverseFinalize($leave);
                } else {
                    LeaveRequest::where('token', $leave->token)
                        ->update(['approval_progress' => $leave->approval_level]);
                }

                // Sibling rows (level lain) dikembalikan ke pending
                LeaveRequest::where('token', $leave->token)
                    ->where('id', '!=', $leave->id)
                    ->where('void', 'true')
                    ->update(['status' => 'pending', 'void' => 'false']);
            }

            $conn->commit();
        } catch (\Throwable $e) {
            try {
                $conn->rollBack();
            } catch (\Throwable $ignored) {
                // sudah rollback
            }
            Log::error("[InboundApproval] revert #$requestId failed: " . $e->getMessage());
            return "⛔ Terjadi kesalahan saat membatalkan pengajuan #$requestId. Perubahan tidak disimpan.";
        }

        Log::info("[InboundApproval] #$requestId reverted by {$approver->NPK}");

        $approverName = $approver->NAMA ?? $approver->NPK;
        $timestamp    = now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i');

        return "↩️ *Keputusan Dibatalkan*\n\n"
            . "Pengajuan cuti *#$requestId* telah dibatalkan oleh $approverName.\n"
            . "Saldo cuti dikembalikan.\n"
            . "Waktu: $timestamp WIB";
    }

    /**
     * Terapkan efek samping keputusan (advance progress / finalisasi / void sibling).
     *
     * Dipanggil DI DALAM transaksi — jangan commit di sini.
     */
    private static function applyDecisionEffects(object $requestRow, string $decision): void
    {
        $leave = LeaveRequest::find($requestRow->id);
        if (!$leave) {
            throw new \RuntimeException("LeaveRequest #{$requestRow->id} not found for effects");
        }

        if ($decision === 'reject') {
            LeaveApprovalEffects::voidSiblingRows($leave);
            Log::info("[InboundApproval] #$leave->id rejected → sibling rows voided");
            return;
        }

        LeaveApprovalEffects::advanceOrFinalize($leave);
        Log::info("[InboundApproval] #$leave->id approved → progress advanced / finalized");
    }
}
