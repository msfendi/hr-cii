<?php

namespace App\Services\ApprovalWa;

class WaMessageFormatter
{
    /**
     * Format outbound notification message to approver.
     * Includes WhatsApp deep links for approve/reject actions.
     *
     * WA deep link format: https://wa.me/<bot_number>?text=<encoded_text>
     * Bot number taken from env WA_BOT_NUMBER (e.g. 6287731665679)
     */
    public static function formatApprovalRequest(object $request, object $approver): string
    {
        $startDate  = $request->start_date;
        $endDate    = $request->end_date;
        $days       = $request->total_days;
        $pemohon    = $request->NPK;
        $id         = $request->id;
        $level      = (int) $request->approval_level;
        $reason     = $request->reason ?? '-';

        $approverName = $approver->NAMA ?? $approver->NPK;

        // WhatsApp deep links
        $botNumber   = config('services.wa_gateway.bot_number', '6287731665679');
        $approveText = urlencode("/approve $id");
        $rejectText  = urlencode("/tolak $id <alasan>");
        $approveLink = "https://wa.me/$botNumber?text=$approveText";
        $rejectLink  = "https://wa.me/$botNumber?text=$rejectText";

        return <<<MSG
        ╔══════════════════════════╗
        🗂 *PENGAJUAN CUTI - PERLU APPROVAL*
        ╚══════════════════════════╝

        📋 *ID Pengajuan:* #$id
        👤 *Pemohon:* $pemohon
        📅 *Tanggal:* $startDate s/d $endDate ($days hari)
        📝 *Alasan:* $reason
        🔢 *Level Approval:* $level

        ━━━━━━━━━━━━━━━━━━━━━━━━━━
        Yth. *$approverName*,
        Silakan berikan keputusan Anda:

        ✅ *Setujui* → $approveLink
        ❌ *Tolak* → $rejectLink

        Atau balas langsung:
        • */approve $id*
        • */tolak $id <alasan>*

        ⚠️ Pastikan ID pengajuan sesuai sebelum membalas.
        MSG;
    }

    /**
     * Format confirmation message after decision.
     */
    public static function formatConfirmation(int $requestId, string $decision, string $approverName): string
    {
        $status    = strtolower($decision) === 'approve' ? '✅ DISETUJUI' : '❌ DITOLAK';
        $timestamp = now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i');

        return <<<MSG
        ✔️ *Konfirmasi Keputusan*

        Pengajuan cuti *#$requestId* telah *$status*.
        Oleh: $approverName
        Waktu: $timestamp WIB

        Terima kasih.
        MSG;
    }

    /**
     * Format error/unauthorized message.
     */
    public static function formatUnauthorized(int $requestId, string $reason): string
    {
        return "⛔ Gagal memproses pengajuan *#$requestId*.\n\nAlasan: $reason";
    }
}
