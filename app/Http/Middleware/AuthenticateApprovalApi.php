<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Auth untuk API approval (Opsi C — messaging layer Hermes ↔ business logic Laravel).
 *
 * Dua lapis:
 *  1. Bearer token — cocok dengan config services.approval_api.token
 *  2. IP allowlist (opsional) — config services.approval_api.allowed_ips
 *
 * Kalau token belum diset → endpoint menolak semua request (fail-closed).
 */
class AuthenticateApprovalApi
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('services.approval_api.token', '');
        $allowedIps = config('services.approval_api.allowed_ips', []);

        if ($expected === '') {
            Log::error('[ApprovalApi] APPROVAL_API_TOKEN belum diset — request ditolak.');
            return response()->json([
                'status' => 'error',
                'error'  => 'API approval belum dikonfigurasi (APPROVAL_API_TOKEN kosong).',
            ], 503);
        }

        // IP allowlist
        if (!empty($allowedIps) && !in_array($request->ip(), (array) $allowedIps, true)) {
            Log::warning("[ApprovalApi] IP {$request->ip()} tidak di allowlist.");
            return response()->json(['status' => 'error', 'error' => 'Forbidden'], 403);
        }

        // Bearer token
        $header = (string) $request->header('Authorization', '');
        $token  = '';

        if (stripos($header, 'Bearer ') === 0) {
            $token = trim(substr($header, 7));
        } elseif ($request->has('api_token')) {
            $token = (string) $request->query('api_token');
        }

        if ($token === '' || !hash_equals($expected, $token)) {
            Log::warning("[ApprovalApi] Token tidak valid dari {$request->ip()}.");
            return response()->json(['status' => 'error', 'error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
