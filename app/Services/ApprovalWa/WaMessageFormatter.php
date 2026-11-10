<?php

namespace App\Services\ApprovalWa;

class WaMessageFormatter
{
    /**
     * Format outbound notification message to approver.
     */
    public static function formatApprovalRequest(object $request, object $approver): string
    {
        $startDate = $request->start_date;
        $endDate   = $request->end_date;
        $days      = $request->total_days;
        $pemohon   = $request->pemohon_nama ?? $request->NPK;
        $id        = $request->id;
        $level     = (int)$request->approval_progress + 1;
        $totalLevel= $request->approval_level;
        $reason    = $request->reason ?? '-';

        return <<<MSG
        ╔══════════════════════════╗
        🗂 *PENGAJUAN CUTI - PERLU APPROVAL*
        ╚══════════════════════════╝

        📋 *ID Pengajuan:* #$id
        👤 *Pemohon:* $pemohon ($request->NPK)
        📅 *Tanggal:* $startDate s/d $endDate ($days hari)
        📝 *Alasan:* $reason
        🔢 *Level Approval:* $level dari $totalLevel

        ━━━━━━━━━━━━━━━━━━━━━━━━━━
        Yth. *{$approver->NAMA}*,
        Mohon berikan keputusan Anda dengan membalas:

        ✅ *APPROVE $id*
        ❌ *REJECT $id <alasan>*

        ⚠️ Pastikan ID pengajuan sesuai sebelum membalas.
        MSG;
    }

    /**
     * Format confirmation message after decision.
     */
    public static function formatConfirmation(int $requestId, string $decision, string $approverName): string
    {
        $status    = strtolower($decision) === 'approve' ? '✅ DISETUJUI' : '❌ DITOLAK';
        $timestamp = now()->format('d/m/Y H:i');

        return <<<MSG
        ✔️ *Konfirmasi Keputusan*

        Pengajuan cuti *#$requestId* telah *$status*.
        Oleh: $approverName
        Waktu: $timestamp

        Terima kasih.
        MSG;
    }

    /**
     * Format error/unauthorized message.
     */
    public static function formatUnauthorized(int $requestId, string $reason): string
    {
        return "⛔ Gagal memproses pengajuan #$requestId.\n\nAlasan: $reason";
    }

    /**
     * Format message when no pending requests found (for cron log / admin notification).
     */
    public static function formatNoPending(): string
    {
        return "✅ Tidak ada pengajuan cuti yang perlu approval saat ini.";
    }
}
