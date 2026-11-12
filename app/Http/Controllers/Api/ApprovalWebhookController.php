<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApprovalWa\InboundApprovalProcessor;
use App\Services\ApprovalWa\PhoneFormatter;
use App\Services\ApprovalWa\WaGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApprovalWebhookController extends Controller
{
    /**
     * Inbound webhook from WhatsApp gateway.
     *
     * Expected POST body (JSON):
     * {
     *   "sender": "628123456789",   // phone number of sender
     *   "message": "APPROVE 123"    // raw message text
     * }
     *
     * This endpoint is called by the WhatsApp gateway when a message arrives.
     * No auth middleware — secure via WA_WEBHOOK_SECRET token in header if needed.
     */
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        // Optional: validate secret token
        $secret = config('services.wa_gateway.webhook_secret');
        if ($secret && $request->header('X-WA-Token') !== $secret) {
            Log::warning('[ApprovalWebhook] Unauthorized request from ' . $request->ip());
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $rawPhone = $request->input('sender', '');
        $message  = trim($request->input('message', ''));

        if (empty($rawPhone) || empty($message)) {
            return response()->json(['error' => 'Missing sender or message'], 422);
        }

        // Normalize phone
        $phone = PhoneFormatter::normalize($rawPhone);
        if (!PhoneFormatter::isValid($phone)) {
            Log::info("[ApprovalWebhook] Invalid phone format: $rawPhone — ignored.");
            return response()->json(['status' => 'ignored', 'reason' => 'invalid phone']);
        }

        Log::info("[ApprovalWebhook] Inbound from $phone: $message");

        // Process the decision
        $reply = InboundApprovalProcessor::handle($phone, $message);

        // Send reply back via gateway
        WaGateway::send($phone, $reply);

        return response()->json(['status' => 'ok', 'reply' => $reply]);
    }
}
