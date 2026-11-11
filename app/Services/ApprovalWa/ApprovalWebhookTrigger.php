<?php

namespace App\Services\ApprovalWa;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Trigger messaging layer (Hermes) via Hermes Webhook platform.
 *
 * Arah: Laravel (business logic) → Hermes (messaging layer).
 *
 * Hermes webhook endpoint:
 *   POST http://<hermes-host>:8644/webhooks/<route_name>
 *
 * Auth: HMAC-SHA256 V2 (replay-safe):
 *   X-Webhook-Timestamp: <unix timestamp>
 *   X-Webhook-Signature-V2: hex_hmac_sha256(secret, "<timestamp>.<raw_body>")
 *
 * Env:
 *   APPROVAL_WEBHOOK_URL     full URL termasuk route, mis.
 *                             http://192.168.0.68:8644/webhooks/leave-approval
 *   APPROVAL_WEBHOOK_SECRET  shared secret (harus sama dengan Hermes subscribe --secret)
 *
 * Kalau URL kosong → mode single-server (Laravel kirim langsung ke WA bridge).
 */
class ApprovalWebhookTrigger
{
    /**
     * Trigger notifikasi untuk satu token pengajuan.
     *
     * @param string $token
     * @param string $triggerType 'submit' | 'approve' | 'cron' — untuk logging & filtering di Hermes
     */
    public static function notify(string $token, string $triggerType = 'submit'): bool
    {
        $url    = rtrim((string) config('services.approval_api.webhook_url', ''), '/');
        $secret = (string) config('services.approval_api.webhook_secret', '');

        if ($url === '') {
            Log::info('[ApprovalWebhook] webhook_url kosong — fallback ke bridge lokal.');
            return self::sendDirect($token);
        }

        $payload = [
            'token'   => $token,
            'trigger' => $triggerType,
        ];

        try {
            $body    = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $ts      = (string) now()->timestamp;
            $headers = [
                'X-Webhook-Timestamp' => $ts,
            ];

            if ($secret !== '') {
                // Hermes: X-Webhook-Signature-V2 = hex HMAC-SHA256("<timestamp>.<raw_body>")
                $headers['X-Webhook-Signature-V2'] = hash_hmac('sha256', $ts . '.' . $body, $secret);
            }

            // withBody() — kirim string apa adanya. Http::post($url, $string) akan
            // membungkus jadi JSON string literal sehingga signature tidak cocok.
            $response = Http::timeout(15)
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($url);

            if ($response->successful()) {
                Log::info("[ApprovalWebhook] Hermes notified for token $token (trigger=$triggerType)");
                return true;
            }

            Log::warning("[ApprovalWebhook] Hermes returned {$response->status()} for token $token: " . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error("[ApprovalWebhook] Hermes unreachable: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fallback mode single-server: kirim langsung ke WA bridge.
     */
    private static function sendDirect(string $token): bool
    {
        $result = LeaveApprovalNotifier::notifyToken($token);

        if ($result['notified'] > 0) {
            return true;
        }

        foreach ($result['skipped'] as $reason) {
            Log::warning("[ApprovalWebhook] token $token: $reason");
        }

        return false;
    }
}
