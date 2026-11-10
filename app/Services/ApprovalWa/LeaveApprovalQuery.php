<?php

namespace App\Services\ApprovalWa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveApprovalQuery
{
    /**
     * Get all pending leave_requests that have not yet reached their final approval level.
     * Returns requests where approval_progress < approval_level AND status = 'pending'.
     * NOTE: Does NOT join PKWT here (slow table). pemohon_nama resolved separately.
     */
    public static function getPendingRequests(): array
    {
        return DB::connection('cii')->select("
            SELECT
                lr.id,
                lr.NPK,
                lr.leave_type_id,
                lr.reason,
                lr.start_date,
                lr.end_date,
                lr.total_days,
                lr.approval_id,
                lr.approval_level,
                lr.approval_progress,
                lr.status,
                lr.NPK AS pemohon_nama
            FROM leave_requests lr
            WHERE lr.status = 'pending'
              AND (lr.void IS NULL OR lr.void = 'false' OR lr.void = '')
              AND lr.approval_progress < lr.approval_level
            ORDER BY lr.id ASC
        ");
    }

    /**
     * Get the approver at a specific level for a given leave request.
     * approval_id on leave_requests maps to approval_id on approval_rules.
     * We pick the approver whose level = approval_progress + 1 (next level to act).
     * NOTE: HP fetched separately to avoid slow PKWT join.
     */
    public static function getNextApprover(string $approvalId, int $currentProgress): ?object
    {
        $nextLevel = $currentProgress + 1;

        // Get approver NPK from approval_rules (fast)
        $rule = DB::connection('cii')->select("
            SELECT TOP 1 ar.id, ar.approval_id, ar.name, ar.level, ar.rules_id
            FROM approval_rules ar
            WHERE ar.approval_id = ?
              AND ar.level = ?
        ", [$approvalId, $nextLevel]);

        if (empty($rule)) {
            return null;
        }

        $approver = $rule[0];

        // Fetch HP separately with timeout-safe query
        $pkwt = DB::connection('cii')->select("
            SELECT TOP 1 p.HP, p.NAMA
            FROM PKWT p
            WHERE p.NPK = ?
        ", [$approver->approval_id]);

        $approver->HP   = $pkwt[0]->HP ?? null;
        $approver->NAMA = $pkwt[0]->NAMA ?? $approver->name;

        return $approver;
    }

    /**
     * Get approver info by NPK (used for inbound validation).
     */
    public static function getApproverByNpk(string $npk): ?object
    {
        $result = DB::connection('cii')->select("
            SELECT TOP 1 ar.approval_id, ar.name, ar.level, ar.rules_id, p.HP
            FROM approval_rules ar
            LEFT JOIN PKWT p ON p.NPK = ar.approval_id
            WHERE ar.approval_id = ?
        ", [$npk]);

        return $result[0] ?? null;
    }

    /**
     * Find leave_request by ID and validate the approver has the right to approve it.
     * Checks:
     *   - request exists and is pending
     *   - approver NPK matches the approval_id on the request
     *   - approver level = approval_progress + 1 (sequential enforcement)
     */
    public static function validateApproverForRequest(int $requestId, string $approverNpk): array
    {
        $request = DB::connection('cii')->select("
            SELECT TOP 1 id, NPK, approval_id, approval_level, approval_progress, status
            FROM leave_requests
            WHERE id = ? AND status = 'pending' AND void IS NULL
        ", [$requestId]);

        if (empty($request)) {
            return ['valid' => false, 'reason' => 'Pengajuan tidak ditemukan atau bukan status pending.'];
        }

        $req = $request[0];
        $nextLevel = (int)$req->approval_progress + 1;

        // Check approver is in approval_rules for this request's approval_id at the correct level
        $rule = DB::connection('cii')->select("
            SELECT TOP 1 id, level
            FROM approval_rules
            WHERE approval_id = ? AND level = ?
        ", [$approverNpk, $nextLevel]);

        // Also check the request's approval_id group matches this approver's rules_id chain
        $inChain = DB::connection('cii')->select("
            SELECT TOP 1 ar2.approval_id
            FROM approval_rules ar1
            JOIN approval_rules ar2 ON ar1.rules_id = ar2.rules_id
            WHERE ar1.approval_id = ? AND ar2.approval_id = ?
        ", [$req->approval_id, $approverNpk]);

        if (empty($rule) || empty($inChain)) {
            return [
                'valid'  => false,
                'reason' => "Anda tidak berwenang approve pengajuan #$requestId di level ini.",
            ];
        }

        return ['valid' => true, 'request' => $req, 'next_level' => $nextLevel];
    }

    /**
     * Update leave_request after approval/rejection.
     */
    public static function updateDecision(int $requestId, string $decision, ?string $comment, string $approverNpk): void
    {
        $status   = strtolower($decision) === 'approve' ? 'approved' : 'rejected';
        $now      = now()->toDateTimeString();

        // If approved and not yet at final level, increment progress
        $req = DB::connection('cii')->select(
            'SELECT TOP 1 approval_level, approval_progress FROM leave_requests WHERE id = ?',
            [$requestId]
        )[0];

        $newProgress = (int)$req->approval_progress + 1;
        $finalStatus = ($status === 'rejected' || $newProgress >= (int)$req->approval_level)
            ? $status
            : 'pending'; // still pending, waiting next level

        DB::connection('cii')->update("
            UPDATE leave_requests
            SET status            = ?,
                approval_progress = ?,
                approval_date     = ?,
                comment           = ?,
                updated_at        = ?
            WHERE id = ?
        ", [$finalStatus, $newProgress, $now, $comment, $now, $requestId]);

        Log::info("[ApprovalWa] Request #$requestId → $finalStatus by $approverNpk (progress: $newProgress)");
    }
}
