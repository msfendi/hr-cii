<?php

namespace App\Services\ApprovalWa;

use App\Models\IjinMeninggalkanRequest;
use App\Services\IzinMeninggalkan\IjinMeninggalkanApproval;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Akses data Izin Meninggalkan Tugas/Pekerjaan untuk jalur WhatsApp.
 */
class IzinMeninggalkanApprovalQuery
{
    public static function getByToken(string $token): ?IjinMeninggalkanRequest
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        try {
            return IjinMeninggalkanRequest::where('token', $token)->first();
        } catch (Throwable) {
            return null;
        }
    }

    public static function getById(int $id): ?IjinMeninggalkanRequest
    {
        if ($id <= 0) {
            return null;
        }

        try {
            return IjinMeninggalkanRequest::find($id);
        } catch (Throwable) {
            return null;
        }
    }

    public static function totalLevels(IjinMeninggalkanRequest $request): int
    {
        return count($request->chain());
    }

    public static function levelOf(IjinMeninggalkanRequest $request, string $npk): ?int
    {
        return IjinMeninggalkanApproval::levelOf($request, $npk);
    }

    public static function approverInfo(string $npk): ?object
    {
        $npk = trim($npk);

        if ($npk === '') {
            return null;
        }

        try {
            $rows = DB::connection('cii')->table('PKWT')
                ->where('NPK', $npk)
                ->limit(1)
                ->get(['NPK', 'NAMA', 'HP']);
        } catch (Throwable) {
            return null;
        }

        return $rows[0] ?? null;
    }

    /**
     * Cari approver pada rantai pengajuan ini berdasarkan nomor WhatsApp.
     *
     * @return array{npk:string, name:string, level:?int, phone:string}|null
     */
    public static function resolveApproverByPhone(IjinMeninggalkanRequest $request, string $phone): ?array
    {
        $phone = PhoneFormatter::normalize($phone);

        if ($phone === null) {
            return null;
        }

        $chain = $request->chain();
        $npks  = array_values(array_unique(array_filter(array_map(
            fn ($step) => trim((string) ($step['npk'] ?? '')),
            $chain
        ))));

        if (empty($npks)) {
            return null;
        }

        try {
            $rows = DB::connection('cii')->table('PKWT')
                ->whereIn('NPK', $npks)
                ->get(['NPK', 'NAMA', 'HP']);
        } catch (Throwable) {
            return null;
        }

        foreach ($rows as $row) {
            $normalized = PhoneFormatter::normalize($row->HP ?? null);

            if ($normalized !== null && $normalized === $phone) {
                $npk = (string) $row->NPK;

                return [
                    'npk'   => $npk,
                    'name'  => (string) ($row->NAMA ?? $npk),
                    'level' => self::levelOf($request, $npk),
                    'phone' => $normalized,
                ];
            }
        }

        return null;
    }

    public static function currentActionableLevel(IjinMeninggalkanRequest $request): ?int
    {
        if (!$request->isOpen()) {
            return null;
        }

        $level = (int) $request->current_level;

        return IjinMeninggalkanApproval::isActionable($request, $level) ? $level : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function stepAt(IjinMeninggalkanRequest $request, int $level): array
    {
        foreach ($request->chain() as $step) {
            if ((int) ($step['level'] ?? 0) === $level) {
                return $step;
            }
        }

        return [];
    }
}
