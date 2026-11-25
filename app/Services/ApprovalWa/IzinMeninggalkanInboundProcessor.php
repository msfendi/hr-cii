<?php

namespace App\Services\ApprovalWa;

use App\Services\IzinMeninggalkan\IjinMeninggalkanApproval;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Proses keputusan approval Izin Meninggalkan Tugas/Pekerjaan lewat WhatsApp.
 */
class IzinMeninggalkanInboundProcessor
{
    public static function handle(string $phone, string $message): string
    {
        $parsed = ApprovalCommandParser::parse($message);

        if ($parsed === null) {
            return ApprovalCommandParser::helpText();
        }

        $requestId = (int) $parsed['id'];
        $decision  = (string) $parsed['decision'];
        $reason    = $parsed['reason'];

        if ($decision === 'revert') {
            return IzinMeninggalkanMessageFormatter::formatUnsupported($requestId, 'Pembatalan keputusan');
        }

        $request = IzinMeninggalkanApprovalQuery::getById($requestId);

        if (!$request) {
            Log::warning("[IzinMeninggalkanInbound] #$requestId tidak ditemukan (dari $phone)");

            return "⛔ Pengajuan *Izin Meninggalkan #$requestId* tidak ditemukan.";
        }

        $approver = IzinMeninggalkanApprovalQuery::resolveApproverByPhone($request, $phone);

        if (!$approver) {
            Log::warning("[IzinMeninggalkanInbound] nomor $phone bukan approver #$requestId");

            return IzinMeninggalkanMessageFormatter::formatUnauthorized(
                $requestId,
                "Nomor Anda ($phone) tidak terdaftar sebagai approver pada pengajuan ini."
            );
        }

        $level = $approver['level'];

        if ($level === null) {
            return IzinMeninggalkanMessageFormatter::formatUnauthorized(
                $requestId,
                'Anda tidak tercantum pada rantai approval pengajuan ini.'
            );
        }

        if (!$request->isOpen()) {
            return IzinMeninggalkanMessageFormatter::formatUnauthorized(
                $requestId,
                'Pengajuan ini sudah diproses sebelumnya (status: ' . $request->status . ').'
            );
        }

        if ($decision === 'reject' && ($reason === null || trim((string) $reason) === '')) {
            return "⚠️ Alasan penolakan wajib diisi.\n\n"
                . "Balas dengan:\n• /tolak-izin-meninggalkan$requestId <alasan>";
        }

        try {
            $result = $decision === 'approve'
                ? IjinMeninggalkanApproval::approve($request, $approver['npk'], $level, null)
                : IjinMeninggalkanApproval::reject($request, $approver['npk'], $level, $reason);
        } catch (Throwable $e) {
            Log::error("[IzinMeninggalkanInbound] #$requestId gagal diproses: " . $e->getMessage());

            return "⛔ Terjadi kesalahan saat memproses *Izin Meninggalkan #$requestId*. Perubahan tidak disimpan.";
        }

        if (empty($result['ok'])) {
            Log::warning("[IzinMeninggalkanInbound] #$requestId ditolak service: " . ($result['message'] ?? '-'));

            return IzinMeninggalkanMessageFormatter::formatUnauthorized(
                $requestId,
                (string) ($result['message'] ?? 'Keputusan tidak dapat diproses.')
            );
        }

        if ($decision === 'approve') {
            try {
                ApprovalWebhookTrigger::notify(
                    (string) $request->token,
                    'approve',
                    ApprovalCommandParser::DOC_IZIN_MENINGGALKAN
                );
            } catch (Throwable $e) {
                Log::error("[IzinMeninggalkanInbound] cascade notify #$requestId: " . $e->getMessage());
            }
        }

        Log::info(
            "[IzinMeninggalkanInbound] #$requestId {$decision} oleh {$approver['npk']} "
            . "(level $level) via WhatsApp"
        );

        $fresh = IzinMeninggalkanApprovalQuery::getById($requestId) ?? $request;

        return IzinMeninggalkanMessageFormatter::formatConfirmation($fresh, $decision, $approver['name']);
    }
}
