<?php

namespace App\Services\ApprovalWa;

use App\Models\WorkAssignmentRequest;
use App\Services\WorkAssignment\WorkAssignmentApproval;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Akses data Izin Dinas (Work Assignment) untuk jalur WhatsApp.
 *
 * Beda mendasar dengan cuti: cuti menyimpan satu row per level di
 * `leave_requests`, sedangkan Izin Dinas menyimpan seluruh rantai approval
 * sebagai JSON di `approval_chain` + `answers`. Jadi jangan ada query per
 * level — rantai dibaca sekali dari model, aturan giliran diserahkan ke
 * WorkAssignmentApproval yang sudah menanganinya.
 */
class IzinDinasApprovalQuery
{
    /** Cari pengajuan berdasarkan token. */
    public static function getByToken(string $token): ?WorkAssignmentRequest
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        try {
            return WorkAssignmentRequest::where('token', $token)->first();
        } catch (Throwable) {
            return null;
        }
    }

    /** Cari pengajuan berdasarkan nomor (primary key). */
    public static function getById(int $id): ?WorkAssignmentRequest
    {
        if ($id <= 0) {
            return null;
        }

        try {
            return WorkAssignmentRequest::find($id);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Jumlah level pada rantai approval pengajuan.
     */
    public static function totalLevels(WorkAssignmentRequest $request): int
    {
        return count($request->chain());
    }

    /**
     * Level milik seorang approver pada pengajuan ini (null kalau bukan approver-nya).
     */
    public static function levelOf(WorkAssignmentRequest $request, string $npk): ?int
    {
        return WorkAssignmentApproval::levelOf($request, $npk);
    }

    /**
     * Info karyawan (nama + HP) dari master, untuk NPK tertentu.
     *
     * @return object|null {NPK, NAMA, HP}
     */
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
     * Sengaja TIDAK memakai daftar approver master (`approval_rules`): rantai
     * Izin Dinas adalah snapshot, sehingga NPK-nya bisa sudah tidak ada di
     * master ketika pengajuan dibuat. Yang menentukan wewenang adalah isi
     * `approval_chain` dokumen itu sendiri.
     *
     * @return array{npk:string, name:string, level:?int, phone:string}|null
     */
    public static function resolveApproverByPhone(WorkAssignmentRequest $request, string $phone): ?array
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

    /**
     * Approver pada rantai yang nomornya tidak bisa dipakai (kosong/invalid).
     *
     * Dipakai saat submit: lebih baik menolak pengajuan dengan alasan jelas
     * daripada membiarkannya macet karena approver tak pernah menerima pesan.
     *
     * @param  array<int, array<string, mixed>>  $chain  rantai approval (snapshot)
     * @return array<int, array{npk:string, name:string, level:int}>
     */
    public static function approversWithoutPhone(array $chain): array
    {
        $missing = [];

        foreach ($chain as $step) {
            $npk = trim((string) ($step['npk'] ?? ''));

            if ($npk === '') {
                $missing[] = [
                    'npk'   => '-',
                    'name'  => (string) ($step['name'] ?? $step['jabatan'] ?? '-'),
                    'level' => (int) ($step['level'] ?? 0),
                ];
                continue;
            }

            $info  = self::approverInfo($npk);
            $phone = PhoneFormatter::normalize($info->HP ?? null);

            if (!PhoneFormatter::isValid($phone)) {
                $missing[] = [
                    'npk'   => $npk,
                    'name'  => (string) ($info->NAMA ?? $step['name'] ?? $npk),
                    'level' => (int) ($step['level'] ?? 0),
                ];
            }
        }

        return $missing;
    }

    /**
     * Level yang sedang giliran diputuskan (null kalau rantai selesai/ditolak).
     */
    public static function currentActionableLevel(WorkAssignmentRequest $request): ?int
    {
        if (!$request->isOpen()) {
            return null;
        }

        $level = (int) $request->current_level;

        return WorkAssignmentApproval::isActionable($request, $level) ? $level : null;
    }

    /**
     * Ambil satu langkah rantai berdasarkan level.
     *
     * WorkAssignmentApproval menyimpan helper ini sebagai private; di sini
     * dibutuhkan untuk menyusun pesan notifikasi (nama approver per level).
     *
     * @return array<string, mixed>  kosong kalau level tidak ada di rantai
     */
    public static function stepAt(WorkAssignmentRequest $request, int $level): array
    {
        foreach ($request->chain() as $step) {
            if ((int) ($step['level'] ?? 0) === $level) {
                return $step;
            }
        }

        return [];
    }
}