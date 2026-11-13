<?php

namespace App\Services\ApprovalWa;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaGateway
{
    /**
     * Send a WhatsApp message via Hermes WhatsApp gateway (Baileys bridge).
     *
     * Gateway REST API (bridge.js):
     *   POST /send  { chatId: "<62xxx>@s.whatsapp.net", message: "..." }
     *
     * Bridge handles WhatsApp formatting + chunking (4096 char) internally,
     * so no extra processing needed here.
     *
     * Env (.env):
     *   WA_GATEWAY_URL=http://localhost:3000
     *   WA_GATEWAY_TOKEN=          (optional bearer token)
     *   WA_SEND_JITTER_SECONDS=15  (jitter sebelum tiap kirim, 0 = disable)
     *   WA_SEND_MIN_GAP_SECONDS=15 (gap minimum antar pesan)
     *   WA_SEND_MAX_PER_MINUTE=6   (burst guard)
     */
    public static function send(string $phone, string $message, bool $throttle = true): bool
    {
        $url   = rtrim(config('services.wa_gateway.url', 'http://localhost:3000'), '/');
        $token = config('services.wa_gateway.token', '');

        // Normalisasi ke digit saja (bisa datang dengan "+" atau format lokal).
        $phone = (string) PhoneFormatter::normalize($phone);

        if ($phone === '') {
            Log::warning('[WaGateway] Nomor tidak bisa dinormalisasi — tidak dikirim.');
            return false;
        }

        try {
            // Throttle: jeda acak + rate limit sebelum kirim.
            // Mencegah pola bursty yang mudah di-flag WhatsApp.
            if ($throttle) {
                WaSendThrottle::wait();
            }

            // Hermes WA bridge uses chatId in JID format: 628xxx@s.whatsapp.net
            // (kode negara non-Indonesia ikut, mis. 84913662215@s.whatsapp.net)
            $chatId = $phone . '@s.whatsapp.net';

            $payload = [
                'chatId'  => $chatId,
                'message' => $message,
            ];

            $request = Http::timeout(30);
            if ($token) {
                $request = $request->withToken($token);
            }

            $response = $request->post("$url/send", $payload);

            if ($response->successful()) {
                WaSendThrottle::markSent();
                Log::info("[WaGateway] Sent to $phone: OK");
                return true;
            }

            Log::warning("[WaGateway] Failed to send to $phone: " . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error("[WaGateway] Exception sending to $phone: " . $e->getMessage());
            return false;
        }
    }
}
