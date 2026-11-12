<?php

namespace App\Services\ApprovalWa;

use Illuminate\Support\Facades\Log;

/**
 * Notifikasi per-pengajuan (token), bukan sweep semua pending.
 *
 * Dipanggil dari:
 *  - PengajuanCutiController::store()  → setelah pengajuan baru dibuat
 *  - LeaveApprovalController::approve() → setelah level N approve
 *  - InboundApprovalProcessor::handle() → setelah approve via WhatsApp
 *  - Artisan command approval:notify-token (testing manual)
 *
 * Aturan: hanya level yang "giliran" yang dikabari.
 * - Row pending di token, urut level.
 * - Level 1 → selalu eligible (level sebelumnya tidak ada).
 * - Level N → eligible hanya jika semua level 1..N-1 di token itu = approved.
 * - Jika ada level yang rejected → stop, jangan kabari level mana pun.
 */
class LeaveApprovalNotifier
{
    /**
     * Kirim notifikasi WhatsApp untuk satu token (satu pengajuan).
     *
     * @param string $token   token pengajuan
     * @param bool   $dryRun  true = print saja, tidak kirim
     * @return array{notified:int, skipped:string[], sent:array<int,string>}
     */
    public static function notifyToken(string $token, bool $dryRun = false): array
    {
        $result = ['notified' => 0, 'skipped' => [], 'sent' => []];

        $rows = LeaveApprovalQuery::getTokenRows($token);
        if (empty($rows)) {
            $result['skipped'][] = "Token $token tidak ditemukan";
            return $result;
        }

        $eligible = self::pickEligibleLevel($rows);

        if ($eligible === null) {
            $result['skipped'][] = "Token $token tidak punya level yang eligible (sudah diproses / ditolak)";
            return $result;
        }

        $row      = $eligible['row'];
        $requestId = (int) $row->id;
        $level    = (int) $row->approval_level;

        // Validasi approver punya nomor HP yang bisa dipakai
        $approverInfo = LeaveApprovalQuery::getApproverInfo($row->approval_id);
        if (!$approverInfo) {
            $result['skipped'][] = "#$requestId: approver {$row->approval_id} tidak ada di PKWT";
            Log::warning("[ApprovalNotifier] #$requestId: approver {$row->approval_id} not found in PKWT");
            return $result;
        }

        $rawPhone = $approverInfo->HP ?? null;
        $phone    = PhoneFormatter::normalize($rawPhone);

        if (!PhoneFormatter::isValid($phone)) {
            $result['skipped'][] = "#$requestId: nomor HP approver tidak valid ('$rawPhone')";
            Log::warning("[ApprovalNotifier] #$requestId: invalid phone for {$row->approval_id}: '$rawPhone'");
            return $result;
        }

        $message = WaMessageFormatter::formatApprovalRequest($row, $approverInfo);

        if ($dryRun) {
            Log::info("[ApprovalNotifier] DRY RUN #$requestId → $phone (level $level)");
            $result['notified'] = 1;
            $result['sent'][$requestId] = $phone;
            $result['message'] = $message;
            return $result;
        }

        $ok = WaGateway::send($phone, $message);
        if ($ok) {
            $result['notified'] = 1;
            $result['sent'][$requestId] = $phone;
            Log::info("[ApprovalNotifier] #$requestId notified to $phone (level $level)");
        } else {
            $result['skipped'][] = "#$requestId: gagal kirim ke $phone";
            Log::error("[ApprovalNotifier] #$requestId failed to send to $phone");
        }

        return $result;
    }

    /**
     * Build payload item (tanpa mengirim) untuk satu token.
     *
     * Dipakai oleh Opsi C: messaging layer (Hermes) yang benar-benar mengirim,
     * Laravel hanya menyiapkan data + pesan siap kirim.
     *
     * @return array|null payload item, atau null kalau tidak ada level eligible
     */
    public static function buildPayloadForToken(string $token): ?array
    {
        $rows = self::getTokenRows($token);
        if (empty($rows)) {
            return null;
        }

        $eligible = self::pickEligibleLevel($rows);
        if ($eligible === null) {
            return null;
        }

        $row = $eligible['row'];

        $approverInfo = LeaveApprovalQuery::getApproverInfo($row->approval_id);
        if (!$approverInfo) {
            Log::warning("[ApprovalNotifier] approver {$row->approval_id} not found in PKWT (token $token)");
            return null;
        }

        $phone = PhoneFormatter::normalize($approverInfo->HP ?? null);
        if (!PhoneFormatter::isValid($phone)) {
            Log::warning("[ApprovalNotifier] invalid phone for {$row->approval_id} (token $token): '{$approverInfo->HP}'");
            return null;
        }

        $id      = (int) $row->id;
        $message = WaMessageFormatter::formatApprovalRequest($row, $approverInfo);

        return [
            'request_id'      => $id,
            'token'           => $token,
            'level'           => (int) $row->approval_level,
            'total_levels'    => LeaveApprovalQuery::countTokenLevels($token),
            'phone'           => $phone,
            'raw_phone'       => $approverInfo->HP,
            'approver_npk'    => $row->approval_id,
            'approver_name'   => $approverInfo->NAMA ?? $approverInfo->NPK,
            'employee_npk'    => $row->NPK,
            'employee_name'   => LeaveApprovalQuery::getEmployeeName((string) $row->NPK),
            'leave_type_id'   => (int) ($row->leave_type_id ?? 0),
            'leave_type'      => LeaveApprovalQuery::getLeaveTypeName((int) ($row->leave_type_id ?? 0)),
            'submitted_at'    => $row->created_at ?? null,
            'start_date'      => $row->start_date,
            'end_date'        => $row->end_date,
            'duration_days'   => (int) $row->total_days,
            'reason'          => $row->reason,
            'message'         => $message,
            'approve_command' => "/approve $id",
            'reject_command'  => "/tolak $id <alasan>",
        ];
    }

    /**
     * Ambil semua row satu token, urut level.
     */
    public static function getTokenRows(string $token): array
    {
        return \Illuminate\Support\Facades\DB::connection('cii')->select("
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
            WHERE lr.token = ?
            ORDER BY lr.approval_level ASC
        ", [$token]);
    }

    /**
     * Tentukan level mana yang giliran notified.
     *
     * @return array{row:object, level:int}|null
     */
    private static function pickEligibleLevel(array $rows): ?array
    {
        // Status map: level → status
        $statusByLevel = [];
        foreach ($rows as $row) {
            $statusByLevel[(int)$row->approval_level] = $row->status;
        }

        // Kalau ada level rejected → stop chain, jangan kabari apa pun
        foreach ($statusByLevel as $status) {
            if ($status === 'rejected') {
                return null;
            }
        }

        // Cari level pending pertama yang semua level sebelumnya approved
        foreach ($rows as $row) {
            $level  = (int)$row->approval_level;
            $status = $row->status;

            if ($status !== 'pending') {
                continue; // sudah diproses
            }

            $allPrevApproved = true;
            for ($prev = 1; $prev < $level; $prev++) {
                if (($statusByLevel[$prev] ?? null) !== 'approved') {
                    $allPrevApproved = false;
                    break;
                }
            }

            if ($allPrevApproved) {
                return ['row' => $row, 'level' => $level];
            }
        }

        return null;
    }

    /**
     * Dipanggil setelah approve di level N.
     * Notifikasi level N+1 kalau ada (dan level N+1 belum approved).
     */
    public static function notifyNextLevel(string $token, bool $dryRun = false): array
    {
        return self::notifyToken($token, $dryRun);
    }
}
