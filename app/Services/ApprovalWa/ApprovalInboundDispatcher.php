<?php

namespace App\Services\ApprovalWa;

/**
 * Titik masuk tunggal untuk pesan approval dari WhatsApp.
 *
 * Membaca doctype dari command lalu meneruskan ke processor yang tepat.
 * Pemanggil (webhook gateway / API Hermes) tidak perlu tahu dokumen apa saja
 * yang didukung — cukup satu pintu.
 */
class ApprovalInboundDispatcher
{
    /**
     * Proses satu pesan masuk dan kembalikan balasan siap kirim.
     */
    public static function handle(string $phone, string $message): string
    {
        $parsed = ApprovalCommandParser::parse($message);

        if ($parsed === null) {
            return ApprovalCommandParser::helpText();
        }

        return $parsed['doc'] === ApprovalCommandParser::DOC_IZIN_DINAS
            ? IzinDinasInboundProcessor::handle($phone, $message)
            : LeaveInboundProcessor::handle($phone, $message);
    }
}