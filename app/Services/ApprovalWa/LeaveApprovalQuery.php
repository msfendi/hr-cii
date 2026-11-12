<?php

namespace App\Services\ApprovalWa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveApprovalQuery
{
    /**
     * Get pending leave_requests that NEED notification NOW.
     *
     * Skema: Satu pengajuan cuti menghasilkan N row di leave_requests,
     * satu per approver dalam chain. Semua row satu pengajuan punya token yang sama.
     * - approval_id    = NPK approver yang bertanggung jawab untuk row ini
     * - approval_level = level approver ini dalam chain (1, 2, 3, ...)
     * - approval_progress = selalu '1' saat baru dibuat; tetap '1' sampai diproses
     * - status = 'pending' | 'approved' | 'rejected'
     *
     * Aturan notifikasi sequential:
     * - Level 1: kirim jika status = 'pending' (tidak ada prerequisite)
     * - Level N: kirim hanya jika row level N-1 (token sama) sudah status = 'approved'
     * - Jangan kirim jika salah satu row sebelumnya 'rejected'
     */
    public static function getPendingForNotification(): array
    {
        // Ambil semua pending rows beserta info token-group mereka
        $rows = DB::connection('cii')->select("
            SELECT
                lr.id,
                lr.NPK,
                lr.token,
                lr.reason,
                lr.start_date,
                lr.end_date,
                lr.total_days,
                lr.approval_id,
                lr.approval_level,
                lr.approval_progress,
                lr.status,
                lr.leave_type_id,
                lr.created_at
            FROM leave_requests lr
            WHERE lr.status = 'pending'
              AND (lr.void IS NULL OR lr.void = 'false' OR lr.void = '')
            ORDER BY lr.token, lr.approval_level ASC
        ");

        if (empty($rows)) {
            return [];
        }

        // Group by token untuk bisa cek prerequisite level sebelumnya
        $byToken = [];
        foreach ($rows as $row) {
            $byToken[$row->token][] = $row;
        }

        // Ambil semua approved rows untuk token yang ada (cek prerequisite)
        $tokens = array_keys($byToken);
        $placeholders = implode(',', array_fill(0, count($tokens), '?'));
        $approvedRows = DB::connection('cii')->select("
            SELECT token, approval_level, status
            FROM leave_requests
            WHERE token IN ($placeholders)
              AND status IN ('approved', 'rejected')
        ", $tokens);

        // Build lookup: token -> level -> status
        $statusMap = [];
        foreach ($approvedRows as $r) {
            $statusMap[$r->token][(int)$r->approval_level] = $r->status;
        }

        // Filter: hanya row yang giliran notifikasi sekarang
        $toNotify = [];
        foreach ($byToken as $token => $pendingRows) {
            foreach ($pendingRows as $row) {
                $level = (int) $row->approval_level;

                if ($level === 1) {
                    // Level 1: langsung eligible
                    $toNotify[] = $row;
                } else {
                    // Level N: cek semua level 1..N-1 sudah 'approved'
                    $allPrevApproved = true;
                    for ($prevLevel = 1; $prevLevel < $level; $prevLevel++) {
                        $prevStatus = $statusMap[$token][$prevLevel] ?? null;
                        if ($prevStatus !== 'approved') {
                            $allPrevApproved = false;
                            break;
                        }
                    }

                    // Jika ada yang 'rejected' di level sebelumnya, skip seluruh chain
                    $anyRejected = false;
                    foreach ($statusMap[$token] ?? [] as $st) {
                        if ($st === 'rejected') {
                            $anyRejected = true;
                            break;
                        }
                    }

                    if ($allPrevApproved && !$anyRejected) {
                        $toNotify[] = $row;
                    }
                }
            }
        }

        return $toNotify;
    }

    /**
     * Get approver info (HP, NAMA) for a given NPK from PKWT.
     */
    public static function getApproverInfo(string $npk): ?object
    {
        $result = DB::connection('cii')->select("
            SELECT TOP 1 p.NPK, p.NAMA, p.HP
            FROM PKWT p
            WHERE p.NPK = ?
        ", [$npk]);

        return $result[0] ?? null;
    }

    /**
     * Validate that a sender (by phone) is authorized to approve a specific request.
     *
     * Checks:
     * 1. Phone → NPK lookup in PKWT (normalized)
     * 2. NPK matches approval_id of the target leave_request row
     * 3. That row is still 'pending'
     * 4. All previous levels in the chain are 'approved'
     */
    public static function validateApprover(string $senderPhone, int $requestId): array
    {
        // 1. Find the leave_request
        $req = DB::connection('cii')->select("
            SELECT TOP 1 id, NPK, token, approval_id, approval_level, approval_progress, status
            FROM leave_requests
            WHERE id = ?
        ", [$requestId]);

        if (empty($req)) {
            return ['valid' => false, 'reason' => "Pengajuan #$requestId tidak ditemukan."];
        }
        $req = $req[0];

        if ($req->status !== 'pending') {
            return ['valid' => false, 'reason' => "Pengajuan #$requestId sudah berstatus: {$req->status}."];
        }

        // 2. Find approver NPK by phone
        $approver = self::findApproverByPhone($senderPhone);
        if (!$approver) {
            return ['valid' => false, 'reason' => "Nomor Anda tidak terdaftar sebagai approver di sistem."];
        }

        // 3. Check this approver is the one assigned to this request
        if ($approver->NPK !== $req->approval_id) {
            return ['valid' => false, 'reason' => "Anda tidak berwenang memutuskan pengajuan #$requestId."];
        }

        // 4. Check all previous levels approved (sequential enforcement)
        $level = (int) $req->approval_level;
        if ($level > 1) {
            $prevApproved = DB::connection('cii')->select("
                SELECT COUNT(*) as cnt
                FROM leave_requests
                WHERE token = ?
                  AND approval_level < ?
                  AND status = 'approved'
            ", [$req->token, $level]);

            $approvedCount = (int)($prevApproved[0]->cnt ?? 0);
            if ($approvedCount < $level - 1) {
                return ['valid' => false, 'reason' => "Belum semua level sebelumnya menyetujui pengajuan #$requestId."];
            }
        }

        return ['valid' => true, 'request' => $req, 'approver' => $approver];
    }

    /**
     * Update leave_request status after decision — ATOMIK.
     *
     * Memakai conditional UPDATE:
     *   UPDATE ... WHERE id = ? AND status = 'pending'
     *
     * Affected rows = 1 → berhasil, proceed dengan efek samping (potong balance).
     * Affected rows = 0 → request sudah diproses orang lain (race condition),
     *                    pemanggil harus abort dan TIDAK applying efek.
     *
     * Ini mencegah double potong saldo cuti saat dua pesan approval datang
     * bersamaan (double-tap link, retry WA, dua device).
     *
     * @return int jumlah row yang ter-update (1 = berhasil, 0 = sudah diproses)
     */
    public static function updateDecision(int $requestId, string $decision, ?string $comment, string $approverNpk): int
    {
        $status = strtolower($decision) === 'approve' ? 'approved' : 'rejected';
        $now    = now()->toDateTimeString();

        $affected = DB::connection('cii')->update("
            UPDATE leave_requests
            SET status       = ?,
                approval_date = ?,
                comment      = ?,
                updated_at   = ?
            WHERE id = ? AND status = 'pending'
        ", [$status, $now, $comment, $now, $requestId]);

        if ($affected > 0) {
            Log::info("[ApprovalWa] Request #$requestId → $status by $approverNpk");
        } else {
            Log::warning("[ApprovalWa] Request #$requestId NOT updated (already processed) by $approverNpk");
        }

        return $affected;
    }

    /**
     * Batalkan keputusan yang SUDAH diambil (approved → rejected).
     *
     * Dipakai untuk command /batal via WhatsApp. Hanya boleh kalau level
     * berikutnya belum bertindak — kalau level bawah sudah approve, chain sudah
     * maju dan pembatalan tidak aman.
     *
     * @return array{ok:bool, reason:?string}
     */
    public static function revertDecision(int $requestId, string $approverNpk): array
    {
        $req = DB::connection('cii')->select("
            SELECT TOP 1 id, token, approval_id, approval_level, status
            FROM leave_requests
            WHERE id = ?
        ", [$requestId]);

        if (empty($req)) {
            return ['ok' => false, 'reason' => "Pengajuan #$requestId tidak ditemukan."];
        }

        $req = $req[0];

        // Hanya approver yang=row ini yang boleh membatalkan
        if ($req->approval_id !== $approverNpk) {
            return ['ok' => false, 'reason' => "Anda tidak berwenang membatalkan pengajuan #$requestId."];
        }

        // Hanya bisa membatalkan yang sudah approved
        if ($req->status !== 'approved') {
            return [
                'ok'     => false,
                'reason' => "Pengajuan #$requestId berstatus '{$req->status}'. Gunakan /tolak untuk pengajuan yang masih pending.",
            ];
        }

        // Guard: level berikutnya belum boleh bertindak
        $laterLevelActed = DB::connection('cii')->select("
            SELECT COUNT(*) as cnt
            FROM leave_requests
            WHERE token = ?
              AND approval_level > ?
              AND status != 'pending'
              AND void != 'true'
        ", [$req->token, $req->approval_level]);

        if ((int)($laterLevelActed[0]->cnt ?? 0) > 0) {
            return [
                'ok'     => false,
                'reason' => "Tidak bisa dibatalkan — level berikutnya sudah menyetujui pengajuan #$requestId.",
            ];
        }

        return ['ok' => true, 'request' => $req];
    }

    /**
     * Update status row secara bersyarat (untuk revert).
     *
     * @return int affected rows (1 = berhasil, 0 = gagal / sudah berubah)
     */
    public static function applyRevert(int $requestId, string $reason): int
    {
        $now = now()->toDateTimeString();

        return DB::connection('cii')->update("
            UPDATE leave_requests
            SET status       = 'rejected',
                approval_date = ?,
                comment      = ?,
                updated_at   = ?
            WHERE id = ? AND status = 'approved'
        ", [$now, $reason, $now, $requestId]);
    }

    /**
     * Nama karyawan (pemohon) dari master data.
     *
     * BIODATA = master karyawan; dipakai juga oleh LeaveApprovalController.
     * Lookup TOP 1 by NPK supaya tidak full-scan.
     */
    public static function getEmployeeName(string $npk): ?string
    {
        $rows = DB::connection('cii')->select("
            SELECT TOP 1 NAMA_KARYAWAN FROM BIODATA WHERE NPK = ?
        ", [$npk]);

        $name = $rows[0]->NAMA_KARYAWAN ?? null;
        $name = is_string($name) ? trim($name) : null;

        return ($name === '') ? null : $name;
    }

    /**
     * Nama jenis cuti dari leave_types (mis. "Cuti Tahunan", "Cuti Menikah").
     */
    public static function getLeaveTypeName(int $leaveTypeId): ?string
    {
        $rows = DB::connection('cii')->select("
            SELECT TOP 1 name FROM leave_types WHERE id = ?
        ", [$leaveTypeId]);

        $name = $rows[0]->name ?? null;
        $name = is_string($name) ? trim($name) : null;

        return ($name === '') ? null : $name;
    }

    /**
     * Hitung jumlah level dalam satu token (total chain approval).
     */
    public static function countTokenLevels(string $token): int
    {
        $rows = DB::connection('cii')->select("
            SELECT COUNT(DISTINCT approval_level) as cnt
            FROM leave_requests
            WHERE token = ?
        ", [$token]);

        return (int)($rows[0]->cnt ?? 0);
    }

    /**
     * Find approver by normalized phone number.
     * Looks up all approver NPKs from approval_rules, then checks PKWT.HP one by one.
     * Returns NPK + NAMA if found.
     */
    public static function findApproverByPhone(string $senderPhone): ?object
    {
        // Get distinct approver NPKs from approval_rules
        $npks = DB::connection('cii')->select("
            SELECT DISTINCT approval_id FROM approval_rules
        ");

        foreach ($npks as $row) {
            $pkwt = DB::connection('cii')->select("
                SELECT TOP 1 NPK, NAMA, HP FROM PKWT WHERE NPK = ?
            ", [$row->approval_id]);

            if (empty($pkwt) || empty($pkwt[0]->HP)) {
                continue;
            }

            $normalized = PhoneFormatter::normalize($pkwt[0]->HP);
            if ($normalized === $senderPhone) {
                return $pkwt[0];
            }
        }

        return null;
    }
}
