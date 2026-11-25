<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApprovalWa\ApprovalApiPayload;
use App\Services\ApprovalWa\ApprovalCommandParser;
use App\Services\ApprovalWa\ApprovalInboundDispatcher;
use App\Services\ApprovalWa\IzinDinasNotifier;
use App\Services\ApprovalWa\IzinMeninggalkanNotifier;
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
        $doc = trim((string) $request->input('doc', ApprovalCommandParser::DOC_CUTI));

        $payload = ApprovalApiPayload::pendingPayload($doc);

        Log::info("[ApprovalApi] pending ($doc) requested by {$request->ip()} — {$payload['count']} item(s)");

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
        $doc       = trim((string) $request->input('doc', ''));

        if ($decision !== '' && $requestId > 0) {
            // Bangun ulang jadi command supaya jalurnya sama dengan pesan teks
            // asli dari approver — termasuk jenis dokumennya.
            $slug = match ($doc) {
                ApprovalCommandParser::DOC_IZIN_DINAS        => 'izin-dinas',
                ApprovalCommandParser::DOC_IZIN_MENINGGALKAN => 'izin-meninggalkan',
                default                                      => 'cuti',
            };
            $verb = $decision === 'approve'
                ? 'approve'
                : ($decision === 'revert' ? 'batal' : 'tolak');

            $message = "/{$verb}-{$slug}{$requestId}";

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

        $reply = ApprovalInboundDispatcher::handle($phone, $message);

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
     *   { "token": "abc123", "doc": "izin-dinas" }   // doc default: cuti
     *
     * Response: payload item siap kirim (Hermes tinggal POST ke bridge).
     */
    public function trigger(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = trim((string) $request->input('token', ''));
        if ($token === '') {
            return response()->json(['status' => 'error', 'error' => 'Missing token'], 422);
        }

        $doc = trim((string) $request->input('doc', ApprovalCommandParser::DOC_CUTI));

        Log::info("[ApprovalApi] trigger for $doc token $token from {$request->ip()}");

        // Dry-run build payload — tidak kirim. Pengiriman dilakukan messaging layer.
        $result = match ($doc) {
            ApprovalCommandParser::DOC_IZIN_DINAS        => IzinDinasNotifier::buildPayloadForToken($token),
            ApprovalCommandParser::DOC_IZIN_MENINGGALKAN => IzinMeninggalkanNotifier::buildPayloadForToken($token),
            default                                      => LeaveApprovalNotifier::buildPayloadForToken($token),
        };

        if ($result === null) {
            return response()->json([
                'status' => 'not_eligible',
                'reason' => 'Tidak ada level yang eligible untuk token ini (sudah diproses / ditolak).',
                'token'  => $token,
                'doc'    => $doc,
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'doc'    => $doc,
            'token'  => $token,
            'items'  => [$result],
        ]);
    }
}
