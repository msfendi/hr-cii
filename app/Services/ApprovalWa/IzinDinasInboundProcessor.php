<?php

namespace App\Services\ApprovalWa;

use App\Services\WorkAssignment\WorkAssignmentApproval;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Proses keputusan approval Izin Dinas yang masuk lewat WhatsApp.
 *
 * Alur: parse command → cari pengajuan → cocokkan nomor pengirim dengan
 * approver pada rantai → serahkan keputusan ke WorkAssignmentApproval
 * (sudah atomik + berurutan) → cascade notifikasi → balasan.
 *
 * Tidak ada efek samping saldo: Izin Dinas tidak memotong saldo cuti, jadi
 * berbeda dari cuti yang memanggil LeaveApprovalEffects.
 */
class IzinDinasInboundProcessor
{
    /**
     * Tangani satu pesan masuk untuk dokumen Izin Dinas.
     */
    public static function handle(string $phone, string $message): string
    {
        $parsed = ApprovalCommandParser::parse($message);

        if ($parsed === null) {
            return ApprovalCommandParser::helpText();
        }

        $requestId = (int) $parsed['id'];
        $decision  = (string) $parsed['decision'];
        $reason    = $parsed['reason'];

        // Pembatalan keputusan tidak disediakan untuk Izin Dinas.
        if ($decision === 'revert') {
            return IzinDinasMessageFormatter::formatUnsupported($requestId, 'Pembatalan keputusan');
        }

        $request = IzinDinasApprovalQuery::getById($requestId);

        if (!$request) {
            Log::warning("[IzinDinasInbound] #$requestId tidak ditemukan (dari $phone)");

            return "⛔ Pengajuan *Izin Dinas #$requestId* tidak ditemukan.";
        }

        // ── Identitas: nomor pengirim harus approver pada rantai pengajuan ini ──
        $approver = IzinDinasApprovalQuery::resolveApproverByPhone($request, $phone);

        if (!$approver) {
            Log::warning("[IzinDinasInbound] nomor $phone bukan approver #$requestId");

            return IzinDinasMessageFormatter::formatUnauthorized(
                $requestId,
                "Nomor Anda ($phone) tidak terdaftar sebagai approver pada pengajuan ini."
            );
        }

        $level = $approver['level'];

        if ($level === null) {
            return IzinDinasMessageFormatter::formatUnauthorized(
                $requestId,
                'Anda tidak tercantum pada rantai approval pengajuan ini.'
            );
        }

        if (!$request->isOpen()) {
            return IzinDinasMessageFormatter::formatUnauthorized(
                $requestId,
                'Pengajuan ini sudah diproses sebelumnya (status: ' . $request->status . ').'
            );
        }

        // ── Tolak wajib beralasan ──
        if ($decision === 'reject' && ($reason === null || trim((string) $reason) === '')) {
            return "⚠️ Alasan penolakan wajib diisi.\n\n"
                . "Balas dengan:\n• /tolak-izin-dinas$requestId <alasan>";
        }

        // ── Terapkan keputusan lewat service yang sudah atomik ──
        try {
            $result = $decision === 'approve'
                ? WorkAssignmentApproval::approve($request, $approver['npk'], $level, null)
                : WorkAssignmentApproval::reject($request, $approver['npk'], $level, $reason);
        } catch (Throwable $e) {
            Log::error("[IzinDinasInbound] #$requestId gagal diproses: " . $e->getMessage());

            return "⛔ Terjadi kesalahan saat memproses *Izin Dinas #$requestId*. Perubahan tidak disimpan.";
        }

        if (empty($result['ok'])) {
            Log::warning("[IzinDinasInbound] #$requestId ditolak service: " . ($result['message'] ?? '-'));

            return IzinDinasMessageFormatter::formatUnauthorized(
                $requestId,
                (string) ($result['message'] ?? 'Keputusan tidak dapat diproses.')
            );
        }

        // ── Cascade: kabari level berikutnya setelah approve ──
        if ($decision === 'approve') {
            try {
                ApprovalWebhookTrigger::notify(
                    (string) $request->token,
                    'approve',
                    ApprovalCommandParser::DOC_IZIN_DINAS
                );
            } catch (Throwable $e) {
                Log::error("[IzinDinasInbound] cascade notify #$requestId: " . $e->getMessage());
            }
        }

        Log::info(
            "[IzinDinasInbound] #$requestId {$decision} oleh {$approver['npk']} "
            . "(level $level) via WhatsApp"
        );

        $fresh = IzinDinasApprovalQuery::getById($requestId) ?? $request;

        return IzinDinasMessageFormatter::formatConfirmation($fresh, $decision, $approver['name']);
    }
}