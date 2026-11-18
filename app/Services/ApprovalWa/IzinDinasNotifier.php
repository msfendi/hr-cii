<?php

namespace App\Services\ApprovalWa;

use App\Models\WorkAssignmentRequest;
use App\Services\WorkAssignment\EmployeeLookupService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi WhatsApp untuk Izin Dinas.
 *
 * Aturan sama dengan cuti: hanya level yang sedang GILIRAN yang dikabari,
 * lalu cascade ke level berikutnya setelah level sebelumnya menyetujui.
 *
 * Dipanggil dari:
 *  - WorkAssignmentController::submit()          → setelah pengajuan dibuat
 *  - WorkAssignmentApprovalController::approve() → setelah approve di web
 *  - IzinDinasInboundProcessor                   → setelah approve via WhatsApp
 *  - artisan (dry-run/manual)
 */
class IzinDinasNotifier
{
    /**
     * Kirim notifikasi untuk satu pengajuan (token).
     *
     * @return array{notified:int, skipped:string[], sent:array<int,string>, message?:string}
     */
    public static function notifyToken(string $token, bool $dryRun = false): array
    {
        $result = ['notified' => 0, 'skipped' => [], 'sent' => []];

        $eligible = self::resolveEligible($token);

        if (isset($eligible['error'])) {
            $result['skipped'][] = $eligible['error'];

            return $result;
        }

        $request  = $eligible['request'];
        $level    = $eligible['level'];
        $info     = $eligible['info'];
        $phone    = $eligible['phone'];
        $id       = (int) $request->id;

        $message = IzinDinasMessageFormatter::formatApprovalRequest(
            $request,
            (string) ($info->NAMA ?? $info->NPK),
            $level,
            IzinDinasApprovalQuery::totalLevels($request)
        );

        if ($dryRun) {
            Log::info("[IzinDinasNotifier] DRY RUN #$id → $phone (level $level)");
            $result['notified']         = 1;
            $result['sent'][$id]        = $phone;
            $result['message']          = $message;

            return $result;
        }

        $ok = WaGateway::send($phone, $message);

        if ($ok) {
            $result['notified']  = 1;
            $result['sent'][$id] = $phone;
            Log::info("[IzinDinasNotifier] #$id notified to $phone (level $level)");
        } else {
            $result['skipped'][] = "#$id: gagal kirim ke $phone";
            Log::error("[IzinDinasNotifier] #$id failed to send to $phone");
        }

        return $result;
    }

    /**
     * Susun payload siap kirim (tanpa mengirim) untuk messaging layer.
     *
     * @return array<string, mixed>|null  null kalau tidak ada level yang eligible
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
        $message = IzinDinasMessageFormatter::formatApprovalRequest(
            $request,
            (string) ($info->NAMA ?? $info->NPK),
            $level,
            IzinDinasApprovalQuery::totalLevels($request)
        );

        $approveCmd = '/approve-izin-dinas' . $id;
        $rejectCmd  = '/tolak-izin-dinas' . $id . ' <alasan>';

        return [
            'doc'             => ApprovalCommandParser::DOC_IZIN_DINAS,
            'request_id'      => $id,
            'token'           => $token,
            'level'           => $level,
            'total_levels'    => IzinDinasApprovalQuery::totalLevels($request),
            'phone'           => $phone,
            'raw_phone'       => $info->HP ?? null,
            'approver_npk'    => (string) $info->NPK,
            'approver_name'   => (string) ($info->NAMA ?? $info->NPK),
            'employee_npk'    => (string) $request->npk,
            'employee_name'   => self::employeeName((string) $request->npk),
            'duty_dates'      => $request->dates(),
            'start_date'      => $request->start_date?->format('Y-m-d'),
            'end_date'        => $request->end_date?->format('Y-m-d'),
            'duration_days'   => (int) $request->total_days,
            'dinas_ke'        => $request->dinas_ke,
            'keperluan'       => $request->keperluan,
            'jam_keluar'      => $request->jam_keluar,
            'rencana_kembali' => $request->rencana_kembali,
            'submitted_at'    => $request->created_at?->format('Y-m-d H:i:s'),
            'message'         => $message,
            'approve_command' => $approveCmd,
            'reject_command'  => $rejectCmd,
        ];
    }

    /**
     * Cari level yang berhak dikabari beserta approver + nomornya.
     *
     * @return array{request?:WorkAssignmentRequest, level?:int, info?:object, phone?:string, error?:string}
     */
    private static function resolveEligible(string $token): array
    {
        $request = IzinDinasApprovalQuery::getByToken($token);

        if (!$request) {
            return ['error' => "Token $token tidak ditemukan"];
        }

        $level = IzinDinasApprovalQuery::currentActionableLevel($request);

        if ($level === null) {
            return ['error' => "Token $token tidak punya level yang eligible (sudah diproses / ditolak)"];
        }

        $step = IzinDinasApprovalQuery::stepAt($request, $level);
        $npk  = trim((string) ($step['npk'] ?? ''));

        if ($npk === '') {
            return ['error' => "Level $level tidak punya approver pada rantai pengajuan #{$request->id}"];
        }

        $info = IzinDinasApprovalQuery::approverInfo($npk);

        if (!$info) {
            Log::warning("[IzinDinasNotifier] #{$request->id}: approver $npk tidak ada di PKWT");

            return ['error' => "#{$request->id}: approver $npk tidak ada di master karyawan"];
        }

        $phone = PhoneFormatter::normalize($info->HP ?? null);

        if (!PhoneFormatter::isValid($phone)) {
            Log::warning("[IzinDinasNotifier] #{$request->id}: nomor approver $npk tidak valid ('{$info->HP}')");

            return ['error' => "#{$request->id}: nomor HP approver $npk tidak valid ('{$info->HP}')"];
        }

        return [
            'request' => $request,
            'level'   => $level,
            'info'    => $info,
            'phone'   => $phone,
        ];
    }

    /** Nama karyawan pemohon dari master. */
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