<?php

namespace App\Services\ApprovalWa;

use App\Models\IjinMeninggalkanRequest;
use App\Services\WorkAssignment\EmployeeLookupService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi WhatsApp untuk Izin Meninggalkan Tugas / Pekerjaan.
 */
class IzinMeninggalkanNotifier
{
    /**
     * @return array{notified:int, skipped:string[], sent:array<int,string>, message?:string}
     */
    public static function notifyToken(string $token, bool $dryRun = false): array
    {
        $result   = ['notified' => 0, 'skipped' => [], 'sent' => []];
        $eligible = self::resolveEligible($token);

        if (isset($eligible['error'])) {
            $result['skipped'][] = $eligible['error'];

            return $result;
        }

        $request = $eligible['request'];
        $level   = $eligible['level'];
        $info    = $eligible['info'];
        $phone   = $eligible['phone'];
        $id      = (int) $request->id;

        $message = IzinMeninggalkanMessageFormatter::formatApprovalRequest(
            $request,
            (string) ($info->NAMA ?? $info->NPK),
            $level,
            IzinMeninggalkanApprovalQuery::totalLevels($request)
        );

        if ($dryRun) {
            Log::info("[IzinMeninggalkanNotifier] DRY RUN #$id → $phone (level $level)");
            $result['notified']  = 1;
            $result['sent'][$id] = $phone;
            $result['message']   = $message;

            return $result;
        }

        $ok = WaGateway::send($phone, $message);

        if ($ok) {
            $result['notified']  = 1;
            $result['sent'][$id] = $phone;
            Log::info("[IzinMeninggalkanNotifier] #$id notified to $phone (level $level)");
        } else {
            $result['skipped'][] = "#$id: gagal kirim ke $phone";
            Log::error("[IzinMeninggalkanNotifier] #$id failed to send to $phone");
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function buildPayloadForToken(string $token): ?array
    {
        $eligible = self::resolveEligible($token);

        if (isset($eligible['error'])) {
            return null;
        }

        $request = $eligible['request'];
        $level   = $eligible['level'];
        $info    = $eligible['info'];
        $phone   = $eligible['phone'];
        $id      = (int) $request->id;

        $message = IzinMeninggalkanMessageFormatter::formatApprovalRequest(
            $request,
            (string) ($info->NAMA ?? $info->NPK),
            $level,
            IzinMeninggalkanApprovalQuery::totalLevels($request)
        );

        return [
            'doc'             => ApprovalCommandParser::DOC_IZIN_MENINGGALKAN,
            'request_id'      => $id,
            'token'           => $token,
            'level'           => $level,
            'total_levels'    => IzinMeninggalkanApprovalQuery::totalLevels($request),
            'phone'           => $phone,
            'raw_phone'       => $info->HP ?? null,
            'approver_npk'    => (string) $info->NPK,
            'approver_name'   => (string) ($info->NAMA ?? $info->NPK),
            'employee_npk'    => (string) $request->npk,
            'employee_name'   => self::employeeName((string) $request->npk),
            'tanggal'         => $request->tanggal?->format('Y-m-d'),
            'jam_keluar'      => $request->jam_keluar,
            'rencana_kembali' => $request->rencana_kembali,
            'alasan'          => $request->alasan,
            'submitted_at'    => $request->created_at?->format('Y-m-d H:i:s'),
            'message'         => $message,
            'approve_command' => '/approve-izin-meninggalkan' . $id,
            'reject_command'  => '/tolak-izin-meninggalkan' . $id . ' <alasan>',
        ];
    }

    /**
     * @return array{request?:IjinMeninggalkanRequest, level?:int, info?:object, phone?:string, error?:string}
     */
    private static function resolveEligible(string $token): array
    {
        $request = IzinMeninggalkanApprovalQuery::getByToken($token);

        if (!$request) {
            return ['error' => "Token $token tidak ditemukan"];
        }

        $level = IzinMeninggalkanApprovalQuery::currentActionableLevel($request);

        if ($level === null) {
            return ['error' => "Token $token tidak punya level yang eligible (sudah diproses / ditolak)"];
        }

        $step = IzinMeninggalkanApprovalQuery::stepAt($request, $level);
        $npk  = trim((string) ($step['npk'] ?? ''));

        if ($npk === '') {
            return ['error' => "Level $level tidak punya approver pada rantai pengajuan #{$request->id}"];
        }

        $info = IzinMeninggalkanApprovalQuery::approverInfo($npk);

        if (!$info) {
            Log::warning("[IzinMeninggalkanNotifier] #{$request->id}: approver $npk tidak ada di PKWT");

            return ['error' => "#{$request->id}: approver $npk tidak ada di master karyawan"];
        }

        $phone = PhoneFormatter::normalize($info->HP ?? null);

        if (!PhoneFormatter::isValid($phone)) {
            Log::warning("[IzinMeninggalkanNotifier] #{$request->id}: nomor approver $npk tidak valid ('{$info->HP}')");

            return ['error' => "#{$request->id}: nomor HP approver $npk tidak valid ('{$info->HP}')"];
        }

        return [
            'request' => $request,
            'level'   => $level,
            'info'    => $info,
            'phone'   => $phone,
        ];
    }

    private static function employeeName(string $npk): ?string
    {
        try {
            $names = EmployeeLookupService::namesFor([$npk]);

            return $names[$npk] ?? null;
        } catch (Throwable) {
            return null;
        }
    }
}
