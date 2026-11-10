<?php

namespace App\Services\ApprovalWa;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WaGateway
{
    /**
     * Send a WhatsApp message via Hermes WhatsApp gateway.
     * Uses the gateway REST API endpoint configured in .env:
     *   WA_GATEWAY_URL=http://localhost:3000
     *   WA_GATEWAY_TOKEN=  (optional bearer token)
     */
    public static function send(string $phone, string $message): bool
    {
        $url   = rtrim(config('services.wa_gateway.url', 'http://localhost:3000'), '/');
        $token = config('services.wa_gateway.token', '');

        try {
            $payload = [
                'phone'   => $phone,
                'message' => $message,
            ];

            $request = Http::timeout(10);
            if ($token) {
                $request = $request->withToken($token);
            }

            $response = $request->post("$url/send-message", $payload);

            if ($response->successful()) {
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
