<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApprovalWa\ApprovalApiPayload;
use App\Services\ApprovalWa\InboundApprovalProcessor;
use App\Services\ApprovalWa\LeaveApprovalNotifier;
use App\Services\ApprovalWa\PhoneFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * API Approval — messaging layer (Hermes) ↔ business logic (Laravel).
 *
 * Opsi C decoupling:
 *  - Laravel = business logic + data. TIDAK pernah kirim WA sendiri.
 *  - Hermes = messaging layer. Ambil payload dari sini, kirim via bridge.
 *
 * Endpoint:
 *  GET  /api/approval/pending   → list pengajuan yang siap dikabari + pesan siap kirim
 *  POST /api/approval/decision  → proses keputusan dari approver (inbound WA)
 *  POST /api/approval/trigger   → Laravel minta Hermes kirim notif untuk 1 token (instant)
 *
 * Auth: bearer token (config services.approval_api.token) + optional IP allowlist.
 */
class ApprovalApiController extends Controller
{
    public function __construct()
    {
        // Middleware alias (bukan auth guard) — die-register di app/Http/Kernel.php
        $this->middleware(\App\Http\Middleware\AuthenticateApprovalApi::class);
    }

    /**
     * GET /api/approval/pending
     *
     * Return pengajuan yang level-nya "giliran" untuk dikabari.
     * Group by token, sequential enforced (level N hanya kalau level <N approved).
     */
    public function pending(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = ApprovalApiPayload::pendingPayload();

        Log::info("[ApprovalApi] pending requested by {$request->ip()} — {$payload['count']} item(s)");

        return response()->json($payload);
    }

    /**
     * POST /api/approval/decision
     *
     * Body:
     * {
     *   "sender": "6281234567890",     // nomor approver (raw)
     *   "message": "/approve 849",      // pesan dari approver
     *   "decision": "approve",          // opsional: parsed decision (dipakai kalau message absent)
     *   "reason": "..."                // opsional
     * }
     *
     * Response:
     * {
     *   "request_id": 849,
     *   "status": "ok" | "rejected" | "unauthorized" | "error",
     *   "reply": "pesan balasan untuk approver (siap dikirim Hermes)",
     *   ...
     * }
     */
    public function decision(Request $request): \Illuminate\Http\JsonResponse
    {
        $rawPhone = trim((string) $request->input('sender', ''));
        $message  = trim((string) $request->input('message', ''));

        // Kalau Hermes sudah parse sendiri, boleh kirim decision + id langsung
        $decision = trim((string) $request->input('decision', ''));
        $requestId = (int) $request->input('request_id', 0);
        $reason    = $request->input('reason');

        if ($decision !== '' && $requestId > 0) {
            $message = $decision === 'approve'
                ? "/approve $requestId"
                : ($decision === 'revert' ? "/batal $requestId" : "/tolak $requestId");
            if (!empty($reason)) {
                $message .= ' ' . $reason;
            }
        }

        if ($rawPhone === '' || $message === '') {
            return response()->json([
                'status' => 'error',
                'error'  => 'Missing sender or message',
            ], 422);
        }

        $phone = PhoneFormatter::normalize($rawPhone);
        if (!PhoneFormatter::isValid($phone)) {
            Log::info("[ApprovalApi] invalid phone format: $rawPhone — ignored.");
            return response()->json([
                'status' => 'ignored',
                'reason' => 'invalid phone',
            ]);
        }

        Log::info("[ApprovalApi] decision from $phone: $message");

        $reply = InboundApprovalProcessor::handle($phone, $message);

        // Bedakan sukses vs penolakan berdasarkan isi reply
        $status = 'ok';
        if (str_starts_with($reply, '⛔') || str_starts_with($reply, '⚠️')) {
            $status = 'rejected';
        }

        return response()->json(ApprovalApiPayload::decisionResult($requestId, $status, $reply));
    }

    /**
     * POST /api/approval/trigger
     *
     * Laravel meminta Hermes mengirim notifikasi untuk 1 pengajuan (token).
     * Dipakai setelah submit pengajuan atau setelah approve — supaya approver
     * level berikutnya langsung dikabari tanpa menunggu cron.
     *
     * Body:
     *   { "token": "abc123" }
     *
     * Response: payload item siap kirim (Hermes tinggal POST ke bridge).
     */
    public function trigger(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = trim((string) $request->input('token', ''));
        if ($token === '') {
            return response()->json(['status' => 'error', 'error' => 'Missing token'], 422);
        }

        Log::info("[ApprovalApi] trigger for token $token from {$request->ip()}");

        // Dry-run build payload — tidak kirim. Pengiriman dilakukan messaging layer.
        $result = LeaveApprovalNotifier::buildPayloadForToken($token);

        if ($result === null) {
            return response()->json([
                'status' => 'not_eligible',
                'reason' => 'Tidak ada level yang eligible untuk token ini (sudah diproses / ditolak).',
                'token'  => $token,
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'token'  => $token,
            'items'  => [$result],
        ]);
    }
}
